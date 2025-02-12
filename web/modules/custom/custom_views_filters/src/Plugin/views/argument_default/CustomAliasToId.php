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
  
  /**
   * Provide the default argument value.
   *
   * @return string|null
   *   The node ID if the alias resolves to a node, or NULL otherwise.
   */
  public function getArgument() {
    // Get the current alias from the URL.
    $current_alias = \Drupal::service('path.current')->getPath();
    
    // Resolve the alias to an internal path.
    $internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($current_alias);

    // Check if the internal path corresponds to a node.
    if (strpos($internal_path, '/node/') === 0) {
      // Extract and return the node ID.
      return str_replace('/node/', '', $internal_path);
    }

    // Return NULL if the path does not resolve to a node.
    return NULL;
  }
}
