<?php

namespace Drupal\euf_csv_import_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

abstract class ImportFormBase extends FormBase {

  public const ENTITY_TYPE = '';
  public const ENTITY_LABEL = '';

  public function getFormId() {
    return;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL) {
    // Store entity type to know what we are importing (Programme, Unit, etc.)
    $form_state->set('import_entity_type', static::ENTITY_TYPE);

    $form['header_title'] = $this->getTitle();

    // @todo Figure out how the user interaction is going to happen.
    // $form['parent_selection'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Select HEI'),
    //   '#options' => $this->getSelectParentOptions(), // Implement this to load your HEIs
    //   '#required' => TRUE,
    // ];

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
    // $file->setPermanent();
    // $file->save();

    $hei_id = $form_state->getValue('hei_selection');
    $entity_type = $form_state->get('import_entity_type');

    // @ todo Convert this to Batch processing.
    $this->importCsv($file, $hei_id, $entity_type);

    $this->messenger()->addStatus($this->t('Import started for @type.', ['@type' => $entity_type]));
  }

  protected function getSelectParentOptions() {
    // Return an array of HEI ID => Name
    return [1 => 'HEI Alpha', 2 => 'HEI Beta'];
  }

  protected function importCsv($file, $hei_id, $entity_type) {
    return;
  }

  protected function getTitle() {
    // Use a 'markup' element for simple HTML
    $header = [
      '#markup' => '<h2>' . $this->t('Import @type', ['@type' => self::ENTITY_LABEL]) . '</h2>',
      '#weight' => -100, // Ensure it stays at the very top
    ];

    // @todo Add help text.
    // $help_text = [
    //   '#type' => 'item',
    //   '#markup' => $this->t('Please ensure your CSV file follows the standard template for @type.', [
    //     '@type' => $this->getEntityType()
    //   ]),
    //   '#weight' => -99,
    // ];

    return $header;
  }


}