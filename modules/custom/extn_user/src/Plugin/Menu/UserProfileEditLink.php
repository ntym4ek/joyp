<?php

namespace Drupal\extn_user\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\user\Entity\User;

class UserProfileEditLink extends MenuLinkDefault {

  /**
   * {@inheritDoc}
   */
  public function getTitle() {
    return 'Личные данные';
  }

  /**
   * {@inheritDoc}
   */
  public function getRouteName() {
    return 'profile.user_page.single';
  }

  public function getRouteParameters() {
    $current_user = \Drupal::currentUser();
    if ($current_user->isAuthenticated()) {
      return [
        'user' => $current_user->id(),
        'profile_type' => 'user',
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.roles:authenticated'];
  }

}
