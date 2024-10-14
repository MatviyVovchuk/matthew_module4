<?php

namespace Drupal\matthew_tables\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying the Matthew Tables form.
 */
class MatthewTablesController extends ControllerBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
    );
  }

  /**
   * Returns a page with the table form.
   */
  public function content(): array {
    // Render the custom form.
    $form = $this->formBuilder->getForm('Drupal\matthew_tables\Form\MatthewTablesForm');
    return [
      '#theme' => 'matthew-tables',
      '#form' => $form,
      '#attached' => [
        'library' => [
          'matthew_tables/matthew_tables_styles',
        ],
      ],
    ];
  }

}
