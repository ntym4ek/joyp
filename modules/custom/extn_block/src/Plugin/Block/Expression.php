<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Expression' Block.
 */

#[Block(
  id: "expression",
  admin_label: new TranslatableMarkup("Expression"),
  category: new TranslatableMarkup("Custom Block")
)]

class Expression extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
