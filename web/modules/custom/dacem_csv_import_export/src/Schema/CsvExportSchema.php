<?php

namespace Drupal\dacem_csv_import_export\Schema;

/**
 * Keeps export headers and their value extraction instructions in one order.
 */
final class CsvExportSchema {

  /**
   * @param \Drupal\dacem_csv_import_export\Schema\CsvExportColumn[] $columns
   *   Ordered export columns.
   */
  public function __construct(public readonly array $columns) {}

  public function headers(): array {
    return array_map(
      static fn (CsvExportColumn $column): string => $column->column->header,
      $this->columns,
    );
  }

}
