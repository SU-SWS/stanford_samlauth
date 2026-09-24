<?php

namespace Drupal\Tests\stanford_samlauth\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\stanford_samlauth\Drush\Commands\StanfordSamlAuthCommands;
use Drupal\user\Entity\Role;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for stanford_samlauth kernel tests.
 */
abstract class StanfordSamlAuthTestBase extends KernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'system',
    'stanford_samlauth',
    'samlauth',
    'externalauth',
    'user',
    'stanford_samlauth_test',
    'path_alias',
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installEntitySchema('path_alias');
    $this->installSchema('externalauth', 'authmap');
    $this->installConfig(['stanford_samlauth']);

    for ($i = 0; $i < 5; $i++) {
      Role::create(['label' => "Role $i", 'id' => "role$i"])->save();
    }
  }

}
