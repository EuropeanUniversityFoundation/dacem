<?php

namespace Drupal\dacem_csv_import_export\CsvConverter;

use Drupal\dacem_csv_import_export\Import\Conversion\CoordinatesConverter;
use Drupal\dacem_csv_import_export\Import\Conversion\FormattedTextConverter;
use Drupal\dacem_csv_import_export\Import\ImportRow;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;

/**
 * Maps CSV cells into field replacements and delegates value conversion.
 */
class CsvDecoder {

  public function __construct(
    protected CsvHeaderParser $headerParser,
    protected CoordinatesConverter $coordinatesConverter,
    protected FormattedTextConverter $formattedTextConverter,
  ) {}

  /**
   * @return \Drupal\dacem_csv_import_export\Import\ImportRow[]
   *   Decoded rows retaining the original CSV record numbers.
   */
  public function decodeMultiple(string $entity_type, array $records): array {
    $entity_data = [];
    $columns = [];
    foreach ($records as $row_number => $record) {
      $entity_data[] = $this->decodeRecord($entity_type, $record, $columns, $row_number + 1);
    }

    return $entity_data;
  }

  public function decode(string $entity_type, array $record, int $row_number = 2): ImportRow {
    $columns = [];
    return $this->decodeRecord($entity_type, $record, $columns, $row_number);
  }

  /**
   * Shares parsed headers across rows while preserving absent and empty cells.
   *
   * @param \Drupal\dacem_csv_import_export\Schema\CsvColumn[] $columns
   *   Columns keyed by original header, compiled once per distinct header.
   */
  protected function decodeRecord(string $entity_type, array $record, array &$columns, int $row_number): ImportRow {
    $entity_data = [];
    $source_columns = [];

    foreach ($record as $key => $value) {
      // A present empty cell clears a value; missing columns remain untouched.
      $value = $value ?? '';
      $column = $columns[$key] ??= $this->headerParser->parse($key);
      $source_columns[$key] = $column;
      // These are operation metadata or protected Drupal fields, not content.
      if (in_array($column->fieldName, ['type', 'langcode', 'csv_row'], TRUE)) {
        continue;
      }
      $language = $column->language ?? CsvHeaderParser::DEFAULT_LANGUAGE;
      $field_name = $column->fieldName;
      $delta = $column->delta;
      $property = $column->property;

      if ($delta !== NULL && $property !== NULL) {
        $entity_data[$language][$field_name][$delta][$property] = $value;
      }
      elseif ($delta !== NULL) {
        $entity_data[$language][$field_name][$delta] = $value;
      }
      elseif ($property !== NULL) {
        $entity_data[$language][$field_name][$property] = $value;
      }
      else {
        $entity_data[$language][$field_name] = $value;
      }
    }

    $row = new ImportRow($entity_type, $row_number, $entity_data, $source_columns);
    $row = $this->coordinatesConverter->convert($row);
    return $this->formattedTextConverter->convert($row);
  }

}
