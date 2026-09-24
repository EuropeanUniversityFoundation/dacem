<?php

namespace Drupal\dacem_csv_import_export\Plugin\views\area;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Views area handler to display a uniform CSV Export action button.
 *
 * @ViewsArea("dacem_csv_export_link")
 */
class CsvExportLink extends AreaPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    // 1. Safely extract the active exposed input from the current view execution
    $exposed_input = $this->view->getExposedInput();

    // 2. Build the dynamic route pointing to your Export Controller
    $url = Url::fromRoute('dacem_csv_import_export.export_processor', [
      'view_id' => $this->view->id(),
      'display_id' => $this->view->current_display,
    ]);

    // 3. Attach the active filters directly as query parameters (?field_ou_type=dept)
    if (!empty($exposed_input)) {
      $url->setOption('query', $exposed_input);
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Export to CSV'),
      '#url' => $url,
      '#attributes' => [
        // 'button--action' mimics standard Local Actions in modern admin themes
        'class' => ['button', 'button--action', 'button--primary', 'csv-export-button'],
      ],
    ];
  }

}
