<?php

namespace Drupal\dacem_csv_import_export\AccessManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\dacem_csv_import_export\Repository\CatalogueEntityRepository;
use Drupal\ewp_institutions\Entity\InstitutionEntity;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupMembership;
use Drupal\node\Entity\Node;

class UserAccessManager {

  public function __construct(
    protected AccountInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CatalogueEntityRepository $repository,
  ) {}

  public function loadCurrentUserHeis() {
    $memberships = GroupMembership::loadByUser($this->account);
    $userInstitutions = [];

    foreach ($memberships as $membership) {
      $groupInstitutionReferences = $membership->getGroup()->get('field_institution_profile');

      /** @var EntityReferenceFieldItemList $groupInstitutionReferences */
      $groupInstitutions = $groupInstitutionReferences->referencedEntities();

      foreach ($groupInstitutions as $groupInstitution) {
        $userInstitutions[$groupInstitution->id()] = $groupInstitution;
      }
    }

    return array_values($userInstitutions);
  }

  public function loadCurrentUserInstitutions(): array {
    $userHeis = $this->loadCurrentUserHeis();
    $userInstitutions = [];
    $schac_codes = [];

    foreach ($userHeis as $hei) {
      $schac_codes[] = $this->getSchacCodeFromHei($hei);
    }

    $userInstitutions = $this->repository->findInstitutionsBySchacCodes($schac_codes);

    return $userInstitutions;
  }

  public function getSchacCodesFromInstitutions(array $institutions) {
    $institutionCodes = [];

    foreach ($institutions as $institution) {
      /** @var Node $institution */
      $schac_code = $this->getSchacCodeFromInstitution($institution);

      if (!in_array($schac_code, $institutionCodes)) {
        $institutionCodes[] = $schac_code;
      }
    }

    return $institutionCodes;
  }

  public function getSchacCodeFromInstitution(Node $institution) {

    return $institution->get('field_shac_code')[0]->value;
  }

  public function getCurrentUserInstitutionCodes(): array {
    $userInstitutions = $this->loadCurrentUserInstitutions();
    $userInstitutionCodes = $this->getSchacCodesFromInstitutions($userInstitutions);

    return $userInstitutionCodes;
  }

  public function getSchacCodeFromHei(InstitutionEntity $institution) {

    return $institution->get('hei_id')[0]->value;
  }

  public function loadHeiBySchacCode(string $schac_code) {
    $storage = $this->entityTypeManager->getStorage('hei');
    $hei = $storage
      ->loadByProperties([
        'hei_id' => $schac_code,
      ]);

    return reset($hei);
  }

  public function getGroupForInstitution(Node $institution) {
    $schac_code = $this->getSchacCodeFromInstitution($institution);
    $hei = $this->loadHeiBySchacCode($schac_code);

    $storage = $this->entityTypeManager->getStorage('group');

    $group = $storage
      ->loadByProperties([
        'field_institution_profile' => $hei->id(),
      ]);

    return reset($group);
  }

  public function addEntityToInstitutionGroup(Node $entity, Node $institution) {
    /** @var Group $group */
    $group = $this->getGroupForInstitution($institution);
    $plugin_id = 'group_node:' . $entity->getType();

    $existing_relations = $group->getRelationshipsByEntity($entity, $plugin_id);

    if (empty($existing_relations)) {
      $group->addRelationship($entity, $plugin_id);
    }
  }

}
