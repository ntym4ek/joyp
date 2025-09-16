<?php

namespace Drupal\func_helper\Helper;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Набор вспомогательных функций.
 */
class CommerceHelper {

  /**
   * Вернуть количество уникальных продуктов в корзине текущего пользователя
   *
   * @return int
   */
  public static function getCurrentUserCartProductsCount(): int
  {
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $carts = $cart_provider->getCarts();

    $products = [];
    foreach ($carts as $cart) {
      foreach ($cart->getItems() as $order_item) {
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        $variation = $order_item->getPurchasedEntity();
        if ($variation instanceof ProductVariationInterface) {
          $products[$variation->getProductId()] = $variation->getProductId();
        }
      }
    }

    return count($products);
  }


  /**
   * Сделать запрос в СДЭК и получить актуальный список населённых пунктов
   * с применением фильтра
   */
  public static function getCitiesFromCDEK(string $string = ''): ?\CdekSDK2\Dto\CityList
  {
    $client = \Drupal::HttpClient();
    $cdek = new \CdekSDK2\Client($client);
    $cdek->setAccount('wRajj6IwpjXh07CYG56pG9ePQsxhc5k2');
    $cdek->setSecure('zzw2kj6mG5uxGaG5MWD11vUAGhieIBy9');

    try {
      $cdek->authorize();
    } catch (AuthException $exception) {
//      echo $exception->getMessage();
    }

    $result = $cdek->citiesSuggest()->getFiltered(['country_code' => 'RU', 'name' => $string]);
    if ($result->isOk()) {
      if ($list = $cdek->formatResponseList($result, \CdekSDK2\Dto\CityList::class)) {
        return $list;
      }
    }

    return null;
  }

  /**
   * Обновить словарь Городов доставки по списку
   *
   * @param $list - список в формате CDEK
   */
  public static function updateShippingCities(\CdekSDK2\Dto\CityList $list): void
  {

    $connection = \Drupal::database();
    $transaction = $connection->startTransaction();

    try {
      /** @var \Drupal\taxonomy\TermStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $terms = $storage->loadByProperties(['vid' => 'shipping_cities']);
      if (!empty($terms)) {
        $storage->delete($terms);
      }
      foreach ($list->items as $city) {
        $term = $storage->create([
          'vid' => 'shipping_cities',
          'name' => $city->city,
        ]);
        $term->set('field_city_code', $city->code);
        $term->set('field_city_region', $city->region);
        $term->save();
      }

    } catch (\Exception $e) {
      $transaction->rollback();
    }
  }


  /**
   * Проверяем, есть ли вариация товара в корзине.
   */
  public static function isProductInCart(ProductInterface $product): bool
  {
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $carts = $cart_provider->getCarts();

    foreach ($carts as $cart) {
      foreach ($cart->getItems() as $order_item) {
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        $variation = $order_item->getPurchasedEntity();
        if ($variation instanceof ProductVariationInterface && $variation->getProductId() == $product->id()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
