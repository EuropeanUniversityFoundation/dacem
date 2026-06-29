<?php

namespace Drupal\admin_area\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\user\UserInterface;

/**
 * Resolves the current admin context from the logged-in user's group.
 */
class AdminContextResolver {

  /**
   * Maps group role IDs to admin area role IDs.
   */
  private const ROLE_MAP = [
    'universitytypegroup-university_a' => 'university_admin',
    'universitytypegroup-degree_admin' => 'programme_admin',
    'universitytypegroup-subject_admi' => 'iec_admin',
    'universitytypegroup-campus_edito' => 'campus_editor',
    'universitytypegroup-ou_administr' => 'ou_administrator',
  ];

  /**
   * Constructs the resolver.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly GroupMembershipLoaderInterface $membershipLoader,
    private readonly Connection $database,
  ) {}

  /**
   * Returns the current Drupal user entity.
   */
  public function getCurrentUserEntity(): ?UserInterface {
    if ($this->currentUser->isAnonymous()) {
      return NULL;
    }

    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    return $user instanceof UserInterface ? $user : NULL;
  }

  /**
   * Returns the current user's first group.
   */
  public function getCurrentGroup(): ?GroupInterface {
    $user = $this->getCurrentUserEntity();
    if (!$user) {
      return NULL;
    }

    $memberships = $this->membershipLoader->loadByUser($user);
    if (empty($memberships) || !is_array($memberships)) {
      return NULL;
    }

    $membership = reset($memberships);
    $group = $membership ? $membership->getGroup() : NULL;

    return $group instanceof GroupInterface ? $group : NULL;
  }

  /**
   * Returns the current user's group ID.
   */
  public function getCurrentGroupId(): ?int {
    $group = $this->getCurrentGroup();
    return $group ? (int) $group->id() : NULL;
  }

  /**
   * Returns the current admin role resolved from group membership.
   */
  public function getCurrentAdminRole(): ?string {
    $group = $this->getCurrentGroup();
    $user = $this->getCurrentUserEntity();

    if (!$group || !$user) {
      return NULL;
    }

    return $this->getAdminRoleForGroup($group, $user);
  }

  /**
   * Returns whether the current admin role matches one of the given roles.
   */
  public function hasCurrentAdminRole(array $roles): bool {
    $role = $this->getCurrentAdminRole();
    return $role ? in_array($role, $roles, TRUE) : FALSE;
  }

  /**
   * Returns whether the current group is of a given group type.
   */
  public function isCurrentGroupType(string $group_type_id): bool {
    $group = $this->getCurrentGroup();
    return $group ? $group->getGroupType()->id() === $group_type_id : FALSE;
  }

  /**
   * Resolves the admin role for a given group and account.
   */
  public function getAdminRoleForGroup(GroupInterface $group, AccountInterface $account): ?string {
    $membership = $this->membershipLoader->load($group, $account);
    if (!$membership) {
      return NULL;
    }

    foreach ($membership->getRoles() as $role) {
      $role_id = $role->id();
      if (isset(self::ROLE_MAP[$role_id])) {
        return self::ROLE_MAP[$role_id];
      }
    }

    return NULL;
  }

  /**
   * Returns the institution node ID linked to the current user's group.
   */
  public function getCurrentInstitutionNodeId(): ?int {
    $group = $this->getCurrentGroup();
    return $group ? $this->getInstitutionNodeIdForGroup($group) : NULL;
  }

  /**
   * Returns the institution node ID linked to a group.
   */
  public function getInstitutionNodeIdForGroup(GroupInterface $group): ?int {
    $institution_nid = $this->database
      ->select('group_relationship_field_data', 'grfd')
      ->fields('grfd', ['entity_id'])
      ->condition('grfd.gid', $group->id())
      ->condition('grfd.plugin_id', 'group_node:institution')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $institution_nid ? (int) $institution_nid : NULL;
  }

}
