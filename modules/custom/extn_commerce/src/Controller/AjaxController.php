<?php

namespace Drupal\extn_commerce\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\func_helper\Helper\CommerceHelper;

class AjaxController extends ControllerBase {

  /**
   * Обработчик AJAX-запроса.
   *
   * @param string $string
   *   Аргумент из URL.
   */
  public function getCities($string)
  {
    $html_list = '<ul class="input-dropdown-menu">';
    $list = CommerceHelper::getCitiesFromCDEK($string);
    if ($list->items) {
      foreach ($list->items as $city) {
        $city_array = explode(', ', $city->full_name);
        $city_name = array_shift($city_array);
        $city_region = implode(', ', $city_array);
        $html_list .= '<li class="input-dropdown-item" data-code="' . $city->code . '">' . $city_name . '<span>, ' . $city_region . '</span></li>';
      }
    } else {
      $html_list .= '<li class="input-dropdown-item">Совпадений не найдено</li>';
    }
    $html_list .= '</ul>';

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('.input-dropdown-menu', $html_list));

    return $response;
  }

}
