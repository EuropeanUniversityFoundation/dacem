<?php

namespace Drupal\dacem_csv_import_export\Definition;

use Drupal\dacem_csv_import_export\Enum\ImportTargetEntityType;

/**
 * Owns the bundle-specific CSV rules used by import and export services.
 */
final class BundleCsvDefinitions {

  /**
   * Returns NULL for bundles without custom CSV rules, allowing export fallback.
   */
  public function find(string $bundle): ?BundleCsvDefinition {
    return match ($bundle) {
      ImportTargetEntityType::HEI->value => new BundleCsvDefinition(
        bundle: $bundle,
        label: 'Institution',
        codeField: 'field_shac_code',
      ),
      ImportTargetEntityType::OUNIT->value => new BundleCsvDefinition(
        bundle: $bundle,
        importable: TRUE,
        label: 'Organizational Unit',
        codeField: 'field_ou_code',
        institutionColumn: 'field_ou_institution',
        requiredSharedColumns: [
          'field_ou_institution',
          'field_ou_code',
        ],
        requiredTranslatedColumns: [
          'field_ou_description',
          'title',
          'field_ou_type',
        ],
        exportFields: [
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
        referenceResolution: [
          [
            'reference_field_name' => 'field_ou_institution',
            'referenced_entity' => 'node',
            'referenced_entity_type' => ImportTargetEntityType::HEI->value,
            'referenced_field_name' => 'field_shac_code',
            'cardinality' => 1,
          ],
          [
            'reference_field_name' => 'field_parent_organizational_unit',
            'referenced_entity' => 'node',
            'referenced_entity_type' => ImportTargetEntityType::OUNIT->value,
            'referenced_field_name' => 'field_ou_code',
            'referenced_hei_field_name' => 'field_ou_institution',
            'cardinality' => 1,
            'runs' => 'on_save',
          ],
        ],
        referenceValidation: [
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
        parentField: 'field_parent_organizational_unit',
        validationSkippedFields: ['field_parent_organizational_unit'],
        coordinates: [
          'target_field_name' => 'field_ou_map',
          'lat_column' => 'field_ou_map___lat',
          'lon_column' => 'field_ou_map___lon',
          'type' => 'POINT',
          'lat_property' => 'lat',
          'lon_property' => 'lon',
        ],
      ),
      ImportTargetEntityType::PROGRAMME->value => new BundleCsvDefinition(
        bundle: $bundle,
        importable: TRUE,
        label: 'Programme',
        codeField: 'field_programme_code',
        institutionColumn: 'field_programme_institution',
        requiredSharedColumns: [
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
        requiredTranslatedColumns: [
          'title',
          'field_programme_description',
          'field_programme_learn_outcomes',
        ],
        exportFields: [
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
        referenceResolution: [
          [
            'reference_field_name' => 'field_programme_institution',
            'referenced_entity' => 'node',
            'referenced_entity_type' => ImportTargetEntityType::HEI->value,
            'referenced_field_name' => 'field_shac_code',
            'cardinality' => 1,
          ],
          [
            'reference_field_name' => 'field_programme_ou',
            'referenced_entity' => 'node',
            'referenced_entity_type' => ImportTargetEntityType::OUNIT->value,
            'referenced_field_name' => 'field_ou_code',
            'referenced_hei_field_name' => 'field_ou_institution',
            'cardinality' => 1,
          ],
          [
            'reference_field_name' => 'field_programme_language_of_inst',
            'referenced_entity' => 'taxonomy_term',
            'referenced_entity_type' => NULL,
            'referenced_field_name' => 'name',
            'cardinality' => -1,
          ],
          [
            'reference_field_name' => 'field_programme_type',
            'referenced_entity' => 'taxonomy_term',
            'referenced_entity_type' => NULL,
            'referenced_field_name' => 'name',
            'cardinality' => 1,
          ],
        ],
        referenceValidation: [
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
            'hei_field_name' => 'field_ou_institution',
          ],
        ],
      ),
      ImportTargetEntityType::COURSE->value => new BundleCsvDefinition(
        bundle: $bundle,
        importable: TRUE,
        label: 'Course',
        codeField: 'field_iec_code',
        institutionColumn: 'field_iec_programme.entity.field_programme_institution',
        requiredSharedColumns: [
          'field_iec_code',
          'field_iec_language_of_instructio____0',
          'field_iec_credits',
          'field_iec_programme',
          'field_iec_type',
          'field_iec_term____0',
          'field_iec_year____0'
        ],
        requiredTranslatedColumns: [
          'title',
          'field_iec_description',
          'field_iec_learning_outcomes',
        ],
        exportFields: [
          
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
        referenceResolution: [
          [
            'reference_field_name' => 'field_iec_programme',
            'referenced_entity' => 'node',
            'referenced_entity_type' => ImportTargetEntityType::PROGRAMME->value,
            'referenced_field_name' => 'field_programme_code',
            'referenced_hei_field_name' => 'field_programme_institution',
            'cardinality' => 1,
          ],
          [
            'reference_field_name' => 'field_iec_language_of_instructio',
            'referenced_entity' => 'taxonomy_term',
            'referenced_entity_type' => NULL,
            'referenced_field_name' => 'name',
            'cardinality' => -1,
          ],
        ],
        referenceValidation: [
          'field_iec_programme' => [
            'entity_label' => 'Programme',
            'target_entity' => 'node',
            'target_type' => 'programme',
            'references_label' => 'Programme code',
            'references' => 'field_programme_code',
            'hei_field_name' => 'field_programme_institution',
          ],
        ],
      ),
      ImportTargetEntityType::COURSE_INSTANCE->value => new BundleCsvDefinition(
        bundle: $bundle,
        label: 'Course instance',
        referenceValidation: [
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
      ),
      default => NULL,
    };
  }

  /**
   * Requires a known definition for operations that need bundle-specific rules.
   */
  public function get(string $bundle): BundleCsvDefinition {
    return $this->find($bundle) ?? throw new \InvalidArgumentException('No CSV definition for bundle: ' . $bundle);
  }

}
