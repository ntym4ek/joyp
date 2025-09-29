<?php

namespace Drupal\extn_commerce\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection)
  {
    // для страницы оформления заказа добавляем проверку доступа
    if ($route = $collection->get('commerce_checkout.form')) {
      $route->setRequirement('_checkout_complete_access_check', 'TRUE');
    }

    // заменить вывод списка заказов в ЛК на свой
    if ($route = $collection->get('view.commerce_user_orders.order_page')) {
      $route->setDefault('_controller', '\Drupal\extn_commerce\Controller\UserOrdersListController::view');
      $route->setRequirement('_custom_access', '\Drupal\extn_commerce\Controller\UserOrdersListController::access');
    }
  }

}
