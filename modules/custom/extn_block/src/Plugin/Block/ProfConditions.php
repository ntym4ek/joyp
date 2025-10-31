<?php

namespace Drupal\extn_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Professionals Conditions' Block.
 */

#[Block(
  id: "profconditions",
  admin_label: new TranslatableMarkup("Professionals Conditions"),
  category: new TranslatableMarkup("Custom Block")
)]

class ProfConditions extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
