<?php

namespace Drupal\dacem_csv_import_export\CsvConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\node\Entity\Node;

class ReferenceResolver {

  public function __construct(
    protected CatalogueEntityRepository $repository,
    protected BundleCsvDefinitions $definitions,
  ) {}

  public function resolveReferencesMultiple(array $rows, Node $institution): array {
    return array_map(fn (ImportRow $row): ImportRow => $this->resolveReferences($row, $institution), $rows);
  }

  public function resolveReferences(ImportRow $row, Node $institution): ImportRow {
    return $this->resolveRow($row, $institution, FALSE);
  }

  public function resolveReferencesOnSave(ImportRow $row, Node $institution): ImportRow {
    return $this->resolveRow($row, $institution, TRUE);
  }

  protected function resolveRow(ImportRow $row, Node $institution, bool $on_save): ImportRow {
    $fields = $row->fieldsByLanguage;
    foreach (($this->definitions->find($row->bundle)?->referenceResolution ?? []) as $reference_details) {
      $deferred = ($reference_details['runs'] ?? NULL) === 'on_save';
      if ($deferred === $on_save) {
        $this->processReference($fields, $reference_details, $institution);
      }
    }
    return $row->withFields($fields);
  }

  protected function processReference(array &$decoded_entity, array $reference_details, Node $institution): void {
    $field_name = $reference_details['reference_field_name'];

    foreach ($decoded_entity as $language => &$translation) {
      if (!array_key_exists($field_name, $translation)) {
        continue;
      }

      if ($translation[$field_name] === '' || $translation[$field_name] === NULL) {
        $translation[$field_name] = [];
        continue;
      }

      if ($reference_details['cardinality'] === 1) {
        $this->processSingleReference($translation, $reference_details, $institution);
      }
      else {
        $this->processMultipleReferences($translation, $reference_details, $institution);
      }
    }
  }

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

      if ($search_value === '' || $search_value === NULL) {
        unset($decoded_entity[$field_name][$key]);
        continue;
      }

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

  public function loadEntity(
    string $entity_id,
    string $field_name,
    string $field_value,
    Node $institution,
    ?string $entity_type = NULL,
    ?string $entity_bundle = NULL,
    ?string $hei_field_name = NULL
  ): EntityInterface | FALSE {

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
      $bundle_key = ($entity_id === 'taxonomy_term') ? 'vid' : 'type';

      $conditions[] = [
        'field' => $bundle_key,
        'value' => $entity_bundle,
        'operator' => NULL,
      ];
    }

    $entity = $this->repository->findByConditions(
      entity_type: $entity_id,
      conditions: $conditions
    );

    return !empty($entity) ? reset($entity) : FALSE;
  }

}
