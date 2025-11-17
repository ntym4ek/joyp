<?php

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\func_helper\Helper\CommerceHelper;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Implements hook_form_FORM_ID_alter()
 */
function extn_commerce_form_commerce_checkout_flow_multistep_default_alter(&$form, FormStateInterface $form_state): void
{
  // панель доставки
  if (isset($form["shipping_information"])) {
    $form['#attached']['library'][] = 'extn_commerce/cdek-widget';
    $form['#attached']['library'][] = 'extn_commerce/commerce-shipping-cdek';
    $form["shipping_information"]["shipping_profile"]["address"]["#access"] = false;
    $form["shipping_information"]["shipping_profile"]["field_user_phone"]["#access"] = false;
    $form["shipping_information"]["recalculate_shipping"]['#attributes']['class'][] = 'js-hide';
    $form["shipping_information"]["shipping_profile"]["#element_validate"][] = 'extn_commerce_shipping_profile_validate';
    $form["shipping_information"]["shipments"]["#element_validate"][] = 'extn_commerce_shipping_method_validate';
  }

  // модальное окно ввода сертификата
  if (isset($form["commerce_giftcard_redemption"])) {
    $form['#attached']['library'][] = 'extn_commerce/commerce-giftcard-modal';
    $form["commerce_giftcard_redemption"]["#prefix"] = '<div class="checkout-pane checkout-pane--promotions"><div class="checkout-pane__content">';
    $form["commerce_giftcard_redemption"]["form"]["code"]["#type"] = 'hidden';
    $form["commerce_giftcard_redemption"]["form"]["apply"]["#attributes"]['class'][] = 'js-hide';
    $form["commerce_giftcard_redemption"]["form"]["#element_validate"][] = 'extn_commerce_commerce_giftcard_redemption_element_validate';
    $form["commerce_giftcard_redemption"]["#title"] = Markup::create('');
    if (empty($form["commerce_giftcard_redemption"]["form"]["giftcards"])) {
      $form["commerce_giftcard_redemption"]["form"]['show_modal'] = ['#markup' => '<div class="button button--primary giftcard-modal-button">Применить сертификат</div>'];
    } else {
      $form["commerce_giftcard_redemption"]["form"]["giftcards"][0]["remove_button"]['#attributes']['class'][] = 'button--primary';
    }
  }

  // модальное окно ввода промокода
  if (isset($form["coupon_redemption"])) {
    $form['#attached']['library'][] = 'extn_commerce/commerce-promocode-modal';
    $form["coupon_redemption"]["#suffix"] = '</div></div>';
    $form["coupon_redemption"]["form"]["code"]["#type"] = 'hidden';
    $form["coupon_redemption"]["form"]["apply"]["#attributes"]['class'][] = 'js-hide';
    $form["coupon_redemption"]["form"]["#element_validate"][] = 'extn_commerce_coupon_redemption_element_validate';
    $form["coupon_redemption"]["#title"] = Markup::create('');
    if (empty($form["coupon_redemption"]["form"]["coupons"])) {
      $form["coupon_redemption"]["form"]['show_modal'] = ['#markup' => '<div class="button button--primary promocode-modal-button">Ввести промокод</div>'];
    } else {
      $form["coupon_redemption"]["form"]["coupons"][0]["remove_button"]['#attributes']['class'][] = 'button--primary';
    }
  }

  // панель оплаты
  if (isset($form["payment_information"]["payment_method"]["yoomoney"])) {
    $form["payment_information"]["payment_method"]["yoomoney"]['#description'] = 'Карты Visa, Mastercard, Maestro, Мир, JCB';
    $form["payment_information"]["billing_information"]["copy_fields"]['#access'] = false;
  }

  // добавить проверку телефона по СМС для анонимов
  if (\Drupal::currentUser()->isAnonymous()) {
    // элемент кода из СМС
    $form['sms_verification_code'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['form-with-verification-code']],
    ];

    $form['#prefix'] = '<div id="checkout-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'form-with-verification';
    $form['#attached']['library'][] = 'extn_user/user-auth-modal';
    $form["actions"]["next"]["#ajax"] = [
      'callback' => 'extn_commerce_checkout_form_ajax',
      'event' => 'click',
      'wrapper' => 'checkout-form-wrapper',
    ];
    $form["actions"]["next"]['#attributes']['class'][] = 'form-with-verification-submit';
    // выполняем валидацию с Верификацией номера после всех проверок
    $form["#validate"][] = 'extn_commerce_checkout_form_validate';
    array_unshift($form["#submit"], 'extn_commerce_checkout_form_submit');
    array_unshift($form["actions"]["next"]["#submit"], 'extn_commerce_checkout_form_submit');

  }
}

function extn_commerce_checkout_form_validate(&$form, FormStateInterface $form_state)
{
  if ($form_state->hasAnyErrors()) return;

  $contacts = $form_state->getValue('contact_information');
  $form_state->set('sms_verification_phone', $contacts['phone']);

  if ($form_state->getTriggeringElement()['#type'] == 'submit') {
    // сначала проверка, так как может быть запрошена повторная отправка кода
    extn_user_sms_check_code($form_state);
    if (!$form_state->get('sms_verification_passed')) {
      // Устанавливаем ошибку валидации кода, чтобы не сработали сабмиты.
      $form_state->setErrorByName('sms_verification_code');
      extn_user_sms_send_code($form_state);
    }
  }
}

function extn_commerce_checkout_form_submit(&$form, FormStateInterface $form_state)
{
  if ($form_state->getTriggeringElement()['#type'] == 'submit') {
    // На этом этапе проверка по СМС должна быть пройдена,
    // но дополнительно проверяем sms_verification_passed.
    // Также пользователь должен быть не авторизован.
    if ($form_state->get('sms_verification_passed') && \Drupal::currentUser()->isAnonymous()) {

      $checkout_flow = $form_state->getFormObject();
      $configuration = $checkout_flow->getConfiguration();
      $order = $checkout_flow->getOrder();
      $guest_new_account = $configuration['guest_new_account'] ?? FALSE;

      $user_phone = $form_state->get('sms_verification_phone');
      $user = extn_user_user_load_by_phone($user_phone);
      // Если пользователь с таким номером телефона не существует
      // и в настройках чекаута включено создание нового аккаунта, создаём.
      if (!$user && $guest_new_account) {
        $contacts = $form_state->getValue('contact_information');
        $email = $contacts['email'];

        $user = User::create([
          'name' => $email,
          'mail' => $email,
          'pass' => \Drupal::service('password_generator')->generate(10),
          'status' => TRUE,
        ]);
        $user->save();

        // для нового пользователя создать профиль с данными
        $profile = Profile::create([
          'type' => 'user',
          'uid' => $user->id(),
          'status' => TRUE,
          'field_user_phone' => $contacts['phone'],
          'field_user_surname' => $contacts['surname'],
          'field_user_name' => $contacts['name'],
          'field_user_name2' => $contacts['name2'],
        ]);
        $profile->save();
      }

      // привязываем заказ
      $order_assignment = \Drupal::service('commerce_order.order_assignment');
      $order_assignment->assign($order, $user, TRUE);

      // авторизовать пользователя
      user_login_finalize($user);
    }

  }
}

/**
 * AJAX callback для submit.
 */
function extn_commerce_checkout_form_ajax(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
{
  $response = new AjaxResponse();

  // если нет ошибок
  $errors = $form_state->getErrors();
  if (!$errors) {
    // редирект на следующий шаг
    $order = \Drupal::routeMatch()->getParameter('commerce_order');
    $step_id = $order->get('checkout_step')->value;
    $step_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $step_id,
    ]);
    $response->addCommand(new RedirectCommand($step_url->toString()));
  }
  // если ошибка проверки кода
  elseif (isset($errors['sms_verification_code'])) {
    $dialog = extn_user_sms_input_dialog($form_state);
    $response->addCommand(new OpenModalDialogCommand('Проверка номера телефона', $dialog, ['width' => '550']));
    \Drupal::messenger()->deleteAll();
  }

  $form['status_messages'] = [
    '#type' => 'status_messages',
    '#weight' => -10,
  ];

  $response->addCommand(new ReplaceCommand('#checkout-form-wrapper', $form));

  return $response;
}

/**
 * Implements hook_commerce_checkout_pane_info_alter().
 */
function extn_commerce_commerce_checkout_pane_info_alter(&$definitions)
{
  // замена панели чекаута ContactInformarion на свою
  if (isset($definitions['contact_information'])) {
    $definitions['contact_information']['class'] = \Drupal\extn_commerce\Plugin\Commerce\CheckoutPane\ContactInformation::class;
    $definitions['contact_information']['provider'] = 'extn_commerce';
  }
}

function extn_commerce_commerce_inline_form_info_alter(array &$inline_form)
{
  if (isset($inline_form['customer_profile'])) {
    // замена класса формы Customer Profile на свой
    $inline_form['customer_profile']['class'] = 'Drupal\extn_commerce\Plugin\Commerce\InlineForm\CustomerProfile';
  }
}

function extn_commerce_commerce_inline_form_customer_profile_alter(array &$inline_form, FormStateInterface $form_state, array &$complete_form)
{
  // Modify the shipping profile when in "form" mode.
  $is_admin = \Drupal::service('router.admin_context')->isAdminRoute();
  if (!$is_admin && $inline_form['#profile_scope'] == 'shipping' && !isset($inline_form['rendered'])) {

    // в зависимости от метода доставки изменить набор полей
    $shipping_method_id = 0;
    $checkout_flow = $form_state->getFormObject();
    $order = $checkout_flow->getOrder();
    if ($shipments = $order->get('shipments')->referencedEntities()) {
      $shipping_method_id = reset($shipments)->getShippingMethodId();
    }
    if ($shipping_method_id == SHIPPING_COURIER) {
      $inline_form["field_shipping_pvz"]["widget"][0]["value"]["#type"] = 'hidden';
    }
    if ($shipping_method_id == SHIPPING_PICKUP) {
      $inline_form["field_shipping_address"]["widget"][0]["value"]["#type"] = 'hidden';
    }

    // настраиваем поля для работы со СДЭК
    $inline_form["field_shipping_city"]["widget"][0]["value"]["#attributes"]["class"][] = 'shipping-city';
    $inline_form["field_shipping_address"]["widget"][0]["value"]["#attributes"]["class"][] = 'shipping-address';
    $inline_form["field_shipping_json"]["widget"][0]["value"]["#attributes"]["class"][] = 'shipping-json';
    $inline_form["field_shipping_json"]["widget"][0]["value"]["#type"] = 'hidden';
    $inline_form["field_shipping_pvz"]["widget"][0]["value"]["#attributes"]["class"][] = 'shipping-pvz';
    $inline_form["field_shipping_pvz"]["widget"][0]["value"]["#placeholder"] = 'кликните, чтобы открыть карту';

    // Подготовить список городов по введённому текстовому значению.
    // В основном нужно только для того, чтобы после выбора города и перезагрузки формы
    // выпадающий список не оказался пустым.
    // Изначально загружать список всех возможных городов затратно и нецелесообразно.
    // Пользователь начнёт набирать и тогда посмотрим...
    $cities = [];
    if ($user_input = $form_state->getUserInput()) {
      $string = Html::escape($user_input["shipping_information"]["shipping_profile"]["field_shipping_city"][0]["value"]);
    } else $string = $inline_form["field_shipping_city"]["widget"][0]["value"]["#default_value"];
    if ($string && $list = CommerceHelper::getCitiesFromCDEK($string)) {
      foreach ($list->items as $city) {
        $city_array = explode(', ', $city->full_name);
        $city_name = array_shift($city_array);
        $city_region = implode(', ', $city_array);
        $cities[$city->code] = Markup::create($city_name . '<span>, ' . $city_region . '</span>');
      }
    }
    if (!$cities) $cities = ['0' => 'Начните набирать и выберите из списка' ];
    $inline_form["field_shipping_city"]["widget"][0]["value"]['#dropdown_options'] = $cities;

    // Сменить оформление поля на выпадающий список с фильтром
    $inline_form["field_shipping_city"]["widget"][0]["value"]['#theme'] = 'input_dropdown_filter';
    // отключить фильтрацию при наборе
    $inline_form["field_shipping_city"]["widget"][0]["value"]['#attributes']['data-diacritics'] = 'off';
  }
}


function extn_commerce_coupon_redemption_element_validate(&$element, FormStateInterface $form_state): void
{
  // Если обнаружена ошибка при вводе купона, то сбросить введённое значение,
  // так как поле ввода скрыто и пользователь не сможет убрать
  // перед оформлением заказа неправильное значение.
  $errors = $form_state->getErrors();
  if (isset($errors["coupon_redemption][form][code"])) {
    $element["code"]["#value"] = '';
  }
}

function extn_commerce_commerce_giftcard_redemption_element_validate(&$element, FormStateInterface $form_state): void
{
  // Если обнаружена ошибка при вводе сертификата, то сбросить введённое значение,
  // так как поле ввода скрыто и пользователь не сможет убрать
  // перед оформлением заказа неправильное значение.
  $errors = $form_state->getErrors();
  if (isset($errors["commerce_giftcard_redemption][form][code"])) {
    $element["code"]["#value"] = '';
  }
}

function extn_commerce_shipping_profile_validate(&$element, FormStateInterface $form_state): void
{
  // в зависимости от выбранного метода, поле адреса или ПВЗ при сабмите не должно быть пустым
  $values = $form_state->getValue('shipping_information');
  $shipping_method = $values["shipments"][0]["shipping_method"][0];

  $triggering_element = $form_state->getTriggeringElement();
  if (empty($triggering_element["#recalculate"])) {
    if ($shipping_method == '1--default'
      && empty($values["shipping_profile"]["field_shipping_address"][0]["value"])) {
      $form_state->setErrorByName('shipping_information][shipping_profile][field_shipping_address', 'Задайте адрес для курьерской доставки');
    }
    if ($shipping_method == '2--default'
      && empty($values["shipping_profile"]["field_shipping_pvz"][0]["value"])) {
      $form_state->setErrorByName('shipping_information][shipping_profile][field_shipping_pvz', 'Выберите пункт выдачи заказов');
    }
  }

}

function extn_commerce_shipping_method_validate(&$element, FormStateInterface $form_state): void
{
  // в зависимости от выбранного метода, поле адреса или ПВЗ при сабмите не должно быть пустым
  $values = $form_state->getValue('shipping_information');
  $shipping_method = $values["shipments"][0]["shipping_method"][0];

  $rates = $form_state->get('shipping_information_shipments_0_shipping_method_0');
  $data = $rates[$shipping_method]->getData();
  if (!empty($data['errors'])) {
    $form_state->setErrorByName(implode('][', $element['#parents']), 'Выбранный метод доставки недоступен, выберите другой.');
  }
}

