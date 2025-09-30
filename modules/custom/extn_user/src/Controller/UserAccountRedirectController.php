<?php

namespace Drupal\extn_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserAccountRedirectController extends ControllerBase {

  protected $currentUser;

  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Редирект с /user/{user}.
   */
  public function redirectUser($user) {
    $url = Url::fromRoute('profile.user_page.single', [
      'user' => $user->id(),
      'profile_type' => 'user',
    ])->toString();

    return new RedirectResponse($url, 301);
  }

  /**
   * Редирект с /user (текущий пользователь).
   */
  public function redirectCurrent() {
    $uid = $this->currentUser->id();

    $url = Url::fromRoute('profile.user_page.single', [
      'user' => $uid,
      'profile_type' => 'user',
    ])->toString();

    return new RedirectResponse($url, 301);
  }

}
