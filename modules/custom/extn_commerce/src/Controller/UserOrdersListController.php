<?php

namespace Drupal\extn_commerce\Controller;

use Drupal\commerce_price\Price;
use Drupal\Core\Render\Markup;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\UserInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom user profile controller.
 */
class UserOrdersListController {

  /**
   * Displays user profile page.
   */
  public function view(UserInterface $user)
  {
    // собрать заказы пользователя с детализацией по покупкам и вывести в Аккордеон

    $build = [
      '#theme' => 'user_orders_list',
      '#orders' => $this->get_user_orders($user),
    ];

    return $build;
  }

  /**
   * Checks access for the user profile page.
   */
  public function access(UserInterface $user, AccountInterface $account)
  {
    return AccessResult::allowedIf($user && $account->isAuthenticated());
  }

  /**
   * Получить список заказов пользователя с товарами.
   *
   * @param \Drupal\user\UserInterface $user
   *   Объект пользователя.
   *
   * @return array
   *   Массив заказов с деталями.
   */
  private function get_user_orders(UserInterface $user)
  {
    $orders = \Drupal::entityTypeManager()
      ->getStorage('commerce_order')
      ->loadByProperties([
        'uid' => $user->id(),
      ]);

    $result = [];
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');

    /** @var \Drupal\commerce_order\Entity\Order $order */
    foreach ($orders as $order) {
      // кроме корзин
      if ($order->get('state')->value != 'draft') {

        $promotions = [];

        $total_price = $order->getTotalPrice();

        $order_data = [
          'id' => $order->id(),
          'number' => $order->get("order_number")->value,
          'created' => $order->get("created")->value,
          'state' => t($order->getState()->getLabel()),
          'total_price' => $total_price,
          'items' => [],
          'summary' => [],
        ];

        // собрать покупки
        foreach ($order->getItems() as $order_item) {
          /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
          $purchased_entity = $order_item->getPurchasedEntity(); // это product variation
          $product = $purchased_entity?->getProduct();
          $price = $order_item->getUnitPrice();
          $total_price = $order_item->getTotalPrice();

          $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product');
          $build = $view_builder->view($product, 'cart');

          $order_data['items'][] = [
            'title' => $order_item->getTitle(),
            'quantity' => (integer) $order_item->getQuantity(),
            'price' => $price,
            'total_price' => $total_price,
            'product' => $build,
          ];

          // собрать Adjustments
          foreach ($order_item->getAdjustments() as $adjustment) {
            if ($adjustment->getType() == 'promotion') {
              if (!isset($promotions[$adjustment->getLabel()])) {
                $promotions[$adjustment->getLabel()] = [
                  'label' => $adjustment->getLabel(),
                  'notes' => '',
                  'amount' => $adjustment->getAmount(),
                ];
              } else {
                $promotions[$adjustment->getLabel()]['amount'] = $promotions[$adjustment->getLabel()]['amount']->add($adjustment->getAmount());
              }
            }
          }
        }

        // подготовить summary
          // строка предварительной стоимости и количества
        $order_data['summary'][] = [
          'label' => 'Товары (' . count($order_data['items']) . ' шт.)',
          'notes' => '',
          'amount' => $order->getSubTotalPrice(),
        ];


          // строки модификаторов цены
        foreach ($order->getAdjustments() as $adjustment) {
          $type = $adjustment->getType();
          $notes = '';
          $label = $adjustment->getLabel();
          $amount = $adjustment->getAmount();

          if ($type == 'shipping') {
            // для доставки определить адрес
            if ($shipments = $order->get('shipments')->referencedEntities()) {
              $shipping_method = reset($shipments)->getShippingMethod();
              $shipping_label = $shipping_method->getPlugin()->getLabel();
              $shipping_address = [];

              $address = '';
              if ($profile = reset($shipments)->getShippingProfile()) {
                if ($city = $profile->get('field_shipping_city')->value) $shipping_address[] = $city;
                if ($shipping_method->id() == SHIPPING_COURIER)     $address = $profile->get('field_shipping_address')->value;
                elseif ($shipping_method->id() == SHIPPING_PICKUP)  $address = $profile->get('field_shipping_pvz')->value;
                if ($address) $shipping_address[] = $address;
              }

              $notes = Markup::create($shipping_label . '<br>' . implode(', ', $shipping_address));
            }
          }

          if ($type == 'promotion') {
            if (!isset($promotions[$adjustment->getLabel()])) {
              $promotions[$adjustment->getLabel()] = [
                'label' => $adjustment->getLabel(),
                'notes' => '',
                'amount' => $adjustment->getAmount(),
              ];
            } else {
              $promotions[$adjustment->getLabel()]['amount'] = $promotions[$adjustment->getLabel()]['amount']->add($adjustment->getAmount());
            }
          }

          $order_data['summary'][] = [
            'label' => $label,
            'notes' => $notes,
            'amount' => $amount,
          ];
        }

        $order_data['summary'] += $promotions;

        // строка Итого
        $order_data['summary'][] = [
          'label' => 'ИТОГО:',
          'notes' => '',
          'amount' => $order->getTotalPrice(),
        ];

        $result[] = $order_data;
      }
    }

    return $result;
  }

}
