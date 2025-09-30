<?php

namespace Drupal\extn_commerce\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class CheckoutCompleteAccessCheck implements AccessInterface {

  /**
   * Запретить свободный доступ к странице оформленного заказа.
   * Оставить для админов и владельца, в т.ч. неавторизованного.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param AccountInterface $account
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account)
  {
    $commerce_order = $route_match->getParameter('commerce_order');

    // Администраторы всегда могут просматривать заказ.
    if ($account->hasPermission('administer commerce_order')) {
      return AccessResult::allowed();
    }

    // Если заказ у авторизованного пользователя.
    if ($commerce_order->getCustomerId() && $account->id() == $commerce_order->getCustomerId()) {
      return AccessResult::allowed();
    }

    // Если заказ гостевой, проверим сессию.
    $session = \Drupal::service('session');
    $orders = $session->get('commerce_cart_orders', []);
    $completed_orders = $session->get('commerce_cart_completed_orders', []);
    if (in_array($commerce_order->id(), $orders)
      || in_array($commerce_order->id(), $completed_orders)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }
}
