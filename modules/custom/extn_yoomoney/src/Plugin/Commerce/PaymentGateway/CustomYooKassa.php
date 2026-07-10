<?php

namespace Drupal\extn_yoomoney\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\yookassa\Plugin\Commerce\PaymentGateway\YooKassa as OriginalYooKassa;
use YooKassa\Model\Payment\PaymentMethodType;

/**
 * @CommercePaymentGateway(
 *  id = "custom_yookassa",
 *  label = @Translation("YooKassa (с выбором метода)"),
 *  display_label = @Translation("YooKassa"),
 *  forms = {
 *   "offsite-payment" = "Drupal\extn_yoomoney\PluginForm\CustomYooMoneyPaymentOffsiteForm",
 *  },
 *  payment_method_types = {
 *   "yookassa_epl"
 *  }
 * )
 */
class CustomYooKassa extends OriginalYooKassa {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array
  {
    return [
        'payment_method' => 'epl',
      ] + parent::defaultConfiguration();
  }

  /**
   * Добавляем поле в форму настроек шлюза в админке
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
  {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['payment_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Фиксированный метод оплаты'),
      '#description' => $this->t('Выберите "Умный платеж (EPL)", чтобы пользователь выбирал метод на стороне ЮKassa, или принудительный редирект на конкретный метод.'),
      '#options' => [
        'epl'                           => $this->t('Выбор на стороне ЮKassa)'),
        PaymentMethodType::BANK_CARD    => $this->t('Банковские карты'),
        PaymentMethodType::SBP          => $this->t('СБП (Система быстрых платежей)'),
        PaymentMethodType::SBERBANK     => $this->t('SberPay'),
        PaymentMethodType::TINKOFF_BANK => $this->t('T-Pay'),
        PaymentMethodType::YOO_MONEY    => $this->t('YooMoney кошелек'),
      ],
      '#default_value' => $this->getConfiguration()['payment_method'],
    ];

    return $form;
  }

  /**
   * Сохраняем значение конфигурации
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
  {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['payment_method'] = $values['payment_method'];
    }
  }
}
