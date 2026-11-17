<?php

namespace Drupal\dacem_csv_import_export\Schema;

/**
 * Adds Drupal value extraction instructions to a shared CSV column address.
 */
final class CsvExportColumn {

  /**
   * The stored property can differ from the header's optional property suffix.
   * For example, a reference header omits "___target_id" for its sole property.
   */
  public function __construct(
    public readonly CsvColumn $column,
    public readonly ?string $valueProperty = NULL,
    public readonly bool $isReference = FALSE,
  ) {}

}
