<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Url;


/**
 * Provides an 'User Links' Block.
 * Блок ссылок в Личном кабинете
 */

#[Block(
  id: "user_links_block",
  admin_label: new TranslatableMarkup("User Links Block"),
  category: new TranslatableMarkup("Custom Block")
)]

class UserLinksBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $current_path = \Drupal::service('path.current')->getPath();

    // получить Ссылки из меню Пользователя
    $menu_link_tree = \Drupal::service('menu.link_tree');
    $parameters = new MenuTreeParameters();
    $parameters->setRoot('user.page');
    $parameters->onlyEnabledLinks();
    $parameters->setMaxDepth(2);
    $parameters->excludeRoot();
    $tree = $menu_link_tree->load('account', $parameters);

      // Применяем манипуляторы для сортировки
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $tree = $menu_link_tree->transform($tree, $manipulators);

    $links = [];
    foreach ($tree as $item) {
      $link = $item->link;
      $title = $link->getTitle();
      $url = $link->getUrlObject();

      if ($current_path === $url->toString()) {
        $links[] = [
          '#markup' => '<span>' . $title . '</span>',
          '#wrapper_attributes' => ['class' => ['item--current']],
        ];
      }
      else {
        $links[] = [
          '#type' => 'link',
          '#url' => $url,
          '#title' => $title,
        ];
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $links,
      '#cache' => [
        'contexts' => ['url.path'],
      ],
    ];
  }

}
