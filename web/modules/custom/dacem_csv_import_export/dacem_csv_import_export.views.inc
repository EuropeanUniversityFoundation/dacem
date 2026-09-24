<?php

/**
 * @file
 * Views integration for the DACEM CSV Import/Export module.
 */

/**
 * Implements hook_views_data_alter().
 */
function dacem_csv_import_export_views_data_alter(array &$data) {
  // Register the plugin globally under the Views UI area selection window.
  $data['views']['dacem_csv_export_link'] = [
    'title' => t('DACEM CSV Export Button Link'),
    'help' => t('Provides a link button to trigger a background full CSV export with active view filters.'),
    'area' => [
      'id' => 'dacem_csv_export_link', // This MUST match your @ViewsArea annotation value!
    ],
  ];
}