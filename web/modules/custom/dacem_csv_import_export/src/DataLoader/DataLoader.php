<?php

namespace Drupal\dacem_csv_import_export\Dataloader;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\Entity\Node;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Dataloader {

  protected EntityTypeManager $entityTypeManager;

  public function __construct(
    EntityTypeManager $entity_type_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  public function getInstitutionsBySchacCodes(array $schac_codes) {
    $storage = $this->entityTypeManager->getStorage('node');
    $institution_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'institution')
      ->condition('field_shac_code', $schac_codes, 'IN')
      ->execute();

    $institutions = $storage->loadMultiple($institution_ids);

    return $institutions;
  }

  public function getInstitutionBySchacCode(string $schac_code) {
    $storage = $this->entityTypeManager->getStorage('node');
    $institution_id = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'institution')
      ->condition('field_shac_code', $schac_code)
      ->execute();

    if (count($institution_id) > 1) {
      throw new Exception('Multiple Institutions found with the same schac code!');
    }

    $institution = $storage->load(reset($institution_id));

    return $institution;
  }

  public function getEntityByCodeAndInstitution(string $entityType, string $codeField, string $code, string $institutionField, Node $institution) {
    $storage = $this->entityTypeManager->getStorage('node');

    $entity = $storage->loadByProperties([
      'type' => $entityType,
      $codeField => $code,
      $institutionField => $institution->id(),
    ]);

    return $entity;
  }

  public function loadEntitiesWithConditions(string $entity_type_id, array $conditions, bool $access_check = FALSE) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery()
      ->accessCheck($access_check);

    foreach ($conditions as $condition) {
      $query->condition($condition['field'], $condition['value'], $condition['operator']);
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $entities = $storage->loadMultiple($ids);

    return $entities;

  }

  public function loadEntitiesByIds(string $entity_type_id, string $entity_bundle, array $ids) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    return $storage->loadMultiple($ids);
  }

}