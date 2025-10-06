<?php

const USER_FLOOD_ATTEMPTS = 5;
const USER_FLOOD_COOLDOWN = 310;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\smsru\Auth\ApiIdAuth;
use Drupal\smsru\Client\HttpClient;
use Drupal\smsru\Message\Message;
use Drupal\smsru\SmsRu;


/**
 * Implements hook_form_FORM_ID_alter()
 */
function extn_user_form_user_register_form_alter(&$form, FormStateInterface $form_state): void
{
  $form['#prefix'] = '<div id="user-register-form-wrapper">';
  $form['#suffix'] = '</div>';

  // скрыть имя пользователя
  $form["account"]["name"]["#access"] = false;
  $passwordGenerator = \Drupal::service('password_generator');
  $form['account']['name']['#value'] = 'ext_user_' . $passwordGenerator->generate(10);
  $form["account"]["pass"]["#required"] = false;

  $form["account"]["mail"]["#description"] = '';
  $form["user_profiles"]["widget"][0]["#type"] = 'container';


  // добавляем функционал проверки номера телефона через СМС

  $form["user_profiles"]["widget"][0]["entity"]["field_user_phone"]["widget"][0]['value']["#element_validate"][] = 'extn_user_form_field_user_phone_element_validate';

  $form['#attached']['library'][] = 'extn_user/verification-code-modal';

  if ($form_state->get('sms_verification_required')) {
    $form['#attributes']['class'][] = 'form-with-verification';
    $form['actions']['submit']['#attributes']['class'][] = 'form-with-verification-submit';
    $form['sms_verification_code'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['form-with-verification-code']],
    ];

    $field_user_phone = $form_state->getValue('user_profiles')[0]["entity"]["field_user_phone"][0]['value'];

    // Проверка на Частоту отправки кодов (запросить новый код можно раз в 2 минуты)
    $flood = \Drupal::service('func_helper.flood');
    $send_code = $flood->check('sms_verification_send_code', 1, 120, $field_user_phone);

    // генерируем и отправляем сообщение с кодом
    if ($send_code['allowed'] && !$form_state->get('sms_verification_secret')) {
      $secret = rand(1111, 9999);
      $form_state->set('sms_verification_secret', $secret);
      $sms_text = 'Код для входа на сайт JOY PREMIUM: ' . $secret;

      // инициализация и отправка смс
      $smsru_auth = new ApiIdAuth('036AD662-0400-7EAE-FF5C-E7B548FFA439');
      $http_client = new HttpClient($smsru_auth);
      $smsru = new SmsRu($http_client);
      $message = new Message($field_user_phone, $sms_text);
      $message->setTest(TRUE);
      $response = $smsru->smsSend($message);

      if ($response->getStatusCode() == 100) {
        // отправлено
        // Регистрируем отправку кода
        $flood->register('sms_verification_send_code', 120, $field_user_phone);

        //    $data = $response->getData();
        //    $sms_info = reset($data['sms']);
      }
    }
  }


  // выполняем валидацию с Верификацией номера после всех проверок
  $form["#validate"][] = 'extn_user_form_user_register_form_validate';

  // Меняем submit-кнопку на AJAX.
  $form['actions']['submit']['#ajax'] = [
    'callback' => 'extn_user_form_user_register_form_ajax',
    'event' => 'click',
    'wrapper' => 'user-register-form-wrapper',
  ];
}

function extn_user_form_field_user_phone_element_validate($element, $form_state)
{
  if ($user_phone = $form_state->getValue('user_profiles')[0]["entity"]["field_user_phone"][0]['value']) {

    // нормализовать номер
    $normalizer = \Drupal::service('func_helper.phone_normalizer');
    $user_phone_normalized = $normalizer->normalize($user_phone);

    // проверить наличие пользователей с таким номером
    if ($user_phone_normalized) {
      $profile_ids = \Drupal::entityQuery('profile')
        ->condition('type', 'user')
        ->condition('field_user_phone', $user_phone)
        ->accessCheck(FALSE)
        ->execute();

      if ($profile_ids) {
        $form_state->setErrorByName(implode('][', $element['#parents']), 'Пользователь с таким номером уже зарегистрирован.');
      }
    } else {
      $form_state->setErrorByName(implode('][', $element['#parents']), 'Указан некорректный номер телефона.');
    }
  }
}

function extn_user_form_user_register_form_validate(&$form, FormStateInterface $form_state)
{
  if ($form_state->getErrors()) return;

  $sms_verification_code = $form_state->getValue('sms_verification_code');
  // Если поле кода пустое,
  // значить окно ввода кода было закрыто или была обновлена страница
  // и нужно открыть окно ввода кода заново.
  if (empty($sms_verification_code)) {
    $form_state->set('sms_verification_required', FALSE);
  }
  elseif ($sms_verification_code == 'resend') {
    $form_state->set('sms_verification_required', FALSE);
    $form_state->set('sms_verification_secret', FALSE);
    $form_state->set('sms_verification_missed', FALSE);
  }

  // Если запущена процедура проверки кода и нет периода охлаждения,
  // cверяем введённый код с отправленным.
  if ($form_state->get('sms_verification_required')) {
    $flood = \Drupal::service('func_helper.flood');
    $field_user_phone = $form_state->getValue('user_profiles')[0]["entity"]["field_user_phone"][0]['value'];
    $check_code = $flood->check('sms_verification_check_code', USER_FLOOD_ATTEMPTS, USER_FLOOD_COOLDOWN, $field_user_phone);

    // проверка на период охлаждения
    if ($check_code['allowed']) {
      // Регистрируем попытку Верификации
      $flood->register('sms_verification_check_code', USER_FLOOD_COOLDOWN, $field_user_phone);

      $sms_verification_input = $form_state->getValue('sms_verification_code');
      if ($sms_verification_input == $form_state->get('sms_verification_secret')) {
        $form_state->set('sms_verification_passed', TRUE);
        $form_state->set('sms_verification_missed', FALSE);
        $flood->clear('sms_verification_check_code', $field_user_phone);

        return;
      }
      else {
        $form_state->set('sms_verification_missed', TRUE);
        // Удалить значение предыдущего кода,
        // которое будет подставлено при обновлении формы.
        $input = $form_state->getUserInput();
        $input['sms_verification_code'] = NULL;
        $form_state->setUserInput($input);
      }
    }
  }
  // ошибок нет и Верификация ещё не проводилась
  else {
    $form_state->set('sms_verification_required', TRUE);
  }

  $form_state->setRebuild();
}

/**
 * AJAX callback для submit.
 */
function extn_user_form_user_register_form_ajax(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
{
  // если нет ошибок
  if (!$form_state->getErrors()) {
    // и требуется верификация по СМС
    if ($form_state->get('sms_verification_required')) {
      $response = new AjaxResponse();

      // Если проверка пройдена, переадресовать на страницу Личного кабинета.
      // В настройках аккаунта нужно убрать опцию "требовать подтверждения email",
      // тогда после регистрации, пользователь будет сразу авторизован
      if ($form_state->get('sms_verification_passed')) {
        $response->addCommand(new RedirectCommand(Url::fromRoute('entity.user.canonical', ['user' => \Drupal::currentUser()->id()])->toString()));
      }
      // если не пройдена, выводим диалоговое окно
      else {
        $field_user_phone = $form_state->getValue('user_profiles')[0]["entity"]["field_user_phone"][0]['value'];
        $flood = \Drupal::service('func_helper.flood');
        $check_code = $flood->check('sms_verification_check_code', USER_FLOOD_ATTEMPTS, USER_FLOOD_COOLDOWN, $field_user_phone);
        $send_code = $flood->check('sms_verification_send_code', 1, 120, $field_user_phone);

        // попытки закончились, период охлаждения
        $notes = $countdown = '';
        if (!$check_code["allowed"]) {
          if ($minutes = intdiv($check_code['wait'], 60)) {
            $time = \Drupal::translation()->formatPlural($minutes, '1 minute', '@count minutes');
          } else {
            $time = \Drupal::translation()->formatPlural(60 - $check_code['wait'], '1 second', '@count seconds');
          }
          $notes = '<span class="text-danger">Слишком много попыток ввода кода. Попробуйте через ' . $time . '</span>';
        }
        else {
          // неверный код
          if ($form_state->get('sms_verification_missed')) {
            $notes = '<span class="text-danger">Введён неверный код. Осталось попыток: ' . $check_code['attempts'] . '.</span>';
          }
          // всё в норме
          else {
            $notes = 'Введите код, отправленный в СМС.';
          }

          if (!$send_code["allowed"]) {
            $countdown = '<span class="countdown">Получить новый код можно через <span class="counter">' . $send_code["wait"] . '</span> сек.</span>';
          }
        }

        // доп обёртка div для того, чтобы verification-code-modal можно было найти в behaviour context
        $dialog =
          '<div>' .
            '<div class="verification-code-modal">' .
              '<div class="form-item form-item--code form-with-verification-dialog-input">' .
                '<input class="code" data-id="1" maxlength="1" type="text" autofocus />' .
                '<input class="code" data-id="2" maxlength="1" type="text" />' .
                '<input class="code" data-id="3" maxlength="1" type="text" />' .
                '<input class="code" data-id="4" maxlength="1" type="text" />' .
              '</div>' .
              '<div class="form-item">' .
                '<div class="ui-dialog-notes">' .
                  '<div class="message">' . $notes . '</div>' .
                  ($countdown ? '<div class="countdown">' . $countdown . '</div>' : '') .
                '</div>' .
              '</div>' .
              '<div class="form-actions">' .
                '<button class="form-with-verification-dialog-button resend button button--link button--sm' . ($send_code["allowed"] ? '' : ' hide') . '">Получить новый код</button>' .
                '<button class="form-with-verification-dialog-button button button--primary">Отправить</button>' .
              '</div>' .
            '</div>' .
          '</div>';

        $response->addCommand(new OpenModalDialogCommand('Проверка номера телефона', $dialog, ['width' => '550']));
        $response->addCommand(new ReplaceCommand('#user-register-form-wrapper', $form));
      }

      return $response;
    }
  }
  // если ошибка
  else {
    // сбрасываем флаг необходимости верификации номера
    $form_state->set('sms_verification_required', FALSE);
  }

  return $form;

}

/**
 * Implements hook_entity_insert().
 */
function extn_user_entity_insert(\Drupal\Core\Entity\EntityInterface $entity)
{
  // задать имя пользователя равным email
  if ($entity->getEntityTypeId() === 'user') {
    /** @var \Drupal\user\UserInterface $entity */
    $new_name = $entity->getEmail();
    $entity->setUsername($new_name);
    $entity->save();
  }
}
