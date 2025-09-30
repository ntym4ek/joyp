<?php

namespace Drupal\extn_user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Профиль текущего пользователя: /user
    if ($route = $collection->get('user.page')) {
      $route->setDefault('_controller', '\Drupal\extn_user\Controller\UserAccountRedirectController::redirectCurrent');
    }

    // Профиль конкретного пользователя: /user/{user}
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setDefault('_controller', '\Drupal\extn_user\Controller\UserAccountRedirectController::redirectUser');
    }
  }

}
