<?php

namespace Drupal\dacem_csv_import_export\CsvConverter;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;
use Drupal\node\Entity\Node;

class FieldMappingService {

  public const LANGUAGE_SEPARATOR = '|';
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
      'hei_column_name' => 'field_iec_programme.entity.field_programme_institution',
    ],
    // ImportTargetEntityType::COURSE_INSTANCE->value => [
    //   'hei_column_name' => 'hei',
    // ]
  ];

  public const CODE_COLUMNS = [
    ImportTargetEntityType::HEI->value => [
      'code_column_name' => 'field_shac_code',
    ],
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
    ImportTargetEntityType::PROGRAMME->value => [
      'non-translatable' => [
        'field_programme_institution',
        'field_programme_code',
        'field_isced_f____0',
        'field_length_of_programme',
        'field_eqf_level',
        'field_credits',
        'field_number_of_terms',
        'field_professional_practices',
        'field_programme_language_of_inst____0'
      ],
      'translatable' => [
        'title',
        'field_programme_description',
        'field_programme_learn_outcomes',
      ],
    ],
    ImportTargetEntityType::COURSE->value => [
      'non-translatable' => [
        'field_iec_code',
        'field_iec_language_of_instructio____0',
        'field_iec_credits',
        'field_iec_programme',
        'field_iec_type',
        'field_iec_term____0',
        'field_iec_year____0'
      ],
      'translatable' => [
        'title',
        'field_iec_description',
        'field_iec_learning_outcomes',
      ],
    ],
    // Out of scope for now.
    // ImportTargetEntityType::COURSE_INSTANCE->value => [
    //   'hei',
    //   'start_date',
    //   'end_date',
    //   'academic_term_id',
    //   'course',
    // ],
  ];

  public const EXPORT_SKIPPED_FIELDS = [
    'nid', 'vid', 'type', 'uuid', 'revision_uid', 'revision_timestamp',
    'revision_log', 'uid', 'status', 'created', 'changed', 'promote',
    'sticky', 'default_langcode', 'revision_translation_affected',
    'content_translation_source', 'content_translation_outdated',
    'langcode', 'path', 'revision_default', 'field_ou_picture',
  ];

  public const EXPORT_SKIPPED_PROPERTIES = [
    'value', 'format', 'processed', 'entity', 'detailed', 'narrow', 'broad', 'options', 'entity',
    'left', 'top', 'right', 'bottom', 'geo_type', 'geohash', 'latlon'
  ];

  public const EXPORT_FIELD_LAYOUT = [
    'field_programme_institution',
    'field_programme_ou',
    'title',
    'field_programme_code',
    'field_programme_abbreviation',
    'field_isced_f',
    'field_programme_type',
    'field_learning_opportunity_type',
    'field_eqf_level',
    'field_programme_mode_of_learning',
    'field_length_of_programme',
    'field_number_of_terms',
    'field_programme_type_of_credits',
    'field_credits',
    'field_programme_mode_of_study',
    'field_programme_language_of_inst',
    'field_programme_start_date',
    'field_programme_end_date',
    'field_avaliable_for_mobility',
    'field_programme_restricted_allia',
    'field_coordinator',
    'field_email',
    'field_programme_phone',
    'field_programme_web',
    'field_programme_description',
    'field_main_objective',
    'field_competencies',
    'field_programme_learn_outcomes',
    'field_qualification_awarded',
    'field_distribution_of_credits',
    'field_programme_requirements',
    'field_arrangments_recognition',
    'field_qualification_requirements',
    'field_programme_grading_scheme',
    'field_programme_grading_table',
    'field_programme_mobility',
    'field_programme_mobility_windows',
    'field_professional_practices',
    'field_programme_work_placements',
    'field_work_based_learning',
    'field_occupational_profiles',
    'field_access_to_further_studies',
    'field_programme_qualification'
  ];

  protected EntityFieldManager $entityFieldManager;

  public function __construct(
    EntityFieldManager $entity_field_manager,
  ) {
    $this->entityFieldManager = $entity_field_manager;
  }

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

  public function getFormattedFieldList(string $entity_bundle) {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $entity_bundle);
    $formatted_types = ['text', 'text_long', 'text_with_summary'];
    $formatted_fields = [];

    foreach ($fields as $field_name => $field) {
      if (in_array($field->getType(), $formatted_types)) {
        $formatted_fields[] = $field_name;
      }
    }

    return $formatted_fields;
  }

  public function resolveLanguageFromColumn(string $column_name) {
    if (str_contains($column_name, self::LANGUAGE_SEPARATOR)) {
      $exploded = explode(self::LANGUAGE_SEPARATOR, $column_name);
      return [end($exploded), reset($exploded)];
    }

    return [self::DEFAULT_LANGUAGE, $column_name];
  }

  public function generateFieldMetadata(string $entity_type_id, string $entity_bundle, array $entities) {
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $entity_bundle);

    $metadata = [];
    $metadata['languages'] = [
      FieldMappingService::DEFAULT_LANGUAGE => TRUE
    ];

    foreach ($entities as $id => $entity) {
      /** @var \Drupal\node\NodeInterface $entity */
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $metadata['languages'][$langcode] = TRUE;
      }
    }

    foreach ($field_definitions as $field_name => $definition) {
      if (in_array($field_name, self::EXPORT_SKIPPED_FIELDS)) {
        continue;
      }

      // 1. Get the internal sub-properties of the field (e.g., ['uri', 'title', 'options'])
      $storage_definition = $definition->getFieldStorageDefinition();
      $properties = $storage_definition->getPropertyNames();

      // Filter out internal/computed properties you do not want in your CSV
      $properties = array_diff($properties, self::EXPORT_SKIPPED_PROPERTIES);

      $metadata['fields'][$field_name] = [
        'translatable' => FALSE,
        'multi-cardinality' => FALSE,
        'max_delta' => 0,
        'properties' => array_values($properties),
        'has_multiple_properties' => (count($properties) > 1),
        'is_reference' => ($definition->getType() === 'entity_reference'),
        'target_entity_type' => $definition->getSetting('target_type'),
        'target_bundle' => $definition->getSetting('handler_settings')['target_bundles'] ?? NULL,
      ];

      if ($definition->isTranslatable()) {
        $metadata['fields'][$field_name]['translatable'] = TRUE;
      }

      if ($storage_definition->isMultiple()) {
        $metadata['fields'][$field_name]['multi-cardinality'] = TRUE;
      }
    }

    foreach ($metadata['fields'] as $field_name => &$field_meta_data) {
      if ($field_meta_data['multi-cardinality'] === TRUE) {
        foreach ($entities as $entity) {
          /** @var \Drupal\node\NodeInterface $entity */
          foreach ($entity->getTranslationLanguages() as $langcode => $language) {
            $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;

            /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
            $field_items = $translation->get($field_name);
            $count = $field_items->count();

            if ($count > $field_meta_data['max_delta']) {
              $field_meta_data['max_delta'] = $count;
            }
          }
        }
      }
    }

    return $metadata;
  }

  /**
   * Compiles the flat CSV headers and a matching high-performance mapping blueprint.
   */
  public function generateHeadersAndBlueprint(array $metadata): array {
    $headers = [];
    $blueprint = [];

    $languages = array_keys($metadata['languages'] ?? [FieldMappingService::DEFAULT_LANGUAGE => TRUE]);
    $fields = $metadata['fields'] ?? [];

    // Separate fields into Non-Translatable and Translatable groups
    $base_fields = [];
    $translatable_fields = [];

    foreach ($fields as $field_name => $field_meta) {
      if (($field_meta['translatable'] ?? FALSE) === TRUE) {
        $translatable_fields[] = $field_name;
      } else {
        $base_fields[] = $field_name;
      }
    }

    // TRACK 1: Compile Non-Translatable Base Fields
    foreach ($base_fields as $field_name) {
      $field_meta = $fields[$field_name];
      $this->compileColumnSchema(
        $field_name,
        $field_meta,
        NULL, // No language for base fields
        $headers,
        $blueprint
      );
    }

    // TRACK 2: Compile Translatable Fields per Language Block
    foreach ($languages as $langcode) {
      foreach ($translatable_fields as $field_name) {
        $field_meta = $fields[$field_name];
        $this->compileColumnSchema(
          $field_name,
          $field_meta,
          $langcode,
          $headers,
          $blueprint
        );
      }
    }

    return [
      'headers' => $headers,
      'blueprint' => $blueprint,
    ];
  }

  /**
   * Internal helper to build header strings and blueprint tokens symmetrically.
   */
  protected function compileColumnSchema(string $field_name, array $field_meta, ?string $langcode, array &$headers, array &$blueprint): void {
    // Determine loop limits for multi-cardinality items (single items loop exactly once)
    $max_delta = (($field_meta['multi-cardinality'] ?? FALSE) === TRUE) ? ($field_meta['max_delta'] ?? 1) : 1;
    // Always ensure at least 1 property item is fetched even if the list is empty
    $properties = !empty($field_meta['properties']) ? $field_meta['properties'] : ['value'];
    $has_multiple_properties = $field_meta['has_multiple_properties'] ?? FALSE;

    for ($delta = 0; $delta < $max_delta; $delta++) {
      foreach ($properties as $property) {

        // 1. Build the Header String Layout
        $header_string = $field_name;
        if (($field_meta['multi-cardinality'] ?? FALSE) === TRUE) {
          $header_string .= str_repeat(self::HEADER_SEPARATOR, 4) . $delta;
        }
        if ($has_multiple_properties) {
          $header_string .= str_repeat(self::HEADER_SEPARATOR, 3) . $property;
        }
        if ($langcode) {
          $header_string .= self::LANGUAGE_SEPARATOR . $langcode;
        }

        $headers[] = $header_string;

        // 2. Build the exact fetch blueprint token instructions for this column slot
        $blueprint[] = [
          'field_name' => $field_name,
          'is_translatable' => !empty($langcode),
          'langcode' => $langcode,
          'is_multiple' => ($field_meta['multi-cardinality'] ?? FALSE),
          'delta' => $delta,
          'property' => $property,
          'is_reference' => $field_meta['is_reference'],
          'target_entity_type' => $field_meta['target_entity_type'],
          'target_bundle' => $field_meta['target_bundle'] ? reset($field_meta['target_bundle']) : NULL,
        ];
      }
    }
  }

  public function exportEntityRow(Node $entity, array $blueprint): array {
    /** @var \Drupal\node\NodeInterface $entity */
    $row = [];

    foreach ($blueprint as $column_meta) {
      $langcode = $column_meta['langcode'];
      $field_name = $column_meta['field_name'];
      $delta = $column_meta['delta'];
      $property = $column_meta['property'];

      if ($column_meta['is_translatable'] && $langcode) {
        $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
      } else {
        $translation = $entity;
      }

      if (!$translation) {
        $row[] = '';
        continue;
      }

      $field_items = $translation->get($field_name);
      $item = $field_items->get($delta);

      if (!$item) {
        $row[] = '';
        continue;
      }

      // RESOLUTION LAYER FOR ENTITY REFERENCES
      if ($column_meta['is_reference'] && $property === 'target_id') {
        // Load the referenced target entity object model natively
        $referenced_entity = $item->entity;

        if ($referenced_entity instanceof \Drupal\Core\Entity\ContentEntityInterface) {
          $target_bundle = $referenced_entity->bundle();

          // Look up which custom code field this bundle uses (e.g. 'field_ou_code')
          $code_field_name = self::CODE_COLUMNS[$target_bundle]['code_column_name'] ?? NULL;

          if ($code_field_name && $referenced_entity->hasField($code_field_name)) {
            // Overwrite value with the human-readable unique code string!
            $value = $referenced_entity->get($code_field_name)->value ?? '';
          } else {
            // Fall back to entity label or raw target ID if no custom code map is found
            $value = $referenced_entity->label() ?: $referenced_entity->id();
          }
        } else {
          $value = '';
        }
      } else {
        // Standard non-reference property value fetching
        $value = $item->{$property} ?? '';
      }

      $row[] = is_scalar($value) ? (string) $value : '';
    }

    return $row;
  }

}
