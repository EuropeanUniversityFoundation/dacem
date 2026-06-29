<?php

namespace Drupal\admin_area\Service;

use Collator;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

/**
 * Provides shared admin form helpers.
 */
class AdminFormHelper {

  /**
   * Constructs the helper.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Applies admin form options for academic authority forms.
   */
  public function alterAcademicAuthorityForm(array &$form, NodeInterface $node): void {
    $group = $this->resolveGroupForNode($node);
    if (!$group) {
      return;
    }

    $institution_options = [];
    $institution_nid = NULL;
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:institution', 'institution') as $entity) {
      $institution_options[$entity->id()] = $entity->label();
      $institution_nid = $entity->id();
    }

    $campus_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:campus', 'campus') as $campus) {
      if ($institution_nid && $campus->hasField('field_campus_institution') && !$campus->get('field_campus_institution')->isEmpty()) {
        $ref = (int) $campus->get('field_campus_institution')->target_id;
        if ($ref !== (int) $institution_nid) {
          continue;
        }
      }
      $campus_options[$campus->id()] = $campus->label();
    }

    $org_unit_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:organizational_unit', 'organizational_unit') as $ou) {
      if ($institution_nid && $ou->hasField('field_ou_institution') && !$ou->get('field_ou_institution')->isEmpty()) {
        $ref = (int) $ou->get('field_ou_institution')->target_id;
        if ($ref !== (int) $institution_nid) {
          continue;
        }
      }
      $org_unit_options[$ou->id()] = $ou->label();
    }

    $this->sortOptions($campus_options);
    $this->sortOptions($org_unit_options);

    $this->applyEntitySelect(
      $form,
      'field_aa_institution',
      $institution_options,
      $this->extractDefaultTargetId($form, 'field_aa_institution', $node, 'field_aa_institution'),
      FALSE,
      'admin_area_er_select_empty_to_null'
    );
    $this->applyEntitySelect(
      $form,
      'field_aa_campus',
      $campus_options,
      $this->extractDefaultTargetId($form, 'field_aa_campus', $node, 'field_aa_campus'),
      FALSE,
      'admin_area_er_select_empty_to_null'
    );
    $this->applyEntitySelect(
      $form,
      'field_aa_organizational_unit',
      $org_unit_options,
      $this->extractDefaultTargetId($form, 'field_aa_organizational_unit', $node, 'field_aa_organizational_unit'),
      FALSE,
      'admin_area_er_select_empty_to_null'
    );
  }

  /**
   * Applies admin form options for organizational unit forms.
   */
  public function alterOrganizationalUnitForm(array &$form, NodeInterface $node): void {
    $group = $this->resolveGroupForNode($node);
    if (!$group) {
      return;
    }

    $institution_options = [];
    $institution_nid = NULL;
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:institution', 'institution') as $entity) {
      $institution_options[$entity->id()] = $entity->label();
      $institution_nid = $entity->id();
    }

    $campus_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:campus', 'campus') as $campus) {
      if ($institution_nid && $campus->hasField('field_campus_institution') && !$campus->get('field_campus_institution')->isEmpty()) {
        $ref = (int) $campus->get('field_campus_institution')->target_id;
        if ($ref !== (int) $institution_nid) {
          continue;
        }
      }
      $campus_options[$campus->id()] = $campus->label();
    }

    $organizational_unit_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:organizational_unit', 'organizational_unit') as $org_unit) {
      if ($org_unit->hasField('field_parent_organizational_unit') && $org_unit->get('field_parent_organizational_unit')->isEmpty()) {
        $organizational_unit_options[$org_unit->id()] = $org_unit->label();
      }
    }

    $this->sortOptions($campus_options);
    $this->sortOptions($organizational_unit_options);

    if (isset($form['field_ou_institution']['widget'][0]['target_id'])) {
      $el =& $form['field_ou_institution']['widget'][0]['target_id'];
      $el['#type'] = 'select';
      $el['#multiple'] = FALSE;
      $el['#size'] = 1;
      $el['#options'] = $this->normalizeOptions($institution_options);
      $el['#required'] = TRUE;
      $el['#disabled'] = TRUE;
      if ($institution_nid) {
        $el['#default_value'] = (string) $institution_nid;
      }
      unset($el['#selection_handler'], $el['#selection_settings'], $el['#autocomplete_route_name']);
    }

    $this->applyEntitySelect(
      $form,
      'field_ou_campus',
      $campus_options,
      !$node->isNew() && !$node->get('field_ou_campus')->isEmpty() ? (string) $node->get('field_ou_campus')->target_id : '_none',
      FALSE,
      'admin_area_er_select_empty_to_null'
    );
    $this->applyEntitySelect(
      $form,
      'field_parent_organizational_unit',
      $organizational_unit_options,
      !$node->isNew() && !$node->get('field_parent_organizational_unit')->isEmpty() ? (string) $node->get('field_parent_organizational_unit')->target_id : '_none',
      FALSE,
      'admin_area_er_select_empty_to_null'
    );
  }

  /**
   * Applies admin form options for programme forms.
   */
  public function alterProgrammeForm(array &$form, NodeInterface $node): void {
    $group = $this->resolveGroupForNode($node);
    if (!$group) {
      return;
    }

    $institution_nid = NULL;
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:institution', 'institution') as $institution) {
      $institution_nid = $institution->id();
      break;
    }

    $org_unit_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:organizational_unit', 'organizational_unit') as $organizational_unit) {
      if ($institution_nid && $organizational_unit->hasField('field_ou_institution') && !$organizational_unit->get('field_ou_institution')->isEmpty()) {
        $ref = (int) $organizational_unit->get('field_ou_institution')->target_id;
        if ($ref !== (int) $institution_nid) {
          continue;
        }
      }
      $org_unit_options[$organizational_unit->id()] = $organizational_unit->label();
    }

    $this->sortOptions($org_unit_options);
    $this->applyEntitySelect(
      $form,
      'field_programme_ou',
      $org_unit_options,
      $this->extractDefaultTargetId($form, 'field_programme_ou', $node, 'field_programme_ou'),
      TRUE,
      'admin_area_er_select_empty_to_null'
    );
  }

  /**
   * Applies admin form options for agreement forms.
   */
  public function alterAgreementForm(array &$form, NodeInterface $node, ?array $user_input = NULL, array $submitted_values = []): void {
    $group = $this->resolveGroupForNode($node);
    if (!$group) {
      return;
    }

    $institution_nid = NULL;
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:institution', 'institution') as $institution) {
      $institution_nid = $institution->id();
      break;
    }

    $org_unit_options = $this->getOrganizationalUnitOptionsForInstitutionFromGroup($group, $institution_nid);
    $this->sortOptions($org_unit_options);

    $default_department_1 = !$node->isNew() && !$node->get('field_department_partner_1')->isEmpty()
      ? (string) $node->get('field_department_partner_1')->target_id
      : '_none';

    $this->applyEntitySelect(
      $form,
      'field_department_partner_1',
      $org_unit_options,
      $default_department_1,
      FALSE,
      'admin_area_er_select_empty_to_null'
    );

    if (isset($form['field_institution_2']['widget'][0]['target_id'])) {
      $form['field_institution_2']['widget'][0]['target_id']['#ajax'] = [
        'callback' => 'admin_area_agreement_department_partner_2_ajax',
        'event' => 'change',
        'wrapper' => 'agreement-department-partner-2-wrapper',
      ];
    }

    if (isset($form['field_department_partner_2'])) {
      $form['field_department_partner_2']['#prefix'] = '<div id="agreement-department-partner-2-wrapper">';
      $form['field_department_partner_2']['#suffix'] = '</div>';
    }

    $selected_institution_2_id = $this->getSelectedAgreementInstitution2Id($user_input ?? [], $submitted_values, $node);
    $org_unit_options_2 = $this->getOrganizationalUnitOptionsForInstitution($selected_institution_2_id);
    $this->sortOptions($org_unit_options_2);

    $current_department_2 = !$node->isNew() && !$node->get('field_department_partner_2')->isEmpty()
      ? (string) $node->get('field_department_partner_2')->target_id
      : '_none';

    $submitted_department_2 = $submitted_values['field_department_partner_2'][0]['target_id'] ?? NULL;
    if ($submitted_department_2 !== NULL && $submitted_department_2 !== '') {
      $current_department_2 = (string) $submitted_department_2;
    }

    if (isset($form['field_department_partner_2']['widget'][0]['target_id'])) {
      $el =& $form['field_department_partner_2']['widget'][0]['target_id'];
      $el['#type'] = 'select';
      $el['#multiple'] = FALSE;
      $el['#size'] = 1;
      $el['#options'] = $this->normalizeOptions($org_unit_options_2);
      $el['#empty_option'] = t('- None -');
      $el['#empty_value'] = '_none';
      $el['#required'] = FALSE;
      $el['#default_value'] = array_key_exists($current_department_2, $org_unit_options_2) ? $current_department_2 : '_none';

      unset($el['#selection_handler'], $el['#selection_settings'], $el['#autocomplete_route_name']);
      $el['#element_validate'][] = 'admin_area_er_select_empty_to_null';
    }
  }

  /**
   * Resolves the group for a create or edit node form.
   */
  private function resolveGroupForNode(NodeInterface $node): ?GroupInterface {
    $group_param = $this->routeMatch->getParameter('group');
    if ($group_param instanceof GroupInterface) {
      return $group_param;
    }
    if (is_numeric($group_param)) {
      $loaded = $this->entityTypeManager->getStorage('group')->load((int) $group_param);
      if ($loaded instanceof GroupInterface) {
        return $loaded;
      }
    }

    $gr_storage = $this->entityTypeManager->getStorage('group_relationship');
    $rels = method_exists($gr_storage, 'loadByEntity')
      ? $gr_storage->loadByEntity($node)
      : $gr_storage->loadByProperties([
        'entity_type' => $node->getEntityTypeId(),
        'entity_id' => $node->id(),
      ]);

    if (!empty($rels)) {
      $rel = reset($rels);
      if (method_exists($rel, 'getGroup')) {
        $group = $rel->getGroup();
        if ($group instanceof GroupInterface) {
          return $group;
        }
      }
      if (method_exists($rel, 'getGroupEntity')) {
        $group = $rel->getGroupEntity();
        if ($group instanceof GroupInterface) {
          return $group;
        }
      }
    }

    $group_content = $this->routeMatch->getParameter('group_content');
    if ($group_content && method_exists($group_content, 'getGroup')) {
      $group = $group_content->getGroup();
      if ($group instanceof GroupInterface) {
        return $group;
      }
    }

    return NULL;
  }

  /**
   * Loads group-related nodes by plugin and expected bundle.
   */
  private function loadGroupEntitiesByPlugin(GroupInterface $group, string $plugin_id, string $bundle): array {
    $gr_storage = $this->entityTypeManager->getStorage('group_relationship');
    $relationships = $gr_storage->loadByProperties([
      'gid' => $group->id(),
      'plugin_id' => $plugin_id,
    ]);

    $entities = [];
    foreach ($relationships as $rel) {
      $entity = NULL;
      if (method_exists($rel, 'getEntity')) {
        $entity = $rel->getEntity();
      }
      elseif (method_exists($rel, 'getContentEntity')) {
        $entity = $rel->getContentEntity();
      }

      if ($entity && $entity->bundle() === $bundle) {
        $entities[] = $entity;
      }
    }

    return $entities;
  }

  /**
   * Returns OU options belonging to the group's institution scope.
   */
  private function getOrganizationalUnitOptionsForInstitutionFromGroup(GroupInterface $group, ?int $institution_nid): array {
    $org_unit_options = [];
    foreach ($this->loadGroupEntitiesByPlugin($group, 'group_node:organizational_unit', 'organizational_unit') as $organizational_unit) {
      if ($institution_nid && $organizational_unit->hasField('field_ou_institution') && !$organizational_unit->get('field_ou_institution')->isEmpty()) {
        $ref = (int) $organizational_unit->get('field_ou_institution')->target_id;
        if ($ref !== (int) $institution_nid) {
          continue;
        }
      }
      $org_unit_options[$organizational_unit->id()] = $organizational_unit->label();
    }

    return $org_unit_options;
  }

  /**
   * Resolves selected institution 2 from user input, submitted values or node.
   */
  private function getSelectedAgreementInstitution2Id(array $user_input, array $submitted_values, NodeInterface $node): ?int {
    if (!empty($user_input['field_institution_2'][0]['target_id']) && is_string($user_input['field_institution_2'][0]['target_id'])) {
      $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($user_input['field_institution_2'][0]['target_id']);
      if ($entity_id) {
        return (int) $entity_id;
      }
    }

    $submitted_value = $submitted_values['field_institution_2'][0]['target_id'] ?? NULL;
    if (is_string($submitted_value) && $submitted_value !== '') {
      $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($submitted_value);
      if ($entity_id) {
        return (int) $entity_id;
      }
    }

    if (is_array($submitted_value) && isset($submitted_value[0]['target_id']) && is_numeric($submitted_value[0]['target_id'])) {
      return (int) $submitted_value[0]['target_id'];
    }

    if (!$node->isNew() && $node->hasField('field_institution_2') && !$node->get('field_institution_2')->isEmpty()) {
      return (int) $node->get('field_institution_2')->target_id;
    }

    return NULL;
  }

  /**
   * Returns organizational units belonging to one institution.
   */
  private function getOrganizationalUnitOptionsForInstitution(?int $institution_id): array {
    if (!$institution_id) {
      return [];
    }

    $org_units = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'organizational_unit',
      'field_ou_institution' => $institution_id,
    ]);

    $options = [];
    foreach ($org_units as $org_unit) {
      if ($org_unit instanceof NodeInterface) {
        $options[(string) $org_unit->id()] = $org_unit->label();
      }
    }

    return $options;
  }

  /**
   * Applies a normalized select widget to an entity reference field.
   */
  private function applyEntitySelect(array &$form, string $field_name, array $options, ?string $default_value, bool $required, string $validate_callback): void {
    if (!isset($form[$field_name]['widget'][0]['target_id'])) {
      return;
    }

    $el =& $form[$field_name]['widget'][0]['target_id'];
    $el['#type'] = 'select';
    $el['#multiple'] = FALSE;
    $el['#size'] = 1;
    $el['#options'] = $this->normalizeOptions($options);
    $el['#empty_option'] = t('- None -');
    $el['#empty_value'] = '_none';
    $el['#required'] = $required;
    $el['#default_value'] = $default_value;

    unset($el['#selection_handler'], $el['#selection_settings'], $el['#autocomplete_route_name']);
    $el['#element_validate'][] = $validate_callback;
  }

  /**
   * Extracts a target ID default from a form element or node field.
   */
  private function extractDefaultTargetId(array $form, string $form_field_name, NodeInterface $node, string $entity_field_name): ?string {
    $element = $form[$form_field_name]['widget'][0]['target_id'] ?? NULL;
    if (is_array($element) && isset($element['#default_value']) && is_object($element['#default_value']) && method_exists($element['#default_value'], 'id')) {
      return (string) $element['#default_value']->id();
    }

    if ($node->hasField($entity_field_name) && !$node->get($entity_field_name)->isEmpty()) {
      return (string) $node->get($entity_field_name)->target_id;
    }

    return NULL;
  }

  /**
   * Normalizes option IDs and labels to strings.
   */
  private function normalizeOptions(array $options): array {
    $normalized = [];
    foreach ($options as $id => $label) {
      $normalized[(string) $id] = (string) $label;
    }
    return $normalized;
  }

  /**
   * Sorts options using the current language when possible.
   */
  private function sortOptions(array &$options): void {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (class_exists(Collator::class)) {
      $collator = new Collator($langcode);
      uasort($options, static fn($a, $b) => $collator->compare((string) $a, (string) $b));
    }
  }

}
