<?php

namespace Drupal\import_from_excel\Service;

class SubjectImport {

  public function process(array $rows) {
    foreach ($rows as $row) {
      $subject_name = $row[0]; // Nombre de la universidad
      // Procesar la universidad
      \Drupal::messenger()->addMessage(t('Procesando la asignatura: @subject', ['@subject' => $subject_name]));
    }
  }
}
