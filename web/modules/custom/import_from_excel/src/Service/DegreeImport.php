<?php

namespace Drupal\import_from_excel\Service;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\GroupTypeInterface;
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
        $query = \Drupal::entityQuery('node')
            ->condition('type', 'universidad')
            ->condition('title', $university_name)
            ->accessCheck(FALSE)
            ->range(0, 1);

        $nids = $query->execute();
        if (!empty($nids)) {
            $nid = reset($nids);
            return \Drupal\node\Entity\Node::load($nid);
        }
        return NULL;
    }

    protected function getDegreeByName($degree_name, $university_id)
    {
        $query = \Drupal::entityQuery('node')
            ->condition('type', 'carrera')
            ->condition('field_universidad', $university_id)
            ->condition('title', $degree_name)
            ->accessCheck(FALSE)
            ->range(0, 1);

        $nids = $query->execute();
        if (!empty($nids)) {
            $nid = reset($nids);
            return \Drupal\node\Entity\Node::load($nid);
        }
        return NULL;
    }

    public function process($worksheet)
    {

        \Drupal::messenger()->addMessage('Process de Degree Import');
        $header = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            if ($rowIndex == 1) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                foreach ($cellIterator as $cell) {
                    $header[] = $cell->getValue();
                }
                continue;
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(FALSE);
            $data = [];
            $headerCount = 0;

            foreach ($cellIterator as $cell) {
                if (isset($header[$headerCount])) {
                    $headerValue = $header[$headerCount];
                    $data[$headerValue] = $cell->getValue();
                }
                $headerCount++;
            }

            if (!isset($data['University'])) {
                \Drupal::messenger()->addError(t('Degreeeee La fila no contiene los datos requeridos.'));
                continue;
            }

            try {
                $university_name = $data['University'];
                $degree_name = $data['Degree'];
                $university = $this->getUniversityByName($university_name);

                // Obtener el grupo de la universidad.
                $group_name = 'Group ' . $university_name;
                $query = \Drupal::entityQuery('group')
                    ->condition('label', $group_name)
                    ->condition('type', 'universitytypegroup')
                    ->accessCheck(FALSE);

                $group_ids = $query->execute();
                if (empty($group_ids)) {
                    \Drupal::messenger()->addError('No se encontró ningún grupo con ese nombre.');
                    continue;
                }

                $group_id = reset($group_ids);
                $group = Group::load($group_id);
                if (!$group) {
                    \Drupal::messenger()->addError('Error al cargar el grupo.');
                    continue;
                }

                // Verificar si el usuario tiene permisos en el grupo.
                $current_user = \Drupal::currentUser();
                $user = User::load($current_user->id());
                $membership = $group->getMember($user);


                /*
                $roles = $membership->getRoles();
                foreach ($roles as $role) {
                    // Obtén los permisos asociados a este rol en el grupo.
                    $role_permissions = $role->getPermissions();
                    \Drupal::messenger()->addMessage('Permisos para el rol ' . $role->label() . ':');
                    foreach ($role_permissions as $permission) {
                        \Drupal::messenger()->addMessage($permission);
                    }
                }
                */


                if ($membership) {
                    // Obtener el ID del miembro y los roles que tiene en el grupo.
                    $user_id = $membership->getUser()->id();
                    $roles = $membership->getRoles();

                    // Convertir los roles a una cadena de texto para imprimir.
                    $roles_list = [];
                    foreach ($roles as $role) {
                        $roles_list[] = $role->label();
                    }

                    // Imprimir información sobre el usuario y sus roles.
                    \Drupal::messenger()->addMessage('Usuario ID: ' . $user_id);
                    \Drupal::messenger()->addMessage('Roles en el grupo: ' . implode(', ', $roles_list));
                } else {
                    \Drupal::messenger()->addMessage('El usuario no pertenece a este grupo.');
                }





                if (!$membership) {
                    \Drupal::messenger()->addError('Degree1 No tienes permisos para crear o editar carreras en esta universidad.');
                    continue;
                }

                if ($membership->hasPermission('create group_node:carrera entity') || $membership->hasPermission('update own group_node:carrera entity')) {

                    // Verificar si la carrera ya existe.
                    $degree = $this->getDegreeByName($degree_name, $university->id());

                    if ($degree) {
                        if ($degree->getOwnerId() == $current_user->id() || $membership->hasPermission('update any group_node:carrera entity')) {
                        $this->updateDegree($degree, $data);
                        }else{
                            \Drupal::messenger()->addError('No tienes permiso para editar esta carrera.');
                        }
                
                    } else {
                        if ($membership->hasPermission('create group_node:carrera entity')) {
                            $this->createNewDegree($degree_name, $university, $data, $group);
                        }else{
                                \Drupal::messenger()->addError('No tienes permiso para crear carreras en esta universidad.');
                            }
                    }
                } else {
                    \Drupal::messenger()->addError('Degree2 No tienes permiso para crear o editar carreras en esta universidad.');
                }
            } catch (\Exception $e) {
                \Drupal::messenger()->addError(t('Error al procesar la fila, error: @e', ['@e' => $e->getMessage()]));
                continue;
            }
        }
    }

    protected function updateDegree($degree, $data)
    {
        // Actualizar los campos de la carrera.
        $degree->set('field_presentacion', $data['Presentation']);
        $degree->set('field_objetivo_principal', $data['Main Objective']);
        $degree->set('field_competencias', $data['Competencies']);
        $degree->set('field_creditos_carrera', $data['Credits']);
        $degree->set('field_nivel', strtolower($data['Level']));
        $degree->set('field_modalidad', strtolower($data['Modality']));
        $degree->set('field_nivel_de_cualificacion', $data['Qualification Level']);
        $degree->set('field_modalidad_de_estudio', $data['Study Modality']);
        $degree->set('field_practicas_profesionales', strtolower($data['External Internships']));
        $degree->set('field_isced_f', $data['ISCED-F']);
        $degree->set('field_curso_academico', $data['Academic Course']);
        $degree->set('field_coordinador', $data['Coordinator']);
        $degree->set('field_telefono', $data['Phone']);
        $degree->set('field_email', $data['Email']);
        $degree->set('field_area', self::VALID_AREAS[$data['Area']]);
        $degree->set('field_cualificacion', $data['Qualification']);
        $degree->save();

        \Drupal::messenger()->addMessage('Carrera actualizada con éxito.');
    }

    protected function createNewDegree($degree_name, $university, $data, $group)
    {
        $degree = Node::create([
            'type' => 'carrera',
            'title' => $degree_name,
            'field_universidad' => ['target_id' => $university->id()],
            'field_presentacion' => $data['Presentation'],
            'field_objetivo_principal' => $data['Main Objective'],
            'field_competencias' => $data['Competencies'],
            'field_creditos_carrera' => $data['Credits'],
            'field_nivel' => strtolower($data['Level']),
            'field_modalidad' => strtolower($data['Modality']),
            'field_nivel_de_cualificacion' => $data['Qualification Level'],
            'field_modalidad_de_estudio' => $data['Study Modality'],
            'field_practicas_profesionales' => strtolower($data['External Internships']),
            'field_isced_f' => $data['ISCED-F'],
            'field_curso_academico' => $data['Academic Course'],
            'field_coordinador' => $data['Coordinator'],
            'field_telefono' => $data['Phone'],
            'field_email' => $data['Email'],
            'field_area' => self::VALID_AREAS[$data['Area']],
            'field_cualificacion' => $data['Qualification'],
            'status' => 1,
        ]);

        $degree->save();

        // Crear el administrador de la carrera.
        $admin_email = $data['Admin Email'];
        $admin_username = $data['Admin Username'];
        $admin_password = $data['Admin Password'];

        $admin_user = User::create([
            'name' => $admin_username,
            'mail' => $admin_email,
            'pass' => $admin_password,
            'status' => 1,
        ]);

        $admin_user->save();
        $degree->setOwner($admin_user);
        $degree->save();

        // Relacionar la carrera con el grupo de la universidad.
        $group->addRelationship($degree, 'group_node:carrera');

        // Añadir el administrador de la carrera al grupo con el rol correspondiente.
        $group->addMember($admin_user, ['group_roles' => ['universitytypegroup-degree_admin']]);

        \Drupal::messenger()->addMessage('Carrera y administrador creados con éxito.');
    }
}






















































/*



<?php

namespace Drupal\import_from_excel\Service;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
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

                /*
                if (!isset(self::VALID_AREAS[$area])) {
                    \Drupal::messenger()->addError(\Drupal::translation()->translate('El área @area no es válida.', ['@area' => $area]));
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



$university = $this->getUniversityByName($university_name);
$degree = $this->getDegreeByName($degree_name, $university->id());

if ($degree) {

    $degree->set('field_presentacion', $presentation);
    $degree->set('field_objetivo_principal', $main_objective);
    $degree->set('field_competencias', $competencies);
    $degree->set('field_creditos_carrera', $credits);
    $degree->set('field_nivel', strtolower($level));  // Validado
    $degree->set('field_modalidad', strtolower($modality));  // Validado
    $degree->set('field_nivel_de_cualificacion', $qualification_level);
    $degree->set('field_modalidad_de_estudio', $study_modality);
    $degree->set('field_practicas_profesionales', strtolower($external_internships));
    $degree->set('field_isced_f', $isced_f);
    $degree->set('field_curso_academico', $academic_course);
    $degree->set('field_coordinador', $coordinator);
    $degree->set('field_telefono', $phone);
    $degree->set('field_email', $email);
    $degree->set('field_area', self::VALID_AREAS[$area]);
    $degree->set('field_cualificacion', $qualification);

    // Guardar los cambios.
    $degree->save();
    \Drupal::messenger()->addMessage('Degree actualizada con éxito.');

} else {


    \Drupal::messenger()->addMessage('Procedemos a creacion de carreiras.');
    // Crear la entidad "Carrera" (suponiendo que es de tipo "node").
    $degree = Node::create([
        'type' => 'carrera',
        'title' => $degree_name,
        'field_universidad' => ['target_id' => $university->id()],  // Referencia a la universidad.
        'field_presentacion' => $presentation,
        'field_objetivo_principal' => $main_objective,
        'field_competencias' => $competencies,
        'field_creditos_carrera' => $credits,
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
    $degree->save();

    $admin_email = $data['Admin Email'];
    $admin_username = $data['Admin Username'];
    $admin_password = $data['Admin Password'];

    // Crear el usuario administrador de la universidad.
    $admin_user = User::create([
        'name' => $admin_username,
        'mail' => $admin_email,
        'pass' => $admin_password,
        'status' => 1,
    ]);

    // Guardar el usuario y asignarlo como propietario de la universidad.
    $admin_user->save();
    $degree->setOwner($admin_user);
    $degree->save();


    // Nombre del grupo que estás buscando.
    $group_name = 'Group ' . $university_name;  // Nombre del grupo.
    \Drupal::messenger()->addMessage($group_name);


    // Cargar los grupos que coincidan con el nombre y el tipo de grupo.
    $query = \Drupal::entityQuery('group')
        ->condition('label', $group_name)
        ->condition('type', 'universitytypegroup')  // Asegurarse de que es el tipo de grupo correcto.
        ->accessCheck(FALSE); // Añadir accessCheck.
    $group_ids = $query->execute();
    
    \Drupal::messenger()->addMessage($group_ids);
    $group = NULL;

    // Si encontramos algún grupo.
    if (!empty($group_ids)) {
        $group_id = reset($group_ids);  // Obtener el primer grupo encontrado.
        $group = Group::load($group_id);

        if ($group) {
            \Drupal::messenger()->addMessage('El grupo "' . $group->label() . '" fue encontrado.');
        }
    } else {
        \Drupal::messenger()->addError('No se encontró ningún grupo con ese nombre.');
    }


    $group->addRelationship($degree, 'group_node:carrera');

    // Añadir el administrador al grupo con el rol correspondiente.
    $group->addMember($admin_user, ['group_roles' => ['universitytypegroup-degree_admin']]);
    $group->save();




    \Drupal::messenger()->addMessage('Carrera y grupo creados con éxito.');



}


} catch (\Exception $e) {
\Drupal::messenger()->addError(\Drupal::translation()->translate('Error al procesar la fila, error: @e', ['@e' => $e]));
continue;  // Continuar con la siguiente fila en caso de error
}
}
}


}

*/