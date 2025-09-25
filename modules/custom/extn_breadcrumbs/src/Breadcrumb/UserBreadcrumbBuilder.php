<?php

namespace Drupal\extn_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

class UserBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Проверяет, применим ли builder к текущему маршруту.
   */
  public function applies(RouteMatchInterface $route_match)
  {
    return $route_match->getRouteName() && explode('.', $route_match->getRouteName())[0] === 'user';
  }

  /**
   * Собираем хлебные крошки.
   */
  public function build(RouteMatchInterface $route_match)
  {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $route_parts = explode('.', $route_match->getRouteName());

    $links = [];
    $links[] = Link::fromTextAndUrl(t('Home'), Url::fromRoute('<front>'));

    if (!empty($route_parts[1])) {
      if ($route_parts[1] == 'login') $links[] = Link::fromTextAndUrl('Вход', Url::fromRoute('<none>'));
      elseif ($route_parts[1] == 'register') $links[] = Link::fromTextAndUrl('Регистрация', Url::fromRoute('<none>'));
      elseif ($route_parts[1] == 'pass') $links[] = Link::fromTextAndUrl('Восстановление пароля', Url::fromRoute('<none>'));
    }

    return $breadcrumb->setLinks($links);
  }
}
