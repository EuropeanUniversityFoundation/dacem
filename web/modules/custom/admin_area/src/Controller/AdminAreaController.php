<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

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
   * Obtiene los datos para University Admin.
   */
  protected function getUniversityAdminData(Group $group, $user_id)
  {
    // Intentar obtener la universidad del usuario.
    $university = $this->getUniversityAuthoredByUser($user_id) ?: $this->getSingleUniversityInGroup($group);

    if (!$university) {
      return [];
    }

    $data = [
      'university' => [
        'title' => $university->label(),
        'link' => $university->toUrl()->toString(),
        'edit_link' => $university->toUrl('edit-form')->toString(),
        'university_primary_color' => $university->field_primary_color[0]->color ?? '#FFFFFF',
      ],
      'degrees' => [],
    ];

    // Obtener carreras asociadas.
    $degrees = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'carrera',
      'field_universidad' => $university->id(),
    ]);

    foreach ($degrees as $degree) {
      $degree_data = [
        'title' => $degree->label(),
        'link' => $degree->toUrl()->toString(),
        'edit_link' => $degree->toUrl('edit-form')->toString(),
        'delete_link' => $degree->toUrl('delete-form')->toString(),
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
          'edit_link' => $subject->toUrl('edit-form')->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
        ];
      }

      $data['degrees'][] = $degree_data;
    }
    $data['role'] = 'universitytypegroup-university_a';
    return $data;
  }

  /**
   * Obtiene los datos para Degree Admin.
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
      //dump($degree);
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
        'edit_link' => $degree->toUrl('edit-form')->toString(),
        'delete_link' => $degree->toUrl('delete-form')->toString(),
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
          'edit_link' => $subject->toUrl('edit-form')->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
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
   * Obtiene los datos para Subject Admin.
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
          'edit_link' => $subject->toUrl('edit-form')->toString(),
          'delete_link' => $subject->toUrl('delete-form')->toString(),
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



}
