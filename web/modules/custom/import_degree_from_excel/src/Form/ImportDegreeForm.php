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
    $form['archivo_excel'] = [
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
    $file = file_save_upload('archivo_excel', [
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
        $this->procesarExcel($file->getFileUri());
      } else {
        \Drupal::messenger()->addError($this->t('Error: No se pudo subir el archivo correctamente.'));
      }
    } else {
      \Drupal::messenger()->addError($this->t('Error: No se seleccionó ningún archivo o el archivo no es válido.'));
    }
  }
  


  /**
   * Procesar el archivo Excel y crear las entidades de carreras.
   */
  protected function procesarExcel($ruta_archivo) {
    // Convertir la ruta Drupal (e.g., "temporary://") a una ruta real del sistema de archivos.
    $ruta_real_archivo = \Drupal::service('file_system')->realpath($ruta_archivo);

    // Verificar si el archivo existe.
    if (!file_exists($ruta_real_archivo)) {
      \Drupal::messenger()->addError($this->t('El archivo no existe en la ruta: @ruta', ['@ruta' => $ruta_real_archivo]));
      return;
    }

    try {
      // Cargar el archivo Excel utilizando PhpSpreadsheet.
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta_real_archivo);
      $worksheet = $spreadsheet->getActiveSheet();
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
      \Drupal::messenger()->addError($this->t('Error al cargar el archivo Excel: @message', ['@message' => $e->getMessage()]));
      return;
    }

    // Validación de campos de lista de texto.
    $areas_validas = [
      'Ingeniería y Arquitectura' => 'Ingeniería y Arquitectura',
    ];
    
    $modalidades_validas = [
      'Presencial' => 'Presencial',
      'Online' => 'Online', // Si hay otras modalidades, añádelas aquí.
    ];

    $niveles_validos = [
      'Grado' => 'Grado',
      'Máster' => 'Máster', // Otros niveles si los tienes.
    ];

    $practicas_profesionales_validas = [
      'Sí' => 'Sí',
      'No' => 'No',
    ];

    // Iterar sobre las filas del Excel para extraer datos.
    foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
      // Saltar la primera fila (nombres de columnas).
      if ($rowIndex == 1) {
        continue;
      }

      $cellIterator = $row->getCellIterator();
      $cellIterator->setIterateOnlyExistingCells(FALSE);
      $datos = [];

      foreach ($cellIterator as $cellIndex => $cell) {
        $datos[] = $cell->getValue();
      }

      // Asignar cada columna a su respectivo campo.
      $nombre_universidad = $datos[0];
      $nombre_carrera = $datos[1];
      $idioma = $datos[2];
      $presentacion = $datos[3];
      $objetivo_principal = $datos[4];
      $competencias = $datos[5];
      $creditos = $datos[6];
      $nivel = $datos[7]; // Campo de lista de texto.
      $modalidad = $datos[8]; // Campo de lista de texto.
      $nivel_cualificacion = $datos[9];
      $modalidad_estudio = $datos[10];
      $practicas_profesionales = $datos[11]; // Campo de lista de texto.
      $isced_f = $datos[12];
      $curso_academico = $datos[13];
      $coordinador = $datos[14];
      $telefono = $datos[15];
      $email = $datos[16];
      $area = $datos[17]; // Campo de lista de texto.
      $cualificacion = $datos[18];

      // Validar campos de lista de texto.
      if (!isset($areas_validas[$area])) {
        \Drupal::messenger()->addError($this->t('El área @area no es válida.', ['@area' => $area]));
        continue;
      }
      if (!isset($modalidades_validas[$modalidad])) {
        \Drupal::messenger()->addError($this->t('La modalidad @modalidad no es válida.', ['@modalidad' => $modalidad]));
        continue;
      }
      if (!isset($niveles_validos[$nivel])) {
        \Drupal::messenger()->addError($this->t('El nivel @nivel no es válido.', ['@nivel' => $nivel]));
        continue;
      }
      if (!isset($practicas_profesionales_validas[$practicas_profesionales])) {
        \Drupal::messenger()->addError($this->t('El valor de prácticas profesionales @practicas_profesionales no es válido.', ['@practicas_profesionales' => $practicas_profesionales]));
        continue;
      }

      // Crear la entidad "Carrera" (suponiendo que es de tipo "node").
      $node = Node::create([
        'type' => 'carrera',
        'title' => $nombre_carrera,
        'field_universidad' => $nombre_universidad,
        'field_idioma' => $idioma,
        'field_presentacion' => $presentacion,
        'field_objetivo_principal' => $objetivo_principal,
        'field_competencias' => $competencias,
        'field_creditos' => $creditos,
        'field_nivel' => $niveles_validos[$nivel],  // Validado
        'field_modalidad' => $modalidades_validas[$modalidad],  // Validado
        'field_nivel_cualificacion' => $nivel_cualificacion,
        'field_modalidad_estudio' => $modalidad_estudio,
        'field_practicas_profesionales' => $practicas_profesionales_validas[$practicas_profesionales],  // Validado
        'field_isced_f' => $isced_f,
        'field_curso_academico' => $curso_academico,
        'field_coordinador' => $coordinador,
        'field_telefono' => $telefono,
        'field_email' => $email,
        'field_area' => $areas_validas[$area],  // Validado
        'field_cualificacion' => $cualificacion,
        'status' => 1,  // Publicado
      ]);

      // Guardar el nodo en la base de datos.
      $node->save();
    }

    \Drupal::messenger()->addMessage($this->t('Importación completada.'));
  }
}
