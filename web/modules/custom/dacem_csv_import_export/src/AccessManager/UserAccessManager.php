<?php

namespace Drupal\dacem_csv_import_export\AccessManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\dacem_csv_import_export\Dataloader\Dataloader;
use Drupal\ewp_institutions\Entity\InstitutionEntity;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupMembership;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserAccessManager {

  protected GroupMembership $groupMembership;
  protected AccountInterface $account;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected Dataloader $dataLoader;


  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    Dataloader $data_loader,
  ) {
    $this->account = $account;
    $this->entityTypeManager = $entity_type_manager;
    $this->dataLoader = $data_loader;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('dacem_csv_import_export.data_loader'),
    );
  }

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

    $userInstitutions = $this->dataLoader->getInstitutionsBySchacCodes($schac_codes);

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
