<?php

namespace Drupal\import_from_excel\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\node\Entity\Node;

class ImportForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'importar_carrera_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $current_user = \Drupal::currentUser();
    $username = $current_user->getDisplayName();
    \Drupal::messenger()->addMessage('El usuario actual es: ' . $username);

    $form['import_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Selecciona el tipo de importación'),
      '#options' => [
        'carreras' => $this->t('Carreras'),
        'asignaturas' => $this->t('Asignaturas'),
      ],
    ];

    $form['asignaturas_ingenieria_software'] = [
      '#type' => 'file',
      '#title' => $this->t('Sube el archivo Excel'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Importar'),
    ];

    return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Obtener el archivo subido.
    $file = file_save_upload('asignaturas_ingenieria_software', [
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



        $import_type = $form_state->getValue('import_type');

        if ($file) {
          // Si es "Carreras", llamar a procesarExcel para carreras.
          if ($import_type == 'carreras') {
            $this->processDegreeExcel($file->getFileUri());
          }
          // Si es "Asignaturas", llamar a procesarExcel para asignaturas.
          else if ($import_type == 'asignaturas') {
            $this->processSubjectsExcel($file->getFileUri());
          }
        } else {
          \Drupal::messenger()->addError($this->t('Error: No se pudo subir el archivo correctamente.'));
        }
      } else {
        \Drupal::messenger()->addError($this->t('Error: No se seleccionó ningún archivo o el archivo no es válido.'));
      }
    }
  }



  protected function getUniversityByName($university_name)
  {

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
  protected function processDegreeExcel($file_path)
  {
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

      try {




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

        $university = $this->getUniversityByName($university_name);

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
      } catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error al procesar la fila @fila: @error', ['@fila' => $rowIndex, '@error' => $e->getMessage()]));
        continue;  // Continuar con la siguiente fila en caso de error
      }
    }

    \Drupal::messenger()->addMessage($this->t('Importación completada.'));
  }







  protected function getDegreeByName($degree_name, $university_id)
  {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'carrera')  // Asumiendo que el tipo de nodo es "carrera".
      ->condition('field_universidad', value: $university_id) // Buscar por universidad.
      ->condition('title', $degree_name)  // Buscar por título.
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






  protected function processSubjectsExcel($file_path)
  {
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
    $valid_courses_quarters = [
      '1º' => '1o',
      '2º' => '2o',
      '3º' => '3o',
      '4º' => '4o',
      
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

      try {




        // Asignar cada columna a su respectivo campo.
        $university_name = $data[0];
        $degree_name = $data[1];
        $subject_name = $data[2];
        $credits = $data[3];
        $quarter = $data[4];
        $course = $data[5];
        $code = $data[6];
        $requirements = $data[7]; // Campo de lista de texto.
        $contents = $data[8]; // Campo de lista de texto.
        $evaluation = $data[9];
        $instructors = $data[10];
        $introduction = $data[11]; // Campo de lista de texto.
        $language = $data[12];
        $learning_outcomes = $data[13];
        $modality = $data[14];
        $planned_activities = $data[15];
        $recommedatios = $data[16];
        $type = strtolower($data[17]); // Campo de lista de texto.
        

        // Validar campos de lista de texto.
        if (!isset($valid_courses_quarters[$course]) && !isset($valid_courses_quarters[$quarter])) {
          \Drupal::messenger()->addError($this->t('El curso @curso o el cuatrimestre @cuatrimestre no son válidos.', ['@curso' => $course, 'cuatrimestre'=>$quarter]));
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

        $university = $this->getUniversityByName($university_name);
        $degree = $this->getDegreeByName($degree_name, $university->id());

        // Crear la entidad "Carrera" (suponiendo que es de tipo "node").
        $node = Node::create([
          'type' => 'asignatura',
          'title' => $subject_name,
          'field_carrera' => ['target_id' => $degree->id()],  // Referencia a la universidad.
          'field_creditos' => $credits,
          'field_cuatrimestre' => $valid_courses_quarters[$quarter],
          'field_curso' => $valid_courses_quarters[$course],
          'field_codigo' => $code,
          'field_requirements' => $requirements,
          'field_subject_contents' => $contents,
          'field_subject_evaluation' => $evaluation,  // Validado
          'field_subject_instructors' => $instructors,  // Validado
          'field_subject_introduction' => $introduction,
          'field_subject_language' => $language,
          'field_subject_learning_outcomes' => $learning_outcomes,  // Validado
          'field_subject_modality' => $modality,
          'field_subject_planned_activities' => $planned_activities,
          'field_subject_recommendations' => $recommedatios,
          'field_tipo' => $type,
          'status' => 1,  // Publicado
        ]);

        // Guardar el nodo en la base de datos.
        $node->save();
      } catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('Error al procesar la fila @fila: @error', ['@fila' => $rowIndex, '@error' => $e->getMessage()]));
        continue;  // Continuar con la siguiente fila en caso de error
      }
    }

    \Drupal::messenger()->addMessage($this->t('Importación completada.'));
  }


















}
