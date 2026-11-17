<?php

namespace Drupal\dacem_csv_import_export\Schema;

use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;

/**
 * Expands a bundle layout and field metadata into ordered export columns.
 */
final class CsvSchemaCompiler {

  public function __construct(
    protected BundleCsvDefinitions $definitions,
    protected CsvHeaderParser $headerParser,
  ) {}

  public function compile(string $bundle, array $metadata): CsvExportSchema {
    $languages = array_keys($metadata['languages'] ?? [CsvHeaderParser::DEFAULT_LANGUAGE => TRUE]);
    $fields = $metadata['fields'] ?? [];
    $layout = $this->definitions->find($bundle)?->exportFields ?? array_keys($fields);
    $columns = [];
    foreach ($layout as $field_name) {
      if (str_contains($field_name, '.')) {
        $columns[] = new CsvExportColumn($this->headerParser->parse($field_name));
        continue;
      }
      // A configured layout may mention fields absent from this installation.
      if (!isset($fields[$field_name])) {
        continue;
      }
      array_push($columns, ...$this->compileField($field_name, $fields[$field_name], $languages));
    }
    return new CsvExportSchema($columns);
  }

  /**
   * Expands delta -> language -> property, preserving the existing CSV order.
   *
   * Cardinality and translatability determine suffix presence; observed data
   * determines the languages and delta count. An empty multi-value field still
   * produces its delta-zero column. A single stored property omits its suffix.
   *
   * @return \Drupal\dacem_csv_import_export\Schema\CsvExportColumn[]
   *   Ordered columns for one field.
   */
  protected function compileField(string $field_name, array $metadata, array $languages): array {
    $multiple = ($metadata['multi-cardinality'] ?? FALSE) === TRUE;
    $translatable = ($metadata['translatable'] ?? FALSE) === TRUE;
    $properties = ($metadata['properties'] ?? []) ?: ['value'];
    $property_suffix = $metadata['has_multiple_properties'] ?? FALSE;
    $delta_count = $multiple ? max((int) ($metadata['max_delta'] ?? 1), 1) : 1;
    $columns = [];
    for ($delta = 0; $delta < $delta_count; $delta++) {
      foreach ($translatable ? $languages : [NULL] as $language) {
        foreach ($properties as $property) {
          $column = $this->headerParser->create(
            $field_name,
            $language,
            $multiple ? $delta : NULL,
            $property_suffix ? $property : NULL,
          );
          $columns[] = new CsvExportColumn($column, $property, $metadata['is_reference']);
        }
      }
    }
    return $columns;
  }

}
