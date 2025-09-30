<?php

namespace Drupal\extn_commerce\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Проверяет, что пользователь — владелец заказа и можно выполнить transition 'cancel'.
 */
class UserOrderCancelAccess
{

  public function access($user, OrderInterface $commerce_order, AccountInterface $current_user)
  {
    // Проверяем авторизованного пользователя.
    if ($current_user->isAnonymous()) {
      return AccessResult::forbidden();
    }

    // Владелец заказа
    $owner_id = $commerce_order->getCustomerId();
    if ($owner_id != $current_user->id()) {
      return AccessResult::forbidden();
    }

    // Проверяем, доступен ли переход 'cancel' для текущего состояния.
    $state = $commerce_order->getState();
    // Получаем возможные транзиты
    $transitions = $state->getTransitions();
    foreach ($transitions as $transition) {
      if ($transition->getId() === 'cancel') {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }
}
