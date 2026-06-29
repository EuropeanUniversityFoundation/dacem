<?php

namespace Drupal\admin_area\Controller;

use Drupal\admin_area\Service\AdminContextResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirect controller for joint programme membership creation.
 */
class AdminAreaMembershipController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs the controller.
   */
  public function __construct(
    private readonly AdminContextResolver $adminContextResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('admin_area.context_resolver'),
    );
  }

  /**
   * Redirects to the membership creation form for a joint programme.
   */
  public function redirectToMembershipForm(NodeInterface $joint_programme): RedirectResponse {
    if ($joint_programme->bundle() !== 'joint_programme') {
      throw new NotFoundHttpException();
    }

    $gid = $this->adminContextResolver->getCurrentGroupId();
    if (!$gid) {
      $this->messenger()->addError($this->t('Could not determine your group.'));
      return $this->redirect('view.admin_available_joint_programmes.page_1');
    }

    $url = Url::fromUserInput('/group/' . $gid . '/content/create/group_node%3Amember', [
      'query' => [
        'field_member_joint_programme' => $joint_programme->id(),
        'destination' => '/admin/admin-available-joint-programmes',
      ],
    ])->toString();

    return new RedirectResponse($url);
  }

}
