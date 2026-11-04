<?php

namespace Drupal\dacem_csv_import_export\CsvConverter;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Schema\CsvColumn;
use Drupal\dacem_csv_import_export\Schema\CsvExportColumn;
use Drupal\dacem_csv_import_export\Schema\CsvExportSchema;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use Drupal\node\Entity\Node;

/**
 * Collects Drupal field metadata and extracts values for compiled CSV schemas.
 */
class FieldMappingService {

  public const LANGUAGE_SEPARATOR = CsvColumn::LANGUAGE_SEPARATOR;
  public const DEFAULT_LANGUAGE = CsvHeaderParser::DEFAULT_LANGUAGE;

  public const EXPORT_SKIPPED_FIELDS = [
    'nid', 'vid', 'type', 'uuid', 'revision_uid', 'revision_timestamp',
    'revision_log', 'uid', 'status', 'created', 'changed', 'promote',
    'sticky', 'default_langcode', 'revision_translation_affected',
    'content_translation_source', 'content_translation_outdated',
    'langcode', 'path', 'revision_default', 'field_ou_picture',
  ];

  public const EXPORT_SKIPPED_PROPERTIES = [
    'value', 'format', 'processed', 'entity', 'detailed', 'narrow', 'broad', 'options', 'entity',
    'left', 'top', 'right', 'bottom', 'geo_type', 'geohash', 'latlon'
  ];

  protected EntityFieldManager $entityFieldManager;

  public function __construct(
    EntityFieldManager $entity_field_manager,
    protected BundleCsvDefinitions $definitions,
  ) {
    $this->entityFieldManager = $entity_field_manager;
  }

  public function generateFieldMetadata(string $entity_type_id, string $entity_bundle, array $entities) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_bundle);

    $metadata = [];
    $metadata['languages'] = [
      FieldMappingService::DEFAULT_LANGUAGE => TRUE
    ];

    foreach ($entities as $id => $entity) {
      /** @var \Drupal\node\NodeInterface $entity */
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $metadata['languages'][$langcode] = TRUE;
      }
    }

    foreach ($field_definitions as $field_name => $definition) {
      if (in_array($field_name, self::EXPORT_SKIPPED_FIELDS)) {
        continue;
      }

      // Only export stored properties, not computed objects such as datetime's date.
      $storage_definition = $definition->getFieldStorageDefinition();
      $properties = [];
      foreach ($storage_definition->getPropertyDefinitions() as $property_name => $property_definition) {
        if (!$property_definition->isComputed()) {
          $properties[] = $property_name;
        }
      }

      // Filter out internal properties you do not want in your CSV.
      $properties = array_diff($properties, self::EXPORT_SKIPPED_PROPERTIES);

      $metadata['fields'][$field_name] = [
        'translatable' => FALSE,
        'multi-cardinality' => FALSE,
        'max_delta' => 0,
        'properties' => array_values($properties),
        'has_multiple_properties' => (count($properties) > 1),
        'is_reference' => ($definition->getType() === 'entity_reference'),
        'target_entity_type' => $definition->getSetting('target_type'),
        'target_bundle' => $definition->getSetting('handler_settings')['target_bundles'] ?? NULL,
      ];

      if ($definition->isTranslatable()) {
        $metadata['fields'][$field_name]['translatable'] = TRUE;
      }

      if ($storage_definition->isMultiple()) {
        $metadata['fields'][$field_name]['multi-cardinality'] = TRUE;
      }
    }

    foreach ($metadata['fields'] as $field_name => &$field_meta_data) {
      if ($field_meta_data['multi-cardinality'] === TRUE) {
        foreach ($entities as $entity) {
          /** @var \Drupal\node\NodeInterface $entity */
          foreach ($entity->getTranslationLanguages() as $langcode => $language) {
            $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;

            /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
            $field_items = $translation->get($field_name);
            $count = $field_items->count();

            if ($count > $field_meta_data['max_delta']) {
              $field_meta_data['max_delta'] = $count;
            }
          }
        }
      }
    }

    return $metadata;
  }

  public function exportEntityRow(Node $entity, CsvExportSchema $schema): array {
    /** @var \Drupal\node\NodeInterface $entity */
    $row = [];

    foreach ($schema->columns as $export_column) {
      if ($export_column->column->isVirtualReference()) {
        $row[] = $this->resolveVirtualReferenceColumn($entity, $export_column->column->referencePath());
        continue;
      }

      $row[] = $this->resolveFieldColumn($entity, $export_column);
    }

    return $row;
  }

  /**
   * Resolves a single "real field" column value for one exported row.
   */
  protected function resolveFieldColumn(Node $entity, CsvExportColumn $export_column): string {
    $column = $export_column->column;
    $langcode = $column->language;
    $field_name = $column->fieldName;
    $delta = $column->delta ?? 0;
    $property = $export_column->valueProperty;

    if ($langcode !== NULL) {
      $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
    } else {
      $translation = $entity;
    }

    if (!$translation || !$translation->hasField($field_name)) {
      return '';
    }

    $field_items = $translation->get($field_name);
    $item = $field_items->get($delta);

    if (!$item) {
      return '';
    }

    // RESOLUTION LAYER FOR ENTITY REFERENCES
    if ($export_column->isReference && $property === 'target_id') {
      $referenced_entity = $item->entity;

      return $referenced_entity instanceof \Drupal\Core\Entity\ContentEntityInterface
        ? $this->resolveReferenceCode($referenced_entity)
        : '';
    }

    // Standard non-reference property value fetching
    $value = $item->{$property} ?? '';

    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Resolves a virtual, dot-path column by walking a chain of references.
   *
   * E.g. for path ['field_iec_programme', 'field_programme_institution'],
   * this follows the course's programme reference, then that programme's
   * institution reference, and returns the institution's code.
   */
  protected function resolveVirtualReferenceColumn(Node $entity, array $path): string {
    $current = $entity;
    $last_index = array_key_last($path);

    foreach ($path as $index => $field_name) {
      if (!$current instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$current->hasField($field_name)) {
        return '';
      }

      $referenced_entity = $current->get($field_name)->entity ?? NULL;

      if ($index === $last_index) {
        return $referenced_entity instanceof \Drupal\Core\Entity\ContentEntityInterface
          ? $this->resolveReferenceCode($referenced_entity)
          : '';
      }

      if (!$referenced_entity) {
        return '';
      }

      $current = $referenced_entity;
    }

    return '';
  }

  /**
   * Resolves the display value for a referenced entity: its configured code
   * field (e.g. field_ou_code) if its bundle has one, otherwise its label
   * or raw ID as a fallback.
   */
  protected function resolveReferenceCode(\Drupal\Core\Entity\ContentEntityInterface $referenced_entity): string {
    $target_bundle = $referenced_entity->bundle();
    $code_field_name = $this->definitions->find($target_bundle)?->codeField ?? NULL;

    if ($code_field_name && $referenced_entity->hasField($code_field_name)) {
      $value = $referenced_entity->get($code_field_name)->value ?? '';
      return is_scalar($value) ? (string) $value : '';
    }

    return (string) ($referenced_entity->label() ?: $referenced_entity->id());
  }

}
