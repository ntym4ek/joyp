<?php

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\func_helper\Helper\CommerceHelper;

/**
 * callback
 */
function extn_commerce_cart_form_alter(&$form, FormStateInterface $form_state): void
{
  // форма Корзины
  foreach (\Drupal\Core\Render\Element::children($form["edit_quantity"]) as $key) {
    $form["edit_quantity"][$key]['#wrapper_attributes']['class'][] = '';
    $form["edit_quantity"][$key]['#prefix'] = Markup::create('<div class="commerce-quantity-controls"><span class="commerce-quantity-control decrement"></span>');
    $form["edit_quantity"][$key]["#suffix"] = Markup::create('<span class="commerce-quantity-control increment"></span></div>');
  }
  $form['#attached']['library'][] = 'project/commerce-quantity-controls';
}

/**
 * Implements hook_form_BASE_FORM_ID_alter(): commerce_order_item_add_to_cart_form.
 */
function extn_commerce_form_commerce_order_item_add_to_cart_form_alter(&$form, FormStateInterface $form_state): void
{
  /** @var ProductInterface $product */
  $product = $form_state->get('product');

  // в Каталоге не выводить количество в форме добавления в корзину
  $storage = $form_state->getStorage();
  if (isset($storage["view_mode"]) && $storage["view_mode"] == 'summary') {
    $form["quantity"]["#access"] = false;
    if (CommerceHelper::isProductInCart($product)) {
      $form["actions"]["submit"]["#attributes"]["class"][] = 'in-cart';
    }
  }
  // Для поля количества вывести кнопки +/-
  if (isset($storage["view_mode"]) && $storage["view_mode"] == 'full') {
    $form["quantity"]['#attributes']['class'][] = 'commerce-quantity-controls';
    $form["quantity"]["widget"]['#prefix'] = Markup::create('<span class="commerce-quantity-control decrement"></span>');
    $form["quantity"]["widget"][0]["value"]["#suffix"] = Markup::create('<span class="commerce-quantity-control increment"></span>');
    $form['#attached']['library'][] = 'project/commerce-quantity-controls';
    $go_to_cart_class = ' hide';
    if (CommerceHelper::isProductInCart($product)) {
      $form["actions"]["submit"]["#access"] = false;
      $form["quantity"]["#access"] = false;
      $go_to_cart_class = '';
    }
    $form["actions"]["to_cart"] = [
      '#markup' => '<a href="/cart" class="go-to-cart button button--primary button--wide' . $go_to_cart_class . '">В корзине<br><span>Перейти</span></a>',
    ];

  }

  // ajax на кнопку добавления в корзину
  $form['actions']['submit']['#id'] = 'add-to-cart-button-' . $product->id();
  $form['actions']['submit']['#ajax'] = [
    'callback' => 'extn_commerce_add_to_cart_ajax',
//    'disable-refocus' => TRUE,
    'event' => 'click',
    'selector' => ':input[data-drupal-selector="add-to-cart-button-' . $product->id() . '"]',
  ];
}

/**
 * "Add to cart" form ajax submit.
 */
function extn_commerce_add_to_cart_ajax(array &$form, FormStateInterface $form_state): AjaxResponse
{
  // Удалить статусные сообщения
  \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS);

  // Получить отрендеренный продукт в Мини view_mode
  $product = $form_state->get('product');
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product');
  $build = $view_builder->view($product, 'mini');
  $output = \Drupal::service('renderer')->render($build);

  $output .=  '<div class="ui-dialog-actions" autofocus>' .
    '<a href="/cart" class="button button--primary">Перейти в корзину</a>' .
    '<a id="buttonDialogClose" class="button button--default button--outline">Вернуться к покупкам</a>' .
    '</div>';

  $response = new AjaxResponse();
  // только на странице Каталога
  $response->addCommand(new InvokeCommand('.product--summary #add-to-cart-button-' . $product->id(), 'addClass', ['in-cart']));
  // только на странице Продукта
  $response->addCommand(new InvokeCommand('.product--full .go-to-cart', 'removeClass', ['hide']));
  $response->addCommand(new InvokeCommand('.product--full .field--name-quantity', 'addClass', ['hide']));
  $response->addCommand(new InvokeCommand('.product--full #add-to-cart-button-' . $product->id(), 'addClass', ['hide']));
  // обновить бейдж на Корзине
  $products_count = CommerceHelper::getCurrentUserCartProductsCount();
  $badge = $products_count ? '<span class="badge">' . $products_count . '</span>' : '';
  $response->addCommand(new HtmlCommand('.cart-badge-wrapper', $badge));
  // Показать диалог
  $response->addCommand(new OpenModalDialogCommand(
    t('Product added to cart'),
    ['#markup' => $output],
    ['width' => '570px']
  ));

  return $response;
}

/**
 * Implements hook_form_FORM_ID_alter(): views_form_commerce_cart_form_default.
 */
function extn_commerce_form_views_form_commerce_cart_form_default_alter(array &$form, FormStateInterface $form_state): void
{
  $form['actions']['checkout']['#attributes']['class'][] = 'button--primary';

  $form['policy'] = [
    '#markup' => '<div class="commerce-cart-policy">Нажимая на кнопку "Оформить заказ", Вы соглашаетесь с <a href="#">Политикой конфиденциальности</a></div>'
  ];

  // AJAXify "update" button
  $form['#action'] = Url::fromRoute('commerce_cart.page')->toString();
  $form['actions']['submit']['#ajax'] = [
    'callback' => 'extn_commerce_ajax_update_cart_form',
    'event' => 'click',
  ];
  $form['actions']['submit']['#attributes']['class'][] = 'js-hide';
  $form['actions']['submit']['#name'] = 'update';

  // AJAXify "quantity" inputs
  foreach (Element::children($form['edit_quantity']) as $key) {
    $form['edit_quantity'][$key]['#ajax'] = [
      'event' => 'change',
      'callback' => '',
      'trigger_as' => [
        'name' => 'update',
      ],
    ];
  }

  // AJAXify "delete" buttons
  foreach (Element::children($form['remove_button']) as $key) {
    $form['remove_button'][$key]['#ajax'] = [
      'callback' => 'extn_commerce_ajax_update_cart_form',
      'event' => 'click',
    ];
  }
}

/**
 * AJAX update cart page.
 */
function extn_commerce_ajax_update_cart_form(array &$form, FormStateInterface $form_state): AjaxResponse
{
  // Delete status messages
  \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS);

  // Create fake request to cart page
  $fake_request = \Drupal::request()->duplicate();
  $fake_request->query->replace(UrlHelper::filterQueryParameters($fake_request->query->all(), [
    FormBuilderInterface::AJAX_FORM_REQUEST,
    AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER,
    MainContentViewSubscriber::WRAPPER_FORMAT,
  ]));
  $fake_request->request->replace([]);
  \Drupal::requestStack()->push($fake_request);

  // Get cart page content
  $cart_page_content = \Drupal::service('controller_resolver')->getController($fake_request)();

  // Update cart page content in browser
  $response = new AjaxResponse();
  $response->addCommand(new ReplaceCommand('.cart-form', $cart_page_content));
  // обновить бейдж на Корзине
  $products_count = CommerceHelper::getCurrentUserCartProductsCount();
  $badge = $products_count ? '<span class="badge">' . $products_count . '</span>' : '';
  $response->addCommand(new HtmlCommand('.cart-badge-wrapper', $badge));

  // Remove fake request
  \Drupal::requestStack()->pop();

  return $response;
}
