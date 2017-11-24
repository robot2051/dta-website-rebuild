<?php

namespace DrupalCodeGenerator\Command\Drupal_8\Plugin\Field;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements d8:plugin:field:widget command.
 */
class Widget extends BaseGenerator {

  protected $name = 'd8:plugin:field:widget';
  protected $description = 'Generates field widget plugin';
  protected $alias = 'field-widget';

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $questions = Utils::defaultPluginQuestions();

    $vars = $this->collectVars($input, $output, $questions);
    $vars['class'] = Utils::camelize($vars['plugin_label'] . 'Widget');

    $this->setFile(
      'src/Plugin/Field/FieldWidget/' . $vars['class'] . '.php',
      'd8/plugin/field/widget.twig',
      $vars
    );

    $this->files['config/schema/' . $vars['machine_name'] . '.schema.yml'] = [
      'content' => $this->render('d8/plugin/field/widget-schema.twig', $vars),
      'action' => 'append',
    ];
  }

}