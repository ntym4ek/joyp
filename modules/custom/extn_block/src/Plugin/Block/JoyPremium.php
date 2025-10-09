<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'JOYPREMIUM' Block.
 */

#[Block(
  id: "joy_premium",
  admin_label: new TranslatableMarkup("JOY PREMIUM"),
  category: new TranslatableMarkup("Custom Block")
)]

class JoyPremium extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
