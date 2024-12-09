<?php

namespace Drupal\dacem_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;

/**
 * Provides a 'Dacem Menu' Block.
 *
 * @Block(
 *   id = "dacem_menu_block",
 *   admin_label = @Translation("Dacem Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class DacemMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  /**
 * {@inheritdoc}
 */
public function build() {
    // Obtener el nombre de la ruta actual.
    $route_name = \Drupal::routeMatch()->getRouteName();
    // Lista de rutas donde el bloque debe aparecer.
    $allowed_routes = [
        'view.lista_de_universidades.page_1', // Reemplaza con la ruta real de la vista.
        'admin_area.my_area',   // Otra vista donde quieres mostrar el bloque.
    ];

    // Mostrar el bloque solo si la ruta actual está en la lista permitida.
    if (!in_array($route_name, $allowed_routes)) {
        return []; // No renderizar el bloque.
    }

    // Ruta del logo del menú.
    $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
    $logo_url = base_path() . $theme_path . '/images/logo-dacem.jpg';

    // Obtener el idioma actual y las opciones de cambio de idioma.
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $switch_links = [];

     // Obtener el idioma actual
     $language_manager = \Drupal::service('language_manager');
     //$current_language = $language_manager->getCurrentLanguage()->getId();
     $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();


    $language_options='';
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
      


    return [
        '#markup' => $this->t('
            <nav class="navbar navbar-expand-lg university-navbar">
              <div class="container-fluid">
                  <a class="navbar-brand" href="@menu_image_url">
                      <img src="@menu_image_url" alt="DACEM Logo" class="menu-logo d-inline-block align-text-top">
                  </a>
                  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
                      <span class="navbar-toggler-icon"></span>
                  </button>
                  <div class="collapse navbar-collapse justify-content-end" id="universityNavbar">
                      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                          <li class="nav-item"><a class="nav-link" href="/" style="color:red;">' . $this->t('ABOUT') . '</a></li>
                          <li class="nav-item"><a class="nav-link" href="/" style="color:red;">' . $this->t('TIMELINE') . '</a></li>
                          <li class="nav-item"><a class="nav-link" href="/" style="color:red;">' . $this->t('RESOURCES') . '</a></li>
                          <li class="nav-item"><a class="nav-link" href="/" style="color:red;">' . $this->t('PARTNERS') . '</a></li>
                          <li class="nav-item"><a class="nav-link" href="/" style="color:red;">' . $this->t('CONTACT') . '</a></li>
                      </ul>
                      <div class="d-flex ms-lg-2 language-buttons">
                          <!-- Menú -->
                            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                              <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle no-hover-bg" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ' . strtoupper($current_language) . ' ' . $flags[$current_language] . '
                                </a>
                          
                                <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                                ' . $language_options . '
                                </ul>
                              </li>
                            </ul>
                          </div>
                  </div>
              </div>
            </nav>',
            [
                '@menu_image_url' => $logo_url,
                '@url_es' => $switch_links['es'] ?? '#',
                '@url_en' => $switch_links['en'] ?? '#',
            ]
        ),
    ];
}

}
