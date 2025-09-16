<?php

namespace Drupal\extn_commerce\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderAssignmentInterface;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GuestCheckoutCompletionSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new GuestCheckoutCompletionSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_order\OrderAssignmentInterface $orderAssignment
   *   The order assignment.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator
   *   The password generator.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected OrderAssignmentInterface $orderAssignment, protected PasswordGeneratorInterface $passwordGenerator) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CheckoutEvents::COMPLETION => ['onCompletion', -100],
    ];
  }

  /**
   * Handles guest checkout completion
   * По приоритету подразумевается, что subscriber из commerce_checkout уже выполнился
   * и создал пользователя.
   *
   * Based on the following checkout flow settings:
   * - guest_new_account: creates new guest account.
   * - guest_order_assign: assigns the order to an existing user account.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCompletion(OrderEvent $event) {
    $order = $event->getOrder();

    $checkout_flow = $order->get('checkout_flow')->entity;
    assert($checkout_flow instanceof CheckoutFlowInterface);
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $configuration = $checkout_flow_plugin->getConfiguration();
    $guest_new_account = $configuration['guest_new_account'] ?? FALSE;
    $guest_order_assign = $configuration['guest_order_assign'] ?? FALSE;
    if (!$guest_new_account && !$guest_order_assign) {
      return;
    }

    $customer = $order->getCustomer();
    if (!$customer->isAuthenticated()) {
      return;
    }

    $shipping_profile = $this->getShippingProfile($order);
    if ($shipping_profile instanceof ProfileInterface) {
      $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
      $user_profile = $profile_storage->loadByUser($customer, 'user');
      // Если профиль Пользователь отсутствует, создать и задать значения.
      // Если профиль есть, то значения менять не нужно.
      if (!($user_profile instanceof ProfileInterface)) {
        $address_item = $shipping_profile->get('address')->first();

        $user_profile = Profile::create([
          'type' => 'user',
          'uid' => $customer->id(),
          'status' => TRUE,
          'field_user_phone' => $shipping_profile->get('field_user_phone')->value,
          'field_user_surname' => $address_item->family_name,
          'field_user_name' => $address_item->given_name,
          'field_user_name2' => $address_item->additional_name,
        ]);
        $user_profile->save();
      }
    }
  }

  /**
   * Gets the shipping profile.
   *
   * The shipping profile is assumed to be the same for all shipments.
   * Therefore, it is taken from the first found shipment, or created from
   * scratch if no shipments were found.
   *
   * @return \Drupal\profile\Entity\ProfileInterface
   *   The shipping profile.
   */
  protected function getShippingProfile(OrderInterface $order): ?ProfileInterface
  {
    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      break;
    }

    return $shipping_profile;
  }

}
