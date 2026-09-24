<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\EventSubscriber;

use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Test route subscriber.
 */
#[Group('stanford_samlauth')]
#[RunTestsInSeparateProcesses]
class SamlAuthRouteSubscriberTest extends StanfordSamlAuthTestBase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('samlauth');
  }

  #[TestWith(['hide_local_login' => TRUE, 'role_mapping' => TRUE])]
  #[TestWith(['hide_local_login' => FALSE, 'role_mapping' => TRUE])]
  #[TestWith(['hide_local_login' => TRUE, 'role_mapping' => FALSE])]
  #[TestWith(['hide_local_login' => FALSE, 'role_mapping' => FALSE])]
  public function testUserSyncEvent(bool $hide_local_login, bool $role_mapping) {
    $this->config('stanford_samlauth.settings')
      ->set('hide_local_login', $hide_local_login)
      ->save();

    if ($role_mapping) {
      $roles = array_keys(Role::loadMultiple());
      $this->config('samlauth.authentication')
        ->set('drupal_login_roles', $roles)
        ->save();
    }

    /** @var \Drupal\Core\Routing\Router $router */
    $router = \Drupal::service('router.no_access_checks');
    $access = $router->getRouteCollection()
      ->get('user.pass')
      ->getRequirement('_access');

    $expected = $hide_local_login && !$role_mapping ? 'FALSE' : 'TRUE';
    $this->assertEquals($expected, $access);
  }

}
