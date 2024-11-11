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

          case 'universitytypegroup-subject_a':
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
    $data['role']='universitytypegroup-university_a';
    return $data;
  }

  /**
   * Obtiene los datos para Degree Admin.
   */
  protected function getDegreeAdminData(Group $group, $user_id)
  {
    $degree = $this->getDegreeAuthoredByUser($user_id);
    
    if (!$degree) {
      return [];
    }
    
    $data = [
      'degree' => [
        'title' => $degree->label(),
        'link' => $degree->toUrl()->toString(),
        'edit_link' => $degree->toUrl('edit-form')->toString(),
        'delete_link' => $degree->toUrl('delete-form')->toString(),
      ],
      'subjects' => [],
    ];

    // Obtener asignaturas asociadas.
    $subjects = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'asignatura',
      'field_carrera' => $degree->id(),
    ]);

    foreach ($subjects as $subject) {
      $data['subjects'][] = [
        'title' => $subject->label(),
        'link' => $subject->toUrl()->toString(),
        'edit_link' => $subject->toUrl('edit-form')->toString(),
        'delete_link' => $subject->toUrl('delete-form')->toString(),
      ];
    }

    $data['role']='universitytypegroup-degree_admin';
    return $data;
  }

  /**
   * Obtiene los datos para Subject Admin.
   */
  protected function getSubjectAdminData(Group $group, $user_id)
  {
    $subject = $this->getSingleEntityInGroup($group, 'asignatura', $user_id);

    if (!$subject) {
      return [];
    }
    
    $data['role']='universitytypegroup-subject_admi';
    $data['subject'] =  [
      'title' => $subject->label(),
      'link' => $subject->toUrl()->toString(),
      'edit_link' => $subject->toUrl('edit-form')->toString(),
      'delete_link' => $subject->toUrl('delete-form')->toString(),
    ];

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

  /**
   * Obtiene una única entidad (carrera o asignatura) dentro de un grupo.
   */
  protected function getSingleEntityInGroup(Group $group, $type, $user_id)
  {
    foreach ($group->getContent() as $content) {
      $entity = $content->getEntity();
      if ($entity->bundle() === $type && $entity->getOwnerId() === $user_id) {
        return $entity;
      }
    }
    return NULL;
  }


  protected function getDegreeAuthoredByUser($user_id)
  {
    $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'carrera',
      'uid' => $user_id,
    ]);
    return !empty($universities) ? reset($universities) : NULL;
  }

  protected function getSubjectAuthoredByUser($user_id)
  {
    $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'asignatura',
      'uid' => $user_id,
    ]);
    return !empty($universities) ? reset($universities) : NULL;
  }









}
