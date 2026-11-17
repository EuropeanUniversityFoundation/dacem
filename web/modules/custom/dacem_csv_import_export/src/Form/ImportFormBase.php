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
  protected const SORT = FALSE;

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

    if ($summary = $form_state->get('import_summary')) {
      $form['summary'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Review import'),
        '#items' => [
          $this->t('File: @file', ['@file' => $form_state->get('import_filename')]),
          $this->t('Import type: @type', ['@type' => static::ENTITY_LABEL]),
          $this->t('Institution: @institution', ['@institution' => $summary->institution]),
          $this->t('Records to create: @count', ['@count' => $summary->createdCount]),
          $this->t('Records to update: @count', ['@count' => count($summary->updatedCodes)]),
        ],
      ];
      $form['updated_codes'] = [
        '#theme' => 'item_list',
        '#title' => $this->t('Codes of entities to update'),
        '#items' => array_map(static fn ($code) => ['#plain_text' => $code], $summary->updatedCodes),
        '#empty' => $this->t('No existing entities will be updated.'),
      ];
      $form['notice'] = ['#markup' => '<p>' . $this->t('Supplied values replace existing values. Empty cells clear existing values. No catalogue data has been saved yet.') . '</p>'];
      $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Confirm import')];
      $form['cancel'] = [
        '#type' => 'submit', '#value' => $this->t('Cancel'),
        '#submit' => ['::cancelImport'], '#limit_validation_errors' => [],
      ];
      return $form;
    }

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
      '#value' => $this->t('Review import'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $reviewing = $form_state->get('import_summary') !== NULL;
    $file_id = $reviewing ? $form_state->get('import_file_id') : ($form_state->getValue('csv_file')[0] ?? NULL);
    $file = $file_id ? File::load($file_id) : NULL;
    if (!$file) {
      $this->messenger()->addError($this->t('The uploaded CSV is no longer available. Upload it again.'));
      $this->resetImport($form_state);
      return;
    }
    $keep_file = FALSE;
    try {
      // Recheck authorization, validation, and target identities on confirmation.
      $result = $this->csvImporter->preview($file, static::ENTITY_TYPE, static::SORT);
      if (!$result->isSuccessful()) {
        $this->displayResult($result);
        $this->resetImport($form_state);
        return;
      }
      $hash = hash_file('sha256', $file->getFileUri());
      if (!$reviewing || $result->summary != $form_state->get('import_summary') || $hash !== $form_state->get('import_hash')) {
        if ($reviewing) {
          $this->messenger()->addWarning($this->t('The import has changed. Review the updated summary before confirming.'));
        }
        $form_state->set('import_summary', $result->summary);
        $form_state->set('import_file_id', $file->id());
        $form_state->set('import_filename', $file->getFilename());
        $form_state->set('import_hash', $hash);
        $form_state->setRebuild();
        $keep_file = TRUE;
        return;
      }
      $this->displayResult($this->importCsv($file, static::ENTITY_TYPE));
      $this->resetImport($form_state);
    }
    finally {
      if (!$keep_file) {
        $file->delete();
      }
    }
  }

  public function cancelImport(array &$form, FormStateInterface $form_state): void {
    if ($file = File::load($form_state->get('import_file_id'))) {
      $file->delete();
    }
    $this->resetImport($form_state);
    $this->messenger()->addStatus($this->t('Import cancelled. No catalogue data was changed.'));
  }

  private function resetImport(FormStateInterface $form_state): void {
    foreach (['import_summary', 'import_file_id', 'import_filename', 'import_hash'] as $key) {
      $form_state->set($key, NULL);
    }
    $form_state->setUserInput([]);
    $form_state->setRebuild();
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
    return $this->csvImporter->import($file, $entity_type, static::SORT);
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
