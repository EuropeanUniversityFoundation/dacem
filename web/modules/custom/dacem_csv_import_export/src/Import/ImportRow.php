<?php

namespace Drupal\dacem_csv_import_export\Import;

use Drupal\dacem_csv_import_export\Schema\CsvColumn;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;

/**
 * Keeps source metadata separate from a row's requested field replacements.
 *
 * An absent field is untouched; a present empty value explicitly clears it.
 * Field values may contain scalars, item/property arrays, or resolved entities.
 */
final class ImportRow {

  /**
   * @param string $bundle
   *   The target node bundle, supplied by the import operation.
   * @param int $rowNumber
   *   The original one-based CSV record number, including the header record.
   * @param array<string, array<string, mixed>> $fieldsByLanguage
   *   Field replacements keyed by language; source metadata is not inserted.
   * @param \Drupal\dacem_csv_import_export\Schema\CsvColumn[] $columnsByHeader
   *   Original column addresses, retained through field transformations.
   */
  public function __construct(
    public readonly string $bundle,
    public readonly int $rowNumber,
    public readonly array $fieldsByLanguage,
    public readonly array $columnsByHeader = [],
  ) {
    if ($rowNumber < 1) {
      throw new \InvalidArgumentException('A CSV row number must be positive.');
    }
  }

  public function fieldValue(string $field, string $language = CsvHeaderParser::DEFAULT_LANGUAGE): mixed {
    return $this->fieldsByLanguage[$language][$field] ?? NULL;
  }

  /**
   * Returns updated field replacements while preserving their source metadata.
   */
  public function withFields(array $fields): self {
    return new self($this->bundle, $this->rowNumber, $fields, $this->columnsByHeader);
  }

  /**
   * Omits deferred fields from preflight without changing the row to be saved.
   */
  public function withoutFields(array $field_names): self {
    $excluded = array_fill_keys($field_names, TRUE);
    $fields = [];
    foreach ($this->fieldsByLanguage as $language => $values) {
      $fields[$language] = array_diff_key($values, $excluded);
    }
    return $this->withFields($fields);
  }

  /**
   * Finds the original CSV address corresponding to a Drupal field violation.
   */
  public function findColumn(string $field, ?string $language, ?int $delta, ?string $property): ?CsvColumn {
    $best = NULL;
    $best_score = -1;
    foreach ($this->columnsByHeader as $column) {
      if ($column->fieldName !== $field
        || ($column->language !== NULL && $language !== NULL && $column->language !== $language)
        || ($column->delta !== NULL && $delta !== NULL && $column->delta !== $delta)
        || ($column->property !== NULL && $property !== NULL && $column->property !== $property)) {
        continue;
      }
      $score = (int) ($column->language === $language) * 4
        + (int) ($column->delta === $delta) * 2
        + (int) ($column->property === $property);
      if ($score > $best_score) {
        $best = $column;
        $best_score = $score;
      }
    }
    return $best;
  }

}
