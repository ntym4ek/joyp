<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'ProductCategories' Block.
 */

#[Block(
  id: "product_categories",
  admin_label: new TranslatableMarkup("Product Categories"),
  category: new TranslatableMarkup("Custom Block")
)]

class ProductCategories extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $img_path = \Drupal::service('file_url_generator')->generateString('public://images/temp/');

    return [
      '#theme' => 'extn_block_product_categories',
      '#categories' => [
        [
          'title' => 'Для волос',
          'image' => ['url' => $img_path. '/cat-hair.png'],
          'url' => '',
        ],
        [
          'title' => 'Для рук',
          'image' => ['url' => $img_path. '/cat-hands.png'],
          'url' => '',
        ],
        [
          'title' => 'Для тела',
          'image' => ['url' => $img_path. '/cat-body.png'],
          'url' => '',
        ],
        [
          'title' => 'Для дома',
          'image' => ['url' => $img_path. '/cat-home.png'],
          'url' => '',
        ],
        [
          'title' => 'TRAVEL',
          'image' => ['url' => $img_path. '/cat-travel.png'],
          'url' => '',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
