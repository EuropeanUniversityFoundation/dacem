<?php

namespace Drupal\euf_csv_import_export\CsvConverter;

use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;

class FieldMappingService {

  public const LANGUAGE_SEPARATOR = '.';
  public const DEFAULT_LANGUAGE = 'en';
  public const HEADER_SEPARATOR = '_';

  public const HEI_COLUMNS = [
    ImportTargetEntityType::OUNIT->value => [
      'hei_column_name' => 'field_ou_institution',
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      'hei_column_name' => 'field_programme_institution',
    ],
    ImportTargetEntityType::COURSE->value => [
      'hei_column_name' => 'hei',
    ],
    ImportTargetEntityType::COURSE_INSTANCE->value => [
      'hei_column_name' => 'hei',
    ]
  ];

  public const CODE_COLUMNS = [
    ImportTargetEntityType::OUNIT->value => [
      'code_column_name' => 'field_ou_code',
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      'code_column_name' => 'field_programme_code',
    ],
    ImportTargetEntityType::COURSE->value => [
      'code_column_name' => 'field_iec_code',
    ],
    // ImportTargetEntityType::COURSE_INSTANCE->value => [
    //   'code_column_name' => 'hei',
    // ]
  ];

  /**
   * List of required headers for each file target.
   */
  public const REQUIRED_DATA = [
    ImportTargetEntityType::OUNIT->value => [
      'non-translatable' => [
        'field_ou_institution',
        'field_ou_code',

      ],
      'translatable' => [
        'field_ou_description',
        'title',
        'field_ou_type',
      ]
    ],
    // @todo Continue with the rest of the entity types.
    ImportTargetEntityType::PROGRAMME->value => ['hei', 'title____0___string', /*'title____0___lang',*/ 'code', 'programme__ects', 'programme__eqf_level_provided', 'programme__isced_code____0', 'language_of_instruction____0', 'programme__length', 'description____0___multiline', /*'description____0___lang',*/ 'learning_outcomes____0___multiline', /*'learning_outcomes____0___lang'*/],
    ImportTargetEntityType::COURSE->value => ['title____0___string', /*'title____0___lang',*/ 'code', 'course__ects', 'language_of_instruction____0', 'description____0___multiline', /*'description____0___lang',*/ 'learning_outcomes____0___multiline', /*'learning_outcomes____0___lang'*/ 'course__academic_term____0', ],
    ImportTargetEntityType::COURSE_INSTANCE->value => ['hei', 'start_date', 'end_date', 'academic_term_id', 'course', /*'ects', 'language_of_instruction____0'*/],
  ];

  public function sortHeadersByLanguage(array $headers) {
    $language_sorted_headers = [];

    foreach ($headers as $header) {
      if (str_contains($header, self::LANGUAGE_SEPARATOR)) {
        $item = explode(self::LANGUAGE_SEPARATOR, $header);
        $language_sorted_headers['translatable'][$item[1]][] = $item[0];
      } else {
        $language_sorted_headers['non-translatable'][] = $header;
      }
    }

    return $language_sorted_headers;
  }

  public function getTranslationLanguagesFromHeaders(array $headers) {
    $language_indicators = [];

    foreach ($headers as $header) {
      if (str_contains($header, self::LANGUAGE_SEPARATOR)) {
        $language_indicators[] = explode(self::LANGUAGE_SEPARATOR, $header)[1];
      }
    }

    return array_values(array_unique($language_indicators));
  }

  public function resolveFieldFromColumn(string $csv_header) {
    for ($i = 4; $i >= 3; $i--) {
      $separator = str_repeat(self::HEADER_SEPARATOR, $i);
      $position = strpos($csv_header, $separator);

      if ($position !== FALSE) {

        return substr($csv_header, 0, $position);
      }
    }

    return $csv_header;
  }

  /**
   *
   */
  public function columnHasDelta(string $csv_header) {
    return str_contains($csv_header, str_repeat(self::HEADER_SEPARATOR, 4));
  }

  /**
   *
   */
  public function resolveColumnDelta(string $csv_header): int | null {

    if (!$this->columnHasDelta($csv_header)) {
      return NULL;
    }

    $delta = explode(str_repeat(self::HEADER_SEPARATOR, 4), $csv_header)[1];

    if (is_numeric($delta)) {
      return (int) $delta;
    }
    else {
      return (int) explode(str_repeat(self::HEADER_SEPARATOR, 3), $delta)[0];
    }
  }

  /**
   *
   */
  public function columnHasProperty(string $csv_header): bool | null {

    if ($this->columnHasDelta($csv_header)) {
      $property_string = explode(str_repeat(self::HEADER_SEPARATOR, 4), $csv_header)[1];
    }
    else {
      $property_string = $csv_header;
    }

    return str_contains($property_string, str_repeat(self::HEADER_SEPARATOR, 3));
  }

  /**
   *
   */
  public function resolveColumnProperty(string $csv_header): string | null {

    if (!$this->columnHasProperty($csv_header)) {
      return NULL;
    }

    if ($this->columnHasDelta($csv_header)) {
      $property_string = explode(str_repeat(self::HEADER_SEPARATOR, 4), $csv_header)[1];
    }
    else {
      $property_string = $csv_header;
    }

    return explode(str_repeat(self::HEADER_SEPARATOR, 3), $property_string)[1];
  }

  public function resolveLanguageFromColumn(string $column_name) {
    if (str_contains($column_name, self::LANGUAGE_SEPARATOR)) {
      $exploded = explode(self::LANGUAGE_SEPARATOR, $column_name);
      return [end($exploded), reset($exploded)];
    }

    return [self::DEFAULT_LANGUAGE, $column_name];
  }

}
