<?php

namespace Drupal\dacem_csv_import_export\Import;

/**
 * Field replacements applied together to one language of an entity.
 *
 * Missing fields are preserved. Present empty values are intentional clears.
 */
final class ImportStage {

  /**
   * @param array<string, mixed> $fields
   *   Drupal field values, including explicit empty values.
   */
  public function __construct(
    public readonly string $language,
    public readonly array $fields,
  ) {}

}
