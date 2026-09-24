<?php

namespace Drupal\Tests\stanford_samlauth\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_samlauth\Form\RoleMappingSettingsForm;
use Drupal\Tests\stanford_samlauth\Kernel\StanfordSamlAuthTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests RoleMappingSettingsForm.
 */
#[Group('stanford_samlauth')]
#[RunTestsInSeparateProcesses]
class RoleMappingSettingsFormTest extends StanfordSamlAuthTestBase {

  public function testForm() {
    $fb = \Drupal::formBuilder();
    $form_state = new FormState();
    $form = $fb->buildForm(RoleMappingSettingsForm::class, $form_state);
    $this->assertNotEmpty($form);

    $form_state->set('mappings', [
      ['role' => 'role1', 'attribute' => 'foo', 'value' => 'bar'],
      ['role' => 'role1', 'attribute' => 'foo', 'value' => 'bar'],
      ['role' => 'role2', 'attribute' => '', 'value' => 'bar'],
      ['role' => 'role3', 'attribute' => 'foo', 'value' => ''],
    ]);
    $form_state->setValue(['role_mapping', 'add'], [
      'role' => 'role4',
      'attribute' => 'bar',
      'value' => 'foo',
    ]);
    $fb->submitForm(RoleMappingSettingsForm::class, $form_state);

    $config = \Drupal::config('stanford_samlauth.settings')->getRawData();
    $this->assertCount(3, $config['role_mapping']['mapping']);
  }

  /**
   * The ajax add and remove mapping callbacks alter the stored mappings.
   */
  public function testMappingCallbacks() {
    $form_object = RoleMappingSettingsForm::create($this->container);
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);
    $form_state->set('mappings', []);

    $this->assertSame($form['user_info']['role_mapping'], $form_object->addMapping($form, $form_state));

    // Without an attribute, the default attribute is used.
    $form_state->setUserInput([
      'role_mapping' => [
        'add' => ['role' => 'role1', 'attribute' => '', 'value' => ' uit:sws '],
      ],
    ]);
    $form_object->addMappingCallback($form, $form_state);
    $this->assertEquals([
      ['role' => 'role1', 'attribute' => 'eduPersonEntitlement', 'value' => 'uit:sws'],
    ], $form_state->get('mappings'));
    $this->assertTrue($form_state->isRebuilding());

    // A mapping without a value is not added.
    $form_state->setUserInput([
      'role_mapping' => [
        'add' => ['role' => 'role2', 'attribute' => 'foo', 'value' => ''],
      ],
    ]);
    $form_object->addMappingCallback($form, $form_state);
    $this->assertCount(1, $form_state->get('mappings'));

    $form_state->setUserInput([
      'role_mapping' => [
        'add' => ['role' => 'role3', 'attribute' => 'foo', 'value' => 'bar'],
      ],
    ]);
    $form_object->addMappingCallback($form, $form_state);
    $this->assertCount(2, $form_state->get('mappings'));

    // Remove the first mapping.
    $form_state->setTriggeringElement(['#mapping' => 0]);
    $form_object->removeMappingCallback($form, $form_state);
    $this->assertEquals([
      1 => ['role' => 'role3', 'attribute' => 'foo', 'value' => 'bar'],
    ], $form_state->get('mappings'));
  }

}
