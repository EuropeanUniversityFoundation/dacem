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

  /**
   * Desired export column order per bundle.
   *
   * Each entry is a base field name (no delta/property/language suffixes -
   * those are expanded dynamically per the field's storage/translatability
   * config). An entry containing a "." is a virtual, computed column: a
   * dot-path resolved through one or more entity reference hops (see
   * resolveVirtualReferenceColumn()), mirroring the HEI_COLUMNS convention.
   */
  public const EXPORT_FIELD_LAYOUT = [
    ImportTargetEntityType::PROGRAMME->value => [
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
      'field_programme_qualification',
    ],
    ImportTargetEntityType::OUNIT->value => [
      'field_ou_institution',
      'field_parent_organizational_unit',
      'title',
      'field_ou_abbreviation',
      'field_ou_code',
      'field_ou_description',
      'field_ou_type',
      'field_ou_knowledge_area',
      'field_ou_address',
      'field_ou_web',
      'field_ou_email',
      'field_ou_phone',
      'field_ou_map',
    ],
    ImportTargetEntityType::COURSE->value => [
      // Virtual column: course -> field_iec_programme -> field_programme_institution.
      'field_iec_programme.entity.field_programme_institution',
      'field_iec_programme',
      'title',
      'field_iec_code',
      'field_fields_of_study',
      'field_subject_area',
      'field_iec_type',
      'field_iec_elm_type',
      'field_iec_year',
      'field_iec_term',
      'field_iec_credits',
      'field_iec_engagement_hours',
      'field_iec_modality',
      'field_iec_language_of_instructio',
      'field_iec_avaliable_for_mobility',
      'field_iec_restricted_alliance',
      'field_iec_coordinator',
      'field_iec_email',
      'field_iec_phone',
      'field_iec_web',
      'field_iec_description',
      'field_iec_learning_outcomes',
      'field_iec_requirements',
      'field_iec_instructors',
      'field_iec_contents',
      'field_iec_recommendations',
      'field_iec_activity_types',
      'field_iec_planned_activities',
      'field_iec_evaluation',
      'field_assessment_method_types',
      'field_iec_type_of_credits',
    ],
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
   *
   * Column order follows EXPORT_FIELD_LAYOUT for the given bundle. For each
   * field in that layout, columns are expanded delta -> language -> property
   * (in that nesting order), so all of a field's columns sit together in the
   * position the layout assigns it, rather than being grouped separately by
   * translatability.
   */
  public function generateHeadersAndBlueprint(string $entity_bundle, array $metadata): array {
    $headers = [];
    $blueprint = [];

    $languages = array_keys($metadata['languages'] ?? [FieldMappingService::DEFAULT_LANGUAGE => TRUE]);
    $fields = $metadata['fields'] ?? [];
    // Fall back to field-definition order if no explicit layout exists for
    // this bundle yet, so newly added bundles degrade gracefully.
    $layout = self::EXPORT_FIELD_LAYOUT[$entity_bundle] ?? array_keys($fields);

    foreach ($layout as $column) {
      if ($this->isVirtualReferenceColumn($column)) {
        $headers[] = $column;
        $blueprint[] = [
          'type' => 'virtual_reference',
          'path' => $this->parseVirtualReferencePath($column),
        ];
        continue;
      }

      if (!isset($fields[$column])) {
        // Field doesn't exist on this bundle's definitions (e.g. layout
        // drifted from the content type) - skip rather than fail.
        continue;
      }

      $this->compileColumnSchema($column, $fields[$column], $languages, $headers, $blueprint);
    }

    return [
      'headers' => $headers,
      'blueprint' => $blueprint,
    ];
  }

  /**
   * Whether a layout entry is a virtual, dot-path computed column.
   */
  protected function isVirtualReferenceColumn(string $column): bool {
    return str_contains($column, '.');
  }

  /**
   * Turns a dot-path layout entry into an ordered list of real field names.
   *
   * The literal "entity" segment is just a documentation marker for "follow
   * the reference"; it carries no extra field to fetch, so it's dropped.
   */
  protected function parseVirtualReferencePath(string $column): array {
    return array_values(array_filter(
      explode('.', $column),
      fn (string $segment) => $segment !== 'entity'
    ));
  }

  /**
   * Internal helper to build header strings and blueprint tokens symmetrically.
   *
   * Suffix presence is driven by field config, not by the data being
   * exported:
   * - Delta suffixes (____N) are added whenever the field storage allows
   *   more than one value, even if only one value is actually populated
   *   (so at minimum a multi-value field always gets a "____0" column).
   *   The number of delta columns is still data-driven (the highest value
   *   count observed across the exported entities, floor of 1), since
   *   unlimited cardinality has no fixed max to read from config.
   * - Language suffixes (|lang) are added whenever the field is
   *   translatable, even if only the default language has content. Which
   *   languages get columns is data-driven (found among the exported
   *   entities).
   * - Property suffixes (___property) are added whenever the field has
   *   more than one property, purely from config.
   */
  protected function compileColumnSchema(string $field_name, array $field_meta, array $languages, array &$headers, array &$blueprint): void {
    $is_multiple = ($field_meta['multi-cardinality'] ?? FALSE) === TRUE;
    $is_translatable = ($field_meta['translatable'] ?? FALSE) === TRUE;
    // Always ensure at least 1 property item is fetched even if the list is empty.
    $properties = !empty($field_meta['properties']) ? $field_meta['properties'] : ['value'];
    $has_multiple_properties = $field_meta['has_multiple_properties'] ?? FALSE;

    // At least one delta column (0), more only if the data actually needs it.
    $delta_count = $is_multiple ? max((int) ($field_meta['max_delta'] ?? 1), 1) : 1;
    // NULL means "no language suffix" for non-translatable fields.
    $target_languages = $is_translatable ? $languages : [NULL];

    for ($delta = 0; $delta < $delta_count; $delta++) {
      foreach ($target_languages as $langcode) {
        foreach ($properties as $property) {

          // 1. Build the Header String Layout (delta -> property -> language)
          $header_string = $field_name;
          if ($is_multiple) {
            $header_string .= str_repeat(self::HEADER_SEPARATOR, 4) . $delta;
          }
          if ($has_multiple_properties) {
            $header_string .= str_repeat(self::HEADER_SEPARATOR, 3) . $property;
          }
          if ($is_translatable && $langcode) {
            $header_string .= self::LANGUAGE_SEPARATOR . $langcode;
          }

          $headers[] = $header_string;

          // 2. Build the exact fetch blueprint token instructions for this column slot
          $blueprint[] = [
            'type' => 'field',
            'field_name' => $field_name,
            'is_translatable' => $is_translatable,
            'langcode' => $langcode,
            'is_multiple' => $is_multiple,
            'delta' => $delta,
            'property' => $property,
            'is_reference' => $field_meta['is_reference'],
            'target_entity_type' => $field_meta['target_entity_type'],
            'target_bundle' => $field_meta['target_bundle'] ? reset($field_meta['target_bundle']) : NULL,
          ];
        }
      }
    }
  }

  public function exportEntityRow(Node $entity, array $blueprint): array {
    /** @var \Drupal\node\NodeInterface $entity */
    $row = [];

    foreach ($blueprint as $column_meta) {
      if (($column_meta['type'] ?? 'field') === 'virtual_reference') {
        $row[] = $this->resolveVirtualReferenceColumn($entity, $column_meta['path']);
        continue;
      }

      $row[] = $this->resolveFieldColumn($entity, $column_meta);
    }

    return $row;
  }

  /**
   * Resolves a single "real field" column value for one exported row.
   */
  protected function resolveFieldColumn(Node $entity, array $column_meta): string {
    $langcode = $column_meta['langcode'];
    $field_name = $column_meta['field_name'];
    $delta = $column_meta['delta'];
    $property = $column_meta['property'];

    if ($column_meta['is_translatable'] && $langcode) {
      $translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
    } else {
      $translation = $entity;
    }

    if (!$translation || !$translation->hasField($field_name)) {
      return '';
    }

    $field_items = $translation->get($field_name);
    $item = $field_items->get($delta);

    if (!$item) {
      return '';
    }

    // RESOLUTION LAYER FOR ENTITY REFERENCES
    if ($column_meta['is_reference'] && $property === 'target_id') {
      $referenced_entity = $item->entity;

      return $referenced_entity instanceof \Drupal\Core\Entity\ContentEntityInterface
        ? $this->resolveReferenceCode($referenced_entity)
        : '';
    }

    // Standard non-reference property value fetching
    $value = $item->{$property} ?? '';

    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Resolves a virtual, dot-path column by walking a chain of references.
   *
   * E.g. for path ['field_iec_programme', 'field_programme_institution'],
   * this follows the course's programme reference, then that programme's
   * institution reference, and returns the institution's code.
   */
  protected function resolveVirtualReferenceColumn(Node $entity, array $path): string {
    $current = $entity;
    $last_index = array_key_last($path);

    foreach ($path as $index => $field_name) {
      if (!$current instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$current->hasField($field_name)) {
        return '';
      }

      $referenced_entity = $current->get($field_name)->entity ?? NULL;

      if ($index === $last_index) {
        return $referenced_entity instanceof \Drupal\Core\Entity\ContentEntityInterface
          ? $this->resolveReferenceCode($referenced_entity)
          : '';
      }

      if (!$referenced_entity) {
        return '';
      }

      $current = $referenced_entity;
    }

    return '';
  }

  /**
   * Resolves the display value for a referenced entity: its configured code
   * field (e.g. field_ou_code) if its bundle has one, otherwise its label
   * or raw ID as a fallback.
   */
  protected function resolveReferenceCode(\Drupal\Core\Entity\ContentEntityInterface $referenced_entity): string {
    $target_bundle = $referenced_entity->bundle();
    $code_field_name = self::CODE_COLUMNS[$target_bundle]['code_column_name'] ?? NULL;

    if ($code_field_name && $referenced_entity->hasField($code_field_name)) {
      $value = $referenced_entity->get($code_field_name)->value ?? '';
      return is_scalar($value) ? (string) $value : '';
    }

    return (string) ($referenced_entity->label() ?: $referenced_entity->id());
  }

}
