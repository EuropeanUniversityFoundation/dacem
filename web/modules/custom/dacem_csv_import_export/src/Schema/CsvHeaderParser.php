<?php

namespace Drupal\dacem_csv_import_export\Schema;

/**
 * Reads and builds field[____delta][___property][|language] headers.
 */
final class CsvHeaderParser {

  public const DEFAULT_LANGUAGE = 'en';

  /**
   * @throws \InvalidArgumentException
   *   When a header contains empty or malformed suffixes.
   */
  public function parse(string $header): CsvColumn {
    $parts = explode(CsvColumn::LANGUAGE_SEPARATOR, $header);
    if (count($parts) > 2 || $parts[0] === '' || (isset($parts[1]) && $parts[1] === '')) {
      throw new \InvalidArgumentException('Invalid CSV header: ' . $header);
    }
    $language = $parts[1] ?? NULL;
    $address = $parts[0];
    $delta_parts = explode(CsvColumn::DELTA_SEPARATOR, $address);
    if (count($delta_parts) > 2) {
      throw new \InvalidArgumentException('Invalid CSV delta suffix: ' . $header);
    }

    $delta = NULL;
    if (count($delta_parts) === 2) {
      $field_name = $delta_parts[0];
      $property_parts = explode(CsvColumn::PROPERTY_SEPARATOR, $delta_parts[1]);
      if ($property_parts[0] === '' || !ctype_digit($property_parts[0])) {
        throw new \InvalidArgumentException('Invalid CSV delta suffix: ' . $header);
      }
      $delta = (int) $property_parts[0];
    }
    else {
      $property_parts = explode(CsvColumn::PROPERTY_SEPARATOR, $address);
      $field_name = $property_parts[0];
    }
    if ($field_name === '' || str_contains($field_name, CsvColumn::PROPERTY_SEPARATOR) || count($property_parts) > 2 || (isset($property_parts[1]) && $property_parts[1] === '')) {
      throw new \InvalidArgumentException('Invalid CSV property suffix: ' . $header);
    }

    return new CsvColumn($header, $field_name, $language, $delta, $property_parts[1] ?? NULL);
  }

  public function create(string $field_name, ?string $language = NULL, ?int $delta = NULL, ?string $property = NULL): CsvColumn {
    $header = $field_name;
    if ($delta !== NULL) {
      $header .= CsvColumn::DELTA_SEPARATOR . $delta;
    }
    if ($property !== NULL) {
      $header .= CsvColumn::PROPERTY_SEPARATOR . $property;
    }
    if ($language !== NULL) {
      $header .= CsvColumn::LANGUAGE_SEPARATOR . $language;
    }
    return $this->parse($header);
  }

  /**
   * Groups original headers while retaining their delta and property suffixes.
   */
  public function groupByLanguage(array $headers): array {
    $groups = ['non-translatable' => [], 'translatable' => []];
    foreach ($headers as $header) {
      $column = $this->parse($header);
      if ($column->language === NULL) {
        $groups['non-translatable'][] = $header;
      }
      else {
        $groups['translatable'][$column->language][] = $column->headerWithoutLanguage();
      }
    }
    return $groups;
  }

  public function languages(array $headers): array {
    return array_keys($this->groupByLanguage($headers)['translatable']);
  }

}
