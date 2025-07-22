<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'AdvantagesCounter' Block.
 */

#[Block(
  id: "advantages_counter",
  admin_label: new TranslatableMarkup("Advantages Counter"),
  category: new TranslatableMarkup("Custom Block")
)]

class AdvantagesCounter extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
