<?php

namespace Drupal\func_helper\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;

class Page404Controller extends ControllerBase {
  public function content()
  {
    return [
      '#theme' => 'markup_404',
    ];
  }
}
