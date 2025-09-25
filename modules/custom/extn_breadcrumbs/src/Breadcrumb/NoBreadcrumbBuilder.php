<?php

namespace Drupal\extn_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

class NoBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Проверяет, применим ли builder к текущему маршруту.
   */
  public function applies(RouteMatchInterface $route_match)
  {
    return !$route_match->getRouteName();
  }

  /**
   * Собираем хлебные крошки.
   */
  public function build(RouteMatchInterface $route_match)
  {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $links = [];

    return $breadcrumb->setLinks($links);
  }
}
