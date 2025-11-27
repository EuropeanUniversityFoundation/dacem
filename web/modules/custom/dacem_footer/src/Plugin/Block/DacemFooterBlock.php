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
class DacemFooterBlock extends BlockBase implements ContainerFactoryPluginInterface
{

  protected $routeMatch;
  protected $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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

  $institution = NULL;
  $primary_color = '#ff4949'; // Default DACEM color.
  $last_updated = NULL;
  $nodebundle = NULL;

  // Idioma de contenido actual.
  $langcode = \Drupal::languageManager()
    ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
    ->getId();

  /**
   * 1) INSTITUTION (para logo y color)
   */
  $institution_machine = $this->routeMatch->getParameter('arg_0');
  if ($institution_machine) {
    $institution_data = $this->getNodeFromAlias('/' . $institution_machine, $langcode);

    $institution = $institution_data ? $institution_data['translated'] : NULL;
    $original_institution = $institution_data ? $institution_data['original'] : NULL;

    if ($original_institution && $original_institution->hasField('field_primary_color') && !$original_institution->get('field_primary_color')->isEmpty()) {
      $primary_color = $original_institution->get('field_primary_color')->first()->color;
    }
  }

  /**
   * 2) NODO "ACTUAL" PARA last_updated / nodebundle
   */
  $current_path = \Drupal::service('path.current')->getPath(); // p.ej. /es/resources-and-services/uvigo
  $parts = explode('/', trim($current_path, '/'));             // ['es', 'resources-and-services', 'uvigo']

  // --- NUEVO: eliminar prefijo de idioma si existe (en, es, etc.) ---
  if (!empty($parts)) {
    $lang_prefixes = ['en', 'es']; // Ajusta si añades más idiomas tipo 'fr', 'pt', etc.
    if (in_array($parts[0], $lang_prefixes, TRUE)) {
      array_shift($parts); // ahora ['resources-and-services', 'uvigo']
    }
  }

  $alias = NULL;

  if (!empty($parts)) {
    $first = $parts[0];

    // /catalogue/...
    if ($first === 'catalogue') {
      // /catalogue/institution_name
      if (isset($parts[1]) && !isset($parts[2])) {
        $alias = '/' . $parts[1]; // Institution
      }
      // /catalogue/institution_name/programme_name
      elseif (isset($parts[1]) && isset($parts[2]) && !isset($parts[3])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Programme o Campus
      }
      // /catalogue/institution_name/programme_name/iec_name
      elseif (isset($parts[1]) && isset($parts[2]) && isset($parts[3])) {
        $alias = '/' . $parts[1] . '/' . $parts[2] . '/' . $parts[3]; // IEC
      }
    }

    // /general-information/institution_name
    elseif ($first === 'general-information') {
      if (isset($parts[1])) {
        $alias = '/' . $parts[1]; // Institution
      }
    }

    // /campus-information/institution_name/campus_name
    elseif ($first === 'campus-information') {
      if (isset($parts[1]) && isset($parts[2])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Campus
      }
    }

    // /organizational-unit-information/institution_name/organizational_unit_name
    elseif ($first === 'organizational-unit-information') {
      if (isset($parts[1]) && isset($parts[2])) {
        $alias = '/' . $parts[1] . '/' . $parts[2]; // Organizational Unit
      }
    }

    // /resources-and-services/...
    elseif ($first === 'resources-and-services') {
      // Alias de resources_and_services:
      //  /rs/Institution
      //  /rs/Institution/Campus

      // /resources-and-services/institution_name
      if (isset($parts[1]) && !isset($parts[2])) {
        $alias = '/rs/' . $parts[1]; // RS de institución
      }
      // /resources-and-services/institution_name/campus_name
      elseif (isset($parts[1]) && isset($parts[2])) {
        $alias = '/rs/' . $parts[1] . '/' . $parts[2]; // RS de campus
      }
    }
  }

  if ($alias) {
    $node_data = $this->getNodeFromAlias($alias, $langcode);

    if ($node_data && !empty($node_data['translated'])) {
      $current_entity = $node_data['translated'];

      // Fecha de última actualización.
      $changed = $current_entity->getChangedTime();
      $last_updated = \Drupal::service('date.formatter')
        ->format($changed, 'custom', 'd/m/Y');

      // Bundle (institution, programme, campus, individual_educational_component,
      // organizational_unit, resources_and_services, etc.).
      $nodebundle = $current_entity->bundle();
    }
  }

  return [
    '#theme'         => 'dacem_footer_block',
    '#institution'   => $institution,
    '#primary_color' => $primary_color,
    '#last_updated'  => $last_updated,
    '#nodebundle'    => $nodebundle,
  ];
}


}
