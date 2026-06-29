<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\group\Entity\Group;
use Drupal\menu_test\Access\AccessCheck;
use Drupal\node\Plugin\views\filter\Access;
use Drupal\Tests\Core\StackMiddleware\FalseContentResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\GroupContent;
use Drupal\Group\Relation\GroupRelationTypeManager;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Controlador para la página /my-area.
 */
class AdminAreaController extends ControllerBase
{

  /**
   * Entry point for the legacy /my-area route.
   */
  public function content() {
    $current_user = $this->currentUser();
    $user = \Drupal\user\Entity\User::load($current_user->id());

    $memberships = \Drupal::service('group.membership_loader')->loadByUser($user);
    if (empty($memberships) || !is_array($memberships)) {
      throw new AccessDeniedHttpException('User is not a member of any group.');
    }

    $membership = reset($memberships);
    $group = $membership->getGroup();
    $role = $this->getGroupRoleId($group, $user->id());

    $entity_map = [
      'university_admin' => 'institution',
      'programme_admin' => 'programme',
      'iec_admin' => 'iec',
      'campus_editor' => 'campus',
      'ou_administrator' => 'organizational_unit',
    ];

    if (!$role || !isset($entity_map[$role])) {
      throw new AccessDeniedHttpException('Access denied for your role.');
    }

    $en = \Drupal::languageManager()->getLanguage('en');
    $options = $en ? ['language' => $en] : [];

    return new RedirectResponse(Url::fromRoute('admin_area.my_entity_page', [
      'entity_type' => $entity_map[$role],
    ], $options)->toString());
  }


  /**
   * Entry point for entity-based admin area pages.
   */
  public function myEntityPage(string $entity_type) {
    $current_user = $this->currentUser();
    $user = \Drupal\user\Entity\User::load($current_user->id());
  
    $memberships = \Drupal::service('group.membership_loader')->loadByUser($user);
  
    if (empty($memberships) || !is_array($memberships)) {
      throw new AccessDeniedHttpException('User is not a member of any group.');
    }
  
    $membership = reset($memberships);
    $group = $membership->getGroup();
    $role = $this->getGroupRoleId($group, $user->id());
    // Definimos las rutas según tipo y rol
    \Drupal::logger('admin_area')->info( $role . ' ' );
    $route_maps = [
      'programme' => [
        'university_admin' => 'view.admin_programmes.page_1',
        'programme_admin'  => 'view.admin_programmes.page_2',
        'ou_administrator' => 'view.admin_programmes.page_3',
        // IEC Admin NO debe acceder
      ],
      'iec' => [
        'university_admin' => 'view.admin_iecs.page_1',
        'programme_admin'  => 'view.admin_iecs.page_2',
        'iec_admin'        => 'view.admin_iecs.page_3',
      ],
      'iec_instance' => [
        'university_admin' => 'view.admin_iec_instances.page_1',
        'programme_admin'  => 'view.admin_iec_instances.page_2',
        'iec_admin'  => 'view.admin_iec_instances.page_3',
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

    // Comprobar que ese tipo está soportado
    if (!isset($route_maps[$entity_type])) {
      throw new AccessDeniedHttpException('Entity type not supported.');
    }

    // Comprobar si el rol tiene acceso
    if (!isset($route_maps[$entity_type][$role])) {
      throw new AccessDeniedHttpException('Access denied for your role.');
    }

    // Redirigir a la ruta correspondiente
    $route_name = $route_maps[$entity_type][$role];
    \Drupal::logger('admin_area')->info('Redirigimos a ' . $route_name);

    $en = \Drupal::languageManager()->getLanguage('en');
    $options = $en ? ['language' => $en] : [];

    return new RedirectResponse(Url::fromRoute($route_name, [], $options)->toString());
  }

  /**
   * Devuelve el rol del usuario dentro del grupo.
   */
  private function getGroupRoleId($group, $user_id): ?string {
    $membership = \Drupal::service('group.membership_loader')->load($group, \Drupal\user\Entity\User::load($user_id));
  
    if (!$membership) {
      return null;
    }
  
    $roles = $membership->getRoles(); // ESTA es la forma correcta
    foreach ($roles as $role) {
      $id = $role->id();
      switch ($id) {
        case 'universitytypegroup-university_a':
          return 'university_admin';
        case 'universitytypegroup-degree_admin':
          return 'programme_admin';
        case 'universitytypegroup-subject_admi':
          return 'iec_admin';
        case 'universitytypegroup-campus_edito':
          return 'campus_editor';
        case 'universitytypegroup-ou_administr':
          return 'ou_administrator';
      }
    }
  
    return null;
  }
  



public function redirectToCreateUserForm() {
  $current_user = $this->currentUser();
  $user = \Drupal\user\Entity\User::load($current_user->id());

  $memberships = \Drupal::service('group.membership_loader')->loadByUser($user);
  if (empty($memberships)) {
    throw new AccessDeniedHttpException('User is not part of any group.');
  }

  $membership = reset($memberships);
  $group = $membership->getGroup();

  $en = \Drupal::languageManager()->getLanguage('en');
  $options = [
    'query' => ['destination' => '/en/admin/admin-users'],
  ];
  if ($en) {
    $options['language'] = $en;
  }

  $url = Url::fromRoute('create_user_group.create_user_form', [
    'group' => $group->id(),
  ], $options);

  return new RedirectResponse($url->toString());
}













































































  protected function getUsersGroup(Group $group)
  {
    $group_members = [];

    // Obtener los usuarios del grupo.
    $members = $group->getMembers();
    foreach ($members as $member) {
      $user = $member->getUser();
      $roles = $member->getRoles();

      // Obtener entidades asociadas al usuario según su rol.
      $entities = [];
      foreach ($roles as $role) {
        switch ($role->id()) {

          case 'universitytypegroup-university_a':
            // Obtener carreras creadas por este usuario.
            $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'institution',
              'uid' => $user->id(),
            ]);
            foreach ($universities as $university) {
              $entities[] = [
                'title' => $university->label(),
                'link' => $this->getCatalogueUrl($university),
              ];
            }
            break;


          case 'universitytypegroup-degree_admin':
            // Obtener carreras creadas por este usuario.
            $carreras = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'programme',
              'uid' => $user->id(),
            ]);
            foreach ($carreras as $carrera) {
              $entities[] = [
                'title' => $carrera->label(),
                'link' => $this->getCatalogueUrl($carrera),
              ];
            }
            break;

          case 'universitytypegroup-subject_admi':
            // Obtener asignaturas creadas por este usuario.
            $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'individual_educational_component',
              'uid' => $user->id(),
            ]);
            foreach ($subjects as $subject) {
              // Obtener la carrera asociada.
              $degree = $subject->get('field_iec_programme')->entity;
              $entities[] = [
                'title' => $subject->label() . ' (' . ($degree ? $degree->label() : 'No Programme') . ')',
                'link' => $this->getCatalogueUrl($subject),
              ];
            }
            break;
        }
      }

      // Formatear las entidades asociadas.
      $user_entities = [];
      foreach ($entities as $entity) {
        $user_entities[] = [
          'title' => $entity['title'],
          'link' => $entity['link'],
        ];
      }

      $group_members[] = [
        'name' => $user->getDisplayName(),
        'email' => $user->getEmail(),
        'roles' => array_map(function ($role) {
          return $role->label();
        }, $roles),
        'edit_link' => $user->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Redirigir a /my-area después de editar.
        ])->toString(),






        'delete_link' => Url::fromRoute('entity.user.cancel_form', [
          'user' => $user->id(),
        ], [
          'query' => ['destination' => '/my-area'], // Redirigir a /my-area después de eliminar.
        ])->toString(),
        'entities' => $user_entities, // Agregar las entidades asociadas al usuario.
      ];
    }

    return $group_members; // Retorna los usuarios del grupo con sus entidades asociadas.
  }


}
