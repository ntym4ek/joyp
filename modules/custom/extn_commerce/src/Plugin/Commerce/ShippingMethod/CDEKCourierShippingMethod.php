<?php

namespace Drupal\extn_commerce\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\state_machine\WorkflowManagerInterface;


/**
 * @CommerceShippingMethod(
 *   id = "extn_commerce_courier_shipping_method",
 *   label = @Translation("CDEK Courier Shipping Method"),
 *   description = @Translation("CDEK courier shipping method."),
 *   status = TRUE
 * )
 */
class CDEKCourierShippingMethod extends ShippingMethodBase {

  private $rounder;

  /**
   * Constructs a new FlatRate object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);
    $this->services['default'] = new ShippingService('default', 'Курьером до двери');
    $this->rounder = \Drupal::service('commerce_price.rounder');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // расчёт стоимости доставки с помощью готового PHP модуля CdekSDK (https://www.cdek.ru/ru/integration/modules/18/)

    $shipping_amount = 0;
    $errors = null;
    $description = 'Выберите город';

    if ($shipping_address = $shipment->getShippingProfile()) {
//      $city = $shipping_address->get('field_shipping_city')->value;
//      $address = $shipping_address->get('field_shipping_address')->value;
      $city_code = 0;
      $json = $shipping_address->get('field_shipping_json')->value;
      if ($json){
        $cdek_data = json_decode($json, TRUE);
        $city_code = $cdek_data['city_code'];
      }
      if ($city_code) {
        $client = \Drupal::HttpClient();
        $cdek = new \CdekSDK2\Client($client);
        $cdek->setAccount('wRajj6IwpjXh07CYG56pG9ePQsxhc5k2');
        $cdek->setSecure('zzw2kj6mG5uxGaG5MWD11vUAGhieIBy9');

        try {
          $cdek->authorize();
        } catch (AuthException $exception) {
          //Авторизация не выполнена, неверные account и secure
//          echo $exception->getMessage();
        }

        $tariff = \CdekSDK2\BaseTypes\Tariff::create([]);
        $tariff->date = (new \DateTime())->format(\DateTime::ISO8601);
        $tariff->type = \CdekSDK2\BaseTypes\Tarifflist::TYPE_ECOMMERCE;
        $tariff->tariff_code = 137;                                                 // Номер тарифа: Посылка склад-дверь (https://api-docs.cdek.ru/63345430.html)
        $tariff->from_location = \CdekSDK2\BaseTypes\Location::create([
          'code' => 44,                                                             // Москва
        ]);
        $tariff->to_location = \CdekSDK2\BaseTypes\Location::create([
          'code' => $city_code,
        ]);
        $tariff->packages = [
          \CdekSDK2\BaseTypes\Package::create([
            'weight' => 1000,
            'length' => 30,
            'width' => 20,
            'height' => 10,
          ])
        ];

        $result = $cdek->calculator()->add($tariff);
        if ($result->hasErrors()) {
          $errors = $result->getErrors();
          if ($errors[0]["code"] == 'err_result_service_empty') $description = 'Доставка по этому адресу недоступна';
          else $description = $errors[0]["message"];
        }

        if ($result->isOk()) {
          $response = $cdek->formatBaseResponse($result, \CdekSDK2\Dto\Tariff::class);
          $shipping_amount = $response->total_sum;
          if ($response->calendar_min) {
            $description = ($response->calendar_min == $response->calendar_max ? $response->calendar_min : $response->calendar_min . '-' . $response->calendar_max) .
              ' ' . new PluralTranslatableMarkup($response->calendar_max, 'day', 'days');
          } else $description = '';
        }
      }
    }

    $amount = new Price((string) $shipping_amount, $shipment->getOrder()->getTotalPrice()->getCurrencyCode());
    $amount = $this->rounder->round($amount);

    $rates = [];
    $rates[] = new ShippingRate([
      'shipping_method_id' => $this->parentEntity->id(),
      'service' => $this->services['default'],
      'amount' => $amount,
      'description' => $description,
      'data' => ['errors' => $errors]
    ]);

    return $rates;
  }

}
