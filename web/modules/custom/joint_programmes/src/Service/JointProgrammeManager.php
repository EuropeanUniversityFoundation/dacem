<?php

namespace Drupal\joint_programmes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Isced\IscedFieldsOfStudy;

final class JointProgrammeManager {

  /**
   * Cache resolved joint programme URLs keyed by programme id.
   *
   * @var array<int, string>
   */
  private array $jointProgrammeUrlCache = [];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly CountryRepositoryInterface $countryRepository,
  ) {}

  /*
   * Joint programme domain logic.
   */

  public function buildJointProgrammeInformationUrl(?NodeInterface $programme, string $langcode): string {
    if (!$programme instanceof NodeInterface) {
      return '';
    }

    $programme_id = (int) $programme->id();
    if (array_key_exists($programme_id, $this->jointProgrammeUrlCache)) {
      return $this->jointProgrammeUrlCache[$programme_id];
    }

    $joint_programme_ids = $this->getNodeStorage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'joint_programme')
      ->condition('field_programme', $programme_id)
      ->sort('nid', 'ASC')
      ->range(0, 1)
      ->execute();

    if (empty($joint_programme_ids)) {
      $this->jointProgrammeUrlCache[$programme_id] = '';
      return '';
    }

    $joint_programme = $this->getNodeStorage()->load((int) reset($joint_programme_ids));
    if (!$joint_programme instanceof NodeInterface) {
      $this->jointProgrammeUrlCache[$programme_id] = '';
      return '';
    }

    $this->jointProgrammeUrlCache[$programme_id] = $this->buildJointProgrammeInformationUrlFromEntity($joint_programme, $langcode);
    return $this->jointProgrammeUrlCache[$programme_id];
  }

  public function getProgrammeOtherInstitutions(?NodeInterface $programme, string $langcode): array {
    if (
      !$programme instanceof NodeInterface ||
      !$programme->hasField('field_programme_institution') ||
      $programme->get('field_programme_institution')->isEmpty()
    ) {
      return [];
    }

    $owner_institution = $programme->get('field_programme_institution')->entity;
    $owner_institution_id = $owner_institution instanceof NodeInterface ? (int) $owner_institution->id() : 0;

    $joint_programme_ids = $this->getNodeStorage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'joint_programme')
      ->condition('field_programme', $programme->id())
      ->execute();

    if (empty($joint_programme_ids)) {
      return [];
    }

    $member_ids = $this->getNodeStorage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'member')
      ->condition('field_member_joint_programme', array_values($joint_programme_ids), 'IN')
      ->execute();

    if (empty($member_ids)) {
      return [];
    }

    $joint_programmes = $this->getNodeStorage()->loadMultiple($joint_programme_ids);
    $members = $this->getNodeStorage()->loadMultiple($member_ids);
    $joint_programme_map = [];

    foreach ($members as $member) {
      if (
        !$member instanceof NodeInterface ||
        !$member->hasField('field_member_institution') ||
        $member->get('field_member_institution')->isEmpty()
      ) {
        continue;
      }

      if (
        !$member->hasField('field_member_joint_programme') ||
        $member->get('field_member_joint_programme')->isEmpty()
      ) {
        continue;
      }

      $joint_programme = $member->get('field_member_joint_programme')->entity;
      if (!$joint_programme instanceof NodeInterface) {
        continue;
      }

      $joint_programme_id = (int) $joint_programme->id();
      $joint_programme_for_display = $this->entityForLanguageWithFallback(
        $joint_programmes[$joint_programme_id] ?? $joint_programme,
        $langcode
      );
      if (!$joint_programme_for_display instanceof NodeInterface) {
        continue;
      }

      $institution = $member->get('field_member_institution')->entity;
      if (!$institution instanceof NodeInterface) {
        continue;
      }

      $institution_id = (int) $institution->id();
      if ($institution_id === $owner_institution_id) {
        continue;
      }

      $institution_for_display = $this->entityForLanguageWithFallback($institution, $langcode);
      if (!$institution_for_display instanceof NodeInterface) {
        continue;
      }

      $ou_label = '';
      $ou_url = '';
      if (
        $member->hasField('field_member_organizational_unit') &&
        !$member->get('field_member_organizational_unit')->isEmpty()
      ) {
        $ou = $member->get('field_member_organizational_unit')->entity;
        $ou_for_display = $this->entityForLanguageWithFallback($ou, $langcode);
        if ($ou_for_display instanceof NodeInterface) {
          $ou_label = $ou_for_display->label();
          $ou_url = $this->buildOrganizationalUnitInformationUrl($ou, $langcode);
        }
      }

      $member_for_display = $this->entityForLanguageWithFallback($member, $langcode);
      $membership_label = $member_for_display instanceof NodeInterface ? trim($member_for_display->label()) : trim($member->label());

      if (!isset($joint_programme_map[$joint_programme_id])) {
        $joint_programme_map[$joint_programme_id] = [
          'id' => $joint_programme_id,
          'title' => $joint_programme_for_display->label(),
          'joint_programme_url' => $this->buildJointProgrammeInformationUrlFromEntity($joint_programme, $langcode),
          'availabilities' => [],
        ];
      }

      $availability_key = implode(':', [
        $membership_label,
        $ou_label,
        (string) $institution_id,
      ]);

      $joint_programme_map[$joint_programme_id]['availabilities'][$availability_key] = [
        'membership_label' => $membership_label,
        'organizational_unit_label' => $ou_label,
        'organizational_unit_url' => $ou_url,
        'institution_label' => $institution_for_display->label(),
        'institution_url' => $this->buildGeneralInformationUrl($institution, $langcode),
      ];
    }

    foreach ($joint_programme_map as &$joint_programme_item) {
      $joint_programme_item['availabilities'] = array_values($joint_programme_item['availabilities']);
      usort($joint_programme_item['availabilities'], static function (array $a, array $b): int {
        return strcasecmp(
          implode(' ', [$a['membership_label'] ?? '', $a['organizational_unit_label'] ?? '', $a['institution_label'] ?? '']),
          implode(' ', [$b['membership_label'] ?? '', $b['organizational_unit_label'] ?? '', $b['institution_label'] ?? ''])
        );
      });
    }
    unset($joint_programme_item);

    $joint_programmes_data = array_values($joint_programme_map);
    usort($joint_programmes_data, static function (array $a, array $b): int {
      return strcasecmp($a['title'], $b['title']);
    });

    return $joint_programmes_data;
  }

  public function programmeHasJointMembership(?NodeInterface $programme, string $langcode): bool {
    return !empty($this->getProgrammeOtherInstitutions($programme, $langcode));
  }

  public function buildProgrammeMembershipMetadata(?NodeInterface $programme, string $langcode): array {
    if (!$programme instanceof NodeInterface) {
      return [
        'is_joint_membership' => FALSE,
        'joint_programme_url' => '',
      ];
    }

    $is_joint_membership = $this->programmeHasJointMembership($programme, $langcode);

    return [
      'is_joint_membership' => $is_joint_membership,
      'joint_programme_url' => $is_joint_membership
        ? $this->buildJointProgrammeInformationUrl($programme, $langcode)
        : '',
    ];
  }

  public function buildProgrammeJointMetadata(?NodeInterface $programme, array $direct_joint_programme_ids, array $linked_joint_programme_ids, string $langcode): array {
    if (!$programme instanceof NodeInterface) {
      return [
        'is_joint_membership' => FALSE,
        'joint_programme_url' => '',
      ];
    }

    $programme_id = (int) $programme->id();
    $is_joint_membership = !empty($direct_joint_programme_ids[$programme_id]) || !empty($linked_joint_programme_ids[$programme_id]);

    return [
      'is_joint_membership' => $is_joint_membership,
      'joint_programme_url' => $is_joint_membership
        ? $this->buildJointProgrammeInformationUrl($programme, $langcode)
        : '',
    ];
  }

  public function getJointProgrammeInformation(?NodeInterface $joint_programme, string $langcode): array {
    if (!$joint_programme instanceof NodeInterface) {
      return [];
    }

    $joint_programme_for_display = $this->entityForLanguageWithFallback($joint_programme, $langcode);
    if (!$joint_programme_for_display instanceof NodeInterface) {
      return [];
    }

    $programme = NULL;
    $programme_url = '';
    $alliance = NULL;
    $institution_primary_color = '#F44743';
    $institution_text_color = '#ffffff';
    $institution_emphasis_text_color = '#ffffff';

    if ($joint_programme_for_display->hasField('field_programme') && !$joint_programme_for_display->get('field_programme')->isEmpty()) {
      $programme = $joint_programme_for_display->get('field_programme')->entity;
      $programme = $this->entityForLanguageWithFallback($programme, $langcode);

      if ($programme instanceof NodeInterface) {
        $programme_url = $this->buildProgrammeCatalogueUrl($programme, $langcode) ?? '';

        if ($programme->hasField('field_programme_institution') && !$programme->get('field_programme_institution')->isEmpty()) {
          $programme_institution = $programme->get('field_programme_institution')->entity;
          $programme_institution = $this->entityForLanguageWithFallback($programme_institution, $langcode);

          if (
            $programme_institution instanceof NodeInterface &&
            $programme_institution->hasField('field_institution_alliance') &&
            !$programme_institution->get('field_institution_alliance')->isEmpty()
          ) {
            $alliance = $programme_institution->get('field_institution_alliance')->entity;
            $alliance = $this->entityForLanguageWithFallback($alliance, $langcode);
          }

          if ($programme_institution instanceof NodeInterface && $programme_institution->hasField('field_primary_color') && !$programme_institution->get('field_primary_color')->isEmpty()) {
            $institution_primary_color = $programme_institution->get('field_primary_color')->first()->color ?? $institution_primary_color;
          }
          if ($programme_institution instanceof NodeInterface && $programme_institution->hasField('field_text_color') && !$programme_institution->get('field_text_color')->isEmpty()) {
            $institution_text_color = $programme_institution->get('field_text_color')->first()->color ?? $institution_text_color;
          }
          if ($programme_institution instanceof NodeInterface && $programme_institution->hasField('field_emphasis_text_color') && !$programme_institution->get('field_emphasis_text_color')->isEmpty()) {
            $institution_emphasis_text_color = $programme_institution->get('field_emphasis_text_color')->first()->color ?? $institution_emphasis_text_color;
          }
        }
      }
    }

    $member_ids = $this->getNodeStorage()->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'member')
      ->condition('field_member_joint_programme', $joint_programme->id())
      ->execute();

    $memberships = [];
    if (!empty($member_ids)) {
      $members = $this->getNodeStorage()->loadMultiple($member_ids);

      foreach ($members as $member) {
        if (!$member instanceof NodeInterface) {
          continue;
        }

        $member_for_display = $this->entityForLanguageWithFallback($member, $langcode);
        if (!$member_for_display instanceof NodeInterface) {
          continue;
        }

        $institution_label = '';
        $institution_url = '';
        if ($member->hasField('field_member_institution') && !$member->get('field_member_institution')->isEmpty()) {
          $institution = $member->get('field_member_institution')->entity;
          $institution_for_display = $this->entityForLanguageWithFallback($institution, $langcode);
          if ($institution_for_display instanceof NodeInterface) {
            $institution_label = $institution_for_display->label();
            $institution_url = $this->buildGeneralInformationUrl($institution, $langcode);
          }
        }

        $organizational_units = [];
        if ($member->hasField('field_member_organizational_unit') && !$member->get('field_member_organizational_unit')->isEmpty()) {
          foreach ($member->get('field_member_organizational_unit') as $item) {
            $ou = $item->entity;
            $ou_for_display = $this->entityForLanguageWithFallback($ou, $langcode);
            if (!$ou_for_display instanceof NodeInterface) {
              continue;
            }

            $organizational_units[] = [
              'label' => $ou_for_display->label(),
              'url' => $this->buildOrganizationalUnitInformationUrl($ou, $langcode),
            ];
          }
        }

        $memberships[] = [
          'title' => $member_for_display->label(),
          'role' => trim(strip_tags((string) ($member_for_display->get('field_member_role')->value ?? ''))),
          'role_raw' => $member_for_display->hasField('field_member_role') && !$member_for_display->get('field_member_role')->isEmpty()
            ? $member_for_display->get('field_member_role')->value
            : '',
          'institution_label' => $institution_label,
          'institution_url' => $institution_url,
          'organizational_units' => $organizational_units,
        ];
      }
    }

    usort($memberships, static function (array $a, array $b): int {
      return strcasecmp(
        implode(' ', [$a['title'] ?? '', $a['role'] ?? '', $a['institution_label'] ?? '']),
        implode(' ', [$b['title'] ?? '', $b['role'] ?? '', $b['institution_label'] ?? ''])
      );
    });

    return [
      'joint_programme' => $joint_programme_for_display,
      'title' => $joint_programme_for_display->label(),
      'body' => $joint_programme_for_display->hasField('body') && !$joint_programme_for_display->get('body')->isEmpty()
        ? $joint_programme_for_display->get('body')->value
        : '',
      'description' => $joint_programme_for_display->hasField('field_description') && !$joint_programme_for_display->get('field_description')->isEmpty()
        ? $joint_programme_for_display->get('field_description')->value
        : '',
      'information' => $joint_programme_for_display->hasField('field_information') && !$joint_programme_for_display->get('field_information')->isEmpty()
        ? $joint_programme_for_display->get('field_information')->value
        : '',
      'joint_description' => $joint_programme_for_display->hasField('field_joint_description') && !$joint_programme_for_display->get('field_joint_description')->isEmpty()
        ? $joint_programme_for_display->get('field_joint_description')->value
        : '',
      'form_of_the_diploma' => $joint_programme_for_display->hasField('field_form_of_the_diploma') && !$joint_programme_for_display->get('field_form_of_the_diploma')->isEmpty()
        ? $joint_programme_for_display->get('field_form_of_the_diploma')->value
        : '',
      'mobility_structure' => $joint_programme_for_display->hasField('field_mobility_structure') && !$joint_programme_for_display->get('field_mobility_structure')->isEmpty()
        ? $joint_programme_for_display->get('field_mobility_structure')->value
        : '',
      'arrangements_for_grading' => $joint_programme_for_display->hasField('field_arrangements_for_grading') && !$joint_programme_for_display->get('field_arrangements_for_grading')->isEmpty()
        ? $joint_programme_for_display->get('field_arrangements_for_grading')->value
        : '',
      'programme_label' => $programme instanceof NodeInterface ? $programme->label() : '',
      'programme_url' => $programme_url,
      'alliance' => $alliance,
      'institution_primary_color' => $institution_primary_color,
      'institution_text_color' => $institution_text_color,
      'institution_emphasis_text_color' => $institution_emphasis_text_color,
      'memberships' => $memberships,
    ];
  }

  public function getJointProgrammeLinkedProgrammeIds(array $programme_ids): array {
    $programme_ids = array_values(array_unique(array_map('intval', array_filter($programme_ids, 'is_numeric'))));
    if ($programme_ids === []) {
      return [];
    }

    $query = $this->database->select('node__field_programme', 'jp');
    $query->join('node__field_member_joint_programme', 'mjp', 'mjp.field_member_joint_programme_target_id = jp.entity_id');
    $linked_programme_ids = $query
      ->fields('jp', ['field_programme_target_id'])
      ->condition('jp.deleted', 0)
      ->condition('mjp.deleted', 0)
      ->condition('jp.field_programme_target_id', $programme_ids, 'IN')
      ->distinct()
      ->execute()
      ->fetchCol();

    return array_fill_keys(array_map('intval', $linked_programme_ids), TRUE);
  }

  public function getJointProgrammesForOrganizationalUnits(array $ou_ids, int $institution_id): array {
    $ou_ids = array_values(array_unique(array_map('intval', array_filter($ou_ids, 'is_numeric'))));
    if ($ou_ids === [] || $institution_id <= 0) {
      return [];
    }

    $query = $this->database->select('node__field_member_organizational_unit', 'mou');
    $query->join('node__field_member_joint_programme', 'mjp', 'mjp.entity_id = mou.entity_id');
    $query->join('node__field_programme', 'jp', 'jp.entity_id = mjp.field_member_joint_programme_target_id');
    $query->join('node__field_programme_institution', 'owner', 'owner.entity_id = jp.field_programme_target_id');
    $programme_ids = $query
      ->fields('jp', ['field_programme_target_id'])
      ->condition('mou.deleted', 0)
      ->condition('mjp.deleted', 0)
      ->condition('jp.deleted', 0)
      ->condition('owner.deleted', 0)
      ->condition('mou.field_member_organizational_unit_target_id', $ou_ids, 'IN')
      ->condition('owner.field_programme_institution_target_id', $institution_id, '<>')
      ->distinct()
      ->execute()
      ->fetchCol();

    if (!$programme_ids) {
      return [];
    }

    return $this->getNodeStorage()->loadMultiple(array_map('intval', $programme_ids));
  }

  public function buildCampusInformationVariables(array $view_result, string $langcode, array $isced_labels): array {
    $generic_area_key = 'generic';
    $programmes_by_area = [];
    $area_meta = [];
    $seen = [];
    $campus = $view_result[0]->_entity ?? NULL;
    $campus_ou_ids = [];
    $institution_id = 0;

    if ($campus instanceof NodeInterface) {
      if ($campus->hasField('field_campus_institution') && !$campus->get('field_campus_institution')->isEmpty()) {
        $institution = $campus->get('field_campus_institution')->entity;
        $institution_id = $institution instanceof NodeInterface ? (int) $institution->id() : 0;
      }

      $campus_ou_ids = $this->getNodeStorage()->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', 'organizational_unit')
        ->condition('field_ou_campus', $campus->id())
        ->execute();
      $campus_ou_ids = array_values(array_unique(array_map('intval', $campus_ou_ids)));
    }

    $joint_programmes = $this->getJointProgrammesForOrganizationalUnits($campus_ou_ids, $institution_id);
    $own_programme_ids = [];
    foreach ($view_result as $row) {
      $programme = $row->_relationship_entities['reverse__node__field_programme_ou'] ?? NULL;
      if ($programme instanceof NodeInterface) {
        $own_programme_ids[] = (int) $programme->id();
      }
    }

    $joint_programme_ids = array_map(static fn($programme) => (int) $programme->id(), $joint_programmes);
    $joint_linked_programme_ids = $this->getJointProgrammeLinkedProgrammeIds(array_merge($own_programme_ids, $joint_programme_ids));

    foreach ($view_result as $row) {
      $programme = $row->_relationship_entities['reverse__node__field_programme_ou'] ?? NULL;
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $key = $programme->id();
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;

      $programme = $this->entityForLanguageWithFallback($programme, $langcode);
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $this->appendProgrammeToAreaBuckets(
        $programmes_by_area,
        $area_meta,
        $programme,
        $langcode,
        $isced_labels,
        $generic_area_key,
        $this->buildProgrammeJointMetadata($programme, [], $joint_linked_programme_ids, $langcode),
        NULL,
      );
    }

    foreach ($joint_programmes as $programme) {
      $programme = $this->entityForLanguageWithFallback($programme, $langcode);
      if (!$programme instanceof NodeInterface || isset($seen[$programme->id()])) {
        continue;
      }
      $seen[$programme->id()] = TRUE;

      $this->appendProgrammeToAreaBuckets(
        $programmes_by_area,
        $area_meta,
        $programme,
        $langcode,
        $isced_labels,
        $generic_area_key,
        $this->buildProgrammeJointMetadata($programme, [$programme->id() => TRUE], [], $langcode),
        NULL,
      );
    }

    $country_name = '';
    $country_code = $view_result[0]->_relationship_entities['field_campus_institution']->get('field_institution_country')->value ?? '';
    if ($country_code !== '') {
      $country_list = $this->countryRepository->getList($langcode);
      if (isset($country_list[$country_code])) {
        $country_name = (string) t($country_list[$country_code], [], ['langcode' => $langcode]);
      }
    }

    return [
      'area_meta' => $this->finalizeProgrammesByArea($programmes_by_area, $area_meta, $generic_area_key),
      'programmes_by_area' => $programmes_by_area,
      'institution_country_name' => $country_name,
    ];
  }

  public function buildOrganizationalUnitInformationVariables(array $view_result, string $langcode, array $isced_labels): array {
    $generic_area_key = 'generic';
    $programmes_by_area = [];
    $area_meta = [];
    $seen = [];
    $organizational_unit = $view_result[0]->_entity ?? NULL;
    $organizational_unit_id = $organizational_unit instanceof NodeInterface ? (int) $organizational_unit->id() : 0;
    $institution_id = 0;

    if ($organizational_unit instanceof NodeInterface) {
      if ($organizational_unit->hasField('field_ou_institution') && !$organizational_unit->get('field_ou_institution')->isEmpty()) {
        $institution = $organizational_unit->get('field_ou_institution')->entity;
        $institution_id = $institution instanceof NodeInterface ? (int) $institution->id() : 0;
      }
      elseif ($organizational_unit->hasField('field_ou_campus') && !$organizational_unit->get('field_ou_campus')->isEmpty()) {
        $campus = $organizational_unit->get('field_ou_campus')->entity;
        if ($campus instanceof NodeInterface && $campus->hasField('field_campus_institution') && !$campus->get('field_campus_institution')->isEmpty()) {
          $institution = $campus->get('field_campus_institution')->entity;
          $institution_id = $institution instanceof NodeInterface ? (int) $institution->id() : 0;
        }
      }
    }

    $joint_programmes = $this->getJointProgrammesForOrganizationalUnits([$organizational_unit_id], $institution_id);
    $own_programme_ids = [];
    foreach ($view_result as $row) {
      $programme = $row->_relationship_entities['reverse__node__field_programme_ou'] ?? NULL;
      if ($programme instanceof NodeInterface) {
        $own_programme_ids[] = (int) $programme->id();
      }
    }

    $joint_programme_ids = array_map(static fn($programme) => (int) $programme->id(), $joint_programmes);
    $joint_linked_programme_ids = $this->getJointProgrammeLinkedProgrammeIds(array_merge($own_programme_ids, $joint_programme_ids));

    foreach ($view_result as $row) {
      $programme = $row->_relationship_entities['reverse__node__field_programme_ou'] ?? NULL;
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $key = $programme->id();
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;

      $programme = $this->entityForLanguageWithFallback($programme, $langcode);
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $this->appendProgrammeToAreaBuckets(
        $programmes_by_area,
        $area_meta,
        $programme,
        $langcode,
        $isced_labels,
        $generic_area_key,
        $this->buildProgrammeJointMetadata($programme, [], $joint_linked_programme_ids, $langcode),
        NULL,
      );
    }

    foreach ($joint_programmes as $programme) {
      $programme = $this->entityForLanguageWithFallback($programme, $langcode);
      if (!$programme instanceof NodeInterface || isset($seen[$programme->id()])) {
        continue;
      }
      $seen[$programme->id()] = TRUE;

      $this->appendProgrammeToAreaBuckets(
        $programmes_by_area,
        $area_meta,
        $programme,
        $langcode,
        $isced_labels,
        $generic_area_key,
        $this->buildProgrammeJointMetadata($programme, [$programme->id() => TRUE], [], $langcode),
        NULL,
      );
    }

    return [
      'area_meta' => $this->finalizeProgrammesByArea($programmes_by_area, $area_meta, $generic_area_key),
      'programmes_by_area' => $programmes_by_area,
    ];
  }

  public function buildInstitutionCatalogueIndexVariables(array $view_result, array $direct_joint_programme_ids, array $linked_joint_programme_ids, string $langcode): array {
    $generic_area_key = 'generic';
    $isced_labels = $this->getIscedLabelsForLanguage($langcode);
    $programmes_by_area = [];
    $area_meta = [];
    $seen = [];

    foreach ($view_result as $row) {
      $programme = $row->_entity ?? NULL;
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $programme = $this->entityForLanguageWithFallback($programme, $langcode);
      if (!$programme instanceof NodeInterface) {
        continue;
      }

      $seen_key = (int) $programme->id();
      if (isset($seen[$seen_key])) {
        continue;
      }
      $seen[$seen_key] = TRUE;

      $this->appendProgrammeToAreaBuckets(
        $programmes_by_area,
        $area_meta,
        $programme,
        $langcode,
        $isced_labels,
        $generic_area_key,
        $this->buildProgrammeJointMetadata($programme, $direct_joint_programme_ids, $linked_joint_programme_ids, $langcode),
        isset($row->custom_url) ? (string) $row->custom_url : NULL,
      );
    }

    return [
      'area_meta' => $this->finalizeProgrammesByArea($programmes_by_area, $area_meta, $generic_area_key),
      'programmes_by_area' => $programmes_by_area,
    ];
  }

  public function getIscedLabelsForLanguage(string $langcode): array {
    $isced = new IscedFieldsOfStudy();
    $labels = [];

    foreach ($isced->getLabeledList() as $code => $label) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $labels[$code] = (string) t($label, [], ['langcode' => $langcode]);
    }

    return $labels;
  }

  /*
   * Generic row/view enrichment helpers.
   */

  public function getReferencedEntityLabelsForLanguage(?EntityInterface $entity, string $field_name, string $langcode): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return [];
    }

    $labels = [];
    foreach ($entity->get($field_name) as $item) {
      $referenced_entity = $item->entity ?? NULL;
      if ($referenced_entity instanceof EntityInterface) {
        $referenced_entity = $this->entityForLanguageWithFallback($referenced_entity, $langcode);
        $labels[] = $referenced_entity?->label();
      }
      elseif (!empty($item->value)) {
        $labels[] = $item->value;
      }
    }

    return array_values(array_filter($labels));
  }

  public function enrichSearchProgrammeRow(object $row, string $current_language): void {
    $programme = $row->_entity ?? NULL;
    if (!$programme instanceof NodeInterface) {
      return;
    }

    $lang_for_url = $this->entityLanguageForUrl($programme, $current_language);
    $programme_for_display = $this->entityForLanguageWithFallback($programme, $current_language);
    if (!$programme_for_display instanceof NodeInterface) {
      return;
    }

    $row->_entity = $programme_for_display;
    $joint_programme_meta = $this->buildProgrammeMembershipMetadata($programme_for_display, $lang_for_url);
    $row->is_joint_membership = $joint_programme_meta['is_joint_membership'];
    $row->joint_programme_url = $joint_programme_meta['joint_programme_url'];
    $row->display_instruction_languages = $this->getReferencedEntityLabelsForLanguage(
      $programme_for_display,
      'field_programme_language_of_inst',
      $lang_for_url
    );

    if (
      !$programme_for_display->hasField('field_programme_institution') ||
      $programme_for_display->get('field_programme_institution')->isEmpty()
    ) {
      return;
    }

    $institution = $programme_for_display->get('field_programme_institution')->entity;
    $institution = $this->entityForLanguageWithFallback($institution, $lang_for_url);
    if (!$institution instanceof NodeInterface) {
      return;
    }

    $row->display_institution = $institution;
    $row->programme_url = $this->buildProgrammeCatalogueUrl($programme_for_display, $lang_for_url);
  }

  public function enrichInstitutionCatalogueProgrammeRow(object $row, string $current_language, array $direct_joint_programme_ids, array $linked_joint_programme_ids): void {
    $programme = $row->_entity ?? NULL;
    if (!$programme instanceof NodeInterface || !$programme->id()) {
      return;
    }

    $lang_for_url = $this->entityLanguageForUrl($programme, $current_language);
    $programme_for_display = $this->entityForLanguageWithFallback($programme, $current_language);
    if (!$programme_for_display instanceof NodeInterface) {
      return;
    }

    $row->_entity = $programme_for_display;
    $joint_programme_meta = $this->buildProgrammeJointMetadata($programme_for_display, $direct_joint_programme_ids, $linked_joint_programme_ids, $lang_for_url);
    $row->is_joint_membership = $joint_programme_meta['is_joint_membership'];
    $row->joint_programme_url = $joint_programme_meta['joint_programme_url'];
    $row->display_instruction_languages = $this->getReferencedEntityLabelsForLanguage(
      $programme_for_display,
      'field_programme_language_of_inst',
      $lang_for_url
    );

    if (
      !$programme_for_display->hasField('field_programme_institution') ||
      $programme_for_display->get('field_programme_institution')->isEmpty()
    ) {
      return;
    }

    $institution = $programme_for_display->get('field_programme_institution')->entity;
    $institution = $this->entityForLanguageWithFallback($institution, $lang_for_url);
    if (!$institution instanceof NodeInterface || !$institution->id()) {
      return;
    }

    $row->display_institution = $institution;

    if ($programme_for_display->hasField('field_programme_ou') && !$programme_for_display->get('field_programme_ou')->isEmpty()) {
      $ou = $programme_for_display->get('field_programme_ou')->entity;
      $row->display_ou = $this->entityForLanguageWithFallback($ou, $lang_for_url);
    }

    $row->custom_url = $this->buildProgrammeCatalogueUrl($programme_for_display, $lang_for_url);
  }

  private function buildJointProgrammeInformationUrlFromEntity(NodeInterface $joint_programme, string $langcode): string {
    $lang_for_url = $this->entityLanguageForUrl($joint_programme, $langcode);
    $language = $this->languageManager->getLanguage($lang_for_url);

    return Url::fromRoute('view.joint_programme_information.page_1', [
      'arg_0' => $joint_programme->id(),
    ], [
      'language' => $language,
    ])->toString();
  }

  private function buildGeneralInformationUrl(?NodeInterface $institution, string $langcode): string {
    if (!$institution instanceof NodeInterface) {
      return '';
    }

    $language_to_use = $this->entityLanguageForUrl($institution, $langcode);
    $language = $this->languageManager->getLanguage($language_to_use);
    $institution_for_url = $this->entityForLanguageWithFallback($institution, $langcode);

    if (!$institution_for_url instanceof NodeInterface) {
      return '';
    }

    $canonical_url = $institution_for_url->toUrl('canonical', ['language' => $language])->toString();
    $institution_alias = basename(trim($canonical_url, '/'));

    if ($institution_alias === '' || $institution_alias === (string) $institution_for_url->id()) {
      return '';
    }

    return Url::fromRoute('view.general_information.page_1', [
      'arg_0' => $institution_alias,
    ], [
      'language' => $language,
    ])->toString();
  }

  private function buildOrganizationalUnitInformationUrl(?NodeInterface $organizational_unit, string $langcode): string {
    if (!$organizational_unit instanceof NodeInterface) {
      return '';
    }

    $language_to_use = $this->entityLanguageForUrl($organizational_unit, $langcode);
    $language = $this->languageManager->getLanguage($language_to_use);
    $ou_for_url = $this->entityForLanguageWithFallback($organizational_unit, $langcode);

    if (!$ou_for_url instanceof NodeInterface) {
      return '';
    }

    $canonical_url = $ou_for_url->toUrl('canonical', ['language' => $language])->toString();
    $parts = explode('/', trim($canonical_url, '/'));

    if (count($parts) < 2) {
      return '';
    }

    $ou_alias = end($parts);
    $institution_alias = prev($parts);

    if (!$institution_alias || !$ou_alias || $ou_alias === (string) $ou_for_url->id()) {
      return '';
    }

    return Url::fromRoute('view.organizational_unit_information.page_1', [
      'arg_0' => $institution_alias,
      'arg_1' => $ou_alias,
    ], [
      'language' => $language,
    ])->toString();
  }

  private function buildProgrammeCatalogueUrl(?NodeInterface $programme, string $langcode): ?string {
    if (!$programme instanceof NodeInterface) {
      return NULL;
    }

    $lang_for_url = $this->entityLanguageForUrl($programme, $langcode);
    $language = $this->languageManager->getLanguage($lang_for_url);
    $programme_for_url = $this->entityForLanguageWithFallback($programme, $langcode);
    if (!$programme_for_url instanceof NodeInterface) {
      return NULL;
    }

    $programme_canonical = Url::fromRoute('entity.node.canonical', ['node' => $programme_for_url->id()], ['language' => $language])->toString();
    $programme_parts = array_values(array_filter(explode('/', trim($programme_canonical, '/'))));
    $programme_alias = end($programme_parts);
    if (!$programme_alias || $programme_alias === (string) $programme_for_url->id()) {
      return NULL;
    }

    if (
      !$programme_for_url->hasField('field_programme_institution') ||
      $programme_for_url->get('field_programme_institution')->isEmpty()
    ) {
      return NULL;
    }

    $institution = $programme_for_url->get('field_programme_institution')->entity;
    $institution_for_url = $this->entityForLanguageWithFallback($institution, $lang_for_url);
    if (!$institution_for_url instanceof NodeInterface) {
      return NULL;
    }

    $institution_canonical = Url::fromRoute('entity.node.canonical', ['node' => $institution_for_url->id()], ['language' => $language])->toString();
    $institution_parts = array_values(array_filter(explode('/', trim($institution_canonical, '/'))));
    $institution_alias = end($institution_parts);
    if (!$institution_alias || $institution_alias === 'node' || $institution_alias === (string) $institution_for_url->id()) {
      return NULL;
    }

    return '/' . $lang_for_url . '/catalogue/' . $institution_alias . '/' . $programme_alias;
  }

  private function entityForLanguageWithFallback(?EntityInterface $entity, string $langcode): ?EntityInterface {
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }

    if ($entity->hasTranslation($langcode)) {
      return $entity->getTranslation($langcode);
    }

    if ($entity->hasTranslation('en')) {
      return $entity->getTranslation('en');
    }

    return $entity;
  }

  private function entityLanguageForUrl(?EntityInterface $entity, string $langcode): string {
    if (!$entity instanceof EntityInterface) {
      return 'en';
    }

    if ($entity->hasTranslation($langcode)) {
      return $langcode;
    }

    if ($entity->hasTranslation('en')) {
      return 'en';
    }

    return $entity->language()->getId();
  }

  private function getNodeStorage() {
    return $this->entityTypeManager->getStorage('node');
  }

  /*
   * Area grouping helpers used by listing views.
   */

  private function programmeLevelBucket(NodeInterface $programme): ?string {
    $lvl_raw = strtolower($programme->get('field_eqf_level')->value ?? '');
    if (str_starts_with($lvl_raw, '6')) {
      return 'B';
    }
    if (str_starts_with($lvl_raw, '7')) {
      return 'M';
    }
    if (str_starts_with($lvl_raw, '8')) {
      return 'PhD';
    }

    $programme_level = $programme->hasField('field_programme_level')
      ? strtolower($programme->get('field_programme_level')->value ?? '')
      : '';

    return [
      'bachelor' => 'B',
      'master' => 'M',
      'phd' => 'PhD',
    ][$programme_level] ?? NULL;
  }

  private function programmeAreaKeys(NodeInterface $programme, string $generic_area_key): array {
    $programme_area_keys = [];
    if ($programme->hasField('field_isced_f') && !$programme->get('field_isced_f')->isEmpty()) {
      foreach ($programme->get('field_isced_f') as $item) {
        $broad_key = $item->broad ?? '';
        if ($broad_key !== '') {
          $programme_area_keys[$broad_key] = $broad_key;
        }
      }
    }

    if ($programme_area_keys === []) {
      $programme_area_keys[$generic_area_key] = $generic_area_key;
    }

    return $programme_area_keys;
  }

  private function ensureProgrammeAreaBucket(array &$programmes_by_area, array &$area_meta, string $area_key, string $generic_area_key, array $isced_labels, string $langcode): void {
    if (!isset($programmes_by_area[$area_key])) {
      $programmes_by_area[$area_key] = ['B' => [], 'M' => [], 'PhD' => []];
    }

    if (!isset($area_meta[$area_key])) {
      $area_meta[$area_key] = [
        'label' => $area_key === $generic_area_key
          ? (string) t('Other', [], ['langcode' => $langcode])
          : ($isced_labels[$area_key] ?? $area_key),
        'icon' => 'fas fa-folder',
      ];
    }
  }

  private function buildProgrammeAreaItem(NodeInterface $programme, string $programme_url, array $joint_programme_meta): array {
    return [
      'id' => $programme->id(),
      'label' => $programme->label(),
      'level_code' => $programme->get('field_eqf_level')->value,
      'programme_url' => $programme_url,
      'is_joint_membership' => $joint_programme_meta['is_joint_membership'],
      'joint_programme_url' => $joint_programme_meta['joint_programme_url'],
      'ects' => $programme->hasField('field_credits') && !$programme->get('field_credits')->isEmpty()
        ? $programme->get('field_credits')->value
        : NULL,
      'interuniversity' => $programme->hasField('field_interuniversity') && !$programme->get('field_interuniversity')->isEmpty()
        ? (bool) $programme->get('field_interuniversity')->value
        : FALSE,
    ];
  }

  private function finalizeProgrammesByArea(array &$programmes_by_area, array $area_meta, string $generic_area_key): array {
    foreach ($programmes_by_area as $area => $levels) {
      foreach (['B', 'M', 'PhD'] as $lev) {
        usort($programmes_by_area[$area][$lev], static function ($a, $b) {
          return strcasecmp($a['label'], $b['label']);
        });
      }
    }

    $area_meta_filtered = [];
    foreach ($programmes_by_area as $area_key => $levels) {
      $total = count($levels['B']) + count($levels['M']) + count($levels['PhD']);
      if ($total > 0) {
        $area_meta_filtered[$area_key] = $area_meta[$area_key];
      }
    }

    if (isset($area_meta_filtered[$generic_area_key])) {
      $generic_area = $area_meta_filtered[$generic_area_key];
      unset($area_meta_filtered[$generic_area_key]);
      ksort($area_meta_filtered, SORT_NATURAL);
      $area_meta_filtered[$generic_area_key] = $generic_area;
    }
    else {
      ksort($area_meta_filtered, SORT_NATURAL);
    }

    return $area_meta_filtered;
  }

  private function appendProgrammeToAreaBuckets(array &$programmes_by_area, array &$area_meta, NodeInterface $programme, string $langcode, array $isced_labels, string $generic_area_key, array $joint_programme_meta, ?string $programme_url_override = NULL): void {
    $level = $this->programmeLevelBucket($programme);
    if (!$level) {
      return;
    }

    $programme_area_keys = $this->programmeAreaKeys($programme, $generic_area_key);
    $programme_url = $programme_url_override ?? $this->buildProgrammeCatalogueUrl($programme, $langcode) ?? '';
    foreach ($programme_area_keys as $area_key) {
      $this->ensureProgrammeAreaBucket($programmes_by_area, $area_meta, $area_key, $generic_area_key, $isced_labels, $langcode);
      $programmes_by_area[$area_key][$level][] = $this->buildProgrammeAreaItem($programme, $programme_url, $joint_programme_meta);
    }
  }

}
