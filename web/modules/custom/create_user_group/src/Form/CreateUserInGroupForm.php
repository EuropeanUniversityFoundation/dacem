<?php

namespace Drupal\create_user_group\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupMembership;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Custom form to create a user in a group.
 */
class CreateUserInGroupForm extends FormBase {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $currentRequestStack;

  /**
   * Constructs a CreateUserInGroupForm object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RouteMatchInterface $route_match, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->currentRouteMatch = $route_match;
    $this->account = $current_user;
    $this->currentRequestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('current_route_match'),
    $container->get('current_user'),
    $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_user_in_group_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $group = $this->currentRouteMatch->getParameter('group');

    if (!$group || !$group instanceof Group) {
      return ['#markup' => $this->t('Invalid group.')];
    }

    $membership = GroupMembership::loadSingle($group, $this->account);

    if ($membership) {
      $roles = $membership->getRoles();
      foreach ($roles as $role) {
        if ($role->id() == 'universitytypegroup-university_a') {
          $form['username'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Username'),
            '#required' => TRUE,
          ];

          $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email'),
            '#required' => TRUE,
          ];

          $form['password'] = [
            '#type' => 'password',
            '#title' => $this->t('Password'),
            '#required' => TRUE,
          ];

          $form['role'] = [
            '#type' => 'select',
            '#title' => $this->t('Role'),
            '#options' => [
              'university_a' => $this->t('Higher Education Institution (HEI) Administrator'),
              'campus_edito' => $this->t('Campus Editor'),
              'ou_administr' => $this->t('Organizational Unit (OU) Administrator'),
              'degree_admin' => $this->t('Programme Administrator'),
              'subject_admi' => $this->t('Course Editor'),

            ],
            '#required' => TRUE,
          ];

          $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Create User and Add to Group'),
          ];

          return $form;
        }
      }
    }

    return ['#markup' => $this->t('You do not have permission to create users in this group.')];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('username');
    $email = $form_state->getValue('email');
    $password = $form_state->getValue('password');
    $role = $form_state->getValue('role');

    $user = User::create([
      'name' => $username,
      'mail' => $email,
      'pass' => $password,
      'status' => 1,
    ]);
    $user->save();

    $group = $this->currentRouteMatch->getParameter('group');
    if ($group instanceof Group) {
      $group->addMember($user, ['group_roles' => ['universitytypegroup-' . $role]]);
    }

    $destination = $this->currentRequestStack->getCurrentRequest()->query->get('destination');

    if ($destination) {
      $form_state->setRedirectUrl(Url::fromUserInput($destination));
    }
    else {
      $group = $this->currentRouteMatch->getParameter('group');
      $form_state->setRedirect('view.group_members.page_1', ['group' => $group->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('username');
    $email = $form_state->getValue('email');

    if (user_load_by_name($username)) {
      $form_state->setErrorByName('username', $this->t('The username %name is already taken.', ['%name' => $username]));
    }

    if (user_load_by_mail($email)) {
      $form_state->setErrorByName('email', $this->t('The email %email is already registered.', ['%email' => $email]));
    }
  }

}
