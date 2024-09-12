<?php

namespace Drupal\import_degree_from_excel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\node\Entity\Node;

class ImportDegreeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'importar_carrera_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['archivo_excel9'] = [
      '#type' => 'file',
      '#title' => $this->t('Sube el archivo Excel'),
      '#description' => $this->t('Selecciona el archivo Excel (.xlsx o .xls) que contiene los datos de las carreras.'),
      '#upload_validators' => [
        'file_validate_extensions' => ['xls xlsx'],
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Importar Carreras'),
    ];

    return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Obtener el archivo subido.
    $file = file_save_upload('archivo_excel9', [
      'file_validate_extensions' => ['xls xlsx'],
    ]);
  
    if ($file) {
      // file_save_upload puede devolver un array o un objeto de archivo.
      if (is_array($file)) {
        // Si se devuelve un array, obtenemos el primer archivo.
        $file = reset($file);
      }
  
      // Asegurarnos de que el archivo es un objeto antes de llamar a setPermanent().
      if ($file instanceof \Drupal\file\FileInterface) {
        // Verificar la ruta del archivo y el tipo MIME.
        $mime = mime_content_type($file->getFileUri());
        \Drupal::messenger()->addMessage($this->t('Tipo MIME del archivo: @mime', ['@mime' => $mime]));
  
        // Asegurarnos de que sea un archivo Excel.
        if (!in_array($mime, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])) {
          \Drupal::messenger()->addError($this->t('Error: El archivo no es un archivo Excel válido.'));
          return;
        }
  
        // Mover el archivo a una ubicación permanente.
        $file->setPermanent();
        $file->save();
  
        // Procesar el archivo Excel.
        $this->processExcel($file->getFileUri());
      } else {
        \Drupal::messenger()->addError($this->t('Error: No se pudo subir el archivo correctamente.'));
      }
    } else {
      \Drupal::messenger()->addError($this->t('Error: No se seleccionó ningún archivo o el archivo no es válido.'));
    }
  }
  


  protected function get_university_by_name($university_name) {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'universidad')  // Asumiendo que el tipo de nodo es "universidad".
      ->condition('title', $university_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.
  
    $nids = $query->execute();  // Ejecutar la consulta.
    // Si encontramos la universidad, devolver su ID.
    if (!empty($nids)) {
      $nid = reset($nids);
      return \Drupal\node\Entity\Node::load($nid);  // Devolver el nodo de la universidad.
    }
  
    // Si no se encuentra la universidad, devolver NULL.
    return NULL;
  }
  



  /**
   * Procesar el archivo Excel y crear las entidades de carreras.
   */
  protected function processExcel($file_path) {
    // Convertir la ruta Drupal (e.g., "temporary://") a una ruta real del sistema de archivos.
    $real_file_path = \Drupal::service('file_system')->realpath($file_path);

    // Verificar si el archivo existe.
    if (!file_exists($real_file_path)) {
      \Drupal::messenger()->addError($this->t('El archivo no existe en la ruta: @ruta', ['@ruta' => $real_file_path]));
      return;
    }

    try {
      // Cargar el archivo Excel utilizando PhpSpreadsheet.
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($real_file_path);
      $worksheet = $spreadsheet->getActiveSheet();
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error al cargar el archivo Excel: @message', ['@message' => $e->getMessage()]));
      return;
    }

    // Validación de campos de lista de texto.
    $valid_areas = [
      'Artes y Humanidades' => 'artes_humanidades',
      'Ciencias' => 'ciencias',
      'Ciencias de la salud' => 'ciencias_de_la_salud',
      'Ciencias sociales y jurídicas' => 'ciencias_sociales_y_juridicas',
      'Ingeniería y Arquitectura' => 'ingenieria_y_arquitectura',
    ];
    
    // Iterar sobre las filas del Excel para extraer data.
    foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
      // Saltar la primera fila (nombres de columnas).
      if ($rowIndex == 1) {
        continue;
      }

      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $data = [];

      foreach ($cellIterator as $cellIndex => $cell) {
        $data[] = $cell->getValue();
      }

      // Asignar cada columna a su respectivo campo.
      $university_name = $data[0];
      $degree_name = $data[1];
      $language = $data[2];
      $presentation = $data[3];
      $main_objective = $data[4];
      $competencies = $data[5];
      $credits = $data[6];
      $level = $data[7]; // Campo de lista de texto.
      $modality = $data[8]; // Campo de lista de texto.
      $qualification_level = $data[9];
      $study_modality = $data[10];
      $external_internships = $data[11]; // Campo de lista de texto.
      $isced_f = $data[12];
      $academic_course = $data[13];
      $coordinator = $data[14];
      $phone = $data[15];
      $email = $data[16];
      $area = $data[17]; // Campo de lista de texto.
      $qualification = $data[18];

      // Validar campos de lista de texto.
      if (!isset($valid_areas[$area])) {
        \Drupal::messenger()->addError($this->t('El área @area no es válida.', ['@area' => $area]));
        continue;
      }

      /*

      if (!isset($valid_modalitys[$modality])) {
        \Drupal::messenger()->addError($this->t('La modalidad @modality no es válida.', ['@modality' => $modality]));
        continue;
      }
      if (!isset($valid_levels[$level])) {
        \Drupal::messenger()->addError($this->t('El nivel @level no es válido.', ['@level' => $level]));
        continue;
      }
      if (!isset($valid_external_internships[$external_internships])) {
        \Drupal::messenger()->addError($this->t('El valor de prácticas profesionales @external_internships no es válido.', ['@external_internships' => $external_internships]));
        continue;
      }

      */
      
      $university = $this->get_university_by_name($university_name);

      // Crear la entidad "Carrera" (suponiendo que es de tipo "node").
      $node = Node::create([
        'type' => 'carrera',
        'title' => $degree_name,
        'field_universidad' => ['target_id' => $university->id()],  // Referencia a la universidad.
        'field_idioma' => $language,
        'field_presentacion' => $presentation,
        'field_objetivo_principal' => $main_objective,
        'field_competencias' => $competencies,
        'field_creditos' => $credits,
        'field_nivel' => strtolower($level),  // Validado
        'field_modalidad' => strtolower($modality),  // Validado
        'field_nivel_de_cualificacion' => $qualification_level,
        'field_modalidad_de_estudio' => $study_modality,
        'field_practicas_profesionales' => strtolower($external_internships),  // Validado
        'field_isced_f' => $isced_f,
        'field_curso_academico' => $academic_course,
        'field_coordinador' => $coordinator,
        'field_telefono' => $phone,
        'field_email' => $email,
        'field_area' => $valid_areas[$area],  // Validado
        'field_cualificacion' => $qualification,
        'status' => 1,  // Publicado
      ]);

      // Guardar el nodo en la base de datos.
      $node->save();
    }

    \Drupal::messenger()->addMessage($this->t('Importación completada.'));
  }
}
