<?php

namespace Drupal\euf_csv_import_export\FileValidator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\euf_csv_import_export\AccessManager\UserAccessManager;
use Drupal\euf_csv_import_export\CsvConverter\FieldMappingService;
use Drupal\euf_csv_import_export\Dataloader\Dataloader;
use Drupal\euf_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FileValidator {

  public const VALIDATION_BY_TYPE = [
    ImportTargetEntityType::OUNIT->value => [
      [FileValidator::class, 'validateFileHeiUserMatch'],
      [FileValidator::class, 'validateUniqueCodes'],
      [FileValidator::class, 'validateNonEmpty'],
      [FileValidator::class, 'validateReferences'],
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      [FileValidator::class, 'validateFileHeiUserMatch'],
      [FileValidator::class, 'validateUniqueCodes'],
      [FileValidator::class, 'validateNonEmpty'],
      [FileValidator::class, 'validateReferences'],
    ],
    // ImportTargetEntityType::COURSE->value => [
    //   [FileValidator::class, 'validateFileHeiUserMatch'],
    //   [FileValidator::class, 'validateUniqueCodes'],
    //   [FileValidator::class, 'validateNonEmpty'],
    //   [FileValidator::class, 'validateReferences'],
    // ],
    // ImportTargetEntityType::COURSE_INSTANCE->value => [
    //   [FileValidator::class, 'validateFileHeiUserMatch'],
    //   [FileValidator::class, 'validateReferences'],
    // ]
  ];

  public const UNIQUE_CODE_COLUMNS = [
    ImportTargetEntityType::OUNIT->value => [
      'column_name' => 'field_ou_code',
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      'column_name' => 'field_programme_code',
    ],
    // ImportTargetEntityType::COURSE->value => [
    //   'column_name' => 'code',
    // ]
  ];

  public const REFERENCES_VALIDATION = [
    ImportTargetEntityType::OUNIT->value => [
      'field_ou_institution' => [
        'entity_label' => 'Institution',
        'target_entity' => 'node',
        'target_type' => 'institution',
        'references_label' => 'SCHAC code',
        'references' => 'field_shac_code',
      ],
      'field_parent_organizational_unit' => [
        'entity_label' => 'Organisational unit',
        'target_entity' => 'node',
        'target_type' => 'organizational_unit',
        'references_label' => 'Organizational unit code',
        'references' => 'field_ou_code',
        'in_file_column_name' => 'field_ou_code',
        'hei_field_name' => 'field_ou_institution',
      ],
    ],
    ImportTargetEntityType::PROGRAMME->value => [
      'field_programme_institution' => [
        'entity_label' => 'Institution',
        'target_entity' => 'node',
        'target_type' => 'institution',
        'references_label' => 'SCHAC code',
        'references' => 'field_shac_code',
      ],
      'field_programme_ou' => [
        'entity_label' => 'Organisational unit',
        'target_entity' => 'node',
        'target_type' => 'organizational_unit',
        'references_label' => 'Organizational unit code',
        'references' => 'field_ou_code',
        'hei_field_name' => 'field_ou_code',
      ],
    ],
    ImportTargetEntityType::COURSE->value => [
      'hei' => [
        'entity_label' => 'Institution',
        'target_entity' => 'hei',
        'references_label' => 'SCHAC code',
        'references' => 'hei_id',
      ],
      'ounit' => [
        'entity_label' => 'Organisational unit',
        'target_entity' => 'ounit',
        'references_label' => 'Organizational unit code',
        'references' => 'field_ou_code',
        'hei_field_name' => 'parent_hei',
      ],
      'course__related_programme' => [
        'property_name' => 'code',
        'entity_label' => 'Programme',
        'target_entity' => 'occ_los',
        'target_bundle' => 'programme',
        'references_label' => 'Programme code',
        'references' => 'code',
        'hei_field_name' => 'hei',
      ],
      'course__prerequisite_course' => [
        'entity_label' => 'Course',
        'target_entity' => 'occ_los',
        'target_bundle' => 'course',
        'references_label' => 'Course code',
        'references' => 'code',
        'in_file_column_name' => 'code',
        'hei_field_name' => 'hei',
      ],
    ],
    ImportTargetEntityType::COURSE_INSTANCE->value => [
      'course' => [
        'entity_label' => 'Course',
        'target_entity' => 'occ_los',
        'target_bundle' => 'course',
        'references_label' => 'Course code',
        'references' => 'code',
        'in_file_column_name' => 'code',
        'hei_field_name' => 'hei',
      ]
    ],
  ];

  protected array $errors = [];

  protected UserAccessManager $userAccessManager;
  protected FieldMappingService $fieldMappingService;
  protected EntityTypeManager $entityTypeManager;
  protected Dataloader $dataLoader;


  public function __construct(
    UserAccessManager $user_access_manager,
    FieldMappingService $field_mapping_service,
    EntityTypeManager $entity_type_manager,
    Dataloader $data_loader,
  ) {
    $this->userAccessManager = $user_access_manager;
    $this->fieldMappingService = $field_mapping_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('euf_csv_import_export.user_access_manager'),
      $container->get('euf_csv_import_export.field_mapping_service'),
      $container->get('entity_type.manager'),
      $container->get('euf_csv_import_export.data_loader'),
    );
  }

  public function validateFileData(array $records, array $headers, string $entityType, array $userInstitutions) {
    $this->validateSingleHeiInFile($records, $entityType);
    $this->validateHeaders($headers, $entityType);

    foreach (self::VALIDATION_BY_TYPE[$entityType] as $method) {
      call_user_func($method, $records, $entityType, $headers, $userInstitutions);
    }

    return $this->errors;
  }

  protected function validateSingleHeiInFile(array $records, string $entityType) {
    $heis_in_file = [];
    $hei_column_name = FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name'];

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
    $hei_column_name = FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name'];

    foreach ($records as $row_number => $record) {
      if (!in_array($record[$hei_column_name], $userInstitutionCodes)) {
        $this->addError('User Institution mismatch', $hei_column_name, $row_number + 1, 'The logged in user is not part of this Institution: ' . $record[$hei_column_name] . '.');
      }
    }
  }

  protected function validateHeaders(array $headers, string $entityType): void {
    $language_sorted_headers = $this->fieldMappingService->sortHeadersByLanguage($headers);

    foreach (FieldMappingService::REQUIRED_DATA[$entityType]['non-translatable'] as $required_header) {
      if (!in_array($required_header, $language_sorted_headers['non-translatable'])) {
        $this->addError('Required header is missing', $required_header, 'CSV Headers', 'Required header is missing from CSV.');
      }
    }

    foreach (FieldMappingService::REQUIRED_DATA[$entityType]['translatable'] as $required_header) {
      foreach ($language_sorted_headers['translatable'] as $language => $headers_by_language) {
        if (!in_array($required_header, $headers_by_language)) {
          $this->addError('Required header is missing', $required_header . FieldMappingService::LANGUAGE_SEPARATOR . $language, 'CSV Headers', 'Required header is missing from CSV.');
        }
      }
    }
  }

  public function validateUniqueCodes(array $records, string $entityType, array $headers, array $userInstitutions): bool {

    $results = [];
    $duplicates = [];
    $column_name = self::UNIQUE_CODE_COLUMNS[$entityType]['column_name'];

    foreach ($records as $row_number => $record) {
      $results[$record[$column_name]][] = $row_number;
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

    return empty($this->errors['csv_records']['non_unique_codes']);
  }

  public function validateNonEmpty(array $records, string $entityType, array $headers, ?array $userInstitutions = NULL): void {
    $languages = $this->fieldMappingService->getTranslationLanguagesFromHeaders($headers);
    $non_translatable = FieldMappingService::REQUIRED_DATA[$entityType]['non-translatable'];
    $translatable = FieldMappingService::REQUIRED_DATA[$entityType]['translatable'];

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
        $is_default = ($language === FieldMappingService::DEFAULT_LANGUAGE);
        // Values in any secondary language are only required if the row has at least one value for it.
        $has_any_data_for_lang = false;

        // Check if a specific language has any content in the row.
        foreach ($translatable as $column_name) {
          $full_column_name = $column_name . FieldMappingService::LANGUAGE_SEPARATOR . $language;
          if (!empty($record[$full_column_name])) {
            $has_any_data_for_lang = true;
            break;
          }
        }

        // Validate the translation.
        if ($is_default || $has_any_data_for_lang) {
          foreach ($translatable as $column_name) {
            $full_column_name = $column_name . FieldMappingService::LANGUAGE_SEPARATOR . $language;
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
    $hei_column = FieldMappingService::HEI_COLUMNS[$entityType]['hei_column_name'];
    $first_record = $records[array_key_first($records)];
    // Only one schac code is accepted per file.
    $schac_in_records = $first_record[$hei_column] ?? NULL;

    foreach ($userInstitutions as $institution) {
      if ($this->userAccessManager->getSchacCodeFromInstitution($institution) === $schac_in_records) {
        $hei = $institution;
        break;
      }
    }

    foreach (self::REFERENCES_VALIDATION[$entityType] as $target_field => $definition) {
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

  protected function getExistingCodesInDb(array $definition, array $csv_codes, ?Node $hei = NULL): array {
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
    $entities = $this->dataLoader->loadEntitiesWithConditions($definition['target_entity'], $conditions);

    $found_codes = [];

    foreach ($entities as $entity) {
      /** @var ContentEntityInterface $entity */
      $found_codes[] = $entity->get($definition['references'])->value;
    }

    return $found_codes;
  }

  protected function addError(string $type, string $source, mixed $row = '', ?string $message = '', ?array $values = []): void {
    $this->errors[$type][] = [
      'message' => $message ?: "No error message was provided",
      'source' => $source,
      'values' => $values,
      'row_number' => $row,
    ];
  }

}
