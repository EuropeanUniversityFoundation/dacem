<?php

namespace Drupal\dacem_csv_import_export\Import\Result;

/**
 * The final outcome: a completed row count or errors with zero committed rows.
 */
final class ImportResult {

  /**
   * @param \Drupal\dacem_csv_import_export\Import\Result\ImportError[] $errors
   *   Errors already suitable for presentation by the import form.
   */
  private function __construct(
    public readonly int $importedCount,
    public readonly array $errors,
  ) {}

  public static function success(int $count): self {
    return new self($count, []);
  }

  public static function failure(array $errors): self {
    if (!$errors) {
      throw new \InvalidArgumentException('A failed import must have at least one error.');
    }
    return new self(0, $errors);
  }

  public function isSuccessful(): bool {
    return !$this->errors;
  }

}
