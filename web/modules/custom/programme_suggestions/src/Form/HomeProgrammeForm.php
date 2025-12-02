<?php

namespace Drupal\programme_suggestions\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

class HomeProgrammeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'programme_suggestions_home_programme_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['home_programme'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Home programme'),
      '#target_type' => 'node',
      // Handler custom que creamos (para Institution--OU--Programme).
      '#selection_handler' => 'programme_suggestions_programme_selection',
      '#required' => TRUE,
      '#description' => $this->t('Start typing the programme you are studying.'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   * Solo permite enviar si se ha seleccionado un Programme válido.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('home_programme');

    // Si viene vacío o null → error.
    if (!$value) {
      $form_state->setErrorByName('home_programme', $this->t('Please select a programme from the list.'));
      return;
    }

    /** @var \Drupal\node\Entity\Node|null $node */
    $node = Node::load($value);
    if (!$node || $node->bundle() !== 'programme') {
      $form_state->setErrorByName('home_programme', $this->t('The selected programme is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('home_programme');

    // Aquí decides qué hacer con el ID:
    // - Guardarlo en la cuenta de usuario
    // - Guardarlo en sesión
    // - Usarlo para redirigir, etc.
    // De momento solo mostramos un mensaje.
    $this->messenger()->addStatus($this->t('Selected home programme ID: @nid', ['@nid' => $nid]));
  }

}
