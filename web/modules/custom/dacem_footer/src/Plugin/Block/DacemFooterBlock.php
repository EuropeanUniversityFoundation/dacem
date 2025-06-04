<?php

namespace Drupal\dacem_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides the DACEM footer block.
 *
 * @Block(
 *   id = "dacem_footer_block",
 *   admin_label = @Translation("DACEM Footer Block"),
 * )
 */
class DacemFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $routeMatch;
  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  protected function getNodeFromAlias($alias, $langcode = NULL)
    {
        $alias_manager = \Drupal::service('path_alias.manager');

        // Obtener el path interno en el idioma deseado
        $internal_path = $alias_manager->getPathByAlias($alias, $langcode);
        

        if (preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
          
            $node = \Drupal\node\Entity\Node::load((int) $matches[1]);

            // Verificar si hay una traducción disponible y cargarla
            if ($langcode && $node->hasTranslation($langcode)) {
                return $node->getTranslation($langcode);
            }

            return $node;
        }

        return NULL;
    }
    
  public function build() {
    \Drupal::logger('dacem_footer')->notice('Footer block ejecutado');
    $university = NULL;
    $logo_url = NULL;
    $color = '#f44336'; // Color por defecto

    
    $institution_alias = \Drupal::routeMatch()->getParameter('arg_0');
    $institution=$this->getNodeFromAlias('/' . $institution_alias, 'en');
    // Obtener datos de la universidad (color, logo)
   

    return [
      '#theme' => 'dacem_footer_block',
      '#institution' => $institution,
    ];
    
  }
}
