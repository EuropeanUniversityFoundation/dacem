<?php

namespace Drupal\dacem_csv_import_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dacem_csv_import_export\CsvExporter\CsvExporter;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class CsvExportController extends ControllerBase {

  protected CsvExporter $csvExporter;

  public function __construct(
    CsvExporter $csv_exporter
  ) {
    $this->csvExporter = $csv_exporter;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('dacem_csv_import_export.csv_exporter'),
    );
  }

  public function processExport(string $view_id, string $display_id, Request $request) {
    $exposed_input = $request->query->all();

    $executable_view = Views::getView($view_id);
    if (!$executable_view) {
      $this->messenger()->addError($this->t('Export failed: View not found.'));
      return $this->redirect('<current>');
    }

    $executable_view->setDisplay($display_id);
    $executable_view->setExposedInput($exposed_input);
    $executable_view->preExecute();
    $executable_view->setItemsPerPage(0);
    $executable_view->execute();

    $entity_type = $executable_view->getBaseEntityType()->id();
    $entity_bundle = $this->getBundleFromViewFilters($executable_view, $entity_type);

    $all_entity_ids = [];
    foreach ($executable_view->result as $row) {
      if (isset($row->nid)) {
        $all_entity_ids[] = (int) $row->nid;
      }
    }

    if (empty($all_entity_ids) || empty($entity_bundle)) {
      $this->messenger()->addWarning($this->t('No data found or target bundle could not be identified.'));
      return $this->redirect('view.' . $view_id . '.' . $display_id, [], ['query' => $exposed_input]);
    }

    \Drupal::logger('csv_export')->notice('Discovered view mapping: Entity=@ent, Bundle=@bun, Rows=@cnt', [
      '@ent' => $entity_type,
      '@bun' => $entity_bundle,
      '@cnt' => count($all_entity_ids),
    ]);

    // 4. Fire up the Export Batch passing both types
    $download_response = $this->csvExporter->export($entity_type, $entity_bundle, $all_entity_ids);

    if ($download_response) {
      return $download_response;
    }

    return $this->redirect('view.' . $view_id . '.' . $display_id, [], ['query' => $exposed_input]);
  }

  /**
   * Helper to scan a view configuration and identify its bundle target.
   */
  protected function getBundleFromViewFilters(\Drupal\views\ViewExecutable $view, string $entity_type): ?string {
    // Views filters are loaded using getHandlers()
    $filters = $view->display_handler->getHandlers('filter');

    // Case A: If it's a Node View, look for the 'type' (Content Type) filter
    if ($entity_type === 'node' && isset($filters['type'])) {
      $value = $filters['type']->value;
      return is_array($value) ? reset($value) : $value;
    }

    // Case B: If it's a Taxonomy Term View, look for the 'vid' (Vocabulary ID) filter
    if ($entity_type === 'taxonomy_term' && isset($filters['vid'])) {
      $value = $filters['vid']->value;
      return is_array($value) ? reset($value) : $value;
    }

    return NULL;
  }
}
