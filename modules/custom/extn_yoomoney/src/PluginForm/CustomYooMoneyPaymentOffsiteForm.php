<?php

/**
 * @author YooMoney <cms@yoomoney.ru>
 * @copyright © 2025 "YooMoney", NBСO LLC
 * @license  https://yoomoney.ru/doc.xml?id=527052
 */

namespace Drupal\extn_yoomoney\PluginForm;

use Drupal;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItemList;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yookassa\Helpers\YooKassaLoggerHelper;
use Drupal\yookassa\Oauth\YooKassaClientFactory;
use Drupal\yookassa\Plugin\Commerce\PaymentGateway\YooKassa;
use Exception;
use YooKassa\Model\Payment\ConfirmationType;
use YooKassa\Model\Payment\PaymentInterface as PaymentInterfaceAlias;
use YooKassa\Request\Payments\AbstractPaymentRequestBuilder;
use YooKassa\Request\Payments\CreatePaymentRequest;

/**
 * Класс методов для работы с формой оплаты
 *
 * Полностью копирует класс PaymentOffsiteForm из yoomoney.
 * Добавлена только отправка дополнительного параметра PaymentMethodData,
 * который задаётся в форме добавления нового платёжного шлюза.
 *
 * То есть сейчас можно добавить несколько платёжных шлюзов,
 * чтобы пользователь мог при оформлении заказа выбрать метод оплаты.
 * Далее он будет перенаправлен на страницу оплаты выбранным способом.
 */
class CustomYooMoneyPaymentOffsiteForm extends BasePaymentOffsiteForm
{
    /** @var PaymentGatewayInterface */
    private PaymentGatewayInterface $paymentGatewayPlugin;

    /**
     * Конструктор формы для оплаты.
     *
     * @param array $form Массив, содержащий исходную структуру формы плагина
     * @param FormStateInterface $form_state Текущее состояние формы
     *
     * @return array
     * @throws InvalidPluginDefinitionException
     * @throws EntityStorageException
     * @throws NeedsRedirectException
     * @throws Exception
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var PaymentInterface $payment */
        $payment = $this->entity;
        $this->paymentGatewayPlugin = $payment->getPaymentGateway()->getPlugin();

        try {
            $this->getKassaLogger()->sendHeka(['payment.create.init']);
            $config               = $this->paymentGatewayPlugin->getConfiguration();
            $order                = $payment->getOrder();
            $amount               = $order->getTotalPrice();
            $client               = YooKassaClientFactory::getYooKassaClient($config);

            $builder = CreatePaymentRequest::builder()
                ->setAmount($amount->getNumber())
                ->setCapture(true)
                ->setDescription($this->createDescription($order, $config))
                ->setConfirmation([
                    'type'      => ConfirmationType::REDIRECT,
                    'returnUrl' => $form['#return_url'],
                ])
                ->setMetadata([
                    'cms_name'       => YooKassa::getCmsName(),
                    'module_version' => YooKassa::YOOMONEY_MODULE_VERSION,
                ])
                ->setTaxSystemCode($config['default_tax_rate']);

            if (!empty($config['payment_method']) && $config['payment_method'] != 'epl') {
              $builder->setPaymentMethodData([
                'type' => $config['payment_method'],
              ]);
            }

            if ($config['receipt_enabled'] === 1) {
                $this->factoryReceipt($builder, $config, $order);
            }
            $paymentRequest = $builder->build();

            if (($config['receipt_enabled'] === 1) && $paymentRequest->getReceipt() !== null) {
                $paymentRequest->getReceipt()->normalize($paymentRequest->getAmount());
            }
            $this->getKassaLogger()->sendHeka(['payment.create.success']);
        } catch (Exception $e) {
            $this->getKassaLogger()->sendAlertLog('Failed to build request', [
                'methodid' => 'POST/buildConfigurationForm',
                'exception' => $e,
            ], ['payment.create.fail']);
            throw new PaymentGatewayException();
        }

        try {
            $this->getKassaLogger()->sendHeka(['payment.request.init']);
            $response = $client->createPayment($paymentRequest);
            $this->getKassaLogger()->sendHeka(['payment.request.success']);
        } catch (Exception $e) {
            $this->getKassaLogger()->sendAlertLog('Failed to create payment', [
                'methodid' => 'POST/buildConfigurationForm',
                'exception' => $e,
            ], ['payment.request.fail']);
            Drupal::logger('yookassa')->error('Error create payment. Api error: ' . $e->getMessage());
            throw new PaymentGatewayException();
        }

        try {
            $this->getKassaLogger()->sendHeka(['payment.save.init']);
            $payment_storage = Drupal::entityTypeManager()->getStorage('commerce_payment');
            $payments        = $payment_storage->loadByProperties(['order_id' => $order->id()]);
            if ($payments) {
                $payment = reset($payments);
                $payment->enforceIsNew(false);
            }
            $payment->setRemoteId($response->getId());
            $payment->setRemoteState($response->getStatus());
            $payment->save();
            $this->getKassaLogger()->sendHeka(['payment.save.success']);
        } catch (Exception $e) {
            $this->getKassaLogger()->sendAlertLog('Failed to prepare payment', [
                'methodid' => 'POST/buildConfigurationForm',
                'exception' => $e,
            ], ['payment.save.fail']);
            Drupal::logger('yookassa')->error('Failed to save payment. Error: ' . $e->getMessage());
            throw new PaymentGatewayException();
        }

        $redirect_url = $response->confirmation->confirmationUrl;
        $data         = [
            'return' => $form['#return_url'],
            'cancel' => $form['#cancel_url'],
            'total'  => $payment->getAmount()->getNumber(),
        ];
        $this->getKassaLogger()->sendHeka(['payment.redirect.init']);
        return $this->buildRedirectForm($form, $form_state, $redirect_url, $data);
    }

    /**
     * Формирует описание к платежу, подставляет данные в шаблон и обрезает по заданную длину.
     *
     * @param OrderInterface $order Модель заказа
     * @param array $config Конфигурация платежного шлюза
     *
     * @return string
     */
    private function createDescription(OrderInterface $order, array $config): string
    {
        $descriptionTemplate = !empty($config['description_template'])
            ? $config['description_template']
            : t('Оплата заказа №%order_id%');

        $replace = [];
        foreach ($order as $property => $fieldItems) {
            foreach ($fieldItems as $fieldItem) {
                if (!($fieldItem instanceof FieldItemInterface)) {
                    continue;
                }
                $params = $fieldItem->getEntity()->toArray();
                if (empty($params[$property])) {
                    continue;
                }
                if (!is_array($params[$property])) {
                    continue;
                }
                if (empty($params[$property][0])) {
                    continue;
                }
                $fieldData = $params[$property][0];
                if (!is_array($fieldData)) {
                    continue;
                }
                $value = current($fieldData);
                if (!is_scalar($value)) {
                    continue;
                }
                $replace['%'.$property.'%'] = $value;
            }
        }

        $description = strtr($descriptionTemplate, $replace);

        return mb_substr($description, 0, PaymentInterfaceAlias::MAX_LENGTH_DESCRIPTION);
    }

    /**
     * @return YooKassaLoggerHelper
     */
    private function getKassaLogger(): YooKassaLoggerHelper
    {
        return $this->paymentGatewayPlugin->kassaLogger;
    }

    /**
     * @param AbstractPaymentRequestBuilder $builder
     * @param array $config
     * @param OrderInterface $order
     * @return void
     */
    private function factoryReceipt(
        AbstractPaymentRequestBuilder $builder,
        array                         $config,
        OrderInterface                $order
    ): void
    {
        $this->getKassaLogger()->sendHeka(['receipt.create.init']);
        try {
            $profileEmail = $order->get('mail')->getString();
            $builder->setReceiptEmail($profileEmail);
            $items = $order->getItems();
            /** @var OrderItem $item */
            foreach ($items as $item) {
                /** @var AdjustmentItemList $adjustments */
                $adjustments = $item->get('adjustments');

                $taxUuid = null;
                $percentage = 0;
                foreach ($adjustments->getValue() as $adjustmentValue) {
                    /** @var Adjustment $adjustment */
                    $adjustment = $adjustmentValue['value'];
                    if ($adjustment->getType() === 'tax') {
                        $sourceId = explode('|', $adjustment->getSourceId());
                        $taxUuid = $sourceId[2];
                        $percentage = $adjustment->getPercentage();
                    }
                }
                if (array_key_exists($taxUuid, $config['yookassa_tax'])) {
                    $vat_code = $config['yookassa_tax'][$taxUuid];
                } else {
                    $vat_code = $config['default_tax'];
                }

                $priceWithTax = $item->getUnitPrice()->getNumber() * (1 + $percentage);
                $builder->addReceiptItem($item->getTitle(), $priceWithTax, $item->getQuantity(), $vat_code, $config['default_payment_mode'], $config['default_payment_subject']);
            }
            $this->getKassaLogger()->sendHeka(['receipt.create.success']);
        } catch (Exception $e) {
            $this->getKassaLogger()->sendHeka(['receipt.create.fail']);
            Drupal::logger('yookassa')->error('Error build payment. Error: ' . $e->getMessage());
        }
    }
}
