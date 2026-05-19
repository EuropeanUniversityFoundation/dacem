<?php

namespace Drupal\euf_csv_import_export\CsvConverter;

//use Drupal\Component\Uuid\Pecl;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
//use Drupal\occ_development_utility_services\OunitUtilities;
use Symfony\Component\DependencyInjection\ContainerInterface;
//use Drupal\occ_development_utility_services\ProgrammeUtilities1;
//use Drupal\occ_development_utility_services\InstitutionUtilities;

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

  public function decodeMultiple(array $records) {
    $entity_data = [];
    foreach ($records as $row_number => $record) {
      $entity_data[] = $this->decode($record);
    }

    return $entity_data;
  }

  /**
   *
   */
  public function decode(array $record) {
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
