<?php

namespace Drupal\import_from_excel\Service;

class UniversityImport {

  public function process(array $rows) {
    foreach ($rows as $row) {
      $university_name = $row[0]; // Nombre de la universidad
      // Procesar la universidad
      \Drupal::messenger()->addMessage(t('Procesando universidad: @university', ['@university' => $university_name]));
    }
  }
}
