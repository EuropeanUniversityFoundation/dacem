<?php

namespace Drupal\custom_views_filters\Plugin\views\argument_default;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a default argument based on alias-to-ID logic.
 *
 * @ViewsArgumentDefault(
 *   id = "custom_alias_to_id",
 *   title = @Translation("Custom Alias to ID")
 * )
 */
class CustomAliasToId extends ArgumentDefaultPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * Constructs a CustomAliasToId object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasManagerInterface $alias_manager, CurrentPathStack $current_path) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
    $this->currentPath = $current_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path_alias.manager'),
      $container->get('path.current'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $alias_manager = $this->aliasManager;
    $current_path = $this->currentPath->getPath();

    $path_parts = explode('/', trim($current_path, '/'));

    if (count($path_parts) < 2) {
      return NULL;
    }

    $alias_name = end($path_parts);
    $path = $alias_manager->getPathByAlias('/' . $alias_name);

    if (preg_match('/^\/node\/(\d+)$/', $path, $matches)) {
      return (int) $matches[1];
    }

    return NULL;
  }

}
