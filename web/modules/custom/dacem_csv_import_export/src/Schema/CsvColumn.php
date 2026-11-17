<?php

namespace Drupal\dacem_csv_import_export\Schema;

/**
 * Describes the address encoded by a CSV header, without Drupal field metadata.
 */
final class CsvColumn {

  public const LANGUAGE_SEPARATOR = '|';
  public const DELTA_SEPARATOR = '____';
  public const PROPERTY_SEPARATOR = '___';

  public function __construct(
    public readonly string $header,
    public readonly string $fieldName,
    public readonly ?string $language = NULL,
    public readonly ?int $delta = NULL,
    public readonly ?string $property = NULL,
  ) {}

  public function headerWithoutLanguage(): string {
    return explode(self::LANGUAGE_SEPARATOR, $this->header, 2)[0];
  }

  public function isVirtualReference(): bool {
    return str_contains($this->fieldName, '.');
  }

  /**
   * The literal "entity" segment marks a reference hop, not another field.
   */
  public function referencePath(): array {
    return array_values(array_filter(
      explode('.', $this->fieldName),
      static fn (string $segment): bool => $segment !== 'entity',
    ));
  }

}
