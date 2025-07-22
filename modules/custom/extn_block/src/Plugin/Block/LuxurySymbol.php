<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Luxury Symbol' Block.
 */

#[Block(
  id: "luxury_symbol",
  admin_label: new TranslatableMarkup("Luxury Symbol"),
  category: new TranslatableMarkup("Custom Block")
)]

class LuxurySymbol extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
