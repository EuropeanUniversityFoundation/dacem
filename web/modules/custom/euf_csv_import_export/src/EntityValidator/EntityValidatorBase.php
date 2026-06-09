<?php

namespace Drupal\euf_csv_import_export\EntityValidator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\CsvNormalizer\CsvNormalizer;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class EntityValidatorBase {

  public const SKIPPED_FIELDS = [];
  public const CODE_FIELD = '';
  public const INSTITUTION_FIELD = '';
  public const ENTITY_LABEL = '';


  protected EntityTypeManagerInterface $entityTypeManager;
  protected Dataloader $dataLoader;
  protected CsvNormalizer $csvNormalizer;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, Dataloader $data_loader, CsvNormalizer $csv_normalizer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
    $this->csvNormalizer = $csv_normalizer;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('euf_csv_import_export.data_loader'),
      $container->get('euf_csv_import_export.csv_normalizer'),
    );
  }

  public function validate(string $entity_type, mixed $csv_row_no, array $normalized_data, ?Node $institution = NULL): EntityConstraintViolationList {

    $this->removeSkippedFields($normalized_data);

    if (!$institution) {
      $institution = $normalized_data[FieldMappingService::DEFAULT_LANGUAGE][static::INSTITUTION_FIELD];
    }

    $entity = $this->findEntity(
      $entity_type,
      static::CODE_FIELD,
      $normalized_data[FieldMappingService::DEFAULT_LANGUAGE][static::CODE_FIELD],
      static::INSTITUTION_FIELD,
      $institution,
    );

    if (empty($entity)) {
      $entity = $this->csvNormalizer->denormalizeEntity($entity_type, $normalized_data);
    }
    else {
      $entity = reset($entity);
    }

    /** @var \Drupal\node\NodeInterface $entity */
    $violations = $entity->validate();

    // Validating translations.
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      if ($langcode === $entity->language()->getId()) {
        continue;
      }
      $translation_violations = $entity->getTranslation($langcode)->validate();
      $violations->addAll($translation_violations);
    }

    return $violations;
  }

  public function validateMultiple(string $entityType, array $normalized_data, ?Node $institution = NULL): array {
    $violations_by_row = [];

    foreach ($normalized_data as $entity_data) {
      $original_row_no = $entity_data[FieldMappingService::DEFAULT_LANGUAGE]['csv_row'] + 1;
      if ($institution) {
        $violation_list = $this->validate($entityType, $original_row_no, $entity_data, $institution);
      } else
      {
        $violation_list = $this->validate($entityType, $original_row_no, $entity_data);
      }

      if ($violation_list->count() > 0) {
        $violations_by_row[] = [
          'csv_row' => $original_row_no,
          'list' => $violation_list,
        ];
      }
    }

    return $this->addError($violations_by_row);
  }

  public function removeSkippedFields(array &$normalized_data) {
    foreach ($normalized_data as $language => &$translation_data) {
      foreach ($translation_data as $field => $value) {
        if (in_array($field, static::SKIPPED_FIELDS)) {
          unset($translation_data[$field]);
        }
      }
    }
  }

  public function updateEntity(ContentEntityInterface $entity, array $entity_data) {
    foreach ($entity_data as $key => $value) {
      if ($entity->hasField($key)) {
        $entity->set($key, $value);
      }
    }

    return $entity;
  }

  protected function findEntity (string $entity_type, string $code_field, string $code, string $institution_field, Node $institution){
    $entity = $this->dataLoader->getEntityByCodeAndInstitution(
      $entity_type,
      $code_field,
      $code,
      $institution_field,
      $institution
    );

    return $entity;
  }

  public function addError(array $violations_by_row) {
    $errors = [];

    foreach ($violations_by_row as $row_data) {
      $csv_row = $row_data['csv_row'];
      /** @var \Drupal\Core\Entity\EntityConstraintViolationListInterface $violation_list */
      $violation_list = $row_data['list'];

      foreach ($violation_list as $violation) {
        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
        $invalid_value = $violation->getInvalidValue();
        $invalid_string = '';

        // Case 1: Simple string, integer, or boolean value
        if (is_scalar($invalid_value)) {
          $invalid_string = (string) $invalid_value;
        }
        // Case 2: It is a complex Object (Entity, Field item, etc.)
        elseif (is_object($invalid_value)) {
          if (property_exists($invalid_value, 'value')) {
            $invalid_string = (string) $invalid_value->value;
          }
          // If it is a FieldItemList, map its internal items
          elseif ($invalid_value instanceof \Drupal\Core\Field\FieldItemListInterface) {
            $values = [];
            foreach ($invalid_value as $item) {
              $values[] = $item->value ?? $item->target_id ?? '';
            }
            $invalid_string = implode(', ', array_filter($values));
          }
          // If it is a Drupal Entity, fall back to its Label or ID
          elseif ($invalid_value instanceof \Drupal\Core\Entity\EntityInterface) {
            $invalid_string = $invalid_value->label() ?: $invalid_value->id();
          }
          // If your custom object uses getName() specifically
          elseif (method_exists($invalid_value, 'getName')) {
            $invalid_string = $invalid_value->getName();
          }
          else {
            $invalid_string = get_class($invalid_value);
          }
        }
        // Case 3: It is an array of raw values
        elseif (is_array($invalid_value)) {
          $invalid_string = implode(', ', array_filter(array_map('strval', $invalid_value)));
        }

        $error_type = 'Invalid ' . static::ENTITY_LABEL . ' data';

        $errors[$error_type][] = [
          'message' => $violation->getMessage()->__toString(),
          'source' => $violation->getPropertyPath(),
          'values' => $invalid_string !== '' ? [$invalid_string] : [],
          'row_number' => $csv_row,
        ];
      }
    }

    return $errors;
  }
}
