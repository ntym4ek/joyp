<?php

namespace Drupal\extn_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

class NodePageBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Проверяет, применим ли builder к текущему маршруту.
   */
  public function applies(RouteMatchInterface $route_match) {
    // Пример: срабатываем только для нод.
    return $route_match->getRouteName() === 'entity.node.canonical';
  }

  /**
   * Собираем хлебные крошки.
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $links = [];
    $links[] = Link::fromTextAndUrl(t('Home'), Url::fromRoute('<front>'));

    if ($node = $route_match->getParameter('node')) {
      if ($node->bundle() == 'article') {
        $links[] = Link::fromTextAndUrl(t('Blog'), Url::fromUri('internal:/blog'));
        $links[] = Link::fromTextAndUrl($node->label(), Url::fromRoute('<none>'));
      }
      elseif ($node->bundle() == 'page') {
        // для страниц, кроме:
        //  - Исследования (id5)
        // вывести в крошках "Информация"
        if (!in_array($node->id(), [5])) $links[] = Link::fromTextAndUrl(t('Information'), Url::fromRoute('<none>'));
        $links[] = Link::fromTextAndUrl($node->label(), Url::fromRoute('<none>'));
      }
    }

    return $breadcrumb->setLinks($links);
  }
}
