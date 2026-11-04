<?php

namespace Drupal\dacem_csv_import_export\Import;

/**
 * Preserves the source row when a transaction is rolled back during saving.
 */
final class ImportWriteException extends \RuntimeException {

  public function __construct(
    public readonly ImportRow $row,
    public readonly ?string $language,
    \Throwable $previous,
  ) {
    parent::__construct($previous->getMessage(), 0, $previous);
  }

}
