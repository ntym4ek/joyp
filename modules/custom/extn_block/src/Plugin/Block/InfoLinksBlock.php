<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;

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

    // получить Материалы, которые должны быть размещены в Информации
    // 'field_information_section' => 1,
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $storage->loadByProperties([
      'type' => 'page',
      'status' => 1,
      'field_information_section' => 1,
    ]);

    $links = [];
    foreach ($nodes as $node) {
      if ($current_path === '/node/' . $node->id()) {
        $links[] = [
          '#markup' => '<span>' . $node->label() . '</span>',
          '#wrapper_attributes' => ['class' => ['item--current']],
        ];
      }
      else {
        $links[] = [
          '#type' => 'link',
          '#url' => Url::fromUserInput('/node/' . $node->id()),
          '#title' => $node->label(),
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
