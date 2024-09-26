<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;

class SubjectImport
{


  // Validación de campos de lista de texto.
  const VALID_COURSES_QUARTERS = [
    '1º' => '1o',
    '2º' => '2o',
    '3º' => '3o',
    '4º' => '4o',

  ];




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

  protected function getSubjectByName($subject_name, $degree_id)
  {

    // Crear una consulta para buscar la universidad por su nombre (título).
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'asignatura')
      ->condition('field_carrera', value: $degree_id)
      ->condition('title', $subject_name)  // Buscar por título.
      ->accessCheck(FALSE)  // No verificar permisos de acceso.
      ->range(0, 1);  // Limitar la búsqueda a un resultado.

    $nids = $query->execute();  // Ejecutar la consulta.
    // Si encontramos la asignatura, devolver su ID.
    if (!empty($nids)) {
      $nid = reset($nids);
      return \Drupal\node\Entity\Node::load($nid);  // Devolver el nodo de la asignatura.
    }

    // Si no se encuentra la asignatura, devolver NULL.
    return NULL;
  }


  public function process($worksheet)
  {

    $header = [];  // Inicializar el array de encabezados.

    foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
      // Saltar la primera fila (encabezados).
      if ($rowIndex == 1) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);
        foreach ($cellIterator as $cell) {
          $header[] = $cell->getValue(); // Almacenar los encabezados.
        }
        continue; // Saltar a la siguiente fila.
      }

      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $data = [];

      $headerCount = 0; // Contador para coincidir con las columnas del encabezado.

      foreach ($cellIterator as $cell) {
        if (isset($header[$headerCount])) {
          $headerValue = $header[$headerCount]; // Obtener el nombre de la columna.
          $data[$headerValue] = $cell->getValue(); // Asignar el valor a la clave correspondiente.
        }
        $headerCount++;
      }

      // Comprobar si los encabezados esperados están presentes en la fila.
      if (!isset($data['University'])) {
        \Drupal::messenger()->addError(t('La fila no contiene los datos requeridos.'));
        continue;
      }


      try {




        // Asignar cada columna a su respectivo campo.
        $university_name = $data['University'];
        $degree_name = $data['Degree'];
        $subject_name = $data['Subject'];
        $credits = $data['Credits'];
        $quarter = $data['Quarter'];
        $course = $data['Course'];
        $code = $data['Code'];
        $requirements = $data['Requirements']; // Campo de lista de texto.
        $contents = $data['Subject Contents']; // Campo de lista de texto.
        $evaluation = $data['Subject Evaluation'];
        $instructors = $data['Subject Instructors'];
        $introduction = $data['Subject Introduction']; // Campo de lista de texto.
        $language = $data['Subject Language'];
        $learning_outcomes = $data['Subject Learning Outcomes'];
        $modality = $data['Subject Modality'];
        $planned_activities = $data['Subject Planned Activities'];
        $recommendations = $data['Subject Recommendations'];
        $type = strtolower($data['Type']); // Campo de lista de texto.

        /*
        // Validar campos de lista de texto.
        if (!isset($valid_courses_quarters[$course]) && !isset($valid_courses_quarters[$quarter])) {
          \Drupal::messenger()->addError(\Drupal::translation()->translate('El curso @curso o el cuatrimestre @cuatrimestre no son válidos.', ['@curso' => $course, 'cuatrimestre' => $quarter]));
          continue;
        }
        */

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
        $subject = $this->getSubjectByName($subject_name, $degree->id());


        if ($subject) {

          
          \Drupal::messenger()->addMessage(\Drupal::translation()->translate('Quarter @quarter, Course @course', ['@quarter'=>self::VALID_COURSES_QUARTERS[$quarter], '@course'=>self::VALID_COURSES_QUARTERS[$course]]));

          $subject->set('field_creditos', $credits);
          $subject->set('field_cuatrimestre', self::VALID_COURSES_QUARTERS[$quarter]);
          $subject->set('field_curso', self::VALID_COURSES_QUARTERS[$course]);
          $subject->set('field_codigo', $code);
          $subject->set('field_requirements', $requirements);  // Validado
          $subject->set('field_subject_contents', $contents);  // Validado
          $subject->set('field_subject_evaluation', $evaluation);
          $subject->set('field_subject_instructors', $instructors);
          $subject->set('field_subject_introduction', $introduction);
          $subject->set('field_subject_language', $language);
          $subject->set('field_subject_learning_outcomes', $learning_outcomes);
          $subject->set('field_subject_modality', $modality);
          $subject->set('field_subject_planned_activities', $planned_activities);
          $subject->set('field_subject_recommendations', $recommendations);
          $subject->set('field_tipo', $type);

          $subject->save();

          
        } else {
          // Crear la entidad "Aignatura" (suponiendo que es de tipo "node").
          $node = Node::create(values: [
            'type' => 'asignatura',
            'title' => $subject_name,
            'field_carrera' => ['target_id' => $degree->id()],  // Referencia a la carrera.
            'field_creditos' => $credits,
            'field_cuatrimestre' => self::VALID_COURSES_QUARTERS[$quarter],
            'field_curso' => self::VALID_COURSES_QUARTERS[$course],
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
            'field_subject_recommendations' => $recommendations,
            'field_tipo' => $type,
            'status' => 1,  // Publicado
          ]);

          // Guardar el nodo en la base de datos.
          $node->save();

        }



      } catch (\Exception $e) {
        \Drupal::messenger()->addError(\Drupal::translation()->translate('Error al procesar la fila'));
        continue;  // Continuar con la siguiente fila en caso de error
      }

    }


  }
}



