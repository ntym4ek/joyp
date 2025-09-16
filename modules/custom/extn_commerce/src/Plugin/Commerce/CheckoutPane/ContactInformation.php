<?php

namespace Drupal\extn_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\ContactInformation as BaseContactInformation;

/**
 * Provides the contact information pane.
 */
class ContactInformation extends BaseContactInformation {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form)
  {

    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);

    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($this->order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      break;
    }

    if ($shipping_profile) {
      $address_item = $shipping_profile->get('address')->first();
      $phone = $shipping_profile->get('field_user_phone')->value;
    }


    $pane_form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#default_value' => $phone ?? '',
    ];

    $pane_form['surname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname', [], ['context' => 'user']),
      '#required' => TRUE,
      '#weight' => -1,
      '#default_value' => $address_item->family_name ?? '',
    ];

    $pane_form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name', [], ['context' => 'user']),
      '#required' => TRUE,
      '#weight' => -1,
      '#default_value' => $address_item->given_name ?? '',
    ];

    $pane_form['name2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second name', [], ['context' => 'user']),
      '#weight' => -1,
      '#default_value' => $address_item->additional_name ?? '',
    ];

    // Чтобы сохранить информацию в профиле пользователя до остальных CheckoutPanes,
    // делаем это не в стандартном submitPaneForm, а в сабмите, заданном в #commerce_element_submit,
    // так как он срабатывает раньше.
    // Данные профилей shipping_profile и billing_profile сохраняются именно так
    // и при использовании стандартного submit, информация в профилях начинала отличаться.
    // Оба профиля сохраняли старые значения, а потом местный submitPaneForm обновлял данные профиля доставки.
//    $pane_form['#commerce_element_submit'][] = [get_class($this), 'elementsSubmit'];


    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    parent::validatePaneForm($pane_form, $form_state, $complete_form);

    // todo проверка введённых значений
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    parent::submitPaneForm($pane_form, $form_state, $complete_form);

    // Cохранить данные панели в профиле Покупателя.
    // Данные в billing_profile не обновляются,
    // todo при необходимости добавить его обновление или убрать его создание вообще.
    $pane_values = $form_state->getValue($pane_form['#parents']);

    $shipping_profile = $form_state->get('shipping_profile');

    if ($shipping_profile) {
      if ($shipping_profile->hasField('address') && !$shipping_profile->get('address')->isEmpty()) {
        $address_item = $shipping_profile->get('address')->first();
        $address_item->given_name = $pane_values["name"];
        $address_item->family_name = $pane_values["surname"];
        $address_item->additional_name = $pane_values["name2"];


        if ($shipping_profile->hasField('field_user_phone')) {
          $shipping_profile->set('field_user_phone', $pane_values["phone"]);
        }

        $shipping_profile->save();
      }
    }
  }

}
