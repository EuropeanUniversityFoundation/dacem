<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;

/**
 * Controlador para la página /my-area.
 */
class AdminAreaController extends ControllerBase {

  /**
   * El usuario actual.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Página de /my-area.
   */
  public function content() {
    $user_id = $this->currentUser->id();

    // Cargar los grupos del usuario.
    $user_groups = \Drupal::service('group.membership_loader')->loadByUser($this->currentUser);

    // Variable para almacenar los datos que se mostrarán.
    $data = [];

    // Buscar el grupo de la universidad en el que el usuario es "University Admin".
    foreach ($user_groups as $membership) {
      $group = $membership->getGroup();
      \Drupal::messenger()->addMessage('Grupo cargado: ' . $group->label());

      $roles = $membership->getRoles();

      foreach ($roles as $role) {
        if ($role->id() === 'universitytypegroup-university_a') { // Verifica si el usuario tiene el rol de University Admin en el grupo.
            \Drupal::messenger()->addMessage('Usuario tiene rol University Admin en el grupo.');
          // 1. Intentar obtener la universidad de la que el usuario es autor.
          $university = $this->getUniversityAuthoredByUser($user_id);

          // 2. Si no se encuentra, obtener la única universidad del grupo.
          if (!$university) {
            $university = $this->getSingleUniversityInGroup($group);
          }

          if ($university) {
            $data['university'] = $university->label();

            // Cargar todas las carreras asociadas a esta universidad.
            $carreras = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
              'type' => 'carrera',
              'field_universidad' => $university->id(),
            ]);

            \Drupal::messenger()->addMessage('Id de universidad.' . $university->label());

            $data['carreras'] = [];
            foreach ($carreras as $carrera) {
              $carrera_data = [
                'title' => $carrera->label(),
                'asignaturas' => [],
              ];
              \Drupal::messenger()->addMessage('ID de carrera.' . $carrera->label());
              // Cargar asignaturas de cada carrera.
              $asignaturas = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
                'type' => 'asignatura',
                'field_carrera' => $carrera->id(),
              ]);

              foreach ($asignaturas as $asignatura) {
                $carrera_data['asignaturas'][] = $asignatura->label();
              }

              $data['carreras'][] = $carrera_data;
            }
          }
          // Verifica el contenido de `$data` antes de devolverlo.
        \Drupal::messenger()->addMessage('Datos a enviar a la plantilla: ' . print_r($data, TRUE));

          break 2; // Salir de ambos bucles al encontrar el grupo.
        }
      }
    }
    \Drupal::messenger()->addMessage('ID de carrera.' . print_r($data));
    return [
      '#theme' => 'admin-area', // Asegúrate de que esta parte está configurada correctamente.
      '#title' => $this->t('Admin Area - University Admin'),
      '#data' => $data, 
    ];
    
    
  }

  /**
   * Obtiene la universidad de la que el usuario actual es autor.
   */
  protected function getUniversityAuthoredByUser($user_id) {
    $universities = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'universidad',
      'uid' => $user_id,
    ]);
    if (!empty($universities)) {
      \Drupal::messenger()->addMessage('Universidad encontrada como autor.');
      return reset($universities);
    } else {
      \Drupal::messenger()->addMessage('No se encontró universidad donde el usuario sea autor.');
    }
    return NULL;
  }
  

  /**
   * Obtiene la única universidad en el grupo.
   */
  protected function getSingleUniversityInGroup(Group $group) {
    $group_contents = $group->getContent();
    foreach ($group_contents as $content) {
      if ($content->getEntity()->bundle() === 'universidad') {
        \Drupal::messenger()->addMessage('Universidad encontrada en el grupo.');
        return $content->getEntity();
      }
    }
    \Drupal::messenger()->addMessage('No se encontró universidad en el grupo.');
    return NULL;
  }
  
}
