<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;

class UniversityImport
{

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
    
      $university_name = $data['University']; // Acceder a la columna "Universidad".
      
      // Hacer lo que necesites con los valores.
      \Drupal::messenger()->addMessage(t('Procesando la universidad: @university', [
        '@university' => $university_name,
      ]));

      try {
        
        // Crear la entidad "Carrera" (suponiendo que es de tipo "node").
        $node = Node::create([
          'type' => 'universidad',
          'title' => $university_name,
          
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
