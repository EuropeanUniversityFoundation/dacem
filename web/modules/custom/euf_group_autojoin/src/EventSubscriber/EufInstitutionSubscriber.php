<?php

namespace Drupal\euf_group_autojoin\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// Eventos EUF:
use Drupal\ewp_institutions_user\Event\SetUserInstitutionEvent;
use Drupal\ewp_institutions_user\Event\UserInstitutionChangeEvent;

class EufInstitutionSubscriber implements EventSubscriberInterface {

  // ⬇️ Ajusta si tu Group type tiene otro machine name.
  private const GROUP_TYPE      = 'universitytypegroup';
  // Tu campo en Group que referencia a HEI.
  private const GROUP_HEI_FIELD = 'field_insititution_profile';
  // Base field en User que referencia a HEI (confirmado por ti).
  private const USER_HEI_FIELD  = 'user_institution';

  public function __construct(
    protected EntityTypeManagerInterface $etm,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * Registramos con prioridad NEGATIVA para ejecutar DESPUÉS de EUF.
   * En Symfony: mayor prioridad => antes; menor/negativa => después.
   */
  public static function getSubscribedEvents(): array {
    return [
      SetUserInstitutionEvent::EVENT_NAME   => ['onSetUserInstitution',   -1000],
      UserInstitutionChangeEvent::EVENT_NAME => ['onUserInstitutionChange', -1000],
    ];
  }

  public function onSetUserInstitution(SetUserInstitutionEvent $event): void {
    $user = $event->user ?? NULL;
    $this->logger->notice('[AUTOJOIN] SetUserInstitutionEvent recibido. user=' . ($user?->id() ?? 'null'));
    $this->logger->notice('[AUTOJOIN] HEI LIST');
    if (!$user instanceof UserInterface) {
      return;
    }

    // 1) Intentamos con la lista que trae el evento (si la hay).
    $hei_ids = [];
    if (property_exists($event, 'hei_list') && is_array($event->hei_list)) {
      foreach ($event->hei_list as $hei) {
        $hei_ids[] = is_object($hei) && method_exists($hei, 'id') ? (string) $hei->id() : (string) $hei;
      }
    }
    elseif (method_exists($event, 'getHeiList')) {
      foreach ((array) $event->getHeiList() as $hei) {
        $hei_ids[] = is_object($hei) && method_exists($hei, 'id') ? (string) $hei->id() : (string) $hei;
      }
    }

    // 2) Fallback: si el evento viene vacío, leemos del user el base field.
    if (empty(array_filter($hei_ids))) {
      $this->logger->notice('SetUserInstitutionEvent: user '.$user->id().' con lista HEIs vacía. Fallback al campo user_institution.');
      $fallback = $this->getHeiFromUser($user);
      if ($fallback) {
        $hei_ids = [$fallback];
      }
    }

    if (empty($hei_ids)) {
      // Nada que hacer.
      return;
    }

    foreach ($hei_ids as $hei_id) {
      if ($hei_id !== '' && $hei_id !== NULL) {
        $this->joinUserToHeiGroup($user, (string) $hei_id);
      }
    }
  }

  public function onUserInstitutionChange(UserInstitutionChangeEvent $event): void {
    $user = $event->user ?? NULL;
    $this->logger->notice('[AUTOJOIN] UserInstitutionChangeEvent recibido. user=' . ($user?->id() ?? 'null'));

    if (!$user instanceof UserInterface) {
      return;
    }

    // 1) Intentamos con el heiId del evento.
    $hei_id = $event->heiId ?? (method_exists($event, 'getHeiId') ? $event->getHeiId() : NULL);

    // 2) Fallback si viene vacío: leer del user.
    if (!$hei_id) {
      $this->logger->notice('UserInstitutionChangeEvent: user '.$user->id().' sin heiId. Fallback al campo user_institution.');
      $hei_id = $this->getHeiFromUser($user);
    }

    if ($hei_id) {
      $this->joinUserToHeiGroup($user, (string) $hei_id);
    }
  }

  /**
   * Lee la HEI desde el user (base field).
   */
  protected function getHeiFromUser(UserInterface $user): ?string {
    if ($user->hasField(self::USER_HEI_FIELD) && !$user->get(self::USER_HEI_FIELD)->isEmpty()) {
      $val = $user->get(self::USER_HEI_FIELD)->target_id ?? NULL;
      return $val !== NULL ? (string) $val : NULL;
    }
    return NULL;
  }

  /**
   * Busca el Group por HEI y añade al usuario (idempotente).
   */
  protected function joinUserToHeiGroup(UserInterface $user, string $hei_id): void {
  $this->logger->notice('[AUTOJOIN] Buscando Group type=' . self::GROUP_TYPE . ' con ' . self::GROUP_HEI_FIELD . '=' . $hei_id);

  // 💡 Importante en D10: declarar accessCheck(FALSE)
  // Además, al ser entity reference, apuntamos a la columna target_id.
  $query = \Drupal::entityQuery('group')
    ->accessCheck(FALSE)
    ->condition('type', self::GROUP_TYPE)
    ->condition(self::GROUP_HEI_FIELD . '.target_id', $hei_id)
    ->range(0, 1);

  $gids = $query->execute();

  // Fallback (por si algún storage no mapea bien el sufijo .target_id en tu sitio)
  if (empty($gids)) {
    $gids = \Drupal::entityQuery('group')
      ->accessCheck(FALSE)
      ->condition('type', self::GROUP_TYPE)
      ->condition(self::GROUP_HEI_FIELD, $hei_id)
      ->range(0, 1)
      ->execute();
  }

  if (empty($gids)) {
    $this->logger->warning('No hay Group (type=@type) con @field=@hei para user @uid.', [
      '@type'  => self::GROUP_TYPE,
      '@field' => self::GROUP_HEI_FIELD,
      '@hei'   => $hei_id,
      '@uid'   => $user->id(),
    ]);
    return;
  }

  /** @var \Drupal\group\Entity\GroupInterface $group */
  $group = \Drupal::entityTypeManager()->getStorage('group')->load(reset($gids));
  if (!$group instanceof GroupInterface) {
    $this->logger->error('GID encontrado pero no cargable (user @uid).', ['@uid' => $user->id()]);
    return;
  }

  if ($group->getMember($user)) {
    $this->logger->notice('User @uid ya es miembro del grupo @gid.', ['@uid' => $user->id(), '@gid' => $group->id()]);
    return;
  }

  try {
    $group->addMember($user /* , ['group_roles' => ['institution-member']] */);
    $this->logger->info('User @uid añadido al grupo @gid (HEI @hei).', [
      '@uid' => $user->id(),
      '@gid' => $group->id(),
      '@hei' => $hei_id,
    ]);
  }
  catch (\Throwable $e) {
    $this->logger->error('Error añadiendo user @uid al group @gid: @msg', [
      '@uid' => $user->id(),
      '@gid' => $group->id(),
      '@msg' => $e->getMessage(),
    ]);
  }
}

}
