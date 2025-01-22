<?php

namespace Drupal\my_module\Controller;

use Drupal\Core\Controller\ControllerBase;

class ContactController extends ControllerBase {

  /**
   * Returns the "Website Under Development" page content.
   */
  public function content() {
    return [
      '#theme' => 'b5subtheme',
      '#title' => $this->t('Website Under Development'),
      '#message' => $this->t('Web under development...'),
    ];
  }
}
