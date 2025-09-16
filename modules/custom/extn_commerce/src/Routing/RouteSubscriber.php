<?php

namespace Drupal\extn_commerce\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection)
  {
    // для страницы оформления заказа добавляем проверку досутпа
    if ($route = $collection->get('commerce_checkout.form')) {
      $route->setRequirement('_checkout_complete_access_check', 'TRUE');
    }
  }

}
