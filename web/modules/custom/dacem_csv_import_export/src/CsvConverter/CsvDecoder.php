<?php

namespace Drupal\dacem_csv_import_export\CsvConverter;

use Drupal\dacem_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CsvDecoder {

  public const GEO_FIELD_LAT_LONGS = [
    ImportTargetEntityType::OUNIT->value => [
      'target_field_name' => 'field_ou_map',
      'lat_column' => 'field_ou_map___lat',
      'lon_column' => 'field_ou_map___lon',
      'type' => 'POINT',
      'lat_property' => 'lat',
      'lon_property' => 'lon',
    ]
  ];

  public const DEFAULT_FORMATTED_TEXT_FORMAT = 'basic_html';

  protected FieldMappingService $fieldMappingService;

  public function __construct(
    FieldMappingService $field_mapping_service,
  ) {
    $this->fieldMappingService = $field_mapping_service;
  }

  public static function create(ContainerInterface $container) {

    return new static(
      $container->get('dacem_csv_import_export.field_mapping_service'),
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
    $entity_data = $this->generateWKTFromColumns($entity_type, $entity_data);
    $entity_data = $this->convertLineBreaks($entity_type, $entity_data);

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

  public function generateWKTFromColumns(string $entity_type, array $entity_data): array {
    if (!isset(self::GEO_FIELD_LAT_LONGS[$entity_type])){
      return $entity_data;
    }

    foreach ($entity_data as $language => &$data_row) {
      $lat = $data_row[self::GEO_FIELD_LAT_LONGS[$entity_type]['target_field_name']][self::GEO_FIELD_LAT_LONGS[$entity_type]['lat_property']] ?? NULL;
      $lon = $data_row[self::GEO_FIELD_LAT_LONGS[$entity_type]['target_field_name']][self::GEO_FIELD_LAT_LONGS[$entity_type]['lon_property']] ?? NULL;

      if (!is_null($lat) && !is_null($lon)) {
        $value = sprintf('%s (%f %f)',
          self::GEO_FIELD_LAT_LONGS[$entity_type]['type'],
          $lon,
          $lat,
        );

        $data_row[self::GEO_FIELD_LAT_LONGS[$entity_type]['target_field_name']] = $value;
      }
    }

    return $entity_data;
  }

  public function convertLineBreaks(string $entity_type, array $entity_data): array {
    $formatted_fields = $this->fieldMappingService->getFormattedFieldList($entity_type);
    foreach ($entity_data as $language => &$data_row) {
      foreach ($data_row as $field_name => &$field_value) {
        if (in_array($field_name, $formatted_fields)) {
          $text = nl2br($data_row[$field_name]);
          $data_row[$field_name] = [];
          $data_row[$field_name]['value'] = $text;
          $data_row[$field_name]['format'] = self::DEFAULT_FORMATTED_TEXT_FORMAT;
        }
      }
    }

    return $entity_data;
  }

}
