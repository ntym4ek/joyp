<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Our Destiny' Block.
 */

#[Block(
  id: "our_destiny",
  admin_label: new TranslatableMarkup("Our Destiny"),
  category: new TranslatableMarkup("Custom Block")
)]

class OurDestiny extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
