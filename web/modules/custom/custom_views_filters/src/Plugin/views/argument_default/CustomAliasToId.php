<?php

namespace Drupal\custom_views_filters\Plugin\views\argument_default;

use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Provides a default argument based on alias-to-ID logic.
 *
 * @ViewsArgumentDefault(
 *   id = "custom_alias_to_id",
 *   title = @Translation("Custom Alias to ID")
 * )
 */
class CustomAliasToId extends ArgumentDefaultPluginBase {
  public function getArgument() {
    $alias_manager = \Drupal::service('path_alias.manager');
    $current_path = \Drupal::service('path.current')->getPath();

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
