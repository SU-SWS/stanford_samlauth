<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_samlauth\Form\SamlAuthCreateUserForm;
use Drupal\stanford_samlauth\Service\WorkgroupApiInterface;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests SamlAuthCreateUserForm.
 */
#[Group('stanford_samlauth')]
#[RunTestsInSeparateProcesses]
class SamlAuthCreateUserFormTest extends StanfordSamlAuthTestBase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    // Only "validsunet" and "othersunet" exist according to the workgroup api.
    $workgroup_api = $this->createMock(WorkgroupApiInterface::class);
    $workgroup_api->method('connectionSuccessful')->willReturn(TRUE);
    $workgroup_api->method('isSunetValid')
      ->willReturnCallback(fn(string $sunet) => in_array($sunet, ['validsunet', 'othersunet']));
    $this->container->set('stanford_samlauth.workgroup_api', $workgroup_api);
  }

  /**
   * The form contains the expected elements and assignable roles.
   */
  public function testBuildForm(): void {
    $form = \Drupal::formBuilder()->getForm(SamlAuthCreateUserForm::class);
    foreach (['sunetid', 'name', 'email', 'roles', 'notify', 'submit'] as $key) {
      $this->assertArrayHasKey($key, $form);
    }
    $this->assertTrue($form['sunetid']['#required']);

    $options = $form['roles']['#options'];
    for ($i = 0; $i < 5; $i++) {
      $this->assertEquals("Role $i", (string) $options["role$i"]);
    }
    $this->assertArrayNotHasKey('authenticated', $options);
    $this->assertArrayNotHasKey('anonymous', $options);
  }

  /**
   * A user is created with default name/email, roles, authmap and notice.
   */
  public function testCreateUserDefaults(): void {
    $form_state = $this->submitCreateForm([
      'sunetid' => 'validsunet',
      'roles' => ['role1', 'role3'],
      'notify' => 1,
    ]);
    $this->assertEmpty($form_state->getErrors());

    $users = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['name' => 'validsunet']);
    $this->assertCount(1, $users);
    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);
    $this->assertEquals('validsunet@stanford.edu', $user->getEmail());
    $this->assertTrue($user->isActive());
    $this->assertTrue($user->hasRole('role1'));
    $this->assertTrue($user->hasRole('role3'));
    $this->assertFalse($user->hasRole('role2'));

    $this->assertEquals('validsunet', \Drupal::service('externalauth.authmap')
      ->get($user->id(), 'samlauth'));

    $mails = \Drupal::state()->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertEquals('validsunet@stanford.edu', $mails[0]['to']);

    $messages = array_map('strval', \Drupal::messenger()->messagesByType('status'));
    $this->assertContains('Successfully created SSO account for <em class="placeholder">validsunet</em>', $messages);
    $this->assertContains('Email sent to user', $messages);
  }

  /**
   * Custom name and email are trimmed and used, without sending an email.
   */
  public function testCreateUserCustomValues(): void {
    $form_state = $this->submitCreateForm([
      'sunetid' => 'validsunet',
      'name' => ' Preferred Name ',
      'email' => 'preferred@example.com',
      'roles' => [],
    ]);
    $this->assertEmpty($form_state->getErrors());

    $users = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['name' => 'Preferred Name']);
    $user = reset($users);
    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('preferred@example.com', $user->getEmail());
    $this->assertEquals('validsunet', \Drupal::service('externalauth.authmap')
      ->get($user->id(), 'samlauth'));
    $this->assertEmpty(\Drupal::state()->get('system.test_mail_collector', []));
  }

  /**
   * SUNetIDs with invalid characters or unknown to the workgroup api fail.
   */
  public function testInvalidSunetId(): void {
    foreach (['Bad-ID!', 'unknownsunet'] as $sunet) {
      $form_state = $this->submitCreateForm(['sunetid' => $sunet, 'roles' => []]);
      $this->assertEquals('Invalid SunetID', (string) $form_state->getErrors()['sunetid']);
    }
    $this->assertEmpty(User::loadMultiple());
  }

  /**
   * The same SUNetID can not be mapped to a second account.
   */
  public function testDuplicateSunetId(): void {
    $this->submitCreateForm(['sunetid' => 'validsunet', 'roles' => []]);

    $form_state = $this->submitCreateForm([
      'sunetid' => 'validsunet',
      'name' => 'another name',
      'email' => 'another@example.com',
      'roles' => [],
    ]);
    $this->assertStringContainsString('Authname <em class="placeholder">validsunet</em> already exists', (string) $form_state->getErrors()['sunetid']);
    $this->assertCount(1, User::loadMultiple());
  }

  /**
   * Existing usernames and email addresses, and invalid emails, are rejected.
   */
  public function testDuplicateNameAndEmail(): void {
    User::create([
      'name' => 'taken',
      'mail' => 'othersunet@stanford.edu',
    ])->save();

    $form_state = $this->submitCreateForm([
      'sunetid' => 'othersunet',
      'name' => 'taken',
      'roles' => [],
    ]);
    $errors = $form_state->getErrors();
    $this->assertStringContainsString('Username <em class="placeholder">taken</em> already exists', (string) $errors['name']);
    $this->assertStringContainsString('Email <em class="placeholder">othersunet@stanford.edu</em> already in use', (string) $errors['email']);

    $form_state = $this->submitCreateForm([
      'sunetid' => 'othersunet',
      'email' => 'not-an-email',
      'roles' => [],
    ]);
    $this->assertArrayHasKey('email', $form_state->getErrors());
    $this->assertCount(1, User::loadMultiple());
  }

  /**
   * Programmatically submit the create user form.
   *
   * @param array $values
   *   Form values.
   *
   * @return \Drupal\Core\Form\FormState
   *   Submitted form state.
   */
  protected function submitCreateForm(array $values): FormState {
    $form_state = (new FormState())->setValues($values);
    \Drupal::formBuilder()->submitForm(SamlAuthCreateUserForm::class, $form_state);
    return $form_state;
  }

}
