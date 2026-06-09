<?php

namespace Drupal\euf_csv_import_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\euf_csv_import_export\CsvImporter\CsvImporter;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ImportFormBase extends FormBase {

  public const ENTITY_TYPE = '';
  public const ENTITY_LABEL = '';

  protected CsvImporter $csvImporter;
  protected RendererInterface $renderer;

  public function __construct(
    CsvImporter $csv_importer,
    RendererInterface $renderer,
  ) {
    $this->csvImporter = $csv_importer;
    $this->renderer = $renderer;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('euf_csv_import_export.csv_importer'),
      $container->get('renderer'),
    );
  }

  public function getFormId() {
    return;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL) {

    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV File'),
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#upload_location' => 'public://import-csv/',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file_id = $form_state->getValue('csv_file')[0];
    $file = File::load($file_id);
    // @todo Decide if we want to keep the files.
    // $file->setPermanent();
    // $file->save();

    // @ todo Convert this to Batch processing if needed.
    $results = $this->importCsv($file, static::ENTITY_TYPE);

    if (!empty($results['errors'])) {
      $table = $this->createErrorsTable($results['errors']);
      $rendered_table = $this->renderer->renderInIsolation($table);

      $this->messenger()->addError(Markup::create($rendered_table));
    }
    $this->messenger()->addStatus($this->t('Import started for @type.', ['@type' => static::ENTITY_LABEL]));

    $file->delete();
  }

  protected function importCsv(File $file, string $entity_type) {
    return $this->csvImporter->import($file, $entity_type);
  }

  protected function createErrorsTable(array $errors) {
    $header = [$this->t('Row #'), $this->t('Error type'), $this->t('Message'), $this->t('CSV column'), $this->t('Values')];
    $rows = [];

    foreach ($errors as $type => $error_list) {
      foreach ($error_list as $error) {
        $rows[] = [
          $error['row_number'],
          $type,
          $error['message'],
          $error['source'],
          implode(',', $error['values'])
        ];
      }
    }

    $error_table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['csv-error-table']],
    ];

    return  $error_table;
  }

}
