<?php

namespace Drupal\euf_csv_import_export\CsvConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ReferenceResolver {

  public const REFERENCES_BY_ENTITY_TYPE = [
    ImportTargetEntityType::OUNIT->value => [
      [
        'reference_field_name' => 'field_ou_institution',
        'referenced_entity' => 'node',
        'referenced_entity_type' => ImportTargetEntityType::HEI->value,
        'referenced_field_name' => 'field_shac_code',
        'cardinality' => 1,
      ],
      [
        'reference_field_name' => 'field_parent_organizational_unit',
        'referenced_entity' => 'node',
        'referenced_entity_type' => ImportTargetEntityType::OUNIT->value,
        'referenced_field_name' => 'field_ou_code',
        'referenced_hei_field_name' => 'field_ou_institution',
        'cardinality' => 1,
        'runs' => 'on_save',
      ],
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      [
        'reference_field_name' => 'field_programme_institution',
        'referenced_entity' => 'node',
        'referenced_entity_type' => ImportTargetEntityType::HEI->value,
        'referenced_field_name' => 'field_shac_code',
        'cardinality' => 1,
      ],
      [
        'reference_field_name' => 'field_programme_ou',
        'referenced_entity' => 'node',
        'referenced_entity_type' => ImportTargetEntityType::OUNIT->value,
        'referenced_field_name' => 'field_ou_code',
        'referenced_hei_field_name' => 'field_ou_institution',
        'cardinality' => 1,
      ],
    ],
    // 'course' => [
    //   [
    //     'reference_field_name' => 'hei',
    //     'referenced_entity_type' => 'hei',
    //     'referenced_field_name' => 'hei_id',
    //     'cardinality' => 1,
    //   ],
    //   [
    //     'reference_field_name' => 'ounit',
    //     'referenced_entity_type' => 'ounit',
    //     'referenced_field_name' => 'ounit_code',
    //     'referenced_hei_field_name' => 'parent_hei',
    //     'cardinality' => 1,
    //   ],
    //   [
    //     'reference_field_name' => 'course__related_programme',
    //     'reference_field_property_name' => 'code',
    //     'reference_field_target_property_name' => 'target_id',
    //     'referenced_entity_type' => 'occ_los',
    //     'referenced_entity_bundle' => 'programme',
    //     'referenced_field_name' => 'code',
    //     'referenced_hei_field_name' => 'hei',
    //     'cardinality' => -1,
    //   ],
    //   [
    //     'reference_field_name' => 'course__prerequisite_course',
    //     'referenced_entity_type' => 'occ_los',
    //     'referenced_entity_bundle' => 'course',
    //     'referenced_field_name' => 'code',
    //     'referenced_hei_field_name' => 'hei',
    //     'cardinality' => -1,
    //     'runs' => 'on_save',
    //   ],
    // ],
    // 'course_instance' => [
    //   [
    //     'reference_field_name' => 'course',
    //     'referenced_entity_type' => 'occ_los',
    //     'referenced_entity_bundle' => 'course',
    //     'referenced_field_name' => 'code',
    //     'referenced_hei_field_name' => 'hei',
    //     'cardinality' => 1,
    //   ],
    // ],
  ];

  protected EntityTypeManagerInterface $entityTypeManager;
  protected Dataloader $dataLoader;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Dataloader $data_loader,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
  }

  /**
   *
   */
  public static function create(ContainerInterface $container) {
    // @phpstan-ignore new.static
    return new static(
      $container->get('entity_type.manager'),
      $container->get('euf_csv_import_export.data_loader'),
    );
  }

  /**
   *
   */
  public function resolveReferencesMultiple(string $entity_type, array &$decoded_data, Node $institution): array {
    array_walk($decoded_data, function (&$decoded_entity) use ($entity_type, $institution) {
      $this->resolveReferences($entity_type, $decoded_entity, $institution);
    });

    return $decoded_data;
  }

  /**
   *
   */
  public function resolveReferences(string $entity_type, array &$decoded_entity, Node $institution): void {
    if (!isset(self::REFERENCES_BY_ENTITY_TYPE[$entity_type])) {
      return;
    }

    foreach (self::REFERENCES_BY_ENTITY_TYPE[$entity_type] as $reference_details) {
      // @phpstan-ignore booleanOr.rightAlwaysFalse
      if (!isset($reference_details['runs']) || $reference_details['runs'] != 'on_save') {
        $this->processReference($decoded_entity, $reference_details, $institution);
      }
    }
  }

  /**
   *
   */
  public function resolveReferencesOnSave(string $entity_type, array &$decoded_entity, Node $institution): void {
    if (!isset(self::REFERENCES_BY_ENTITY_TYPE[$entity_type])) {
      return;
    }

    foreach (self::REFERENCES_BY_ENTITY_TYPE[$entity_type] as $reference_details) {
      if (isset($reference_details['runs']) && $reference_details['runs'] === 'on_save') {
        $this->processReference($decoded_entity, $reference_details, $institution);
      }
    }
  }

  protected function processReference(array &$decoded_entity, array $reference_details, Node $institution): void {
    $field_name = $reference_details['reference_field_name'];

    foreach ($decoded_entity as $language => &$translation) {
      if (!isset($translation[$field_name])) {
        return;
      }

      if ($reference_details['cardinality'] === 1) {
        $this->processSingleReference($translation, $reference_details, $institution);
      }
      else {
        $this->processMultipleReferences($translation, $reference_details, $institution);
      }
    }
  }

  /**
   *
   */
  protected function processSingleReference(array &$translation, array $reference_details, Node $institution): void {
    $field_name = $reference_details['reference_field_name'];
    $field_value = $translation[$field_name];

    $entity = $this->loadEntity(
      entity_id: $reference_details['referenced_entity'],
      field_name: $reference_details['referenced_field_name'],
      field_value: $field_value,
      institution: $institution,
      entity_type:$reference_details['referenced_entity_type'],
      entity_bundle: $reference_details['referenced_entity_bundle'] ?? NULL,
      hei_field_name:$reference_details['referenced_hei_field_name'] ?? NULL
    );

    if ($entity) {
      if (isset($reference_details['reference_field_property_name'])) {
        $translation[$field_name][$reference_details['reference_field_property_name']] = $entity->id();
      }
      else {
        $translation[$field_name] = $entity;
      }
    }
  }

  protected function processMultipleReferences(array &$decoded_entity, array $reference_details, Node $institution): void {
    $field_name = $reference_details['reference_field_name'];
    $has_property = isset($reference_details['reference_field_property_name']);
    $has_target_property = isset($reference_details['reference_field_target_property_name']);

    foreach ($decoded_entity[$field_name] as $key => $value) {
      $search_value = $has_property ? $value[$reference_details['reference_field_property_name']] : $value;

      $entity = $this->loadEntity(
        entity_id: $reference_details['referenced_entity'],
        field_name: $reference_details['referenced_field_name'],
        field_value:$search_value,
        institution: $institution,
        entity_type:$reference_details['referenced_entity_type'],
        entity_bundle: $reference_details['referenced_entity_bundle'] ?? NULL,
        hei_field_name:$reference_details['referenced_hei_field_name'] ?? NULL
      );

      if ($entity) {
        if ($has_property && $has_target_property) {
          $decoded_entity[$field_name][$key][$reference_details['reference_field_target_property_name']] = $entity->id();
        }
        elseif ($has_property) {
          $decoded_entity[$field_name][$key][$reference_details['reference_field_property_name']] = $entity;
        }
        else {
          $decoded_entity[$field_name][$key] = $entity;
        }
      }
    }
  }

  // @todo Move to data_loader
  public function loadEntity(string $entity_id, string $field_name, string $field_value, Node $institution, ?string $entity_type = NULL, ?string $entity_bundle = NULL, ?string $hei_field_name = NULL): ?EntityInterface {

    $conditions[] = [
      'field' => $field_name,
      'value' => $field_value,
      'operator' => NULL,
    ];

    if (isset($hei_field_name)) {
      $conditions[] = [
        'field' => $hei_field_name,
        'value' => $institution->id(),
        'operator' => NULL,
      ];
    }

    if (isset($entity_type)) {
      $conditions[] = [
        'field' => 'type',
        'value' => $entity_type,
        'operator' => NULL,
      ];
    }

    if (isset($entity_bundle)) {
      $conditions[] = [
        'field' => 'bundle',
        'value' => $entity_bundle,
        'operator' => NULL,
      ];
    }

    $entity = $this->dataLoader->loadEntitiesWithConditions(
      entity_type_id: $entity_id,
      conditions: $conditions
    );

    return reset($entity);
  }

}
