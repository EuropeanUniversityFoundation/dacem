<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\GroupContent;
use Drupal\Group\Relation\GroupRelationTypeManager;


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
            $data = $this->getInstitutionAdminData($group, $user_id);
            break 2;

          case 'universitytypegroup-degree_admin':

            $data = $this->getProgrammeAdminData($group, $user_id);
            break 2;

          case 'universitytypegroup-subject_admi':
            $data = $this->getIECAdminData($group, $user_id);
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
   * Obtiene los datos para Institution Admin.
   * *****************************************
   * *****************************************
   */
  protected function getInstitutionAdminData(Group $group, $user_id)
  {
    
    





    // Intentar obtener la universidad del usuario.
    $university = $this->getInstitutionAuthoredByUser($user_id) ?: $this->getSingleInstitutionInGroup($group);
    $group_id = $this->getGroupIdsByEntity($university->id());


    if (!$university) {
      return [];
    }

    $data = [
      'university' => [
        'name' => $university->label(),
        'link' => $this->getCatalogueUrl($university),
        'edit_link' => $university->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),
        'university_primary_color' => $university->field_primary_color[0]->color ?? '#FFFFFF',
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $university->id(),
        ])->toString(),
        'create_degree_link' => $this->getGroupEntityCreationUrl($group_id, 'programme', parent_entity: $university),
        'create_campus_link' => $this->getGroupEntityCreationUrl($group_id, 'campus', parent_entity: $university),
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
      'type' => 'programme',
      'field_programme_institution' => $university->id(),
    ]);

    foreach ($degrees as $degree) {
      $group_id = $this->getGroupIdsByEntity($degree->id());
      \Drupal::logger('custom_module')->notice('Degree: ' . $degree->label() . ', con id ' . $degree->id() . '. Group id es ' . $group_id);
      $degree_data = [
        'title' => $degree->label(),
        'link' => $this->getCatalogueUrl($degree),
        'edit_link' => $degree->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),
        'delete_link' => $degree->toUrl('delete-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $degree->id(),
        ])->toString(),
        'create_subject_link' => $this->getGroupEntityCreationUrl($group_id, 'individual_educational_component', $degree),
        'subjects' => [],
      ];


      // Obtener asignaturas asociadas a la carrera.
      $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'individual_educational_component',
        'field_iec_programme' => $degree->id(),
      ]);

      foreach ($subjects as $subject) {
        $degree_data['subjects'][] = [
          'title' => $subject->label(),
          'link' => $this->getCatalogueUrl($subject),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
          ])->toString(),
          'delete_link' => $subject->toUrl('delete-form', [
            'query' => ['destination' => '/my-area'],
          ])->toString(),
          'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
            'node' => $subject->id(),
          ])->toString(),




        ];
      }


      $data['degrees'][] = $degree_data;

    }
    $data['role'] = 'universitytypegroup-university_a';
    //dump($data);



    // Obtener Resources and Services asociado a la universidad
    $resources_services = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'resources_and_services',
      'field_rs_institution' => $university->id(),
    ]);

    // Verificar si hay una entidad existente
    if (!empty($resources_services)) {
      // Tomar el primer elemento si hay varios (debería haber solo uno)
      $resources_services_entity = reset($resources_services);

      $data['resources_services'] = [
        'exists' => true,
        'edit_link' => $resources_services_entity->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
      ];
    } else {
      // No existe Resources and Services, generar el enlace de creación
      $data['resources_services'] = [
        'exists' => false,
        'create_link' => $this->getGroupEntityCreationUrl($group_id, 'resources_and_services', parent_entity: $university),
         
      ];
    }


   



    $campuses = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'campus',
      'field_campus_institution' => $university->id(),
    ]);

    

    $data['campuses'] = [];

    foreach ($campuses as $campus) {
      \Drupal::logger('custom_module')->notice('Campus: ' . $campus->label() . ', con id ' . $campus->id() . '. Group id es ' . $group_id);
    
      // Buscar el nodo de Resources and Services para este campus
      $campus_rs = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'resources_and_services',
        'field_rs_campus' => $campus->id(),
      ]);
    
      $campus_rs_node = reset($campus_rs); // asumimos que hay solo uno
    
      $data['campuses'][] = [
        'title' => $campus->label(),
        'link' => $campus->toUrl()->toString(),
        'edit_link' => $campus->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
        'delete_link' => $campus->toUrl('delete-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
        'edit_rs_link' => $campus_rs_node->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
      ];
    }
    



    














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



  /**
   * *****************************************
   * *****************************************
   * Obtiene los datos para Programme Admin.
   * *****************************************
   * *****************************************
   */
  protected function getProgrammeAdminData(Group $group, $user_id)
  {
    $degrees = $this->getProgrammeAuthoredByUser($user_id);
    //dump($degrees);
    if (!$degrees) {
      return [];
    }


    $count = 0;
    foreach ($degrees as $degree) {

      $group_id = $this->getGroupIdsByEntity($degree->id());
      //dump($group_id);

      if ($count == 0) {

        $university_id = $degree->get('field_programme_institution')->target_id;

        // Cargar la universidad por su ID.
        $university = \Drupal::entityTypeManager()->getStorage('node')->load($university_id);

        // Verificar si la universidad existe.
        if ($university) {
          // Obtener el nombre de la universidad.
          $university_name = $university->label();
          $university_link = $this->getCatalogueUrl($university);
          //dump($university_name);
        }

      }



      $degree_data = [
        'title' => $degree->label(),
        'link' => $this->getCatalogueUrl($degree),
        'edit_link' => $degree->toUrl('edit-form', [
          'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
        ])->toString(),

        'delete_link' => $degree->toUrl('delete-form', [
          'query' => ['destination' => '/my-area'],
        ])->toString(),
        'translation_link' => Url::fromRoute('entity.node.content_translation_overview', [
          'node' => $degree->id(),
        ])->toString(),

        'create_subject_link' => $this->getGroupEntityCreationUrl($group_id, 'individual_educational_component', $degree),
        'subjects' => [],
      ];


      // Obtener asignaturas asociadas a la carrera.
      $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'individual_educational_component',
        'field_iec_programme' => $degree->id(),
      ]);

      foreach ($subjects as $subject) {
        $degree_data['subjects'][] = [
          'title' => $subject->label(),
          'link' => $this->getCatalogueUrl($subject),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
          ])->toString(),
          'delete_link' => $subject->toUrl('delete-form', [
            'query' => ['destination' => '/my-area'],
          ])->toString(),
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
   * Obtiene los datos para IEC Admin.
   * *****************************************
   * *****************************************
   */
  protected function getIECAdminData(Group $group, $user_id)
  {

    // Obtener asignaturas de las que el usuario es autor.
    $subjects = $this->getIECAuthoredByUser($user_id);

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
      $degree_id = $subject->get('field_iec_programme')->target_id;

      // Cargar la carrera.
      $degree = \Drupal::entityTypeManager()->getStorage('node')->load($degree_id);

      if ($degree) {

        if ($count == 0) {

          $university_id = $degree->get('field_programme_institution')->target_id;

          // Cargar la universidad por su ID.
          $university = \Drupal::entityTypeManager()->getStorage('node')->load($university_id);

          // Verificar si la universidad existe.
          if ($university) {
            // Obtener el nombre de la universidad.
            $university_name = $university->label();
            $university_link = $this->getCatalogueUrl($university);
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
            'degree_link' => $this->getCatalogueUrl(entity: $degree),
            'subjects' => [],
          ];
        }

        // Añadir la asignatura a la carrera correspondiente.
        $data['subjects_by_degree'][$degree_id]['subjects'][] = [
          'title' => $subject->label(),
          'link' => $this->getCatalogueUrl($subject),
          'edit_link' => $subject->toUrl('edit-form', [
            'query' => ['destination' => '/my-area'], // Aquí defines la página a la que redirigir.
          ])->toString(),
          'delete_link' => $subject->toUrl('delete-form', [
            'query' => ['destination' => '/my-area'],
          ])->toString(),
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
  protected function getInstitutionAuthoredByUser($user_id)
  {
    $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'institution',
      'uid' => $user_id,
    ]);
    return !empty($universities) ? reset($universities) : NULL;
  }

  /**
   * Obtiene la única universidad en el grupo.
   */
  protected function getSingleInstitutionInGroup(Group $group)
  {
    foreach ($group->getContent() as $content) {
      if ($content->getEntity()->bundle() === 'institution') {
        return $content->getEntity();
      }
    }
    return NULL;
  }


  protected function getProgrammeAuthoredByUser($user_id)
  {

    $degrees = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'programme',
      'uid' => $user_id,
    ]);
    //dump("getdegreebyuser");
    //dump($degrees);
    return !empty($degrees) ? $degrees : [];
  }

  protected function getIECAuthoredByUser($user_id)
  {
    $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'individual_educational_component',
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
    //\Drupal::logger('custom_module')->notice('Dentro de getGroupsByEntityID, nid es ' . $nid);
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
    \Drupal::logger('custom_module')->notice('Dentro de getGroupsByEntityID, ENTITY TYPE  ' . $entity_type);
    if ($entity_type == 'individual_educational_component') {
      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_iec_programme' => $parent_entity->id(), 'destination' => '/my-area'], // Incluye el ID de la carrera.
      ])->toString();

    } else if ($entity_type == 'programme') {

      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_programme_institution' => $parent_entity->id(), 'destination' => '/my-area'], // Incluye el ID de la carrera.
      ])->toString();

    } else if ($entity_type == 'resources_and_services' ){
      
      //Formar enlace de creaccioooooon para rs.
      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_rs_institution' => $parent_entity->id(), 'destination' => '/my-area'], // Incluye el ID de la carrera.
      ])->toString();

    }else if($entity_type == 'campus'){
      //Formar enlace de creaccioooooon para rs.
      return Url::fromRoute('entity.group_relationship.create_form', [
        'group' => $group_id,
        'plugin_id' => 'group_node:' . $entity_type,
      ], [
        'query' => ['field_campus_institution' => $parent_entity->id(), 'destination' => '/my-area'], // Incluye el ID de la carrera.
      ])->toString();


    } else {
      return 0;
    }


  }





  /**
   * Genera el enlace de traducción para un nodo.
   *
   * @param \Drupal\node\Entity\Node $node
   *   El nodo para el que se generará el enlace de traducción.
   *
   * @return string
   *   La URL del enlace de traducción o una cadena vacía si no es válido.
   */

  protected function getTranslationLink($entity)
  {
    // Verifica si la entidad es un nodo.
    if (!$entity instanceof \Drupal\node\Entity\Node) {
      \Drupal::logger('admin_area')->warning('La entidad proporcionada no es un nodo.');
      return '';
    }

    // Obtén el idioma actual.
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Verifica si existe traducción en el idioma actual.
    if (!$entity->hasTranslation($langcode)) {
      // Si no existe traducción, verifica el idioma predeterminado.
      $default_langcode = \Drupal::languageManager()->getDefaultLanguage()->getId();
      if (!$entity->hasTranslation($default_langcode)) {
        // No existe una traducción base, devuelve un enlace vacío o mensaje.
        return '';
      }
    }


    // Genera el enlace de traducción.
    return Url::fromRoute('entity.node.content_translation_overview', [
      'node' => $entity->id(),
    ])->toString();
  }


/**
 * Modifies the URL of an entity to include "/en/catalogue/".
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity whose URL needs to be modified.
 *
 * @return string
 *   The modified URL.
 */
function getCatalogueUrl(\Drupal\Core\Entity\EntityInterface $entity) {
  // Obtener la URL original (ej: "/en/university-vigo/...")
  $original_url = $entity->toUrl()->toString();

  // Eliminar el prefijo del idioma "/en" si está presente al inicio
  $clean_alias = preg_replace('|^/en/|', '', $original_url);

  // Construir la nueva URL con "/en/catalogue/"
  $modified_url = '/en/catalogue/' . $clean_alias;

  return $modified_url;
}





}
