<?php

namespace Drupal\extn_admin\EventSubscriber;

use Drupal\entity_clone\Event\EntityCloneEvent;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductCloneEventSubscriber implements EventSubscriberInterface {

  /**
   * An example event subscriber.
   *
   * Dispatched before an entity is cloned and saved.
   *
   * @see \Drupal\entity_clone\Event\EntityCloneEvents::PRE_CLONE
   */
  public function preClone(EntityCloneEvent $event): void {
    $original = $event->getEntity();
    $newEntity = $event->getClonedEntity();

    // Cleanup existing variations.
    $newEntity->setVariations([]);

    // Copy variations to the cloned product.
    $cloned_variations = [];
    foreach ($original->getVariations() as $variation) {
      $cloned_variation = $variation->createDuplicate();
      /** @var \Drupal\entity_clone\EntityClone\EntityCloneInterface $entity_clone_handler */
      $entity_clone_handler = \Drupal::entityTypeManager()->getHandler($cloned_variation->getEntityTypeId(), 'entity_clone');
      $entity_clone_handler->cloneEntity($variation, $cloned_variation, []);
      $cloned_variations[] = $cloned_variation;
    }

    if ($cloned_variations) {
      $newEntity->setVariations($cloned_variations);
    }

  }

  /**
   * An example event subscriber.
   *
   * Dispatched after an entity is cloned and saved.
   *
   * @see \Drupal\entity_clone\Event\EntityCloneEvents::POST_CLONE
   */
  public function postClone(EntityCloneEvent $event): void {
    $original = $event->getEntity();
    $newEntity = $event->getClonedEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[EntityCloneEvents::PRE_CLONE][] = ['preClone'];
    $events[EntityCloneEvents::POST_CLONE][] = ['postClone'];

    return $events;
  }

}
