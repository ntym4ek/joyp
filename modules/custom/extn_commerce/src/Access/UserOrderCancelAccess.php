<?php

namespace Drupal\extn_commerce\Access;


use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Проверяет, что пользователь — владелец заказа и можно выполнить transition 'cancel'.
 */
class UserOrderCancelAccess implements AccessInterface
{

  public function access(OrderInterface $commerce_order, AccountInterface $current_user)
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
