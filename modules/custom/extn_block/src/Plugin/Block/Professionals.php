<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Luxury Symbol' Block.
 */

#[Block(
  id: "professionals",
  admin_label: new TranslatableMarkup("Professionals"),
  category: new TranslatableMarkup("Custom Block")
)]

class Professionals extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
