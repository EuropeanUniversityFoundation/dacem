<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class DegreeImport
{

    protected const VALID_AREAS = [
        'Artes y Humanidades' => 'artes_humanidades',
        'Ciencias' => 'ciencias',
        'Ciencias de la salud' => 'ciencias_de_la_salud',
        'Ciencias sociales y jurídicas' => 'ciencias_sociales_y_juridicas',
        'Ingeniería y Arquitectura' => 'ingenieria_y_arquitectura',
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




    public function process($worksheet)
    {
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
          
            foreach ($cellIterator as $cellIndex => $cell) {
              $headerValue = $header[$cellIndex]; // Obtener el nombre de la columna.
              $data[$headerValue] = $cell->getValue(); // Asignar el valor a la clave correspondiente.
            }
          


            try {

                // Asignar cada columna a su respectivo campo.
                $university_name = $data['University'];
                $degree_name = $data['Degree'];
                $language = $data['Language'];
                $presentation = $data['Presentation'];
                $main_objective = $data['Main Objective'];
                $competencies = $data['Competencies'];
                $credits = $data['Credits'];
                $level = $data['Level']; // Campo de lista de texto.
                $modality = $data['Modality']; // Campo de lista de texto.
                $qualification_level = $data['Qualification Level'];
                $study_modality = $data['Study Modality'];
                $external_internships = $data['External Internships']; // Campo de lista de texto.
                $isced_f = $data['ISCED-F'];
                $academic_course = $data['Academic Course'];
                $coordinator = $data['Coordinator'];
                $phone = $data['Phone'];
                $email = $data['Email'];
                $area = $data['Area']; // Campo de lista de texto.
                $qualification = $data['Qualification'];

                // Validar campos de lista de texto.
                if (!isset(self::VALID_AREAS[$area])) {
                    \Drupal::messenger()->addError(\Drupal::translation()->translate('El área @area no es válida.', ['@area' => $area]));
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
                    'field_area' => self::VALID_AREAS[$area],  // Validado
                    'field_cualificacion' => $qualification,
                    'status' => 1,  // Publicado
                ]);

                // Guardar el nodo en la base de datos.
                $node->save();
            } catch (\Exception $e) {
                \Drupal::messenger()->addError(\Drupal::translation()->translate('Error al procesar la fila'));
                continue;  // Continuar con la siguiente fila en caso de error
            }
        }
    }


}
