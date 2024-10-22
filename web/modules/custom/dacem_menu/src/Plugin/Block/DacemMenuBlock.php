<?php

namespace Drupal\dacem_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Provides a 'Dacem Menu' Block.
 *
 * @Block(
 *   id = "dacem_menu_block",
 *   admin_label = @Translation("Dacem Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class DacemMenuBlock extends BlockBase
{

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        
        $route_name = \Drupal::routeMatch()->getRouteName();

        // Comprobar si estamos en la vista que muestra las universidades.
        // Cambia "view.universities.page_1" por el nombre de tu vista y display.
        if ($route_name == 'view.lista_de_universidades.page_1') {

            $build = [];

            $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
            $logo_url = base_path() . $theme_path . '/images/logo-dacem.jpg';

            // Obtener el idioma actual
            $language_manager = \Drupal::service('language_manager');

            // Generar URLs de cambio de idioma
            $languages = $language_manager->getLanguages();
            $switch_links = [
                'es' => '',
                'en-gb' => ''
            ];

            foreach ($languages as $language) {
                $langcode = $language->getId();
                if (isset($switch_links[$langcode])) {
                    $url = Url::fromRoute('<current>', [], ['language' => $language]);
                    $switch_links[$langcode] = $url->toString();
                }
            }

            $build = [
                '#markup' => $this->t('
              <nav class="navbar navbar-expand-lg university-navbar">
                  <div class="container-fluid">
                      <!-- Logo del Menú personalizado (Imagen proporcionada) -->
                      <a class="navbar-brand" href="@menu_image_url">
                          <img src="@menu_image_url" alt="DACEM Logo" class="menu-logo d-inline-block align-text-top">
                      </a>
                      
                      <!-- Botón de colapso para móviles -->
                      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
                          <span class="navbar-toggler-icon"></span>
                      </button>
                      
                      <!-- Menú colapsable -->
                      <div class="collapse navbar-collapse justify-content-end" id="universityNavbar">
                          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                              <li class="nav-item">
                                  <a class="nav-link" href="/" style="color:red;">' . $this->t('ABOUT') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/" style="color:red;">' . $this->t('TIMELINE') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/" style="color:red;">' . $this->t('RESOURCES') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/" style="color:red;">' . $this->t('PARTNERS') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/" style="color:red;">' . $this->t('CONTACT') . '</a>
                              </li>
                          </ul>
      
                          <!-- Botones de idioma -->
                          <div class="d-flex ms-lg-2 language-buttons">
                              <a href="@url_es" class="btn btn-outline-secondary me-2" style="color:red; border-color:red;">ES</a>
                              <a href="@url_en_gb" class="btn btn-outline-secondary" style="color:red; border-color:red;">EN</a>
                          </div>
                      </div>
                  </div>
              </nav>',
                    [
                        '@menu_image_url' => $logo_url,
                        '@url_es' => $switch_links['es'],
                        '@url_en_gb' => $switch_links['en-gb'],
                    ]
                ),
            ];



            return $build;
        }
    }
}
