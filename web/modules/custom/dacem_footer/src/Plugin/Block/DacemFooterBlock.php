<?php

namespace Drupal\dacem_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'FeedbackFooterBlock' block.
 *
 * @Block(
 *   id = "dacem_footer_block",
 *   admin_label = @Translation("DACEM Footer Block"),
 * )
 */
class DacemFooterBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'dacem_footer_block',
      
    ];
  }

}
