<?php

namespace Drupal\extn_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a custom message pane.
 *
 * @CommerceCheckoutPane(
 *   id = "extn_commerce_gift_message",
 *   label = @Translation("Gift message"),
 *   default_step = "order_information",
 *   wrapper_element = "fieldset",
 * )
 */
class GiftMessagePane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    $image_url = \Drupal::service('file_url_generator')->generateString('public://images/modules/extn_commerce/gift-card.png');
    $pane_form['image'] = [
      '#markup' =>  '<div class="gift-message">' .
                      '<div class="gift-message__intro">' .
                        '<div class="gift-message__image"><img src="' . $image_url . '" /></div>' .
                        '<div class="gift-message__message">В заказ можно вложить подписанную открытку. Если хотите, напишите текст открытки, но&nbsp;не&nbsp;более 200&nbsp;знаков.</div>' .
                      '</div>' .
                    '</div>',
    ];
    $pane_form['message'] = [
      '#type' => 'textarea',
      '#title' => 'Ваш текст на открытке',
      '#rows' => 4,
    ];

    return $pane_form;
  }

  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form)
  {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->set('field_order_postcard', $values['message']);
  }

  public function buildPaneSummary()
  {
    if ($order_postcard = $this->order->get('field_order_postcard')) {
      return [
        '#plain_text' => $order_postcard,
      ];
    }

    return [];
  }
}
