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
  // в админке не меняем
  if (\Drupal::currentUser()->hasPermission('administer site configuration'))  return;

  $form['#prefix'] = '<div id="user-register-form-wrapper">';
  $form['#suffix'] = '</div>';
  $form['#attributes']['class'][] = 'form-with-verification';
  $form['#attached']['library'][] = 'extn_user/user-auth-modal';

  // скрыть имя пользователя
  $form["account"]["name"]["#access"] = false;
  $passwordGenerator = \Drupal::service('password_generator');
  $form['account']['name']['#value'] = 'ext_user_' . $passwordGenerator->generate(10);
  $form["account"]["pass"]["#required"] = false;

  $form["account"]["mail"]["#description"] = '';
  $form["user_profiles"]["widget"][0]["#type"] = 'container';

  // валидатор поля телефона
  $form["user_profiles"]["widget"][0]["entity"]["field_user_phone"]["widget"][0]['value']["#element_validate"][] = 'extn_user_form_field_user_phone_element_validate';

  // авторизации по коду из СМС
  $form['sms_verification_code'] = [
    '#type' => 'hidden',
    '#attributes' => ['class' => ['form-with-verification-code']],
  ];
  $form['actions']['submit']['#attributes']['class'][] = 'form-with-verification-submit';

  // выполняем валидацию с Верификацией номера после всех проверок
  $form["#validate"][] = 'extn_user_form_user_register_form_validate';
  array_unshift($form["#submit"], 'extn_user_form_user_register_form_submit');

  $form['actions']['submit']['#ajax'] = [
    'callback' => 'extn_user_form_user_register_form_ajax',
    'event' => 'click',
    'wrapper' => 'user-register-form-wrapper',
  ];
}

function extn_user_form_field_user_phone_element_validate($element, $form_state)
{
  $user_profiles = $form_state->getValue('user_profiles');
  if ($user_phone = $user_profiles[0]["entity"]["field_user_phone"][0]['value']) {

    // нормализовать номер
    $normalizer = \Drupal::service('func_helper.phone_normalizer');
    $user_phone_normalized = $normalizer->normalize($user_phone);

    // проверить наличие пользователей с таким номером
    if ($user_phone_normalized) {
      $profile_ids = \Drupal::entityQuery('profile')
        ->condition('type', 'user')
        ->condition('field_user_phone', $user_phone_normalized)
        ->accessCheck(FALSE)
        ->execute();

      if ($profile_ids) {
        $form_state->setErrorByName(implode('][', $element['#parents']), 'Пользователь с таким номером уже зарегистрирован.');
      } else {
        $user_profiles[0]["entity"]["field_user_phone"][0]['value'] = $user_phone_normalized;
        $form_state->setValue('user_profiles', $user_profiles);
      }
    } else {
      $form_state->setErrorByName(implode('][', $element['#parents']), 'Указан некорректный номер телефона.');
    }
  }
}

function extn_user_form_user_register_form_validate(&$form, FormStateInterface $form_state)
{
  if ($form_state->hasAnyErrors()) return;

  $user_profiles = $form_state->getValue('user_profiles');
  $user_phone = $user_profiles[0]["entity"]["field_user_phone"][0]['value'];
  $form_state->set('sms_verification_phone', $user_phone);

  // сначала проверка, так как может быть запрошена повторная отправка кода
  extn_user_sms_check_code($form_state);
  if (!$form_state->get('sms_verification_passed')) {
    // Устанавливаем ошибку валидации кода, чтобы не сработали сабмиты.
    $form_state->setErrorByName('sms_verification_code');
    extn_user_sms_send_code($form_state);
  }
}

function extn_user_form_user_register_form_submit(&$form, FormStateInterface $form_state)
{

}


/**
 * AJAX callback для submit.
 */
function extn_user_form_user_register_form_ajax(array &$form, \Drupal\Core\Form\FormStateInterface $form_state)
{
  $response = new AjaxResponse();

  // если нет ошибок
  $errors = $form_state->getErrors();
  if (!$errors) {
    // Если проверка пройдена, переадресовать на страницу Личного кабинета.
    // В настройках аккаунта нужно убрать опцию "требовать подтверждения email",
    // тогда после регистрации, пользователь будет сразу авторизован
    if ($form_state->get('sms_verification_passed')) {
      $response->addCommand(new RedirectCommand(Url::fromRoute('entity.user.canonical', ['user' => \Drupal::currentUser()->id()])->toString()));
    }
  }
  // если верификация по коду из СМС не пройдена, выводим диалоговое окно
  elseif (isset($errors['sms_verification_code'])) {
    $dialog = extn_user_sms_input_dialog($form_state);
    $response->addCommand(new OpenModalDialogCommand('Проверка номера телефона', $dialog, ['width' => '550']));
    \Drupal::messenger()->deleteAll();
  }

  $form['status_messages'] = [
    '#type' => 'status_messages',
    '#weight' => -10,
  ];
  $response->addCommand(new ReplaceCommand('#user-register-form-wrapper', $form));

  return $response;
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
