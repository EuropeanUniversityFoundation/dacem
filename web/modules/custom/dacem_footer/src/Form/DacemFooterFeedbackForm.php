<?php

namespace Drupal\dacem_footer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Feedback form rendered inside the DACEM footer block.
 */
class DacemFooterFeedbackForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Module logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the form.
   */
  public function __construct(MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory, RequestStack $request_stack, LanguageManagerInterface $language_manager, LoggerInterface $logger) {
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->languageManager = $language_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('logger.factory')->get('dacem_footer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dacem_footer_feedback_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_request = $this->requestStack->getCurrentRequest();

    $form['#attributes']['class'][] = 'dacem-footer-feedback-form';

    $form['row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row'],
      ],
    ];

    $form['row']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Name'),
      '#wrapper_attributes' => [
        'class' => ['col-6', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['row']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Email'),
      '#wrapper_attributes' => [
        'class' => ['col-6', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['row']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your message'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Message'),
      '#rows' => 3,
      '#wrapper_attributes' => [
        'class' => ['col-12', 'mb-3'],
      ],
      '#attributes' => [
        'class' => ['form-control'],
      ],
    ];

    $form['context_page'] = [
      '#type' => 'hidden',
      '#value' => $current_request ? $current_request->getUri() : '',
    ];

    $form['row']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['col-12', 'text-end'],
      ],
    ];

    $form['row']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['btn', 'btn-dark', 'px-4'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach (['name', 'email', 'message'] as $field) {
      $value = trim((string) $form_state->getValue($field));
      $form_state->setValue($field, $value);

      if ($value === '') {
        $form_state->setErrorByName($field, $this->t('This field is required.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $site_config = $this->configFactory->get('system.site');
    $site_name = $site_config->get('name') ?: 'DACEM';
    $site_mail = $site_config->get('mail');
    $to = $site_mail;

    if (empty($to)) {
      $this->messenger()->addError($this->t('Feedback could not be sent because the site email is not configured.'));
      $this->logger->error('Footer feedback could not be sent because system.site:mail is empty.');
      return;
    }

    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $message_text = $form_state->getValue('message');
    $page = $form_state->getValue('context_page');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    $params = [
      'subject' => (string) $this->t('[DACEM] Footer feedback from @name', ['@name' => $name]),
      'message' => implode("\n\n", [
        'A new footer feedback message has been submitted.',
        'Name: ' . $name,
        'Email: ' . $email,
        'Page: ' . $page,
        'Site: ' . $site_name,
        'Message:',
        $message_text,
      ]),
      'reply_to' => $email,
    ];

    $result = $this->mailManager->mail('dacem_footer', 'feedback', $to, $langcode, $params, $site_mail, TRUE);

    if (!empty($result['result'])) {
      $this->messenger()->addStatus($this->t('Thanks. Your feedback has been sent.'));
      $form_state->setRedirect('<current>');
      return;
    }

    $this->logger->error('Footer feedback email failed for %mail on page %page.', [
      '%mail' => $email,
      '%page' => $page,
    ]);
    $this->messenger()->addError($this->t('The feedback could not be sent right now. Please try again later.'));
  }

}
