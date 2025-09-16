<?php

namespace Drupal\extn_commerce\Plugin\Commerce\InlineForm;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Plugin\Commerce\InlineForm\CustomerProfile as BaseCustomerProfile;

/**
 * Provides the contact information pane.
 */
class CustomerProfile extends BaseCustomerProfile {

  /**
   * {@inheritDoc}
   */
  protected function shouldRender(array $inline_form, FormStateInterface $form_state)
  {
    $render = parent::shouldRender($inline_form, $form_state);
    // Всегда возвращать форму ввода данных профиля Покупателя,
    // вместо вывода значений полей с кнопкой "Редактировать"
    if ($inline_form['#profile_scope'] === 'shipping') {
      return FALSE;
    }

    return $render;
  }
}
