<?php

namespace Drupal\euf_csv_import_export\AccessManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\ewp_institutions\Entity\InstitutionEntity;
use Drupal\group\Entity\GroupMembership;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserAccessManager {

  protected GroupMembership $groupMembership;
  protected AccountInterface $account;
  protected EntityTypeManagerInterface $entityTypeManager;


  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->account = $account;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
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

    $storage = $this->entityTypeManager->getStorage('node');
    $institution_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'institution')
      ->condition('field_shac_code', $schac_codes, 'IN')
      ->execute();

    $userInstitutions = $storage->loadMultiple($institution_ids);

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



}

