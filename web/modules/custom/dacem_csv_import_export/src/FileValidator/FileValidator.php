<?php

namespace Drupal\dacem_csv_import_export\FileValidator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\dacem_csv_import_export\AccessManager\UserAccessManager;
use Drupal\dacem_csv_import_export\Definition\BundleCsvDefinitions;
use Drupal\dacem_csv_import_export\Import\Result\ImportError;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\dacem_csv_import_export\Schema\CsvColumn;
use Drupal\dacem_csv_import_export\Schema\CsvHeaderParser;
use Drupal\node\Entity\Node;

class FileValidator {

  private const VALIDATION_METHODS = [
    'validateFileHeiUserMatch',
    'validateUniqueCodes',
    'validateNonEmpty',
    'validateReferences',
  ];

  /**
   * @var \Drupal\dacem_csv_import_export\Import\Result\ImportError[]
   */
  protected array $errors = [];

  public function __construct(
    protected UserAccessManager $userAccessManager,
    protected CsvHeaderParser $headerParser,
    protected CatalogueEntityRepository $repository,
    protected BundleCsvDefinitions $definitions,
  ) {}

  public function validateFileData(array $records, array $headers, string $entityType, array $userInstitutions): array {
    $this->errors = [];
    if (!$this->definitions->get($entityType)->importable) {
      throw new \InvalidArgumentException('CSV import is not supported for bundle: ' . $entityType);
    }
    // Report malformed suffixes as CSV errors before decoding or querying data.
    $invalid_headers = FALSE;
    foreach ($headers as $header) {
      try {
        $this->headerParser->parse($header);
      }
      catch (\InvalidArgumentException $exception) {
        $this->addError('Invalid CSV header', $header, 'CSV Headers', $exception->getMessage());
        $invalid_headers = TRUE;
      }
    }
    if ($invalid_headers) {
      return $this->errors;
    }
    $this->validateHeaders($headers, $entityType);
    if ($this->errors) {
      return $this->errors;
    }
    if (!$records) {
      $this->addError('Empty CSV', '', 'File', 'The CSV contains no data rows.');
      return $this->errors;
    }
    $this->validateSingleHeiInFile($records, $entityType);

    foreach (self::VALIDATION_METHODS as $method) {
      $this->{$method}($records, $entityType, $headers, $userInstitutions);
    }

    return $this->errors;
  }

  protected function validateSingleHeiInFile(array $records, string $entityType) {
    $heis_in_file = [];
    $hei_column_name = $this->definitions->get($entityType)->institutionColumn;

    foreach ($records as $record) {
      if (!in_array($record[$hei_column_name], $heis_in_file)) {
        $heis_in_file[] = $record[$hei_column_name];
      }
    }

    if (count($heis_in_file) > 1) {
      $this->addError('Multiple Institutions in CSV', $hei_column_name, 'Global', 'CSV file can not contain data for multiple Institutions.', $heis_in_file);
    }
  }

  protected function validateFileHeiUserMatch(array $records, string $entityType, array $headers, array $userInstitutions) {
    $userInstitutionCodes = $this->userAccessManager->getSchacCodesFromInstitutions($userInstitutions);
    $hei_column_name = $this->definitions->get($entityType)->institutionColumn;

    foreach ($records as $row_number => $record) {
      if (!in_array($record[$hei_column_name], $userInstitutionCodes)) {
        $this->addError('User Institution mismatch', $hei_column_name, $row_number + 1, 'The logged in user is not part of this Institution: ' . $record[$hei_column_name] . '.');
      }
    }
  }

  protected function validateHeaders(array $headers, string $entityType): void {
    $language_sorted_headers = $this->headerParser->groupByLanguage($headers);

    foreach ($this->definitions->get($entityType)->requiredSharedColumns as $required_header) {
      if (!in_array($required_header, $language_sorted_headers['non-translatable'])) {
        $this->addError('Required header is missing', $required_header, 'CSV Headers', 'Required header is missing from CSV.');
      }
    }

    foreach ($this->definitions->get($entityType)->requiredTranslatedColumns as $required_header) {
      foreach ($language_sorted_headers['translatable'] as $language => $headers_by_language) {
        if (!in_array($required_header, $headers_by_language)) {
          $this->addError('Required header is missing', $required_header . CsvColumn::LANGUAGE_SEPARATOR . $language, 'CSV Headers', 'Required header is missing from CSV.');
        }
      }
    }
  }

  public function validateUniqueCodes(array $records, string $entityType, array $headers, array $userInstitutions): bool {

    $results = [];
    $duplicates = [];
    $column_name = $this->definitions->get($entityType)->codeField;

    foreach ($records as $row_number => $record) {
      $results[$record[$column_name]][] = $row_number + 1;
    }

    foreach ($results as $code => $rows) {
      if (count($rows) > 1) {
        $duplicates[$code] = $rows;
      }
    }

    if (!empty($duplicates)) {
      foreach ($duplicates as $code => $rows) {
        $row_list = implode(', ', $rows);
        $this->addError('Non-unique codes', $column_name, $row_list, 'Values in the ' . $column_name . ' column must be unique.', [$code]);
      }
    }

    return !$duplicates;
  }

  public function validateNonEmpty(array $records, string $entityType, array $headers, ?array $userInstitutions = NULL): void {
    $languages = $this->headerParser->languages($headers);
    $non_translatable = $this->definitions->get($entityType)->requiredSharedColumns;
    $translatable = $this->definitions->get($entityType)->requiredTranslatedColumns;

    foreach ($records as $index => $record) {
      $row_number = $index + 1;

      // Always required non-translatable fields.
      foreach ($non_translatable as $column_name) {
        if ($this->isValueEmpty($record[$column_name])) {
          $this->addError('Required value is missing', $column_name, $row_number);
        }
      }

      // Required translatable fields.
      foreach ($languages as $language) {

        // Values in Default language, are always required.
        $is_default = ($language === CsvHeaderParser::DEFAULT_LANGUAGE);
        // Values in any secondary language are only required if the row has at least one value for it.
        $has_any_data_for_lang = false;

        // Check if a specific language has any content in the row.
        foreach ($translatable as $column_name) {
          $full_column_name = $column_name . CsvColumn::LANGUAGE_SEPARATOR . $language;
          if (!empty($record[$full_column_name])) {
            $has_any_data_for_lang = true;
            break;
          }
        }

        // Validate the translation.
        if ($is_default || $has_any_data_for_lang) {
          foreach ($translatable as $column_name) {
            $full_column_name = $column_name . CsvColumn::LANGUAGE_SEPARATOR . $language;
            if (empty($record[$full_column_name])) {
              $message = $is_default
                ? "Base translation ($language) is required."
                : "Incomplete translation ($language). If you provide one field as translated, you must provide all required translatable fields.";

              $this->addError('Required translation is missing', $full_column_name, $row_number, $message);
            }
          }
        }
      }
    }
  }

  public function validateReferences(array $records, string $entityType, array $headers, array $userInstitutions): void {
    $hei_column = $this->definitions->get($entityType)->institutionColumn;
    $first_record = $records[array_key_first($records)];
    // Only one schac code is accepted per file.
    $schac_in_records = $first_record[$hei_column] ?? NULL;

    $hei = NULL;
    foreach ($userInstitutions as $institution) {
      if ($this->userAccessManager->getSchacCodeFromInstitution($institution) === $schac_in_records) {
        $hei = $institution;
        break;
      }
    }

    // Institution access errors have already been reported; do not query with
    // an unknown institution or expose whether its referenced entities exist.
    if (!$hei) {
      return;
    }

    foreach ($this->definitions->get($entityType)->referenceValidation as $target_field => $definition) {
      // The name of the primary identifier (code) column in the CSV.
      $pk_column = isset($definition['in_file_column_name']) ? $definition['in_file_column_name'] : NULL;

      // The name of the column containing the references in the CSV.
      $ref_column = $target_field;

      // List of all primary keys present in the CSV.
      $codes_defined_in_file = $pk_column ? array_unique(array_column($records, $pk_column)): [];

      // List of all primary keys requested as references in the CSV.
      $all_requested_parents = array_filter(array_unique(array_column($records, $ref_column)));

      // Check which of those requested references exist in the DB.
      $found_in_db = $this->getExistingCodesInDb($definition, $all_requested_parents, $hei);

      foreach ($records as $index => $record) {
        $row_number = $index + 1;
        $parent_code_to_check = $record[$ref_column] ?? '';

        if ($parent_code_to_check === '') continue;

        // Is the referenced code found in the primary key column of the file or in the DB?
        $exists_in_file = in_array($parent_code_to_check, $codes_defined_in_file);
        $exists_in_db = in_array($parent_code_to_check, $found_in_db);

        if (!$exists_in_file && !$exists_in_db) {
          $this->addError(
            'Invalid reference',
            $ref_column,
            $row_number,
            'The referenced ' . $definition['entity_label'] . ' with code ' . $parent_code_to_check . ' does not exist in the file or database.',
            [$parent_code_to_check]
          );
        }
      }
    }
  }

  protected function isValueEmpty(mixed $value): bool {
    if ($value === NULL) {
      return TRUE;
    }
    // Convert to string and trim trailing spaces to handle cells with empty spaces
    $trimmed = trim((string) $value);

    // A value is only empty if it evaluates to an empty string ''
    return $trimmed === '';
  }

  protected function getExistingCodesInDb(array $definition, array $csv_codes, ?Node $institution = NULL): array {
    if (empty($csv_codes)) {
      return [];
    }

    $conditions = [
      [
        'field' => $definition['references'],
        'value' => $csv_codes,
        'operator' => 'IN',
      ],
      [
        'field' => 'type',
        'value' => $definition['target_type'],
        'operator' => NULL,
      ],
    ];

    if (isset($definition['hei_field_name'])) {
      $conditions[] = [
        'field' => $definition['hei_field_name'],
        'value' => $institution->id(),
        'operator' => NULL,
      ];
    }

    $entities = $this->repository->findByConditions($definition['target_entity'], $conditions);

    $found_codes = [];

    foreach ($entities as $entity) {
      /** @var ContentEntityInterface $entity */
      $found_codes[] = $entity->get($definition['references'])->value;
    }

    return $found_codes;
  }

  protected function addError(string $type, string $source, mixed $row = '', ?string $message = '', ?array $values = []): void {
    $language = NULL;
    try {
      $language = $this->headerParser->parse($source)->language;
    }
    catch (\InvalidArgumentException) {
      // Global errors and malformed headers need no inferred language.
    }
    $this->errors[] = new ImportError(
      $type, $message ?: $type . '.', $row, $source, $values ?? [], $language,
    );
  }

}
