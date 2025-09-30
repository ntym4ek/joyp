<?php

namespace Drupal\extn_commerce\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Форма подтверждения отмены заказа.
 */
class UserOrderCancelForm extends ConfirmFormBase {

  /**
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return parent::create($container);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'extn_commerce_order_cancel';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return 'Отменить заказ?';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return 'После отмены восстановление заказа невозможно.';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('view.commerce_user_orders.order_page', [
      'user' => $this->currentUser()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return 'Отменить';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL, OrderInterface $commerce_order = NULL) {
    $this->order = $commerce_order;
    $form = parent::buildForm($form, $form_state);
    $form["#attributes"]['autofocus'] = '';
    unset($form['actions']['cancel']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $redirect_url = $form_state->getRedirect() ?: $this->getCancelUrl();

    try {
      $state = $this->order->getState();
      $available = FALSE;
      foreach ($state->getTransitions() as $transition) {
        if ($transition->getId() === 'cancel') {
          $available = TRUE;
          break;
        }
      }

      if ($available) {
        $this->order->getState()->applyTransitionById('cancel');
        $this->order->save();
        $this->messenger()->addStatus($this->t('The order has been cancelled.'));
      }
      else {
        $this->messenger()->addError($this->t('This order cannot be cancelled.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to cancel the order.'));
    }

    $form_state->setRedirectUrl($redirect_url);
  }
}
