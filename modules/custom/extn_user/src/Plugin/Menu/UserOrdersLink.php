<?php

namespace Drupal\extn_user\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\user\Entity\User;

class UserOrdersLink extends MenuLinkDefault {

  /**
   * {@inheritDoc}
   */
  public function getTitle() {
    return t('Orders');
  }

  /**
   * {@inheritDoc}
   */
  public function getRouteName() {
    return 'view.commerce_user_orders.order_page';
  }

  public function getRouteParameters() {
    $current_user = \Drupal::currentUser();
    if ($current_user->isAuthenticated()) {
      return ['user' => $current_user->id()];
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
