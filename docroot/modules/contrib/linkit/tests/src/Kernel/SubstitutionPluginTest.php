<?php

namespace Drupal\Tests\linkit\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\file\Entity\File;
use Drupal\linkit\Plugin\Linkit\Substitution\Canonical as CanonicalSubstitutionPlugin;
use Drupal\linkit\Plugin\Linkit\Substitution\File as FileSubstitutionPlugin;
use Drupal\linkit\Plugin\Linkit\Substitution\Media as MediaSubstitutionPlugin;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Tests the substitution plugins.
 *
 * @group linkit
 *
 * @requires module media_entity
 */
class SubstitutionPluginTest extends LinkitKernelTestBase {

  /**
   * The substitution manager.
   *
   * @var \Drupal\linkit\SubstitutionManagerInterface
   */
  protected $substitutionManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Additional modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'file',
    'entity_test',
    'media',
    'image',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->substitutionManager = $this->container->get('plugin.manager.linkit.substitution');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installEntitySchema('file');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('media');
    $this->installEntitySchema('media_type');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installSchema('file', ['file_usage']);

    unset($GLOBALS['config']['system.file']);
    \Drupal::configFactory()->getEditable('system.file')->set('default_scheme', 'public')->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->register('stream_wrapper.public', 'Drupal\Core\StreamWrapper\PublicStream')
      ->addTag('stream_wrapper', ['scheme' => 'public']);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem() {
    $public_file_directory = $this->siteDirectory . '/files';

    require_once 'core/includes/file.inc';

    mkdir($this->siteDirectory, 0775);
    mkdir($this->siteDirectory . '/files', 0775);
    mkdir($this->siteDirectory . '/files/config/' . CONFIG_SYNC_DIRECTORY, 0775, TRUE);

    $this->setSetting('file_public_path', $public_file_directory);

    $GLOBALS['config_directories'] = [
      CONFIG_SYNC_DIRECTORY => $this->siteDirectory . '/files/config/sync',
    ];
  }

  /**
   * Test the file substitution.
   */
  public function testFileSubstitutions() {
    $fileSubstitution = $this->substitutionManager->createInstance('file');
    $file = File::create([
      'uid' => 1,
      'filename' => 'druplicon.txt',
      'uri' => 'public://druplicon.txt',
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $file->save();
    $this->assertEquals($GLOBALS['base_url'] . '/' . $this->siteDirectory . '/files/druplicon.txt', $fileSubstitution->getUrl($file)->getGeneratedUrl());

    $entity_type = $this->entityTypeManager->getDefinition('file');
    $this->assertTrue(FileSubstitutionPlugin::isApplicable($entity_type), 'The entity type File is applicable the file substitution.');

    $entity_type = $this->entityTypeManager->getDefinition('entity_test');
    $this->assertFalse(FileSubstitutionPlugin::isApplicable($entity_type), 'The entity type EntityTest is not applicable the file substitution.');
  }

  /**
   * Test the canonical substitution.
   */
  public function testCanonicalSubstitution() {
    $canonicalSubstitution = $this->substitutionManager->createInstance('canonical');
    $entity = EntityTest::create([]);
    $entity->save();
    $this->assertEquals('/entity_test/1', $canonicalSubstitution->getUrl($entity)->getGeneratedUrl());

    $entity_type = $this->entityTypeManager->getDefinition('entity_test');
    $this->assertTrue(CanonicalSubstitutionPlugin::isApplicable($entity_type), 'The entity type EntityTest is applicable the canonical substitution.');

    $entity_type = $this->entityTypeManager->getDefinition('file');
    $this->assertFalse(CanonicalSubstitutionPlugin::isApplicable($entity_type), 'The entity type File is not applicable the canonical substitution.');
  }

  /**
   * Test the media substitution.
   */
  public function testMediaSubstitution() {
    // Set up media bundle and fields.
    $media_type = MediaType::create([
      'label' => 'test',
      'id' => 'test',
      'description' => 'Test type.',
      'source' => 'file',
    ]);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $media_type->set('source_configuration', [
      'source_field' => $source_field->getName(),
    ])->save();

    $file = File::create([
      'uid' => 1,
      'filename' => 'druplicon.txt',
      'uri' => 'public://druplicon.txt',
      'filemime' => 'text/plain',
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'test',
      $source_field->getName() => ['target_id' => $file->id()],
    ]);
    $media->save();

    $media_substitution = $this->substitutionManager->createInstance('media');
    $this->assertEquals($GLOBALS['base_url'] . '/' . $this->siteDirectory . '/files/druplicon.txt', $media_substitution->getUrl($media)->getGeneratedUrl());

    $entity_type = $this->entityTypeManager->getDefinition('media');
    $this->assertTrue(MediaSubstitutionPlugin::isApplicable($entity_type), 'The entity type Media is applicable the media substitution.');

    $entity_type = $this->entityTypeManager->getDefinition('file');
    $this->assertFalse(MediaSubstitutionPlugin::isApplicable($entity_type), 'The entity type File is not applicable the media substitution.');
  }

}
