<?php

namespace Drupal\dacem_csv_import_export\CsvNormalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dacem_csv_import_export\AccessManager\UserAccessManager;
use Drupal\dacem_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\dacem_csv_import_export\CsvConverter\ReferenceResolver;
use Drupal\dacem_csv_import_export\DataLoader\DataLoader;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CsvNormalizer {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected DataLoader $dataLoader;
  protected ReferenceResolver $referenceResolver;
  protected UserAccessManager $userAccessManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, DataLoader $data_loader, ReferenceResolver $reference_resolver, UserAccessManager $user_access_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
    $this->referenceResolver = $reference_resolver;
    $this->userAccessManager = $user_access_manager;
  }

  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('dacem_csv_import_export.data_loader'),
      $container->get('dacem_csv_import_export.reference_resolver'),
      $container->get('dacem_csv_import_export.user_access_manager'),
    );
  }

  public function upsertMultiple(string $entityType, array &$decoded_data, Node $institution) {

    $transaction = \Drupal::database()->startTransaction();
    $storage = $this->entityTypeManager->getStorage('node');
    try {
      foreach ($decoded_data as &$entity_data) {
        $this->referenceResolver->resolveReferencesOnSave($entityType, $entity_data, $institution);
        $code_column_name = FieldMappingService::CODE_COLUMNS[$entityType]['code_column_name'];
        $institution_column_name = FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name'];
        $entities = $this->dataLoader->getEntityByCodeAndInstitution($entityType, $code_column_name, $entity_data[FieldMappingService::DEFAULT_LANGUAGE][$code_column_name], $institution_column_name, $institution);
        $entity = $entities ? reset($entities)->getUntranslated() : $storage->create([
          'type' => $entityType,
          'langcode' => FieldMappingService::DEFAULT_LANGUAGE,
        ]);

        foreach ($this->buildImportStages($entity, $entity_data) as $langcode => $fields) {
          $entity = $this->applyImportStage($entity, $langcode, $fields);
          $violations = $entity->getTranslation($langcode)->validate();
          if ($violations->count()) {
            throw new \Symfony\Component\Validator\Exception\ValidationFailedException($entity, $violations);
          }
          $entity->save();
          // Each translation must compare against the preceding saved stage.
          $entity = $storage->loadUnchanged($entity->id())->getUntranslated();
        }
        $this->userAccessManager->addEntityToInstitutionGroup($entity, $institution);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $storage->resetCache();
      throw $exception;
    }
  }

  /**
   * Routes shared fields to the actual original language, before translations.
   */
  public function buildImportStages(Node $entity, array $entity_data): array {
    $original_language = $entity->getUntranslated()->language()->getId();
    $stages = [$original_language => []];
    foreach ($entity_data as $langcode => $fields) {
      foreach ($fields as $field_name => $value) {
        // These describe the CSV row, not editable content.
        if (in_array($field_name, ['type', 'langcode', 'csv_row'], TRUE) || !$entity->hasField($field_name)) {
          continue;
        }
        $definition = $entity->getFieldDefinition($field_name);
        if ($definition->isComputed()) {
          continue;
        }
        $target_language = $definition->isTranslatable() ? $langcode : $original_language;
        $stages[$target_language][$field_name] = $value;
      }
    }
    return $stages;
  }

  protected function applyImportStage(Node $entity, string $langcode, array $fields): Node {
    $translation = $entity->hasTranslation($langcode)
      ? $entity->getTranslation($langcode)
      : $entity->addTranslation($langcode);
    foreach ($fields as $field_name => $value) {
      $translation->set($field_name, $value);
    }
    return $entity->getUntranslated();
  }

  /**
   * Simulates the same stages as saving, without writing or changing cached nodes.
   */
  public function validateImport(string $entity_type, array $entity_data, ?Node $entity = NULL): \Drupal\Core\Entity\EntityConstraintViolationList {
    $entity = $entity ? clone $entity->getUntranslated() : $this->entityTypeManager->getStorage('node')->create([
      'type' => $entity_type,
      'langcode' => FieldMappingService::DEFAULT_LANGUAGE,
    ]);
    $violations = new \Drupal\Core\Entity\EntityConstraintViolationList($entity);
    foreach ($this->buildImportStages($entity, $entity_data) as $langcode => $fields) {
      $baseline = clone $entity;
      unset($baseline->original);
      $entity = $this->applyImportStage($entity, $langcode, $fields);
      if (!$entity->isNew()) {
        // Model the baseline that loadUnchanged() supplies after a staged save.
        $entity->original = $baseline;
      }
      $violations->addAll($entity->getTranslation($langcode)->validate());
    }
    return $violations;
  }

  public function denormalizeEntity(string $entity_type, array $entity_data, ?Node $entity = NULL) {
    if (is_null($entity)) {
      $entity = $this->entityTypeManager->getStorage('node')->create([
        'langcode' => FieldMappingService::DEFAULT_LANGUAGE,
        'type' => $entity_type,
      ]);
    }
    /** @var \Drupal\node\NodeInterface $entity */
    if (isset($entity_data[FieldMappingService::DEFAULT_LANGUAGE])) {
      foreach ($entity_data[FieldMappingService::DEFAULT_LANGUAGE] as $field_name => $value) {
        if ($entity->hasField($field_name)) {
          $entity->set($field_name, $value);
        }
      }
    }

    foreach ($entity_data as $langcode => $fields) {
      if ($langcode === FieldMappingService::DEFAULT_LANGUAGE) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $translation */
      if ($entity->hasTranslation($langcode)) {
        $translation = $entity->getTranslation($langcode);
      } else {
        $translation = $entity->addTranslation($langcode);
      }

      foreach ($fields as $field_name => $value) {
        if ($translation->hasField($field_name)) {
          $translation->set($field_name, $value);
        }
      }
    }

    return $entity;
  }
}