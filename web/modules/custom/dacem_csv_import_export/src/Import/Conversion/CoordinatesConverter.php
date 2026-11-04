<?php

namespace Drupal\dacem_csv_import_export\Import\Conversion;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\ImportRow;

/**
 * Assembles configured latitude/longitude properties into a WKT point.
 */
final class CoordinatesConverter {

  public function __construct(protected BundleCsvDefinitions $definitions) {}

  public function convert(ImportRow $row): ImportRow {
    $mapping = $this->definitions->find($row->bundle)?->coordinates ?? [];
    if (!$mapping) {
      return $row;
    }

    $fields = $row->fieldsByLanguage;
    $field_name = $mapping['target_field_name'];
    foreach ($fields as $language => $translation) {
      $latitude = $translation[$field_name][$mapping['lat_property']] ?? NULL;
      $longitude = $translation[$field_name][$mapping['lon_property']] ?? NULL;
      if ($latitude === '' && $longitude === '') {
        $fields[$language][$field_name] = [];
      }
      elseif ($latitude !== NULL && $longitude !== NULL && $latitude !== '' && $longitude !== '') {
        // WKT uses longitude first. Zero is a valid coordinate.
        $fields[$language][$field_name] = sprintf('%s (%f %f)', $mapping['type'], $longitude, $latitude);
      }
      // Keep partial or absent coordinates unchanged for downstream validation.
    }
    return $row->withFields($fields);
  }

}
