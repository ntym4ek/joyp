<?php

namespace Drupal\extn_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

class ProductPageBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Проверяет, применим ли builder к текущему маршруту.
   */
  public function applies(RouteMatchInterface $route_match)
  {
    return $route_match->getRouteName() && $route_match->getRouteName() === 'entity.commerce_product.canonical';
  }

  /**
   * Собираем хлебные крошки.
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $links = [];
    $links[] = Link::fromTextAndUrl(t('Home'), Url::fromRoute('<front>'));

    if ($product = $route_match->getParameter('commerce_product')) {
      // Если у ноды есть категория — добавляем её со ссылкой.
      if ($product->hasField('field_p_application') && !$product->get('field_p_application')->isEmpty()) {
        $term = $product->get('field_p_application')->entity;
        if ($term instanceof TermInterface) {
          $links[] = Link::createFromRoute(
            $term->label(), 'entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]
          );
        }
      }

      $links[] = Link::fromTextAndUrl($product->label(), Url::fromRoute('<none>'));
    }

    return $breadcrumb->setLinks($links);
  }
}
