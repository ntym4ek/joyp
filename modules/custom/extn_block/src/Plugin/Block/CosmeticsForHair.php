<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Cosmetics For Hair' Block.
 */

#[Block(
  id: "cosmetics_for_hair",
  admin_label: new TranslatableMarkup("Cosmetics For Hair"),
  category: new TranslatableMarkup("Custom Block")
)]

class CosmeticsForHair extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
