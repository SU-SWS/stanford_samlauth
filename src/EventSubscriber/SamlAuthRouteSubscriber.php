<?php

declare(strict_types=1);

namespace Drupal\stanford_samlauth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class SamlAuthRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a SamlAuthRouteSubscriber object.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $login_roles = $this->configFactory->get('samlauth.authentication')
      ->get('drupal_login_roles');
    if (array_filter($login_roles)) {
      return;
    }
    $routes_to_block = [
      'user.register',
      'user.pass',
      'user.pass.http',
      'user.login.http',
    ];
    foreach ($routes_to_block as $route) {
      if ($route = $collection->get($route)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
