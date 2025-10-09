<?php

namespace Drupal\extn_breadcrumbs\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

class ProductListBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Проверяет, применим ли builder к текущему маршруту.
   */
  public function applies(RouteMatchInterface $route_match)
  {
    return $route_match->getRouteName() && $route_match->getRouteName() === 'entity.taxonomy_term.canonical';
  }

  /**
   * Собираем хлебные крошки.
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    $links = [];
    $links[] = Link::fromTextAndUrl(t('Home'), Url::fromRoute('<front>'));

    if ($view_id = $route_match->getParameter('view_id')) {
      if ($view_id == 'blog') {
        $links[] = Link::fromTextAndUrl('Блог', Url::fromUserInput('/blog'));
      }
      if ($view_id == 'katalog') {
        $links[] = Link::fromTextAndUrl('Каталог', Url::fromUserInput('/catalog'));
      }
    }

    if ($term = $route_match->getParameter('taxonomy_term')) {
      if ($view_id == 'taxonomy_term') {
        $vocabulary_id = $term->bundle();
        if ($vocabulary = Vocabulary::load($vocabulary_id)) {
          $links[] = Link::fromTextAndUrl($vocabulary->label(), Url::fromRoute('<none>'));
        }
      }

      $prefix = $view_id == 'blog' ? '#' : '';
      $links[] = Link::fromTextAndUrl($prefix . $term->label(), Url::fromRoute('<none>'));
    }

    return $breadcrumb->setLinks($links);
  }
}
