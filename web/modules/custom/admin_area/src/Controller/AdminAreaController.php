<?php

namespace Drupal\admin_area\Controller;

use Drupal\admin_area\Service\AdminContextResolver;
use Drupal\admin_area\Service\AdminRouteManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for admin area entry points.
 */
class AdminAreaController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly AdminContextResolver $adminContextResolver,
    private readonly AdminRouteManager $adminRouteManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('admin_area.context_resolver'),
      $container->get('admin_area.route_manager'),
    );
  }

  /**
   * Entry point for the legacy /my-area route.
   */
  public function content(): RedirectResponse {
    $role = $this->adminContextResolver->getCurrentAdminRole();
    if (!$role) {
      throw new AccessDeniedHttpException('User is not a member of any group.');
    }

    $entity_type = $this->adminRouteManager->getDefaultEntityTypeForRole($role);
    if (!$entity_type) {
      throw new AccessDeniedHttpException('Access denied for your role.');
    }

    $en = $this->languageManager()->getLanguage('en');
    $options = $en ? ['language' => $en] : [];

    return new RedirectResponse(Url::fromRoute('admin_area.my_entity_page', [
      'entity_type' => $entity_type,
    ], $options)->toString());
  }

  /**
   * Entry point for entity-based admin area pages.
   */
  public function myEntityPage(string $entity_type): RedirectResponse {
    $role = $this->adminContextResolver->getCurrentAdminRole();
    if (!$role) {
      throw new AccessDeniedHttpException('User is not a member of any group.');
    }

    if (!$this->adminRouteManager->supportsEntityType($entity_type)) {
      throw new AccessDeniedHttpException('Entity type not supported.');
    }

    $route_name = $this->adminRouteManager->getRouteName($entity_type, $role);
    if (!$route_name) {
      throw new AccessDeniedHttpException('Access denied for your role.');
    }

    $en = $this->languageManager()->getLanguage('en');
    $options = $en ? ['language' => $en] : [];

    return new RedirectResponse(Url::fromRoute($route_name, [], $options)->toString());
  }

  /**
   * Redirects to the create-user-in-group form.
   */
  public function redirectToCreateUserForm(): RedirectResponse {
    $group = $this->adminContextResolver->getCurrentGroup();
    if (!$group) {
      throw new AccessDeniedHttpException('User is not part of any group.');
    }

    $en = $this->languageManager()->getLanguage('en');
    $options = [
      'query' => ['destination' => '/en/admin/admin-users'],
    ];
    if ($en) {
      $options['language'] = $en;
    }

    $url = Url::fromRoute('create_user_group.create_user_form', [
      'group' => $group->id(),
    ], $options);

    return new RedirectResponse($url->toString());
  }

}
