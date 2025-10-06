<?php

namespace Drupal\func_helper\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Flood\FloodInterface;

class FloodHelper {

  protected FloodInterface $flood;
  protected Connection $database;
  protected TimeInterface $time;

  public function __construct(FloodInterface $flood, Connection $database, TimeInterface $time) {
    $this->flood = $flood;
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * Проверяет flood и возвращает детальную информацию.
   *
   * @param string $event
   *   Идентификатор события.
   * @param string $identifier
   *   Ключ (IP, user ID, телефон и т.д.).
   * @param int $limit
   *   Максимум попыток.
   * @param int $window
   *   Окно времени в секундах.
   *
   * @return array
   *   [
   *     'allowed' => bool,
   *     'remaining_attempts' => int,
   *     'wait_time' => int, // секунды до разблокировки
   *   ]
   */
  public function check(string $event, int $limit, int $window, string $identifier): array {
    $now = $this->time->getCurrentTime();
    $cutoff = $now - $window;

    // Берём все попытки в текущем окне (timestamp >= cutoff).
    $rows = $this->database->select('flood', 'f')
      ->fields('f', ['timestamp'])
      ->condition('event', $event)
      ->condition('identifier', $identifier)
      ->condition('timestamp', $cutoff, '>=')
      ->orderBy('timestamp', 'ASC')
      ->execute()
      ->fetchCol(); // массив timestamp

    $attempts = count($rows);
    $allowed = $attempts < $limit;
    $remaining = max(0, $limit - $attempts);

    $wait_time = 0;
    if (!$allowed) {
      // Когда N >= limit, нужно дождаться, когда "ограничивающая" попытка
      // протухнет: индекс k = N - limit (0-based) — это та попытка, после
      // истечения timestamp + window количество активных попыток станет < limit.
      $k = $attempts - $limit;
      // Защитимся на случай, если чего-то нет (хотя логично, что есть).
      if (isset($rows[$k])) {
        $expire_at = (int) $rows[$k] + $window;
        $wait_time = max(0, $expire_at - $now);
      }
    }

    return [
      'allowed' => $allowed,
      'attempts' => $remaining,
      'wait' => $wait_time,
    ];
  }

  public function register(string $event, int $window, string $identifier): void {
    $this->flood->register($event, $window, $identifier);
  }

  public function clear(string $event, string $identifier): void {
    $this->flood->clear($event, $identifier);
  }

}
