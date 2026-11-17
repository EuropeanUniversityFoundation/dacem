<?php

namespace Drupal\dacem_csv_import_export\Import\Result;

/**
 * A validated import preview, containing no entities or writable field data.
 */
final class ImportSummary {

  /**
   * @param string[] $updatedCodes
   *   Codes of existing entities targeted by the CSV, even if values match.
   */
  public function __construct(
    public readonly string $institution,
    public readonly int $createdCount,
    public readonly array $updatedCodes,
  ) {}

}
