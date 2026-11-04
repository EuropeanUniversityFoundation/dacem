<?php

namespace Drupal\dacem_csv_import_export\Import\Result;

/**
 * A user-facing import failure with CSV source context.
 */
final class ImportError {

  public function __construct(
    public readonly string $type,
    public readonly string $message,
    public readonly int|string|null $rowNumber = NULL,
    public readonly string $column = '',
    public readonly array $values = [],
    public readonly ?string $language = NULL,
    public readonly ?string $fieldPath = NULL,
  ) {}

}
