<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupContent;


/**
 * Controlador para la página /my-area.
 */
class AdminAreaController extends ControllerBase
{
  /**
   * El usuario actual.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor.
   */
  public function __construct(AccountProxyInterface $current_user)
  {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Página de /my-area.
   */
  public function content()
  {
    $user_id = $this->currentUser->id();

    // Cargar los grupos del usuario.
    $user_groups = \Drupal::service('group.membership_loader')->loadByUser($this->currentUser);

    // Variable para almacenar los datos que se mostrarán.
    $data = [];

    foreach ($user_groups as $membership) {
      $group = $membership->getGroup();
      $roles = $membership->getRoles();

      foreach ($roles as $role) {
        switch ($role->id()) {
          case 'universitytypegroup-university_a':
            $data = $this->getUniversityAdminData($group, $user_id);
            break 2;

          case 'universitytypegroup-degree_admin':

            $data = $this->getDegreeAdminData($group, $user_id);
            break 2;

          case 'universitytypegroup-subject_admi':
            $data = $this->getSubjectAdminData($group, $user_id);
            break 2;
        }
      }
    }

    return [
      '#theme' => 'admin-area',
      '#title' => $this->t('Admin Area'),
      '#data' => $data,
    ];
  }

  /**
   *****************************************
   * *****************************************
   * Obtiene los datos para University Admin.
   * *****************************************
   * *****************************************
   */
  protected function getUniversityAdminData(Group $group, $user_id)
  {
    // Intentar obtener la universidad del usuario.
    $university = $this->getUniversityAuthoredByUser($user_id) ?: $this->getSingleUniversityInGroup($group);
    $group_id = $this->getGroupIdsByEntity($university->id());
    if (!$university) {
      return [];
    }

    $data = [
      'university' => [
        'name' => $university->label(),
        'link' => $university->toUrl()->toString(),
        'edit_link' => $university->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
      ])->toString(),
        'university_primary_color' => $university->field_primary_color[0]->color ?? '#FFFFFF',
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $university->id(),
        ])->toString(),
        'create_degree_link' => $this->getGroupEntityCreationUrl($group_id, 'carrera', parent_entity: $university),
      
      ],
      
      'degrees' => [],
      'group_members' => $this->getUsersGroup($group),
      'create_user_link' => Url::fromRoute('create_user_group.create_user_form', [
        'group' => $group->id(),
      ], [
        'query' => ['destination' => '/my-area'], // Parámetro de redirección.
      ])->toString(),
      
    ];

    // Obtener carreras asociadas.
    $degrees = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'carrera',
      'field_universidad' => $university->id(),
    ]);

    foreach ($degrees as $degree) {
      $group_id = $this->getGroupIdsByEntity($degree->id());
      $degree_data = [
        'title' => $degree->label(),
        'link' => $degree->toUrl()->toString(),
        'edit_link' => $degree->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
      ])->toString(),
        'delete_link' => $degree->toUrl('delete-form')->toString(),
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $degree->id(),
        ])->toString(),
        'create_subject_link' => $this->getGroupEntityCreationUrl($group_id, 'asignatura', $degree),
        'subjects' => [],
      ];

      // Obtener asignaturas asociadas a la carrera.
      $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'asignatura',
        'field_carrera' => $degree->id(),
      ]);

      foreach ($subjects as $subject) {
        $degree_data['subjects'][] = [
          'title' => $subject->label(),
          'link' => $subject->toUrl()->toString(),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
          'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
            'node' => $subject->id(),
          ])->toString(),
        ];
      }

      $data['degrees'][] = $degree_data;
    }
    $data['role'] = 'universitytypegroup-university_a';
    return $data;
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
              'type' => 'universidad',
              'uid' => $user->id(),
            ]);
            foreach ($universities as $university) {
              $entities[] = [
                'title' => $university->label(),
                'link' => $university->toUrl()->toString(),
              ];
            }
            break;


          case 'universitytypegroup-degree_admin':
            // Obtener carreras creadas por este usuario.
            $carreras = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'carrera',
              'uid' => $user->id(),
            ]);
            foreach ($carreras as $carrera) {
              $entities[] = [
                'title' => $carrera->label(),
                'link' => $carrera->toUrl()->toString(),
              ];
            }
            break;

          case 'universitytypegroup-subject_admi':
            // Obtener asignaturas creadas por este usuario.
            $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'asignatura',
              'uid' => $user->id(),
            ]);
            foreach ($subjects as $subject) {
              // Obtener la carrera asociada.
              $degree = $subject->get('field_carrera')->entity;
              $entities[] = [
                'title' => $subject->label() . ' (' . ($degree ? $degree->label() : 'No Degree') . ')',
                'link' => $subject->toUrl()->toString(),
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
        'edit_link' => $user->toUrl('edit-form')->toString(),
        'delete_link' => Url::fromRoute('entity.user.cancel_form', [
          'user' => $user->id(),
        ])->toString(),
        'entities' => $user_entities, // Agregar las entidades asociadas al usuario.
      ];
    }

    return $group_members; // Retorna los usuarios del grupo con sus entidades asociadas.
  }



  /**
   * *****************************************
   * *****************************************
   * Obtiene los datos para Degree Admin.
   * *****************************************
   * *****************************************
   */
  protected function getDegreeAdminData(Group $group, $user_id)
  {
    $degrees = $this->getDegreeAuthoredByUser($user_id);
    //dump($degrees);
    if (!$degrees) {
      return [];
    }


    $count = 0;
    foreach ($degrees as $degree) {

      $group_id = $this->getGroupIdsByEntity($degree->id());
      //dump($group_id);

      if ($count == 0) {

        $university_id = $degree->get('field_universidad')->target_id;

        // Cargar la universidad por su ID.
        $university = \Drupal::entityTypeManager()->getStorage('node')->load($university_id);

        // Verificar si la universidad existe.
        if ($university) {
          // Obtener el nombre de la universidad.
          $university_name = $university->label();
          $university_link = $university->toUrl()->toString();
          //dump($university_name);
        }

      }



      $degree_data = [
        'title' => $degree->label(),
        'link' => $degree->toUrl()->toString(),
        'edit_link' => $degree->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),

        'delete_link' => $degree->toUrl('delete-form')->toString(),
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $degree->id(),
        ])->toString(),

        'create_subject_link' => $this->getGroupEntityCreationUrl($group_id, 'asignatura', $degree),
        'subjects' => [],
      ];


      // Obtener asignaturas asociadas a la carrera.
      $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'asignatura',
        'field_carrera' => $degree->id(),
      ]);

      foreach ($subjects as $subject) {
        $degree_data['subjects'][] = [
          'title' => $subject->label(),
          'link' => $subject->toUrl()->toString(),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
          'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
            'node' => $subject->id(),
          ])->toString(),
        ];
      }

      $data['degrees'][] = $degree_data;
      $count++;
    }


    $data['university'] = [
      'name' => $university_name,
      'link' => $university_link,
    ];
    $data['role'] = 'universitytypegroup-degree_admin';
    return $data;
  }

  /**
   * *****************************************
   * *****************************************
   * Obtiene los datos para Subject Admin.
   * *****************************************
   * *****************************************
   */
  protected function getSubjectAdminData(Group $group, $user_id)
  {

    // Obtener asignaturas de las que el usuario es autor.
    $subjects = $this->getSubjectAuthoredByUser($user_id);

    if (empty($subjects)) {
      return [];
    }

    // Variable para almacenar las asignaturas organizadas por carrera.
    $data = [
      'role' => 'universitytypegroup-subject_admi',
      'subjects_by_degree' => [],
    ];


    $count = 0;
    // Organizar asignaturas por carrera.
    foreach ($subjects as $subject) {


      // Obtener el ID de la carrera asociada a la asignatura.
      $degree_id = $subject->get('field_carrera')->target_id;

      // Cargar la carrera.
      $degree = \Drupal::entityTypeManager()->getStorage('node')->load($degree_id);

      if ($degree) {

        if ($count == 0) {

          $university_id = $degree->get('field_universidad')->target_id;

          // Cargar la universidad por su ID.
          $university = \Drupal::entityTypeManager()->getStorage('node')->load($university_id);

          // Verificar si la universidad existe.
          if ($university) {
            // Obtener el nombre de la universidad.
            $university_name = $university->label();
            $university_link = $university->toUrl()->toString();
            //dump($university_name);
          }
          $data['university'] = [
            'name' => $university_name,
            'link' => $university_link,
          ];

        }





        // Si aún no existe esta carrera en el array, inicializarla.
        if (!isset($data['subjects_by_degree'][$degree_id])) {
          $data['subjects_by_degree'][$degree_id] = [
            'degree_title' => $degree->label(),
            'degree_link' => $degree->toUrl()->toString(),
            'subjects' => [],
          ];
        }

        // Añadir la asignatura a la carrera correspondiente.
        $data['subjects_by_degree'][$degree_id]['subjects'][] = [
          'title' => $subject->label(),
          'link' => $subject->toUrl()->toString(),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
          'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
            'node' => $subject->id(),
          ])->toString(),
        ];

      }

      $count++;
    }

    return $data;
  }


  /**
   * Obtiene la universidad de la que el usuario actual es autor.
   */
  protected function getUniversityAuthoredByUser($user_id)
  {
    $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'universidad',
      'uid' => $user_id,
    ]);
    return !empty($universities) ? reset($universities) : NULL;
  }

  /**
   * Obtiene la única universidad en el grupo.
   */
  protected function getSingleUniversityInGroup(Group $group)
  {
    foreach ($group->getContent() as $content) {
      if ($content->getEntity()->bundle() === 'universidad') {
        return $content->getEntity();
      }
    }
    return NULL;
  }


  protected function getDegreeAuthoredByUser($user_id)
  {

    $degrees = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'carrera',
      'uid' => $user_id,
    ]);
    //dump("getdegreebyuser");
    //dump($degrees);
    return !empty($degrees) ? $degrees : [];
  }

  protected function getSubjectAuthoredByUser($user_id)
  {
    $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'asignatura',
      'uid' => $user_id,
    ]);

    return !empty($subjects) ? $subjects : [];
  }


  /**
   * Given a node, find the group IDs that the node is a part of.
   *
   * @param int $nid
   *   The node ID.
   *
   * @return array
   *   An array of group IDs that the node is present in.
   */
  function getGroupIdsByEntity($nid)
  {
    $query = \Drupal::database()->select('group_relationship_field_data', 'gr');
    $query->innerjoin('groups_field_data', 'gfd', 'gr.gid = gfd.id');
    $query->condition('gr.entity_id', $nid);

    // Don't include group user memberships in the query.
    $query->condition('gr.type', 'group-group_membership', '!=');

    $query->fields('gr', ['gid']);
    $result = $query->execute();

    $groupIds = [];
    foreach ($result as $record) {
      $groupIds[] = $record->gid;
    }


    //Para retornar todos os ids de todos os grupos descomentamos a linea de abaixo,
    //temos o [0] porque de momento cada entidad solo pertence a un grupo.
    //return $groupIds;

    return $groupIds[0];
  }



  /**
   * Genera la URL para crear una entidad dentro de un grupo.
   *
   * @param int $group_id
   *   El ID del grupo.
   * @param string $entity_type
   *   El tipo de entidad a crear (por ejemplo, "asignatura").
   *
   * @return string
   *   La URL para crear la entidad dentro del grupo.
   */
  protected function getGroupEntityCreationUrl($group_id, $entity_type, $parent_entity)
  {
    // Generar la ruta para crear la entidad dentro del grupo.

    if ($entity_type == 'asignatura') {
      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_carrera' => $parent_entity->id()], // Incluye el ID de la carrera.
      ])->toString();

    } else if ($entity_type == 'carrera') {

      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_universidad' => $parent_entity->id()], // Incluye el ID de la carrera.
      ])->toString();
    } else {
      return 0;
    }


  }






}
