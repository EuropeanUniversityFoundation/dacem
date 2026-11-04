<?php

namespace Drupal\dacem_csv_import_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\dacem_csv_import_export\CsvImporter\CsvImporter;
use Drupal\dacem_csv_import_export\Import\Result\ImportResult;
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
      $container->get('dacem_csv_import_export.csv_importer'),
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
        'FileExtension' => ['extensions' => 'csv'],
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
    try {
      $this->displayResult($this->importCsv($file, static::ENTITY_TYPE));
    }
    finally {
      $file->delete();
    }
  }

  protected function displayResult(ImportResult $result): void {
    if ($result->isSuccessful()) {
      $this->messenger()->addStatus($this->formatPlural(
        $result->importedCount, 'Imported 1 row for @type.', 'Imported @count rows for @type.',
        ['@type' => static::ENTITY_LABEL],
      ));
      return;
    }
    $this->messenger()->addError($this->t('Import failed. No rows were imported. Correct the errors below and try again.'));
    $table = $this->createErrorsTable($result->errors);
    $this->messenger()->addError(Markup::create($this->renderer->renderInIsolation($table)));
  }

  protected function importCsv(File $file, string $entity_type) {
    return $this->csvImporter->import($file, $entity_type);
  }

  /**
   * Allows filtered message formatting and escapes CSV source context.
   *
   * @param \Drupal\dacem_csv_import_export\Import\Result\ImportError[] $errors
   *   File, preflight, sorting, or save errors in the same format.
   */
  protected function createErrorsTable(array $errors): array {
    $header = [$this->t('Row #'), $this->t('Error type'), $this->t('Message'), $this->t('CSV column'), $this->t('Language'), $this->t('Values')];
    $rows = [];
    foreach ($errors as $error) {
      $values = array_map(fn ($value) => $value === '' ? (string) $this->t('(empty)') : $value, $error->values);
      $cells = [
        (string) ($error->rowNumber ?? ''), $error->type, $error->message,
        $error->column ?: (string) $this->t('Whole row'), $error->language ?? '', implode(', ', $values),
      ];
      $row = array_map(static fn ($value) => ['data' => ['#plain_text' => $value]], $cells);
      // Drupal validation messages include formatted placeholders. Filter them
      // through the renderer rather than trusting arbitrary message HTML.
      $row[2]['data'] = [
        '#markup' => $error->message,
        '#allowed_tags' => ['em', 'strong', 'code', 'a', 'br'],
      ];
      $rows[] = $row;
    }
    return [
      '#type' => 'table', '#header' => $header, '#rows' => $rows,
      '#attributes' => ['class' => ['csv-error-table']],
    ];
  }

}
