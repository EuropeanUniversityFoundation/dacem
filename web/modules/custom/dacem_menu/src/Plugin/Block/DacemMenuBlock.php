<?php

namespace Drupal\dacem_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Dacem Menu' Block.
 *
 * @Block(
 *   id = "dacem_menu_block",
 *   admin_label = @Translation("Dacem Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
class DacemMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected CurrentPathStack $currentPath;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $aliasManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a DacemMenuBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path stack.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, CurrentPathStack $current_path, AliasManagerInterface $alias_manager, ThemeManagerInterface $theme_manager, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->themeManager = $theme_manager;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('theme.manager'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $route_match = $this->routeMatch;
    $route_name = $route_match->getRouteName();

    // Lista de rutas donde el bloque debe aparecer.
    $allowed_routes = [
      'view.main_page.page_1',
      'view.main_page.page_2',
      'view.alliances.page_1',
      'view.alliance_institutions.page_1',
      'view.search_programme.page_1',
      'view.search_iec.page_1',
    ];

    $show_block = in_array($route_name, $allowed_routes, TRUE);

    if (!$show_block && $route_name === 'entity.node.canonical') {
      $current_path = $this->currentPath->getPath();
      $alias = $this->aliasManager->getAliasByPath($current_path);

      if ($alias === '/about' || $alias === '/contact') {
        $show_block = TRUE;
      }
    }

    if (!$show_block) {
      return [];
    }

    $site_branding = Settings::get('site_branding', []);
    $primary_color = $site_branding['primary_color'] ?? '#ff4949';
    $logo_url = $site_branding['menu_logo_image'] ?? NULL;

    if (!$logo_url) {
      $theme_path = $this->themeManager->getActiveTheme()->getPath();
      $logo_url = base_path() . $theme_path . '/images/dacem-imago.png';
    }

    $language_manager = $this->languageManager;
    $languages = $language_manager->getLanguages();

    $current_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $language_options = '';
    $flags = [
      'en' => '🇬🇧',
      'es' => '🇪🇸',
      'pt-pt' => '🇵🇹',
      'fr' => '🇫🇷',
      'it' => '🇮🇹',
      'de' => '🇩🇪',
      'lt' => '🇱🇹',
      'pl' => '🇵🇱',
      'el' => '🇬🇷',
      'cs' => '🇨🇿',
      'sl' => '🇸🇮',
      'hu' => '🇭🇺',
      'et' => '🇪🇪',
      'gl' => '🇪🇸',
    ];

    foreach ($languages as $language) {
      $langcode = $language->getId();
      $abbreviation = strtoupper($langcode);

      $url = Url::fromRoute('<current>', [], ['language' => $language])->toString();

      $flag = $flags[$langcode] ?? '';

      // Language menu.
      $language_options .= '
            <li>
              <a class="dropdown-item" href="' . $url . '">' . $flag . ' ' . $abbreviation . '</a>
            </li>';

    }

    $current_language_object = $language_manager->getLanguage($current_language);
    $language_options_url = $current_language_object ? ['language' => $current_language_object] : [];
    $main_page_url = Url::fromRoute('view.main_page.page_1', [], $language_options_url)->toString();
    $institutions_url = Url::fromRoute('view.main_page.page_2', [], $language_options_url)->toString();
    $alliances_url = Url::fromRoute('view.alliances.page_1', [], $language_options_url)->toString();
    $about_url = Url::fromUserInput('/about', $language_options_url)->toString();
    $contact_url = Url::fromUserInput('/contact', $language_options_url)->toString();

    $alliance_count = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'alliance')
      ->condition('status', 1)
      ->count()
      ->execute();

    $alliances_menu_item = '';
    if ($alliance_count > 1) {
      $alliances_menu_item = '<li class="nav-item dacem-menu-item"><a class="nav-link" href="'
        . $alliances_url
        . '">'
        . $this->t('ALLIANCES')
        . '</a></li>';
    }

    // Obtain the current user to display their profile picture.
    $current_user = $this->currentUser;
    $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';

    if ($current_user->isAuthenticated() && $current_user->id() != 0) {
      // dump($current_user->id());      // Cargar la entidad del usuario.
      $user = $this->entityTypeManager->getStorage('user')->load($current_user->id());

      // Verify if has profile picture.
      if ($user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
        $file = $this->entityTypeManager->getStorage('file')->load($user->get('user_picture')->target_id);
        if ($file) {
          $profile_picture_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
        }
      }

      // If no image, set default image.
      if (!$profile_picture_url) {
        $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
      }

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

    }
    else {
      // If the user is annonymous, dont render the image.
      $profile_html = '';
    }

    return [
      '#markup' => $this->t('
    <nav class="navbar sticky-top navbar-expand-lg university-navbar mb-0 pb-0" style="--university_primary_color: @site_primary_color; --university_emphasis_text_color: #fff;">
      <div class="container-fluid">
          <a class="navbar-brand" href="@main_page_url">
              <img src="@menu_image_url" alt="DACEM Logo" class="menu-logo d-inline-block align-text-top">
          </a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse justify-content-end" id="universityNavbar">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@about_url">' . $this->t('ABOUT') . '</a></li>
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@institutions_url">' . $this->t('INSTITUTIONS') . '</a></li>
                  ' . $alliances_menu_item . '
                  <li class="nav-item dacem-menu-item"><a class="nav-link" href="@contact_url">' . $this->t('CONTACT') . '</a></li>
              </ul>
              <div class="d-flex ms-lg-2 right-buttons-university-menu">
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
                  ' . $profile_html . '
              </div>
          </div>
      </div>
    </nav>',
    [
      '@main_page_url' => $main_page_url,
      '@about_url' => $about_url,
      '@institutions_url' => $institutions_url,
      '@contact_url' => $contact_url,
      '@menu_image_url' => $logo_url,
      '@site_primary_color' => $primary_color,
    ]
      ),
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
