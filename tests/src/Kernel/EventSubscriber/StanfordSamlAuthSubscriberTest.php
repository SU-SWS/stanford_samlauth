<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\EventSubscriber;

use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\samlauth\UserVisibleException;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests StanfordSamlAuthSubscriber.
 */
#[Group('stanford_samlauth')]
#[RunTestsInSeparateProcesses]
class StanfordSamlAuthSubscriberTest extends StanfordSamlAuthTestBase {

  use UserCreationTrait;

  public function testUserSyncEvent() {
    $role_mapping = [
      [
        'role' => 'role1',
        'attribute' => 'fakeAttribute',
        'value' => 'foobar',
      ],
    ];
    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('role_mapping.mapping', $role_mapping)
      ->save();

    $account = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $attributes = [
      'uid' => ['bob'],
      'eduPersonAffiliation' => ['staff', 'member'],
      'fakeAttribute' => 'foobar',
    ];

    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);

    $this->assertNotEmpty($account->get('affiliation'));
    $this->assertContains('role1', $account->getRoles());
  }

  public function testInvalidSunet() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => ['foobar'],
      'affiliations' => [],
      'groups' => [],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save();

    $attributes = [
      'uid' => ['foobar'],
      'eduPersonAffiliation' => ['staff', 'member'],
    ];
    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $attributes['uid'] = ['bob'];
    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testInvalidAffiliation() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => [],
      'affiliations' => ['faculty'],
      'groups' => [],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save();

    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $attributes = [
      'uid' => ['foobar'],
      'eduPersonAffiliation' => ['staff', 'faculty'],
    ];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $attributes = [
      'uid' => ['bob'],
      'eduPersonAffiliation' => ['staff', 'member'],
    ];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testInvalidGroup() {
    $restrictions = [
      'restrict' => TRUE,
      'users' => [],
      'affiliations' => [],
      'groups' => ['foobar'],
    ];

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('allowed', $restrictions)
      ->save();

    $account = User::create([
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
      'status' => 1,
    ]);
    $attributes = ['uid' => ['foobar'], 'eduPersonEntitlement' => ['foobar']];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
    $this->assertFalse($account->isBlocked());

    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'status' => 1,
    ]);
    $attributes = ['uid' => ['bob'], 'eduPersonEntitlement' => ['member']];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    $this->expectException(UserVisibleException::class);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);
  }

  public function testKernelRequest() {
    $user = $this->createUser([]);
    $user->addRole('administrator');
    $user->save();
    $this->setCurrentUser($user);

    $kernel = $this->container->get('kernel');
    $request = Request::create('/admin/people/create');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, KernelEvents::REQUEST);

    $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
  }

  /**
   * The user create page is not redirected when local login is shown.
   */
  public function testKernelRequestLocalLoginShown() {
    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('hide_local_login', FALSE)
      ->save();
    $user = $this->createUser([]);
    $user->addRole('administrator');
    $user->save();
    $this->setCurrentUser($user);

    $kernel = $this->container->get('kernel');
    $request = Request::create('/admin/people/create');
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, KernelEvents::REQUEST);

    $this->assertNull($event->getResponse());
  }

  /**
   * No roles are changed when role mapping re-evaluation is disabled.
   */
  public function testRoleMappingNone() {
    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('role_mapping.reevaluate', 'none')
      ->set('role_mapping.mapping', [
        ['role' => 'role1', 'attribute' => 'fakeAttribute', 'value' => 'foobar'],
      ])
      ->save();

    $account = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $attributes = [
      'uid' => ['bob'],
      'eduPersonAffiliation' => ['staff'],
      'fakeAttribute' => 'foobar',
    ];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);

    $this->assertFalse($account->hasRole('stanford_staff'));
    $this->assertFalse($account->hasRole('role1'));
    $this->assertFalse($event->isAccountChanged());
  }

  /**
   * Existing roles are removed before mapping when re-evaluating all roles.
   */
  public function testRoleMappingReevaluateAll() {
    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('role_mapping.reevaluate', 'all')
      ->save();

    $account = User::create([
      'name' => 'bob',
      'mail' => 'bob@example.com',
      'roles' => ['role2'],
    ]);
    $attributes = ['uid' => ['bob'], 'eduPersonAffiliation' => ['faculty']];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);

    $this->assertFalse($account->hasRole('role2'));
    $this->assertTrue($account->hasRole('stanford_faculty'));
    $this->assertTrue($event->isAccountChanged());
  }

  /**
   * Roles are mapped from workgroups when the workgroup api is configured.
   */
  public function testWorkgroupApiRoleMapping() {
    $workgroup_api = $this->createMock(WorkgroupApiInterface::class);
    $workgroup_api->method('getAllUserWorkgroups')
      ->with('bob')
      ->willReturn(['uit:sws', 'uit:other']);
    $this->container->set('stanford_samlauth.workgroup_api', $workgroup_api);

    \Drupal::configFactory()
      ->getEditable('stanford_samlauth.settings')
      ->set('role_mapping.workgroup_api.cert', '/path/to/cert.pem')
      ->set('role_mapping.mapping', [
        ['role' => 'role1', 'attribute' => 'eduPersonEntitlement', 'value' => 'uit:sws'],
        ['role' => 'role2', 'attribute' => 'eduPersonEntitlement', 'value' => 'uit:missing'],
        // Attribute mappings are ignored when using the workgroup api.
        ['role' => 'role3', 'attribute' => 'fakeAttribute', 'value' => 'foobar'],
      ])
      ->save();

    $account = User::create(['name' => 'bob', 'mail' => 'bob@example.com']);
    $attributes = ['uid' => ['bob'], 'fakeAttribute' => 'foobar'];
    $event = new SamlauthUserSyncEvent($account, $attributes, FALSE);
    \Drupal::service('event_dispatcher')
      ->dispatch($event, SamlauthEvents::USER_SYNC);

    $this->assertTrue($account->hasRole('role1'));
    $this->assertFalse($account->hasRole('role2'));
    $this->assertFalse($account->hasRole('role3'));
    $this->assertTrue($event->isAccountChanged());
  }

}
