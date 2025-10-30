<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Luxury Symbol' Block.
 */

#[Block(
  id: "training",
  admin_label: new TranslatableMarkup("Training"),
  category: new TranslatableMarkup("Custom Block")
)]

class Training extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
