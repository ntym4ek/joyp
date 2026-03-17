<?php

namespace Drupal\func_marketing\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class XFrameSubscriber implements EventSubscriberInterface
{

  public function onResponse(ResponseEvent $event)
  {
    $request = $event->getRequest();
    $response = $event->getResponse();

    $referer = $request->headers->get('referer');

    // убираем заголовок X-Frame-Options для доступа Вебвизора к сайту
    $pattern = '/^https?:\/\/([^\/]+\.)?(joypremium\.com|webvisor\.com|metri[ck]a\.yandex\.(com|ru|by|com\.tr))\//';

    if ($referer && preg_match($pattern, $referer)) {
      $response->headers->remove('X-Frame-Options');
    }
  }

  public static function getSubscribedEvents()
  {
    // Приоритет -100, чтобы сработать ПОСЛЕ FinishResponseSubscriber ядра
    $events[KernelEvents::RESPONSE][] = ['onResponse', -100];
    return $events;
  }
}


