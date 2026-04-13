<?php

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\KernelTests\Core\Test\TestRunTest;
use Drupal\smsru\Auth\ApiIdAuth;
use Drupal\smsru\Client\HttpClient;
use Drupal\smsru\Message\Message;
use Drupal\smsru\SmsRu;
use Drupal\user\Entity\User;

/**
 * Implements hook_form_FORM_ID_alter()
 */
function extn_user_form_user_login_form_alter(&$form, FormStateInterface $form_state): void
{
  $form['#prefix'] = '<div id="user-login-form-wrapper">';
  $form['#suffix'] = '</div>';
  $form['#attributes']['class'][] = 'form-with-verification';

  $form['#attached']['library'][] = 'extn_user/user-auth-modal';

  $form['name']["#title"] = 'E-Mail или номер телефона';
  $form['name']['#element_validate'][] = 'ext_user_login_name_validate';

  $form['pass']["#required"] = false;
  $form['pass']['#attributes']['class'][] = 'form-with-verification-pass';
  $form['pass']['#wrapper_attributes']['class'][] = 'js-hide';

  $form['actions']["register"] = [
    "#markup" => '<a href="/user/register" class="button button--outline button--default">Регистрация</a>',
  ];

  // на случай авторизации по коду из СМС
  $form['sms_verification_code'] = [
    '#type' => 'hidden',
    '#attributes' => ['class' => ['form-with-verification-code']],
  ];

  // выполняем валидацию с Верификацией номера или паролем после всех проверок
  $form["#validate"][] = 'extn_user_form_user_login_form_validate';
  array_unshift($form["#submit"], 'extn_user_form_user_login_form_submit');

  // кнопка для перехода к вводу кода или пароля
  // показываем только если доступен js
  $form['actions']["next"] = [
    '#type' => 'button',
    '#value' => 'Войти',
    '#name' => 'next',
    '#weight' => -1,
    '#attributes' => ['class' => ['button--primary', 'js-show']],
    '#ajax' => [
      'callback' => 'extn_user_form_user_login_form_ajax',
      'event' => 'click',
      'wrapper' => 'user-login-form-wrapper',
    ],
  ];

  // скрываем кнопку сабмита
  $form['actions']['submit']['#attributes']['class'][] = 'form-with-verification-submit';
  $form['actions']['submit']['#attributes']['class'][] = 'js-hide';
  $form['actions']["submit"]['#ajax'] = [
    'callback' => 'extn_user_form_user_login_form_ajax',
    'event' => 'click',
    'wrapper' => 'user-login-form-wrapper',
  ];
}

function ext_user_login_name_validate($element, $form_state)
{
  if ($form_state->hasAnyErrors()) return;

  if ($login = $form_state->getValue('name')) {
    // попробовать нормализовать как номер телефона
    $normalizer = \Drupal::service('func_helper.phone_normalizer');
    // если это номер телефона
    if ($user_phone_normalized = $normalizer->normalize($login)) {
      // если есть такой пользователь
      if ($user = extn_user_user_load_by_phone($user_phone_normalized)) {
        // будем авторизоваться по СМС
        $form_state->set('auth_type', 'sms');
        $form_state->set('auth_uid', $user->id());
        $form_state->set('sms_verification_phone', $user_phone_normalized);
        $form_state->setValue('name', $user->get('name')->value);
      } else {
        $form_state->setErrorByName(implode('][', $element['#parents']), 'Пользователь с таким номером не зарегистрирован.');
      }
    } // пробуем найти по e-mail
    elseif ($user = extn_user_user_load_by_login($login)) {
      $form_state->set('auth_type', 'pass');
      $form_state->set('auth_uid', $user->id());
      $form_state->setValue('name', $user->get('name')->value);
    }
  }
}

function extn_user_form_user_login_form_validate(&$form, FormStateInterface $form_state)
{
  // если сработал стандартный антифлуд, вывести сообщение
  if ($form_state->get('flood_control_triggered')) {
    $url_string = '/forma/zadat-vopros?t=' . urlencode('Не получается войти на сайт с логином ' . $form_state->get('name') . '. Блокировка после пяти неудачных попыток.');
    $form_state->setErrorByName('name', Markup::create('Слишком много неудачных попыток входа. <a href="' . $url_string . '" target="_blank">Напишите нам</a> об ошибке или попробуйте через 6 часов.'));
  }

  $errors = $form_state->getErrors();

  // Если пользователь указал email, то вывести окно ввода пароля.
  // При неудачной проверке пары логин-пароль, сообщение об ошибке выводится в поле логина в форме.
  // Пользователь заново должен нажать сабмит для ввода пароля.
  if ($form_state->get('auth_type') == 'pass') {

    // Если нажата кнопка перехода к вводу пароля или кода,
    // сбросить ошибку неудачной авторизации, установленной стандартным валидатором.
    if ($form_state->getTriggeringElement()['#name'] == 'next') {
      if (!empty($errors['name'])) $form_state->clearErrors();
      // Устанавливаем ошибку проверки пароля, чтобы не сработали сабмиты.
      $form_state->setErrorByName('pass');
    }
    else {
      // todo зачем здесь удаление пароля?
      $input = $form_state->getUserInput();
      $input['pass'] = NULL;
      $form_state->setUserInput($input);
    }
  }

  // Если пользователь указал номер телефона, то вывести окно ввода кода.
  // При неправильном коде окно выводится снова с сообщением об ошибке.
  if ($form_state->get('auth_type') == 'sms') {
    // Сбросить ошибку неудачной авторизации, установленной стандартным валидатором.
    if (!empty($errors['name'])) $form_state->clearErrors();

    // Сначала проверка, так как может быть запрошена повторная отправка кода.
    extn_user_sms_check_code($form_state);
    if (!$form_state->get('sms_verification_passed')) {
      // Устанавливаем ошибку валидации кода, чтобы не сработали сабмиты.
      $form_state->setErrorByName('sms_verification_code');
      $response = extn_user_sms_send_code($form_state);
      // в случае ошибки при отправке кода, вывести сообщение
      if ($response && $response->getStatusCode() != 100) {
        $url_string = '/forma/zadat-vopros?t=' . urlencode('При попытке входа на сайт по номеру телефона ' . $form_state->get('name') . ' возникла ошибка с кодом: ' . $response->getStatusCode());
        $form_state->setErrorByName('name', Markup::create('Ошибка при отправке кода. <a href="' . $url_string . '" target="_blank">Напишите нам</a> об ошибке или попробуйте позже.'));
      }
    }
  }
}

function extn_user_form_user_login_form_submit(&$form, FormStateInterface $form_state)
{
  // авторизуем пользователя, если это была верификация по СМС
  if ($form_state->get('sms_verification_passed')) {
    $account = User::load($form_state->get('auth_uid'));
    user_login_finalize($account);
    $form_state->set('uid', $account->id());
  }
}

/**
 * AJAX callback для submit.
 */
function extn_user_form_user_login_form_ajax(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
{
  $response = new AjaxResponse();

  $errors = $form_state->getErrors();

  // если нет ошибок
  if (!$errors) {
    // и пользователь авторизован
    if ($form_state->get('uid')) {
      $response->addCommand(new RedirectCommand('/'));
    }
  }
  // ошибки валидации
  else {
    // если ошибка проверки имени или при отправке СМС
    if (isset($errors['name'])) {
      // убрать всплывающее с ошибкой
      \Drupal::messenger()->deleteAll();
    }
    // если ошибка проверки кода
    elseif ($form_state->get('auth_type') == 'sms' && isset($errors['sms_verification_code'])) {
      $dialog = extn_user_sms_input_dialog($form_state);
      $response->addCommand(new OpenModalDialogCommand('Проверка номера телефона', $dialog, ['width' => '550']));
      /// убрать всплывающее с ошибкой sms_verification_code
      \Drupal::messenger()->deleteAll();
    }
    // если ошибка отсутствия пароля (pass - задаётся программно, при отсутствии пароля)
    elseif ($form_state->get('auth_type') == 'pass' && isset($errors['pass'])) {
      $dialog = extn_user_pass_input_dialog($form_state);
      $response->addCommand(new OpenModalDialogCommand('Пароль', $dialog, ['width' => '550']));
      // убрать всплывающее с ошибкой pass
      \Drupal::messenger()->deleteAll();
    }
  }

  $form['status_messages'] = [
    '#type' => 'status_messages',
    '#weight' => -10,
  ];
  $response->addCommand(new ReplaceCommand('#user-login-form-wrapper', $form));

  return $response;
}

