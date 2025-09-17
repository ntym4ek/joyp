<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an 'Empty Block' Block.
 * Блок нужен добавляется на страницы, где контент должен отображаться с сайдбаром
 */

#[Block(
  id: "empty_block",
  admin_label: new TranslatableMarkup("Empty Block"),
  category: new TranslatableMarkup("Custom Block")
)]

class EmptyBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
