<?php

namespace Drupal\euf_csv_import_export\EntityValidator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\CsvNormalizer\CsvNormalizer;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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
    // @phpstan-ignore new.static
    return new static(
      $container->get('entity_type.manager'),
      $container->get('euf_csv_import_export.data_loader'),
      $container->get('euf_csv_import_export.csv_normalizer'),
    );
  }

  public function validate(string $entity_type, mixed $csv_row_no, array $normalized_data): EntityConstraintViolationList {

    $this->removeSkippedFields($normalized_data);

    $entity = $this->dataLoader->getEntityByCodeAndInstitution(
      $entity_type,
      static::CODE_FIELD,
      $normalized_data[FieldMappingService::DEFAULT_LANGUAGE][static::CODE_FIELD],
      static::INSTITUTION_FIELD,
      $normalized_data[FieldMappingService::DEFAULT_LANGUAGE][static::INSTITUTION_FIELD]
    );



    if (empty($entity)) {
      //// This is actually denormalizing, move it on occasion.
      // Default language entity creation.
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

  public function validateMultiple(string $entityType, array $normalized_data): array {
    $violations_by_row = [];

    foreach ($normalized_data as $entity_data) {
      $original_row_no = $entity_data[FieldMappingService::DEFAULT_LANGUAGE]['csv_row'] + 1;

      $violation_list = $this->validate($entityType, $original_row_no, $entity_data);

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

  public function addError(array $violations_by_row) {
    $errors = [];

    foreach ($violations_by_row as $row_data) {
      $csv_row = $row_data['csv_row'];
      /** @var \Drupal\Core\Entity\EntityConstraintViolationListInterface $violation_list */
      $violation_list = $row_data['list'];

      foreach ($violation_list as $violation) {
        /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
        $invalid_string = $violation->getInvalidValue()->value;
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
