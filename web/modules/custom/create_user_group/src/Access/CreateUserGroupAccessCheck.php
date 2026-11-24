<?php

namespace Drupal\create_user_group\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\Core\Access\AccessResult;
use Drupal\group\Entity\GroupMembership;

/**
 * Access checker for creating user in group.
 */
class CreateUserGroupAccessCheck {

  /**
   * Checks access to the create-user-in-group route.
   */
  public function access(Group $group, AccountInterface $account) {
    $membership = GroupMembership::loadSingle($group, $account);

    if ($membership) {
      $roles = $membership->getRoles();
      foreach ($roles as $role) {
        if ($role->id() == 'universitytypegroup-university_a') {
          return AccessResult::allowed();
        }
      }
    }

    return AccessResult::forbidden();
  }

}
