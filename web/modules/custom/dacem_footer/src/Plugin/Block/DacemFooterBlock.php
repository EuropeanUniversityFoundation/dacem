<?php

namespace Drupal\dacem_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

use Drupal\Core\Language\LanguageInterface;

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

  protected function getNodeFromAlias($alias, $langcode = NULL) {
  $alias_manager = \Drupal::service('path_alias.manager');
  $internal_path = $alias_manager->getPathByAlias($alias, $langcode);

  if (preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
    $node = \Drupal\node\Entity\Node::load((int) $matches[1]);

    $translated = $node;
    if ($langcode && $node->hasTranslation($langcode)) {
      $translated = $node->getTranslation($langcode);
    }

    // Devuelve ambas
    return [
      'original' => $node,
      'translated' => $translated,
    ];
  }

  return NULL;
}

    
  public function build() {
    \Drupal::logger('dacem_footer')->notice('Footer block ejecutado');
    $university = NULL;
    $logo_url = NULL;
    $color = '#f44336'; // Default color (dacem color)

    
    $institution_alias = \Drupal::routeMatch()->getParameter('arg_0');

// Obtener el idioma actual
$langcode = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
$institution_alias = $this->routeMatch->getParameter('arg_0');
$node_data = $this->getNodeFromAlias('/' . $institution_alias, $langcode);

$institution = $node_data ? $node_data['translated'] : NULL;
$original = $node_data ? $node_data['original'] : NULL;

$primary_color = 'red';

if ($original && $original->hasField('field_primary_color') && !$original->get('field_primary_color')->isEmpty()) {
  $primary_color = $original->get('field_primary_color')->first()->color;
}
   

    return [
      '#theme' => 'dacem_footer_block',
      '#institution' => $institution,
      '#primary_color' => $primary_color,
    ];
    
  }
}
