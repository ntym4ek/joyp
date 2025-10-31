<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Professionals Counter' Block.
 */

#[Block(
  id: "profcounter",
  admin_label: new TranslatableMarkup("Professionals Counter"),
  category: new TranslatableMarkup("Custom Block")
)]

class ProfCounter extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
