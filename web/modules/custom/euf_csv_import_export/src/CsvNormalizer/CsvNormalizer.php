<?php

namespace Drupal\euf_csv_import_export\CsvNormalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\euf_csv_import_export\AccessManager\UserAccessManager;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\CsvConverter\ReferenceResolver;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CsvNormalizer {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected Dataloader $dataLoader;
  protected ReferenceResolver $referenceResolver;
  protected UserAccessManager $userAccessManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, Dataloader $data_loader, ReferenceResolver $reference_resolver, UserAccessManager $user_access_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
    $this->referenceResolver = $reference_resolver;
    $this->userAccessManager = $user_access_manager;
  }

  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('entity_type.manager'),
      $container->get('euf_csv_import_export.data_loader'),
      $container->get('euf_csv_import_export.reference_resolver'),
      $container->get('euf_csv_import_export.user_access_manager'),
    );
  }

  public function upsertMultiple(string $entityType, array &$decoded_data, Node $institution) {

    foreach ($decoded_data as $key => &$entity_data) {
      $this->referenceResolver->resolveReferencesOnSave($entityType, $entity_data, $institution);
      $code_column_name = FieldMappingService::CODE_COLUMNS[$entityType]['code_column_name'];
      $institution_column_name = FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name'];
      $entity = $this->dataLoader->getEntityByCodeAndInstitution($entityType, $code_column_name, $entity_data[FieldMappingService::DEFAULT_LANGUAGE][$code_column_name], $institution_column_name, $institution);

      if (empty($entity)) {
        $entity = $this->denormalizeEntity($entityType, $entity_data);
      } else
      {
        $entity = reset($entity);
        $entity = $this->denormalizeEntity($entityType, $entity_data, $entity);
      }

      $entity->save();
      $this->userAccessManager->addEntityToInstitutionGroup($entity, $institution);
    }
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