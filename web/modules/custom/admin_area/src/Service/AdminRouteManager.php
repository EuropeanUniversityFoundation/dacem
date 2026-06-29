<?php

namespace Drupal\admin_area\Service;

/**
 * Centralizes admin entity and route mappings.
 */
class AdminRouteManager {

  /**
   * Maps roles to their default entity type page.
   */
  private const ROLE_ENTITY_MAP = [
    'university_admin' => 'institution',
    'programme_admin' => 'programme',
    'iec_admin' => 'iec',
    'campus_editor' => 'campus',
    'ou_administrator' => 'organizational_unit',
  ];

  /**
   * Maps entity types and roles to admin view routes.
   */
  private const ROUTE_MAPS = [
    'programme' => [
      'university_admin' => 'view.admin_programmes.page_1',
      'programme_admin' => 'view.admin_programmes.page_2',
      'ou_administrator' => 'view.admin_programmes.page_3',
    ],
    'iec' => [
      'university_admin' => 'view.admin_iecs.page_1',
      'programme_admin' => 'view.admin_iecs.page_2',
      'iec_admin' => 'view.admin_iecs.page_3',
    ],
    'iec_instance' => [
      'university_admin' => 'view.admin_iec_instances.page_1',
      'programme_admin' => 'view.admin_iec_instances.page_2',
      'iec_admin' => 'view.admin_iec_instances.page_3',
    ],
    'campus' => [
      'university_admin' => 'view.admin_campuses.page_1',
      'campus_editor' => 'view.admin_campuses.page_2',
    ],
    'rs' => [
      'university_admin' => 'view.admin_rs.page_1',
      'campus_editor' => 'view.admin_rs.page_2',
    ],
    'institution' => [
      'university_admin' => 'view.admin_institution.page_1',
    ],
    'organizational_unit' => [
      'university_admin' => 'view.admin_organizational_units.page_1',
      'ou_administrator' => 'view.admin_organizational_units.page_2',
    ],
    'users' => [
      'university_admin' => 'view.admin_users.page_1',
    ],
    'users_content' => [
      'university_admin' => 'view.admin_users.page_2',
    ],
    'academic_authority' => [
      'university_admin' => 'view.admin_academic_authorities.page_1',
    ],
    'agreement' => [
      'university_admin' => 'view.admin_agreements.page_1',
    ],
    'joint_programme' => [
      'university_admin' => 'view.admin_joint_programmes.page_1',
    ],
    'member' => [
      'university_admin' => 'view.admin_joint_member.page_1',
    ],
    'available_joint_programmes' => [
      'university_admin' => 'view.admin_available_joint_programmes.page_1',
    ],
  ];

  /**
   * Returns the default entity type for a given admin role.
   */
  public function getDefaultEntityTypeForRole(?string $role): ?string {
    return $role && isset(self::ROLE_ENTITY_MAP[$role]) ? self::ROLE_ENTITY_MAP[$role] : NULL;
  }

  /**
   * Returns the route name for an entity type and admin role.
   */
  public function getRouteName(string $entity_type, ?string $role): ?string {
    return $role && isset(self::ROUTE_MAPS[$entity_type][$role]) ? self::ROUTE_MAPS[$entity_type][$role] : NULL;
  }

  /**
   * Returns whether an entity type is managed by the admin area.
   */
  public function supportsEntityType(string $entity_type): bool {
    return isset(self::ROUTE_MAPS[$entity_type]);
  }

}
