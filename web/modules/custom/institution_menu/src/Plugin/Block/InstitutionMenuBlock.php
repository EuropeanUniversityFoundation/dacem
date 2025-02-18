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



    /**
     * {@inheritdoc}
     */
    public function build()
    {


        dump('institutio-menu');


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

            // Agrega otros idiomas si es necesario
        ];





        $build = [];
        $node_id = \Drupal::routeMatch()->getParameter('arg_0');


        if (!is_numeric($node_id)) {

            
            // Obtiene la ruta interna asociada al alias
            $path = \Drupal::service('path_alias.manager')->getPathByAlias('/' . $node_id);
        
            // Verifica si la ruta interna es de tipo nodo (/node/{nid})
            if (preg_match('/^\/node\/(\d+)$/', $path, $matches)) {
                $node_id = $matches[1]; // Obtiene el ID del nodo
            } else {
                // Si no es un alias válido, salimos
                dump("No se encontró un nodo para el alias: " . $node_id);
                return [];
            }
            
            //$current_node = \Drupal::routeMatch()->getParameter('node');
            $current_node = \Drupal\node\Entity\Node::load($node_id);
            //dump($current_node);
        } else {
            $current_node = \Drupal\node\Entity\Node::load($node_id);
        }


        //$current_node = \Drupal::routeMatch()->getParameter('node');

        //dump($current_node);
        //dump(\Drupal::routeMatch()->getRouteName());
        //dump(\Drupal::routeMatch()->getParameters()->all());


        if ($current_node instanceof NodeInterface) {
            //dump($current_node);
            dump('if node interface');



            $node_type = $current_node->bundle();
            $institution = null;

            if ($node_type == 'institution') {
                dump('node type insitution');
                $institution = $current_node;
            } elseif ($node_type == 'programme') {
                $institution = $current_node->get('field_programme_institution')->entity;
            } elseif ($node_type == 'individual_educational_component') {
                $programme = $current_node->get('field_iec_programme')->entity;
                if ($programme) {
                    $institution = $programme->get('field_programme_institution')->entity;
                }
            }

        } else {



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

            dump('if not empty institution');


            $logo_url = '';
            $logo_dacem_url = '/themes/custom/b5subtheme/images/logo_dacem.png';

            if (!$institution->get('field_logo')->isEmpty()) {
                $media = $institution->get('field_logo')->entity;
                if ($media && $media->hasField('field_media_image')) {
                    $file = $media->get('field_media_image')->entity;
                    if ($file) {
                        $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
                    }
                }
            }



            if ($institution instanceof NodeInterface && $institution->hasField('field_primary_color') && !$institution->get('field_primary_color')->isEmpty()) {

                $color_value = $institution->get('field_primary_color')->value;

            } else {

            }

            // Obtener el idioma actual
            $language_manager = \Drupal::service('language_manager');
            //$current_language = $language_manager->getCurrentLanguage()->getId();
            $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
            //dump($current_language);

            // Generar la URL de la universidad en el idioma actual
            $institution_url = $institution->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage($current_language)])->toString();

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
                $langcode = $language->getId();
                $abbreviation = strtoupper($langcode); // Convertir el código del idioma a mayúsculas

                //$url = Url::fromRoute('<current>', [], ['language' => $language]);
                $url = Url::fromRoute('<current>', [], ['language' => $language])->toString();

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
            $rs_url = Url::fromRoute('view.resources_and_services.page_1', [
                'arg_0' => $institution->id(),
            ], [
                'language' => \Drupal::languageManager()->getLanguage($current_language),
            ])->toString();







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
        <div class="user-profile-container dropdown ms-3">
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
                // Obtener la traducción de la universidad en el idioma actual, o en su idioma original si no está traducida
                $translated_institution = $entity_repository->getTranslationFromContext($institution, $current_language);
                $institution_title = $translated_institution->getTitle();

                // Generar la URL de la universidad en el idioma actual
                $institution_url = $translated_institution->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage($current_language)])->toString();

                $institution_options .= '<li><a class="dropdown-item" href="' . $institution_url . '">' . $institution_title . '</a></li>';
            }

            $institution_options .= '</ul></div>';



            $build = [
                '#markup' => $this->t('
              <nav class="navbar navbar-expand-lg university-navbar" style="margin: 0; padding: 0;">
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
                                  <a class="nav-link" href="' . $general_info_url . '">' . $translations[$current_language]['INSTITUTIONAL INFORMATION'] . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="' . $catalogue_url . '">' . $translations[$current_language]['CATALOGUE'] . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="' . $rs_url . '">' . $translations[$current_language]['RESOURCES AND SERVICES'] . '</a>
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
              </nav>',
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
