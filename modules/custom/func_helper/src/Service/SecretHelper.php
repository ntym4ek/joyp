<?php

namespace Drupal\func_helper\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Сервис для временного хранения и проверки кодов (например, SMS).
 */
class SecretHelper {

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * @var \Drupal\Core\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * @var string
   *   Префикс CID для всех записей.
   */
  protected string $prefix = 'sms_verification_secret:';

  /**
   * Конструктор.
   */
  public function __construct(CacheBackendInterface $cache, TimeInterface $time) {
    $this->cache = $cache;
    $this->time = $time;
  }

  /**
   * Сохраняет код с заданным временем жизни (TTL).
   * Код активен ttl секунд, но живёт в два раза дольше,
   * чтобы пользователь в определённой ситуации мог понять,
   * что код устарел и нужно вручную получить новый.
   *
   * @param string $key
   *   Уникальный идентификатор (например, номер телефона).
   * @param string $secret
   *   Проверочный код.
   * @param int $ttl
   *   Время жизни в секундах.
   */
  public function set(string $key, string $secret, int $ttl = 300): void {
    $cid = $this->prefix . $key;
    $expire = $this->time->getRequestTime() + $ttl*2; // автоматическое удаление по cron

    $this->cache->set($cid, [
      'secret' => $secret,
      'created' => $this->time->getRequestTime(),
      'expire' => $expire,
      'ttl' => $ttl,
    ], $expire);
  }

  /**
   * Возвращает код, если он ещё действителен.
   *
   * @param string $key
   *   Уникальный идентификатор (например, номер телефона).
   *
   * @return string|null
   *   Код, если найден и не истёк, иначе NULL.
   */
  public function get(string $key): ?string {
    $cid = $this->prefix . $key;
    $item = $this->cache->get($cid);

    // Проверяем вручную срок жизни (если по cron ещё не удалён).
    if ($item && $item->data['expire'] < $this->time->getRequestTime()) {
      $this->cache->delete($cid);
      return NULL;
    }

    if (!$item || !$this->isActive($key)) {
      return NULL;
    }

    return $item->data['secret'];
  }

  /**
   * Проверяет, не истёк ли срок жизни кода.
   *
   * @param string $key
   *   Идентификатор.
   *
   * @return bool
   *   TRUE, если код ещё действителен.
   */
  public function exists(string $key): bool {
    $cid = $this->prefix . $key;
    $item = $this->cache->get($cid);

    // Проверяем вручную срок жизни (если по cron ещё не удалён).
    if ($item && $item->data['expire'] < $this->time->getRequestTime()) {
      $this->cache->delete($cid);
      return false;
    }

    return (bool)$item;
  }

  /**
   * Проверяет, не истёк ли срок жизни кода.
   *
   * @param string $key
   *   Идентификатор.
   *
   * @return bool
   *   TRUE, если код ещё действителен.
   */
  public function isActive(string $key): bool {
    $cid = $this->prefix . $key;
    $item = $this->cache->get($cid);

    return $item->data['created'] + $item->data['ttl'] > $this->time->getRequestTime();
  }

  /**
   * Удаляет код.
   *
   * @param string $key
   *   Идентификатор.
   */
  public function delete(string $key): void {
    $cid = $this->prefix . $key;
    $this->cache->delete($cid);
  }

}
