<?php

namespace Drupal\university_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Provides a 'University Menu' Block.
 *
 * @Block(
 *   id = "university_menu_block",
 *   admin_label = @Translation("University Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class UniversityMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $current_node = \Drupal::routeMatch()->getParameter('node');

    if ($current_node instanceof NodeInterface) {
      $node_type = $current_node->bundle();
      $university = null;

      if ($node_type == 'universidad') {
        $university = $current_node;
      } elseif ($node_type == 'carrera') {
        $university = $current_node->get('field_universidad')->entity;
      } elseif ($node_type == 'asignatura') {
        $carrera = $current_node->get('field_carrera')->entity;
        if ($carrera) {
          $university = $carrera->get('field_universidad')->entity;
        }
      }

      if (!empty($university)) {
        $logo_url = '';
        if (!$university->get('field_logo')->isEmpty()) {
          $media = $university->get('field_logo')->entity;
          if ($media && $media->hasField('field_media_image')) {
            $file = $media->get('field_media_image')->entity;
            if ($file) {
              $logo_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
            }
          }
        }

        // Obtener el idioma actual
        $language_manager = \Drupal::service('language_manager');
        $current_language = $language_manager->getCurrentLanguage()->getId();

        // Generar la URL de la universidad en el idioma actual
        $university_url = $university->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage($current_language)])->toString();

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
                      <!-- Logo -->
                      <a class="navbar-brand" href="@university_url">
                          <img src="@logo_url" alt="@university_name" class="university-logo d-inline-block align-text-top">
                      </a>
                      
                      <!-- Botón de colapso para móviles -->
                      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
                          <span class="navbar-toggler-icon"></span>
                      </button>
                      
                      <!-- Menú colapsable -->
                      <div class="collapse navbar-collapse justify-content-end" id="universityNavbar">
                          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                              <li class="nav-item">
                                  <a class="nav-link" href="/@university_path/informacion-institucional">' . $this->t('INSTITUTIONAL INFORMATION') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/@university_path/catalogo">' . $this->t('CATALOGUE') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/@university_path/recursos-y-servicios">' . $this->t('RESOURCES AND SERVICES') . '</a>
                              </li>
                              <li class="nav-item">
                                  <a class="nav-link" href="/@university_path/vida-universitaria">' . $this->t('UNIVERSITY LIFE') . '</a>
                              </li>
                          </ul>
      
                          <!-- Botones de idioma -->
                          <div class="d-flex ms-lg-2">
                              <a href="@url_es" class="btn btn-outline-secondary me-2">ES</a>
                              <a href="@url_en_gb" class="btn btn-outline-secondary">EN</a>
                          </div>
                      </div>
                  </div>
              </nav>',
              [
                  '@logo_url' => $logo_url,
                  '@university_name' => $university->getTitle(),
                  '@university_path' => $university->toUrl()->getInternalPath(),
                  '@url_es' => $switch_links['es'],
                  '@url_en_gb' => $switch_links['en-gb'],
                  '@university_url' => $university_url,
              ]),
      ];
      
        
        
        
      }
    }

    return $build;
  }
}
