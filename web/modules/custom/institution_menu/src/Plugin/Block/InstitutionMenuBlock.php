<?php

namespace Drupal\institution_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;

/**
 * Provides an Institution Menu block.
 *
 * @Block(
 *   id = "institution_menu_block",
 *   admin_label = @Translation("Institution Menu Block"),
 *   category = @Translation("Custom")
 * )
 */
final class InstitutionMenuBlock extends BlockBase {

  private const MENU_TRANSLATIONS = [
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
    'it' => [
      'INSTITUTIONAL INFORMATION' => 'INFORMAZIONI ISTITUZIONALI',
      'CATALOGUE' => 'CATALOGO',
      'RESOURCES AND SERVICES' => 'RISORSE E SERVIZI',
      'UNIVERSITY LIFE' => 'VITA UNIVERSITARIA',
    ],
    'de' => [
      'INSTITUTIONAL INFORMATION' => 'INSTITUTIONELLE INFORMATIONEN',
      'CATALOGUE' => 'KATALOG',
      'RESOURCES AND SERVICES' => 'RESSOURCEN UND DIENSTLEISTUNGEN',
      'UNIVERSITY LIFE' => 'UNIVERSITÄTSLEBEN',
    ],
    'lt' => [
      'INSTITUTIONAL INFORMATION' => 'INSTITUCINĖ INFORMACIJA',
      'CATALOGUE' => 'KATALOGAS',
      'RESOURCES AND SERVICES' => 'IŠTEKLIAI IR PASLAUGOS',
      'UNIVERSITY LIFE' => 'UNIVERSITETO GYVENIMAS',
    ],
    'pl' => [
      'INSTITUTIONAL INFORMATION' => 'INFORMACJE INSTYTUCJONALNE',
      'CATALOGUE' => 'KATALOG',
      'RESOURCES AND SERVICES' => 'ZASOBY I USŁUGI',
      'UNIVERSITY LIFE' => 'ŻYCIE UNIWERSYTECKIE',
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

  private const LANGUAGE_FLAGS = [
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

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_language = \Drupal::languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $current_route = \Drupal::routeMatch()->getRouteName();
    $site_branding = Settings::get('site_branding', []);
    $site_menu_logo_url = $site_branding['menu_logo_image'] ?? '/themes/custom/b5subtheme/images/dacem-imago.png';

    $current_node = $this->resolveCurrentNode($current_language);
    $current_page = $this->resolveCurrentPage($current_route);
    $institution = $this->resolveInstitution($current_node, $current_route);

    if (!$institution instanceof NodeInterface) {
      return [];
    }

    $branding = $this->buildInstitutionBranding($institution, $current_language);
    $language_options = $this->buildLanguageOptions($current_node, $institution, $current_page, $current_route, $current_language);
    $menu_urls = $this->buildMenuUrls($institution, $current_language);
    $institution_options = $this->buildInstitutionOptions($institution, $current_language);
    $profile_html = $this->buildProfileHtml();
    $menu_translations = self::MENU_TRANSLATIONS[$current_language] ?? self::MENU_TRANSLATIONS['en'];

    $gi_active = !in_array($current_page, ['catalogue', 'resources-and-services'], TRUE) ? ' active ' : '';
    $cat_active = $current_page === 'catalogue' ? ' active ' : '';
    $rs_active = $current_page === 'resources-and-services' ? ' active ' : '';

    $gi_aria = $gi_active !== '' ? ' aria-current="page"' : '';
    $cat_aria = $cat_active !== '' ? ' aria-current="page"' : '';
    $rs_aria = $rs_active !== '' ? ' aria-current="page"' : '';

    $main_page_url = Url::fromRoute('view.main_page.page_1', [], [
      'language' => \Drupal::languageManager()->getLanguage($current_language),
    ])->toString();

    return [
      '#markup' => $this->t('

    <nav class="navbar sticky-top navbar-expand-lg university-navbar main-menu-text" style="margin: 0; padding: 0; --university_primary_color: @institution_primary_color; --university_text_color: @institution_text_color; --university_emphasis_text_color: @institution_emphasis_text_color;">
      <div class="container-fluid">

        <a class="navbar-brand" href="@main_page_url">
          <img src="@logo_dacem_url" alt="DACEM" class="university-logo d-inline-block align-text-top">
        </a>

        <div class="dropdown">
          ' . $institution_options . '
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#universityNavbar" aria-controls="universityNavbar" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="universityNavbar">
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link' . $gi_active . '" href="' . $menu_urls['general_info_url'] . '"' . $gi_aria . '>' . $menu_translations['INSTITUTIONAL INFORMATION'] . '</a>
            </li>
            <li class="nav-item">
              <a class="nav-link' . $cat_active . '" href="' . $menu_urls['catalogue_url'] . '"' . $cat_aria . '>' . $menu_translations['CATALOGUE'] . '</a>
            </li>
            <li class="nav-item">
              <a class="nav-link' . $rs_active . '" href="' . $menu_urls['rs_url'] . '"' . $rs_aria . '>' . $menu_translations['RESOURCES AND SERVICES'] . '</a>
            </li>
          </ul>

          <div class="d-flex align-items-center right-buttons-university-menu">
            <div class="language-buttons" style="position: relative; z-index: 1050;">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle no-hover-bg" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    ' . strtoupper($current_language) . ' ' . (self::LANGUAGE_FLAGS[$current_language] ?? '') . '
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
          '@main_page_url' => $main_page_url,
          '@logo_dacem_url' => $site_menu_logo_url,
          '@institution_primary_color' => $branding['primary_color'],
          '@institution_text_color' => $branding['text_color'],
          '@institution_emphasis_text_color' => $branding['emphasis_text_color'],
        ]
      ),
    ];
  }

  protected function getNodeFromAlias(string $alias, ?string $langcode = NULL): ?NodeInterface {
    $internal_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias, $langcode);
    if (!preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
      return NULL;
    }

    $node = Node::load((int) $matches[1]);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    if ($langcode && $node->hasTranslation($langcode)) {
      return $node->getTranslation($langcode);
    }

    return $node;
  }

  protected function getInstitutionResourcesNode(int $institution_id): ?NodeInterface {
    $query = \Drupal::database()->select('node_field_data', 'nfd');
    $query->fields('nfd', ['nid']);
    $query->innerJoin('node__field_rs_institution', 'rs_inst', 'rs_inst.entity_id = nfd.nid');
    $query->leftJoin('node__field_rs_campus', 'rs_campus', 'rs_campus.entity_id = nfd.nid');
    $query
      ->condition('nfd.type', 'resources_and_services')
      ->condition('nfd.status', 1)
      ->condition('rs_inst.field_rs_institution_target_id', $institution_id)
      ->isNull('rs_campus.entity_id')
      ->range(0, 1);

    $nid = $query->execute()->fetchField();
    return $nid ? Node::load((int) $nid) : NULL;
  }

  protected function getCampusResourcesNode(int $campus_id): ?NodeInterface {
    $query = \Drupal::database()->select('node_field_data', 'nfd');
    $query->fields('nfd', ['nid']);
    $query->innerJoin('node__field_rs_campus', 'rs_campus', 'rs_campus.entity_id = nfd.nid');
    $query
      ->condition('nfd.type', 'resources_and_services')
      ->condition('nfd.status', 1)
      ->condition('rs_campus.field_rs_campus_target_id', $campus_id)
      ->range(0, 1);

    $nid = $query->execute()->fetchField();
    return $nid ? Node::load((int) $nid) : NULL;
  }

  private function resolveCurrentNode(string $current_language): ?NodeInterface {
    $route_parameter = \Drupal::routeMatch()->getParameter('arg_0');
    if (is_numeric($route_parameter)) {
      return Node::load((int) $route_parameter);
    }

    $current_path = \Drupal::service('path.current')->getPath();
    $path_without_language = preg_replace('#^/[^/]+/#', '/', $current_path) ?: $current_path;

    return $this->getNodeFromAlias($path_without_language, $current_language);
  }

  private function resolveCurrentPage(string $current_route): string {
    return match ($current_route) {
      'view.general_information.page_1' => 'general-information',
      'view.resources_and_services.page_1',
      'view.resources_and_services.page_2' => 'resources-and-services',
      'view.campus_information.page_1' => 'campus-information',
      'view.organizational_unit_information.page_1' => 'organizational-unit-information',
      'view.institution_catalogue.page_1',
      'view.programme_information.page_1',
      'view.iec_information.page_1',
      'view.institution_new_catalogue.page_1',
      'view.institution_new_catalogue.page_2',
      'view.institution_new_catalogue.page_3',
      'view.joint_programme_information.page_1',
      'view.iec_instance.page_1' => 'catalogue',
      default => '',
    };
  }

  private function resolveInstitution(?NodeInterface $current_node, string $current_route): ?NodeInterface {
    if ($current_node instanceof NodeInterface) {
      return $this->resolveInstitutionFromNode($current_node);
    }

    $route_parameter = \Drupal::routeMatch()->getParameter('arg_0');
    if (!is_numeric($route_parameter)) {
      return NULL;
    }

    return match ($current_route) {
      'view.general_information.page_1',
      'view.institution_catalogue.page_1',
      'view.resources_and_services.page_1',
      'view.institution_new_catalogue.page_1',
      'view.institution_new_catalogue.page_2',
      'view.institution_new_catalogue.page_3' => Node::load((int) $route_parameter),
      default => NULL,
    };
  }

  private function resolveInstitutionFromNode(NodeInterface $node): ?NodeInterface {
    return match ($node->bundle()) {
      'institution' => $node,
      'campus' => $node->get('field_campus_institution')->entity,
      'organizational_unit' => $node->get('field_ou_institution')->entity,
      'programme' => $node->get('field_programme_institution')->entity,
      'individual_educational_component' => $this->resolveInstitutionFromProgrammeReference($node, 'field_iec_programme'),
      'joint_programme' => $this->resolveInstitutionFromProgrammeReference($node, 'field_programme'),
      'iec_instance' => $this->resolveInstitutionFromIecInstance($node),
      default => NULL,
    };
  }

  private function resolveInstitutionFromProgrammeReference(NodeInterface $node, string $field_name): ?NodeInterface {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $programme = $node->get($field_name)->entity;
    if (!$programme instanceof NodeInterface || $programme->get('field_programme_institution')->isEmpty()) {
      return NULL;
    }

    $institution = $programme->get('field_programme_institution')->entity;
    return $institution instanceof NodeInterface ? $institution : NULL;
  }

  private function resolveInstitutionFromIecInstance(NodeInterface $node): ?NodeInterface {
    if (!$node->hasField('field_iec') || $node->get('field_iec')->isEmpty()) {
      return NULL;
    }

    $iec = $node->get('field_iec')->entity;
    if (!$iec instanceof NodeInterface || $iec->get('field_iec_programme')->isEmpty()) {
      return NULL;
    }

    $programme = $iec->get('field_iec_programme')->entity;
    if (!$programme instanceof NodeInterface || $programme->get('field_programme_institution')->isEmpty()) {
      return NULL;
    }

    $institution = $programme->get('field_programme_institution')->entity;
    return $institution instanceof NodeInterface ? $institution : NULL;
  }

  private function buildInstitutionBranding(NodeInterface $institution, string $current_language): array {
    $branding = [
      'logo_url' => '',
      'primary_color' => '#F44743',
      'text_color' => '#000000',
      'emphasis_text_color' => '#000000',
      'institution_url' => $institution->toUrl('canonical', [
        'language' => \Drupal::languageManager()->getLanguage($current_language),
      ])->toString(),
    ];

    if (!$institution->get('field_logo')->isEmpty()) {
      $media = $institution->get('field_logo')->entity;
      if ($media && $media->hasField('field_media_image')) {
        $file = $media->get('field_media_image')->entity;
        if ($file) {
          $branding['logo_url'] = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
      }
    }

    if ($institution->hasField('field_primary_color') && !$institution->get('field_primary_color')->isEmpty()) {
      $branding['primary_color'] = $institution->get('field_primary_color')->first()->color ?? $branding['primary_color'];
    }
    if ($institution->hasField('field_text_color') && !$institution->get('field_text_color')->isEmpty()) {
      $branding['text_color'] = $institution->get('field_text_color')->first()->color ?? $branding['text_color'];
    }
    if ($institution->hasField('field_emphasis_text_color') && !$institution->get('field_emphasis_text_color')->isEmpty()) {
      $branding['emphasis_text_color'] = $institution->get('field_emphasis_text_color')->first()->color ?? $branding['emphasis_text_color'];
    }

    return $branding;
  }

  private function buildLanguageOptions(?NodeInterface $current_node, NodeInterface $institution, string $current_page, string $current_route, string $current_language): string {
    $language_options = '';
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $langcode = $language->getId();
      $language_url = $this->buildLanguageSwitchUrl($current_node, $institution, $current_page, $current_route, $current_language, $language);
      $flag = self::LANGUAGE_FLAGS[$langcode] ?? '';
      $language_options .= '<li><a class="dropdown-item" href="' . $language_url . '">' . $flag . ' ' . strtoupper($langcode) . '</a></li>';
    }

    return $language_options;
  }

  private function buildLanguageSwitchUrl(?NodeInterface $current_node, NodeInterface $institution, string $current_page, string $current_route, string $current_language, $language): string {
    if (
      $current_route === 'view.institution_new_catalogue.page_1' ||
      $current_route === 'view.institution_new_catalogue.page_2' ||
      $current_route === 'view.institution_new_catalogue.page_3'
    ) {
      return Url::fromRoute($current_route, [
        'arg_0' => $institution->id(),
      ], [
        'language' => $language,
        'query' => \Drupal::request()->query->all(),
      ])->toString();
    }

    if ($current_route === 'view.iec_instance.page_1' && $current_node instanceof NodeInterface) {
      $route_language = $current_node->hasTranslation($language->getId())
        ? $language
        : \Drupal::languageManager()->getLanguage('en');
      $instance_for_url = $current_node->hasTranslation($route_language->getId())
        ? $current_node->getTranslation($route_language->getId())
        : $current_node;

      return Url::fromRoute('view.iec_instance.page_1', [
        'arg_0' => $instance_for_url->id(),
      ], [
        'language' => $route_language,
      ])->toString();
    }

    if ($current_route === 'view.joint_programme_information.page_1' && $current_node instanceof NodeInterface) {
      $route_language = $current_node->hasTranslation($language->getId())
        ? $language
        : \Drupal::languageManager()->getLanguage('en');
      $joint_for_url = $current_node->hasTranslation($route_language->getId())
        ? $current_node->getTranslation($route_language->getId())
        : $current_node;

      return Url::fromRoute('view.joint_programme_information.page_1', [
        'arg_0' => $joint_for_url->id(),
      ], [
        'language' => $route_language,
      ])->toString();
    }

    if (
      ($current_route === 'view.resources_and_services.page_1' || $current_route === 'view.resources_and_services.page_2')
      && $current_node instanceof NodeInterface
    ) {
      return $this->buildResourcesAndServicesLanguageUrl($current_route, $current_language, $language);
    }

    if ($current_node instanceof NodeInterface) {
      $target_langcode = $current_node->hasTranslation($language->getId()) ? $language->getId() : 'en';
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $current_node->id(), $target_langcode);
      $alias = $this->stripLanguagePrefix($alias, $target_langcode);
      return '/' . $target_langcode . '/' . $current_page . $alias;
    }

    return $this->buildSectionAliasUrl($institution, 'general-information', $language->getId());
  }

  private function buildResourcesAndServicesLanguageUrl(string $current_route, string $current_language, $language): string {
    $alias_manager = \Drupal::service('path_alias.manager');
    $route_language = $language;
    $target_nid = NULL;
    $entity_name = (string) \Drupal::routeMatch()->getParameter('arg_0');

    if ($current_route === 'view.resources_and_services.page_2') {
      $entity_name .= '/' . (string) \Drupal::routeMatch()->getParameter('arg_1');
    }

    if ($entity_name !== '') {
      $candidate_languages = array_unique([$language->getId(), $current_language, 'en']);
      foreach ($candidate_languages as $candidate_language) {
        $resolved_path = $alias_manager->getPathByAlias('/' . trim($entity_name, '/'), $candidate_language);
        if (preg_match('/^\/node\/(\d+)$/', $resolved_path, $matches)) {
          $target_nid = (int) $matches[1];
          break;
        }
      }
    }

    $rs_node = NULL;
    if ($target_nid) {
      $rs_node = $current_route === 'view.resources_and_services.page_1'
        ? $this->getInstitutionResourcesNode($target_nid)
        : $this->getCampusResourcesNode($target_nid);
    }

    if (!$rs_node || !$rs_node->hasTranslation($language->getId())) {
      $route_language = \Drupal::languageManager()->getLanguage('en');
    }

    $route_langcode = $route_language->getId();
    $route_alias = $target_nid
      ? $alias_manager->getAliasByPath('/node/' . $target_nid, $route_langcode)
      : '/' . trim($entity_name, '/');
    $route_alias = trim($route_alias, '/');
    $route_alias_parts = $route_alias !== '' ? explode('/', $route_alias) : [];

    $route_parameters = [
      'arg_0' => $route_alias_parts[0] ?? \Drupal::routeMatch()->getParameter('arg_0'),
    ];
    if ($current_route === 'view.resources_and_services.page_2') {
      $route_parameters['arg_1'] = $route_alias_parts[1] ?? \Drupal::routeMatch()->getParameter('arg_1');
    }

    return Url::fromRoute($current_route, $route_parameters, [
      'language' => $route_language,
    ])->toString();
  }

  private function buildMenuUrls(NodeInterface $institution, string $current_language): array {
    $catalogue_url = Url::fromRoute('view.institution_new_catalogue.page_3', [
      'arg_0' => $institution->id(),
    ], [
      'language' => \Drupal::languageManager()->getLanguage($current_language),
    ])->toString();

    $general_info_url = $this->buildSectionAliasUrl($institution, 'general-information', $current_language);

    $rs_language = $current_language;
    $institution_rs = $this->getInstitutionResourcesNode((int) $institution->id());
    if (!$institution_rs || !$institution_rs->hasTranslation($current_language)) {
      $rs_language = 'en';
    }

    $rs_url = $this->buildSectionAliasUrl($institution, 'resources-and-services', $rs_language);

    return [
      'general_info_url' => $general_info_url,
      'catalogue_url' => $catalogue_url,
      'rs_url' => $rs_url,
    ];
  }

  private function buildInstitutionOptions(NodeInterface $current_institution, string $current_language): string {
    $institutions = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => 'institution', 'status' => 1]);

    $translated_institution = \Drupal::service('entity.repository')
      ->getTranslationFromContext($current_institution, $current_language);
    $current_institution_name = $translated_institution->getTitle();

    $institution_options = '<div class="institution-dropdown">
    <a href="#" id="institutionDropdown" class="institution-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        ' . $current_institution_name . ' <i class="fas fa-chevron-down"></i>
    </a>
    <ul class="dropdown-menu" aria-labelledby="institutionDropdown">';

    foreach ($institutions as $institution) {
      $used_language = $institution->hasTranslation($current_language) ? $current_language : 'en';
      $translated = $institution->hasTranslation($used_language) ? $institution->getTranslation($used_language) : $institution;
      $institution_url = $this->buildSectionAliasUrl($translated, 'general-information', $used_language);
      $institution_options .= '<li><a class="dropdown-item" href="' . $institution_url . '">' . $translated->getTitle() . '</a></li>';
    }

    $institution_options .= '</ul></div>';
    return $institution_options;
  }

  private function buildSectionAliasUrl(NodeInterface $node, string $section, string $langcode): string {
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id(), $langcode);
    $alias = $this->stripLanguagePrefix($alias, $langcode);
    return '/' . $langcode . '/' . $section . $alias;
  }

  private function stripLanguagePrefix(string $alias, string $langcode): string {
    $language_prefix = '/' . $langcode;
    return str_starts_with($alias, $language_prefix)
      ? substr($alias, strlen($language_prefix))
      : $alias;
  }

  private function buildProfileHtml(): string {
    $current_user = \Drupal::currentUser();
    if (!$current_user->isAuthenticated() || (int) $current_user->id() === 0) {
      return '';
    }

    $profile_picture_url = '/themes/custom/b5subtheme/images/default-profile.jpg';
    $user = User::load($current_user->id());

    if ($user && $user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
      $file = File::load($user->get('user_picture')->target_id);
      if ($file) {
        $profile_picture_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
      }
    }

    return '
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
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
