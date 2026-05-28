<?php

namespace Drupal\euf_csv_import_export\CsvConverter;

use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class CsvDecoder {

  protected FieldMappingService $fieldMappingService;

  public function __construct(
    FieldMappingService $field_mapping_service,
  ) {
    $this->fieldMappingService = $field_mapping_service;
  }

  public static function create(ContainerInterface $container) {
    // @phpstan-ignore new.static
    return new static(
      $container->get('euf_csv_import_export.field_mapping_service'),
    );
  }

  public function decodeMultiple(string $entity_type, array $records) {
    $entity_data = [];
    foreach ($records as $row_number => $record) {
      $entity_data[] = $this->decode($entity_type, $record);
    }

    return $entity_data;
  }

  /**
   *
   */
  public function decode(string $entity_type, array $record) {
    $entity_data = [];

    foreach ($record as $key => $value) {
      [$language, $column_name] = $this->fieldMappingService->resolveLanguageFromColumn($key);
      $field_name = $this->fieldMappingService->resolveFieldFromColumn($column_name);
      $delta = $this->fieldMappingService->resolveColumnDelta($column_name);
      $property = $this->fieldMappingService->resolveColumnProperty($column_name);

      if (!is_null($field_name)
        && !is_null($delta)
        && !is_null($property)
        && $value != '') {

        $entity_data[$language][$field_name][$delta][$property] = $value;

      }
      elseif (!is_null($field_name)
        && !is_null($delta)
        && is_null($property)
        && $value != '') {

        $entity_data[$language][$field_name][$delta] = $value;

      }
      elseif (!is_null($field_name)
        && is_null($delta)
        && !is_null($property)
        && $value != '') {

        $entity_data[$language][$field_name][$property] = $value;

      }
      elseif (!is_null($field_name)
        && is_null($delta)
        && is_null($property)
        && $value != '') {

        $entity_data[$language][$field_name] = $value;
      }
    }

    $entity_data = $this->addEntityType($entity_type, $entity_data);
    $entity_data = $this->addLanguageCode($entity_data);

    return $entity_data;
  }

  public function addEntityType(string $entity_type, array $decoded_data) {
    foreach ($decoded_data as &$data_per_language) {
      $data_per_language['type'] = $entity_type;
    }

    return $decoded_data;
  }

  public function addLanguageCode(array $entity_data) {
    foreach ($entity_data as $language => &$data_per_language) {
      $data_per_language['langcode'] = $language;
    }

    return $entity_data;
  }

  // /**
  //  *
  //  */
  // public function addFakeUuids(string $to_field, array $decoded_data) {
  //   foreach ($decoded_data as $key => &$decoded_entity) {
  //     if (!isset($decoded_entity[$to_field])) {
  //       $decoded_entity[$to_field] = $this->uuid->generate();
  //     }
  //   }

  //   return $decoded_data;
  // }

  // /**
  //  *
  //  */
  // public function addBundle(string $bundle, array $decoded_data): array {
  //   foreach ($decoded_data as $key => &$decoded_entity) {
  //     if (!isset($decoded_entity['bundle'])) {
  //       $decoded_entity['bundle'] = $bundle;
  //     }
  //   }

  //   return $decoded_data;
  // }

}
