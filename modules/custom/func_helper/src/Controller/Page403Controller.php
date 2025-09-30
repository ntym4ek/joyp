<?php

namespace Drupal\func_helper\Controller;

use Drupal\Core\Controller\ControllerBase;

class Page403Controller extends ControllerBase {
  public function content()
  {
    return [
      '#theme' => 'markup_403',
    ];
  }
}
