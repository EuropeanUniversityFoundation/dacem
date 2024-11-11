<?php

namespace Drupal\dacem_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
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

    foreach ($languages as $language) {
        $langcode = $language->getId();
        $url = Url::fromRoute('<current>', [], ['language' => $language]);
        $switch_links[$langcode] = $url->toString();
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
                          <a href="@url_es" class="btn btn-outline-secondary me-2" style="color:red; border-color:red;">ES</a>
                          <a href="@url_en" class="btn btn-outline-secondary" style="color:red; border-color:red;">EN</a>
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
