<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides an 'Info Links' Block.
 * Блок ссылок на информационные страницы
 */

#[Block(
  id: "info_links_block",
  admin_label: new TranslatableMarkup("Information Links Block"),
  category: new TranslatableMarkup("Custom Block")
)]

class InfoLinksBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $current_path = \Drupal::service('path.current')->getPath();
    $pages = [
      ['path' => '/node/6', 'title' => 'Доставка'],
      ['path' => '/node/7', 'title' => 'Оплата'],
      ['path' => '/node/8', 'title' => 'Обмен и возврат'],
//      ['path' => '/node/9', 'title' => 'Сотрудничество'],
    ];

//    $storage = \Drupal::entityTypeManager()->getStorage('node');

    // все Страницы с установленным кастомным (добавлен в extn_node) флагом
//    $nodes = $storage->loadByProperties([
//      'type' => 'page',
//      'information_section' => 1,
//    ]);

//    $pages = [];
//    foreach ($pages as $node) {
      // Работаем с нодой
//      $pages[] = ['path' => '/node/' . $node->id(), 'title' => $node->label()];
//    }


    foreach ($pages as $page) {
      if ($current_path === $page['path']) {
        $links[] = [
          '#markup' => '<span>' . $page['title'] . '</span>',
          '#wrapper_attributes' => ['class' => ['item--current']],
        ];
      }
      else {
        $links[] = [
          '#type' => 'link',
          '#url' => Url::fromUserInput($page['path']),
          '#title' => $page['title'],
        ];
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $links,
    ];
  }

}
