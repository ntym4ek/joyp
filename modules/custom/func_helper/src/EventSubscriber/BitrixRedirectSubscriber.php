<?php

namespace Drupal\func_helper\EventSubscriber;

use Drupal\Core\Path\PathAliasManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber for handling custom redirects.
 */
class BitrixRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new RedirectSubscriber.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $path_alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(AliasManagerInterface $path_alias_manager, RouteMatchInterface $route_match) {
    $this->pathAliasManager = $path_alias_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

  /**
   * Handles the request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event) {
    // Check if this is the main request.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Skip administrative paths.
    if ($this->isAdminPath($path)) {
      return;
    }

    // проверить, возможно путь изначально валидный
    $pathValidator = \Drupal::service('path.validator');
    if ($pathValidator->getUrlIfValid($path)) {
      return;
    }

    // Get the clean path without leading/trailing slashes.

    $clean_path = trim($path, '/');

    // Define your redirect rules.
    $redirect_rules = $this->getRedirectRules();

    // Check for exact match redirects.
    if (isset($redirect_rules['exact'][$clean_path])) {
      $this->performRedirect($event, $redirect_rules['exact'][$clean_path]);
      return;
    }

    // Check for pattern-based redirects.
    foreach ($redirect_rules['patterns'] as $pattern => $replacement) {
      if (preg_match($pattern, $clean_path, $matches)) {
        $new_path = preg_replace($pattern, $replacement, $clean_path);
        $this->performRedirect($event, $new_path);
        return;
      }
    }

    // логика для Товаров
    // пробуем сформировать из исходной ссылки новую,
    // исходя из логики того, что транслит имя товаров одинаковое
    $args = explode('/', $clean_path, 2);
    if ($args[0] == 'catalog' && !empty($args[1])) {
      preg_match('/^(.+-)(\d+)$/', $args[1], $matches);
      if (isset($matches[2]) && is_numeric($matches[2])) {
        $pathAliasManager = \Drupal::service('path_alias.manager');
        $new_arg2 = $matches[0] . '-ml';
        foreach ([21, 22, 23, 24, 25] as $category_id) {
          $category_alias = $pathAliasManager->getAliasByPath('/taxonomy/term/' . $category_id);
          $new_path = $category_alias . '/' . $new_arg2;

          // Проверяем путь (работает с алиасами и системными путями)
          if ($pathValidator->getUrlIfValid($new_path)) {
            $this->performRedirect($event, $new_path);
            return;
          }
        }
      }
    }


    // Check for conditional redirects based on query parameters.
    $this->checkQueryParameterRedirects($event, $clean_path, $request->query->all());
  }

  /**
   * Defines all redirect rules.
   *
   * @return array
   *   Array of redirect rules.
   */
  protected function getRedirectRules() {
    return [
      'exact' => [
        'bitrix' => '/',
        'catalog' => 'katalog',
        'privacy-policy' => 'node/3',
        'payment' => 'node/7',
        'offer' => 'node/11',
        'docs/personal-data-policy.pdf' => 'sites/default/files/attachments/misc/personal-data-policy.pdf',
      ],
      'patterns' => [
        // Regular expression patterns
//        '/^old-blog\/(.*)/' => 'blog/$1',
//        '/^news\/(\d+)\/(.*)/' => 'articles/$1-$2',
//        '/^category\/(.*)\/products$/' => 'catalog/$1',
//        '/^user\/(\d+)\/profile$/' => 'profile/$1',
      ],
    ];
  }

  /**
   * Checks for redirects based on query parameters.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   * @param string $path
   *   The current path.
   * @param array $query_params
   *   The query parameters.
   */
  protected function checkQueryParameterRedirects(RequestEvent $event, $path, array $query_params) {
    $redirect_rules = [
      'search' => [
        'conditions' => ['q' => 'old-search-term'],
        'redirect' => 'search?q=new-search-term',
      ],
    ];

    foreach ($redirect_rules as $rule) {
      $match = true;
      foreach ($rule['conditions'] as $key => $value) {
        if (!isset($query_params[$key]) || $query_params[$key] != $value) {
          $match = false;
          break;
        }
      }

      if ($match) {
        $this->performRedirect($event, $rule['redirect']);
        return;
      }
    }
  }

  /**
   * Checks if the path is an administrative path.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if it's an admin path, FALSE otherwise.
   */
  protected function isAdminPath($path) {
    $admin_paths = [
      '/admin/',
      '/node/add/',
      '/user/',
      '/batch/',
      '/system/',
      '/ajax/',
    ];

    foreach ($admin_paths as $admin_path) {
      if (strpos($path, $admin_path) === 0) {
        return true;
      }
    }

    return false;
  }

  /**
   * Performs the actual redirect.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   * @param string $new_path
   *   The new path to redirect to.
   */
  protected function performRedirect(RequestEvent $event, $new_path) {
    // Ensure the new path starts with a slash.
    if (strpos($new_path, '/') !== 0) {
      $new_path = '/' . $new_path;
    }

    // Получаем алиас пути
    $pathAliasManager = \Drupal::service('path_alias.manager');
    $new_alias = $pathAliasManager->getAliasByPath($new_path);

    // Create and set the redirect response (301 - Permanent Redirect).
    $response = new RedirectResponse($new_alias, 301);
    $event->setResponse($response);

    // Log the redirect for debugging.
    \Drupal::logger('BitrixRedirectSubscriber')->notice('Redirected from @old to @new', [
      '@old' => $event->getRequest()->getPathInfo(),
      '@new' => $new_alias,
    ]);
  }

}
