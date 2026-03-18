<?php

namespace Drupal\extn_commerce\Plugin\Commerce\Condition;

use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides the 'First order' condition for promotions.
 */
#[CommerceCondition(
  id: "order_is_first",
  label: new TranslatableMarkup("First order"),
  entity_type: "commerce_order",
  display_label: new TranslatableMarkup("Is this the customer's first order"),
  category: new TranslatableMarkup("Customer"),
)]
class OrderIsFirst extends ConditionBase {

  public function evaluate(EntityInterface $entity) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $customer = $order->getCustomer();

    if ($customer->isAnonymous()) return TRUE;
//    if ($customer->isAnonymous()) {
//      // Для анонимов можно проверять по email, если он уже введен
//      $email = $order->getEmail();
//      if (empty($email)) { $email = $_POST["contact_information"]["email"] ?? ''; }
//      if (!$email) {
//        return FALSE;
//      }
//      $order_ids = \Drupal::entityQuery('commerce_order')
//        ->condition('mail', $email)
//        ->condition('state', 'draft', '<>')
//        ->range(0, 1)
//        ->accessCheck(FALSE)
//        ->execute();
//      return empty($order_ids);
//    }

    // Для авторизованных проверяем количество заказов через storage
    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $query = $order_storage->getQuery()
      ->condition('uid', $customer->id())
      ->condition('state', 'draft', '<>')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->count();

    return $query->execute() == 0;
  }
}
