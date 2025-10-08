<?php

namespace Drupal\extn_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\ContactInformation as BaseContactInformation;
use Drupal\profile\Entity\Profile;

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
      $surname = $address_item->family_name ?? '';
      $name = $address_item->given_name ?? '';
      $name2 = $address_item->additional_name ?? '';
    }

    $user = $this->order->getCustomer();
    if ($user->isAuthenticated()) {
      $profiles = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'type' => 'user',
          'uid' => $user->id(),
        ]);
      $profile = reset($profiles);

      if ($profile instanceof Profile) {
        $phone = $phone ?? $profile->get('field_user_phone')->value;
        $surname = $surname ?? $profile->get('field_user_surname')->value;
        $name = $name ?? $profile->get('field_user_name')->value;
        $name2 = $name2 ?? $profile->get('field_user_name2')->value;
      }
    }


    $pane_form['email']['#disabled'] = $user->isAuthenticated();

    $pane_form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#disabled' => $user->isAuthenticated(),
      '#default_value' => $phone ?? '',
    ];

    $pane_form['surname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Surname', [], ['context' => 'user']),
      '#required' => TRUE,
      '#weight' => -1,
      '#default_value' => $surname ?? '',
    ];

    $pane_form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name', [], ['context' => 'user']),
      '#required' => TRUE,
      '#weight' => -1,
      '#default_value' => $name ?? '',
    ];

    $pane_form['name2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second name', [], ['context' => 'user']),
      '#weight' => -1,
      '#default_value' => $name2 ?? '',
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

    // проверка корректности ввода номера телефона
    $contacts = $form_state->getValue('contact_information');
    $user_phone = $contacts['phone'];

    // нормализовать номер
    $normalizer = \Drupal::service('func_helper.phone_normalizer');
    $user_phone_normalized = $normalizer->normalize($user_phone);

    // проверить наличие пользователей с таким номером
    if (!$user_phone_normalized) {
      $form_state->setError($pane_form['phone'], 'Указан некорректный номер телефона.');
    }
    // сохраняем нормализованный номер телефона
    $contacts['phone'] = $user_phone_normalized;
    $form_state->setValue('contact_information', $contacts);

    // Для анонимного пользователя.
    // Если указанного номера телефона в базе не существует, то проверить отсутствие регистраций с таким email.
    // Если существует, то нужно проверить, что email принадлежит этому же аккаунту.
    if (\Drupal::currentUser()->isAnonymous()) {
      $user_with_phone = extn_user_user_load_by_phone($user_phone_normalized);

      $user_storage = \Drupal::entityTypeManager()->getStorage('user');
      $user_with_email = $user_storage->loadByProperties(['mail' => $contacts['email']]);
      $user_with_email = reset($user_with_email);

      if ($user_with_email) {
        if (!$user_with_phone || $user_with_email->id() != $user_with_phone->id()) {
          $form_state->setError($pane_form['email'], 'Указанный E-Mail уже зарегистрирован пользователем с другим номером телефона.');
        }
      }
    }
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
        $form_state->set('shipping_profile', $shipping_profile);
      }
    }
  }

}
