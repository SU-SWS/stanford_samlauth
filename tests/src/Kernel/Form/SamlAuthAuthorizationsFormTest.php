<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_samlauth\Form\SamlAuthAuthorizationsForm;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests SamlAuthAuthorizationsForm.
 */
#[Group('stanford_samlauth')]
#[RunTestsInSeparateProcesses]
class SamlAuthAuthorizationsFormTest extends StanfordSamlAuthTestBase {

  public function testAuthForm(){
    $fb = \Drupal::formBuilder();
    $form_state = new FormState();
    $form = $fb->buildForm(SamlAuthAuthorizationsForm::class, $form_state);
    $this->assertNotEmpty($form['restrict']);

    $form_state->setValues(['restrict' => true, 'users' => 'foo, bar baz', 'groups' => '']);
    $fb->submitForm(SamlAuthAuthorizationsForm::class, $form_state);

    $config = \Drupal::config('stanford_samlauth.settings')->getRawData();
    $this->assertContains('barbaz', $config['allowed']['users']);
  }

  /**
   * Restriction values are cleared when restriction is disabled.
   */
  public function testUnrestrictedClearsValues() {
    $form_state = new FormState();
    $form_state->setValues([
      'restrict' => FALSE,
      'users' => 'foo, bar',
      'affiliations' => ['staff' => 'staff'],
      'groups' => 'uit:sws',
    ]);
    \Drupal::formBuilder()->submitForm(SamlAuthAuthorizationsForm::class, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $config = \Drupal::config('stanford_samlauth.settings')->get('allowed');
    $this->assertFalse($config['restrict']);
    $this->assertEmpty($config['users']);
    $this->assertEmpty($config['affiliations']);
    $this->assertEmpty($config['groups']);
  }

}
