<?php

namespace Drupal\institution_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;



/**
 * Provides a 'Institution Menu' Block.
 *
 * @Block(
 *   id = "institution_menu_block",
 *   admin_label = @Translation("Institution Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class InstitutionMenuBlock extends BlockBase
{



    protected function getNodeFromAlias($alias, $langcode = NULL)
    {
        $alias_manager = \Drupal::service('path_alias.manager');

        // Obtener el path interno en el idioma deseado
        $internal_path = $alias_manager->getPathByAlias($alias, $langcode);

        if (preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
            $node = \Drupal\node\Entity\Node::load((int) $matches[1]);

            // Verificar si hay una traducción disponible y cargarla
            if ($langcode && $node->hasTranslation($langcode)) {
                return $node->getTranslation($langcode);
            }

            return $node;
        }

        return NULL;
    }


    /**
     * {@inheritdoc}
     */
    public function build()
    {

        $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();


        // Imagen y menú desplegable de usuario
        $current_user = \Drupal::currentUser();
        if ($current_user->isAuthenticated() && $current_user->id() != 0) {
            $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';

            $user = User::load($current_user->id());

            // Verificar si tiene una foto de perfil
            if ($user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
                $file = File::load($user->get('user_picture')->target_id);
                if ($file) {
                    $profile_picture_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
                }
            }

            // Si no hay imagen, asignar una imagen predeterminada
            if (!$profile_picture_url) {
                $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
            }

            // Generar el HTML de la imagen
            $profile_html = '
    <div class="user-profile-container dropdown ms-1">
        <a href="#" id="userProfileDropdown" class="dropdown-toggle user-profile-link" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="' . $profile_picture_url . '" alt="Profile Picture" class="user-profile-circle">
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userProfileDropdown">
            <li><a class="dropdown-item user-menu-item" href="/user">My Profile</a></li>
            <li><a class="dropdown-item user-menu-item" href="/my-area">My Area</a></li>
            <li><a class="dropdown-item user-menu-item" href="/user/logout">Logout</a></li>
        </ul>
    </div>';

        } else {
            // Si el usuario es anónimo, no se renderiza la imagen
            $profile_html = '';
        }


        $translations = [
            'en' => [
                'INSTITUTIONAL INFORMATION' => 'INSTITUTIONAL INFORMATION',
                'CATALOGUE' => 'CATALOGUE',
                'RESOURCES AND SERVICES' => 'RESOURCES AND SERVICES',
                'UNIVERSITY LIFE' => 'UNIVERSITY LIFE',
            ],
            'es' => [
                'INSTITUTIONAL INFORMATION' => 'INFORMACIÓN INSTITUCIONAL',
                'CATALOGUE' => 'CATÁLOGO',
                'RESOURCES AND SERVICES' => 'RECURSOS Y SERVICIOS',
                'UNIVERSITY LIFE' => 'VIDA UNIVERSITARIA',
            ],
            'fr' => [
                'INSTITUTIONAL INFORMATION' => 'INFORMATIONS INSTITUTIONNELLES',
                'CATALOGUE' => 'CATALOGUE',
                'RESOURCES AND SERVICES' => 'RESSOURCES ET SERVICES',
                'UNIVERSITY LIFE' => 'VIE UNIVERSITAIRE',
            ],
            'pt-pt' => [
                'INSTITUTIONAL INFORMATION' => 'INFORMAÇÃO INSTITUCIONAL',
                'CATALOGUE' => 'CATÁLOGO',
                'RESOURCES AND SERVICES' => 'RECURSOS E SERVIÇOS',
                'UNIVERSITY LIFE' => 'VIDA UNIVERSITÁRIA',
            ],
            'gl' => [
                'INSTITUTIONAL INFORMATION' => 'INFORMACIÓN INSTITUCIONAL',
                'CATALOGUE' => 'CATÁLOGO',
                'RESOURCES AND SERVICES' => 'RECURSOS E SERVIZOS',
                'UNIVERSITY LIFE' => 'VIDA UNIVERSITARIA',
            ],
            'hu' => [
                'INSTITUTIONAL INFORMATION' => 'INTÉZMÉNYI INFORMÁCIÓK',
                'CATALOGUE' => 'KATALÓGUS',
                'RESOURCES AND SERVICES' => 'ERŐFORRÁSOK ÉS SZOLGÁLTATÁSOK',
                'UNIVERSITY LIFE' => 'EGYETEMI ÉLET',
            ],
            'sl' => [
                'INSTITUTIONAL INFORMATION' => 'INSTITUCIONALNE INFORMACIJE',
                'CATALOGUE' => 'KATALOG',
                'RESOURCES AND SERVICES' => 'VIRI IN STORITVE',
                'UNIVERSITY LIFE' => 'UNIVERZITETNO ŽIVLJENJE',
            ],
            'et' => [
                'INSTITUTIONAL INFORMATION' => 'ASUTUSE INFO',
                'CATALOGUE' => 'KATALOOG',
                'RESOURCES AND SERVICES' => 'RESSURSID JA TEENUSED',
                'UNIVERSITY LIFE' => 'ÜLIKOOLIELU',
            ],
            'el' => [
                'INSTITUTIONAL INFORMATION' => 'ΘΕΣΜΙΚΕΣ ΠΛΗΡΟΦΟΡΙΕΣ',
                'CATALOGUE' => 'ΚΑΤΑΛΟΓΟΣ',
                'RESOURCES AND SERVICES' => 'ΠΟΡΟΙ ΚΑΙ ΥΠΗΡΕΣΙΕΣ',
                'UNIVERSITY LIFE' => 'ΦΟΙΤΗΤΙΚΗ ΖΩΗ',
            ],


        ];





        $build = [];
        $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

        ////dump(\Drupal::routeMatch()->getParameters());

        $current_path = \Drupal::service('path.current')->getPath();
        ////dump($current_path);
        $current_path_aux = preg_replace('#^/[^/]+/#', '/', $current_path);

        ////dump($current_path_aux);
        $current_node_aux = $this->getNodeFromAlias($current_path_aux, $current_language);
        ////dump($current_node_aux->bundle());


        ////dump(\Drupal::routeMatch()->getParameters());
        $current_route = \Drupal::routeMatch()->getRouteName();
        ////dump($current_route);
        $current_page = '';


        //Volvese obter institution_id. Creo que se pode quitar de eiqui.
        if ($current_route === 'view.general_information.page_1') {
            $current_page = 'general-information';
            // Obtén el ID de la universidad desde el argumento de la URL.
            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');
            $institution = \Drupal\node\Entity\Node::load($institution_id);
        } elseif ($current_route === 'view.institution_catalogue.page_1') {
            $current_page = 'catalogue';
            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');
            if ($institution_id) {
                $institution = \Drupal\node\Entity\Node::load($institution_id);
            }
        } elseif ($current_route === 'view.resources_and_services.page_1' || $current_route === 'view.resources_and_services.page_2') {

            $current_page = 'resources-and-services';
            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

            $institution = \Drupal\node\Entity\Node::load($institution_id);
        } elseif ($current_route === 'view.programme_information.page_1') {
            $current_page = 'catalogue';

            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');
        } elseif ($current_route === 'view.iec_information.page_1') {
            $current_page = 'catalogue';

            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

        } elseif ($current_route === 'view.campus_information.page_1') {
            $current_page = 'campus-information';

            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

        } elseif ($current_route === 'view.organizational_unit_information.page_1') {
            $current_page = 'organizational-unit-information';

            $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

        }





        //dump($current_page);

        /*
        if (!is_numeric($institution_id)) {

            $url_aux = null;

            if(\Drupal::routeMatch()->getParameter('arg_2') != null){
                $url_aux = '/'. $current_language . '/' . \Drupal::routeMatch()->getParameter('arg_0') . '/' . \Drupal::routeMatch()->getParameter('arg_1') . '/' .\Drupal::routeMatch()->getParameter('arg_2');

            }else if(\Drupal::routeMatch()->getParameter('arg_1') != null){
                ////dump('asñdkfjañslkdfjañsdklfj');
                $url_aux = '/'. $current_language . '/' . \Drupal::routeMatch()->getParameter('arg_0') . '/' . \Drupal::routeMatch()->getParameter('arg_1');


            }else if(\Drupal::routeMatch()->getParameter('arg_0') != null){
                $url_aux = '/'. $current_language . '/' . \Drupal::routeMatch()->getParameter('arg_0');


            }
            ////dump('url_aux auuuux');
            ////dump($url_aux);

            ////dump($current_language);
            // Obtiene la ruta interna asociada al alias
            $path = \Drupal::service('path_alias.manager')->getPathByAlias('/' . $institution_id, $current_language);


            // Verifica si la ruta interna es de tipo nodo (/node/{nid})
            if (preg_match('/^\/node\/(\d+)$/', $path, $matches)) {
                $institution_id = $matches[1]; // Obtiene el ID del nodo
            } else {
                // Si no es un alias válido, salimos
                ////dump("No se encontró un nodo para el alias: " . $institution_id);
                return [];
            }

            //$current_institution = \Drupal::routeMatch()->getParameter('node');
            $current_institution = \Drupal\node\Entity\Node::load($institution_id);
            //////dump($current_institution);
        } else {
            $current_institution = \Drupal\node\Entity\Node::load($institution_id);
        }*/


        //$current_institution = \Drupal::routeMatch()->getParameter('node');

        //////dump($current_institution);
        //////dump(\Drupal::routeMatch()->getRouteName());
        //////dump(\Drupal::routeMatch()->getParameters()->all());


        if ($current_node_aux instanceof NodeInterface) {

            //////dump($current_institution);

            $node_type = $current_node_aux->bundle();

            $institution = null;

            if ($node_type == 'institution') {

                $institution = $current_node_aux;
            } else if ($node_type == 'campus') {
                $institution = $current_node_aux->get('field_campus_institution')->entity;

            } else if ($node_type == 'organizational_unit') {
                $institution = $current_node_aux->get('field_ou_institution')->entity;

            } elseif ($node_type == 'programme') {
                $institution = $current_node_aux->get('field_programme_institution')->entity;
            } elseif ($node_type == 'individual_educational_component') {
                $programme = $current_node_aux->get('field_iec_programme')->entity;
                if ($programme) {
                    $institution = $programme->get('field_programme_institution')->entity;
                }
            }

        } else {

            ///////
            //////ELIMINAR POSIBLEMENTE ISTE ELSE
            //////

            $current_route = \Drupal::routeMatch()->getRouteName();


            if ($current_route === 'view.general_information.page_1') {
                // Obtén el ID de la universidad desde el argumento de la URL.
                $institution_id = \Drupal::routeMatch()->getParameter('arg_0');
                $institution = \Drupal\node\Entity\Node::load($institution_id);
            } elseif ($current_route === 'view.institution_catalogue.page_1') {
                // Si la ruta es de la vista, obtén la universidad desde el argumento.
                $institution_id = \Drupal::routeMatch()->getParameter('arg_0');
                if ($institution_id) {
                    $institution = \Drupal\node\Entity\Node::load($institution_id);
                }
            } elseif ($current_route === 'view.resources_and_services.page_1') {
                $institution_id = \Drupal::routeMatch()->getParameter('arg_0');

                $institution = \Drupal\node\Entity\Node::load($institution_id);
            }

        }



        if (!empty($institution)) {

            ////dump('if not empty institution');
            $logo_url = '';
            $logo_dacem_url = '/themes/custom/b5subtheme/images/dacem-imago.png';

            if (!$institution->get('field_logo')->isEmpty()) {
                $media = $institution->get('field_logo')->entity;
                if ($media && $media->hasField('field_media_image')) {
                    $file = $media->get('field_media_image')->entity;
                    if ($file) {
                        $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
                    }
                }
            }


            /*
            if ($institution instanceof NodeInterface && $institution->hasField('field_primary_color') && !$institution->get('field_primary_color')->isEmpty()) {

                $color_value = $institution->get('field_primary_color')->value;

            } else {

            }
            */


            // Obtener el idioma actual
            $language_manager = \Drupal::service('language_manager');
            //$current_language = $language_manager->getCurrentLanguage()->getId();
            $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
            //////dump($current_language);

            // Generar la URL de la universidad en el idioma actual
            $institution_url = $institution->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage($current_language)])->toString();
            ////dump($institution_url);
            // Generar URLs de cambio de idioma
            $languages = $language_manager->getLanguages();
            $language_options = '';

            $flags = [
                'en' => '🇬🇧', // Inglés
                'es' => '🇪🇸', // Español
                'pt-pt' => '🇵🇹', // Portugués
                'fr' => '🇫🇷', // Francés
                'el' => '🇬🇷', // Griego
                'cs' => '🇨🇿', // Checo
                'sl' => '🇸🇮', // Esloveno
                'hu' => '🇭🇺', // Húngaro
                'et' => '🇪🇪', // Estonio
                'gl' => '🇪🇸', // Gallego
            ];

            foreach ($languages as $language) {


                $alias_manager = \Drupal::service('path_alias.manager');

                // Obtener el alias de la institución en el idioma actual
                //$institution_alias = $alias_manager->getAliasByPath('/node/' . $current_institution->id(), $language->getId());
                ////dump($institution_alias);
                $language_prefix = '/' . $language->getId();

                // Asegurar que el alias no contenga el prefijo del idioma duplicado

                $langcode = $language->getId();
                //////dump($langcode);
                $abbreviation = strtoupper($langcode); // Convertir el código del idioma a mayúsculas

                //$url = Url::fromRoute('<current>', [], ['language' => $language]);
                //$url = Url::fromRoute('<current>', [], ['language' => $language])->toString();
                $url = '';
                ////dump($current_page);
                if ($current_node_aux->hasTranslation($language->getId())) {
                    $url = '/' . $langcode . '/' . $current_page . $alias_manager->getAliasByPath('/node/' . $current_node_aux->id(), $language->getId());
                } else {
                    $url = '/en/' . $current_page . $alias_manager->getAliasByPath('/node/' . $current_node_aux->id(), 'en');
                }

                //$url = '/' . $langcode . '/catalogue' .  $alias_manager->getAliasByPath('/node/' . $current_node_aux->id(), $language->getId());
                ////dump($url);
                ////dump($url);
                $flag = $flags[$langcode] ?? ''; // Asegurarse de tener un icono

                $language_options .= '
              <li>
                <a class="dropdown-item" href="' . $url . '">' . $flag . ' ' . $abbreviation . '</a>
              </li>';

            }


            // Genera la URL con el idioma activo.
            $general_info_url = Url::fromRoute('view.general_information.page_1', [
                'arg_0' => $institution->id(),
            ], [
                'language' => \Drupal::languageManager()->getLanguage($current_language),
            ])->toString();


            $alias_manager = \Drupal::service('path_alias.manager');


            // Obtener el alias de la institución en el idioma actual
            $institution_alias = $alias_manager->getAliasByPath('/node/' . $institution->id(), $current_language);

            //dump($institution_alias);
            // Obtener el prefijo de idioma actual (ejemplo: "/en" o "/es")
            $language_prefix = '/' . $current_language;

            // Asegurar que el alias no contenga el prefijo del idioma duplicado
            if (strpos($institution_alias, $language_prefix) === 0) {
                $institution_alias = substr($institution_alias, strlen($language_prefix));
            }

            // Construir la URL con el formato correcto
            $catalogue_url = $language_prefix . '/catalogue' . $institution_alias;

            $general_info_url = $language_prefix . '/general-information' . $institution_alias;


            // Genera la URL con el idioma activo.
            /*$rs_url = Url::fromRoute('view.resources_and_services.page_1', [
                'arg_0' => $institution->id(),
            ], [
                'language' => \Drupal::languageManager()->getLanguage($current_language),
            ])->toString();*/

            $rs_url = $language_prefix . '/resources-and-services' . $institution_alias;


            $institutions = \Drupal::entityTypeManager()
                ->getStorage('node')
                ->loadByProperties(['type' => 'institution']);

            $entity_repository = \Drupal::service('entity.repository');

            // Obtener la universidad actual traducida correctamente
            $translated_institution = $entity_repository->getTranslationFromContext($institution, $current_language);
            $current_institution_name = $translated_institution->getTitle();

            $institution_options = '<div class="institution-dropdown">
    <a href="#" id="institutionDropdown" class="institution-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        ' . $current_institution_name . ' <i class="fas fa-chevron-down"></i>
    </a>
    <ul class="dropdown-menu" aria-labelledby="institutionDropdown">';

            foreach ($institutions as $institution) {
                // Verificar si la institución tiene traducción al idioma actual
                if ($institution->hasTranslation($current_language)) {
                    $translated_institution = $institution->getTranslation($current_language);
                    $used_language = $current_language; // Se usa la traducción en el idioma seleccionado
                } else {
                    $translated_institution = $institution->getTranslation('en'); // Usar la traducción en inglés
                    $used_language = 'en'; // Se usa inglés como fallback
                }

                $institution_title = $translated_institution->getTitle();
                $institution_alias = $alias_manager->getAliasByPath('/node/' . $translated_institution->id(), $used_language);

                // Generar la URL con el idioma correcto
                $institution_url = '/' . $used_language . '/general-information' . $institution_alias;

                $institution_options .= '<li><a class="dropdown-item" href="' . $institution_url . '">' . $institution_title . '</a></li>';
            }


            $institution_options .= '</ul></div>';

            // Decide qué enlace va activo según $current_page.
            $gi_active = (!in_array($current_page, ['catalogue', 'resources-and-services'])) ? ' active ' : '';
            $cat_active = ($current_page === 'catalogue') ? ' active ' : '';
            $rs_active = ($current_page === 'resources-and-services') ? ' active ' : '';

            // Por accesibilidad, aria-current="page" en el activo.
            $gi_aria = $gi_active ? ' aria-current="page"' : '';
            $cat_aria = $cat_active ? ' aria-current="page"' : '';
            $rs_aria = $rs_active ? ' aria-current="page"' : '';

            $build = [
                '#markup' => $this->t('

    <nav class="navbar navbar-expand-lg university-navbar main-menu-text" style="margin: 0; padding: 0;">
      <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand" href="/main-page">
          <img src="@logo_dacem_url" alt="@dacem" class="university-logo d-inline-block align-text-top">
        </a>

        <!-- Dropdown de universidades -->
        <div class="dropdown">
          ' . $institution_options . '
        </div>

        <!-- Botón de colapso para móviles -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menú colapsable -->
        <div class="collapse navbar-collapse" id="universityNavbar">
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link' . $gi_active . '" href="' . $general_info_url . '"' . $gi_aria . '>' . $translations[$current_language]['INSTITUTIONAL INFORMATION'] . '</a>
            </li>
            
            <li class="nav-item">
              <a class="nav-link' . $cat_active . '" href="' . $catalogue_url . '"' . $cat_aria . '>' . $translations[$current_language]['CATALOGUE'] . '</a>
            </li>
            <li class="nav-item">
              <a class="nav-link' . $rs_active . '" href="' . $rs_url . '"' . $rs_aria . '>' . $translations[$current_language]['RESOURCES AND SERVICES'] . '</a>
            </li>
          </ul>

          <!-- Botón personalizado -->
          <div class="d-flex align-items-center right-buttons-university-menu">
            <!-- Botones de idioma -->
            <div class="language-buttons" style="position: relative; z-index: 1050;">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle no-hover-bg" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    ' . strtoupper($current_language) . ' ' . $flags[$current_language] . '
                  </a>
                  <ul class="dropdown-menu" style="z-index: 1051;" aria-labelledby="languageDropdown">
                    ' . $language_options . '
                  </ul>
                </li>
              </ul>
            </div>
            ' . $profile_html . '
          </div>
        </div>
      </div>
    </nav>
    
  </div>',
                    [
                        '@logo_dacem_url' => $logo_dacem_url,
                        '@logo_url' => $logo_url,
                        '@university_name' => $institution->getTitle(),
                        '@university_path' => $institution->toUrl()->getInternalPath(),
                        '@university_url' => $institution_url,
                    ]
                ),
            ];





        }

        return $build;
    }
}
