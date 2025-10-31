<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Professionals Join' Block.
 */

#[Block(
  id: "profjoin",
  admin_label: new TranslatableMarkup("Professionals Join"),
  category: new TranslatableMarkup("Custom Block")
)]

class ProfJoin extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
