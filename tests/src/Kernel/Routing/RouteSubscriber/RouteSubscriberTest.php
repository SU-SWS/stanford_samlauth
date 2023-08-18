<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Routing\RouteSubscriber;

use Drupal\KernelTests\KernelTestBase;

/**
 * Class RouteSubscriberTest.
 *
 * @package Drupal\Tests\stanford_samlauth\Kernel\Routing\RouteSubscriber
 * @coversDefaultClass \Drupal\stanford_samlauth\Routing\RouteSubscriber
 */
class RouteSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}}
   */
  protected static $modules = [
    'externalauth',
    'simplesamlphp_auth',
    'stanford_samlauth',
    'system',
    'path_alias',
  ];

  /**
   * {@inheritdoc}}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Make sure simplesamlphp_auth routes have been altered.
   */
  public function testAlterRoutes() {
    /** @var \Drupal\Core\Routing\RouteProvider $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    $route = $route_provider->getRouteByName('simplesamlphp_auth.admin_settings_sync');
    $this->assertEquals('Drupal\stanford_samlauth\Form\RoleSyncForm', $route->getDefault('_form'));

    $route = $route_provider->getRouteByName('simplesamlphp_auth.admin_settings_local');
    $this->assertEquals('Drupal\stanford_samlauth\Form\LocalLoginForm', $route->getDefault('_form'));

    /** covers the redirect from '/sso/login' to '/saml_login' */
    $route = $route_provider->getRouteByName('stanford_samlauth.sso_login');
    $this->assertEquals('/sso/login', $route->getPath());
  }

}
