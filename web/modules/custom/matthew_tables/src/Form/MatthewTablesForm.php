<?php

namespace Drupal\matthew_tables\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Matthew Tables form.
 */
class MatthewTablesForm extends FormBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    LoggerInterface $logger,
  ) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): MatthewTablesForm|static {
    return new static(
      $container->get('logger.channel.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'matthew_tables_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $current_year = date('Y');
    $min_year = $form_state->get('min_year') ?: $current_year;
    $years = range($current_year, $min_year);

    $form['add_year'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Year'),
      '#submit' => ['::addYear'],
      '#ajax' => [
        'callback' => '::addYearAjax',
        'wrapper' => 'matthew-tables-wrapper',
      ],
    ];

    $form['table_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'matthew-tables-wrapper'],
    ];

    $form['table_wrapper']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Year'),
        'Jan', 'Feb', 'Mar', 'Q1',
        'Apr', 'May', 'Jun', 'Q2',
        'Jul', 'Aug', 'Sep', 'Q3',
        'Oct', 'Nov', 'Dec', 'Q4',
        'YTD',
      ],
    ];

    foreach ($years as $year) {
      $row = $this->buildYearRow($year, $form_state);
      $form['table_wrapper']['table'][$year] = $row;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#attached']['library'][] = 'matthew_tables/matthew_tables';

    $form['#cache'] = [
      'tags' => ['matthew_tables_entry_list'],
      'contexts' => ['user.permissions'],
      'max-age' => 0,
    ];

    return $form;
  }

  /**
   * Builds a table row for a given year.
   */
  private function buildYearRow($year, FormStateInterface $form_state): array {
    $row = [
      'year' => [
        '#plain_text' => $year,
      ],
    ];

    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $quarters = ['q1', 'q2', 'q3', 'q4'];

    for ($i = 0; $i < 12; $i++) {
      $month = $months[$i];
      $row[$month] = [
        '#type' => 'number',
        '#step' => 'any',
        '#min' => 0,
        '#size' => 8,
        '#attributes' => [
          'class' => ['month-input'],
        ],
        '#default_value' => $form_state->getValue([$year, $month]) ?? '',
      ];

      // Add quarter after every 3 months.
      if (($i + 1) % 3 == 0) {
        $quarter = $quarters[($i + 1) / 3 - 1];
        $row[$quarter] = [
          '#markup' => $form_state->getValue([$year, $quarter]) ?? '0.00',
        ];
      }
    }

    // Add YTD at the end.
    $row['ytd'] = [
      '#markup' => $form_state->getValue([$year, 'ytd']) ?? '0.00',
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $years = array_keys($form_state->getValue('table'));

    foreach ($years as $year) {
      $year_data = $form_state->getValue(['table', $year]);

      // Convert all to number.
      $jan = (float) $year_data['jan'];
      $feb = (float) $year_data['feb'];
      $mar = (float) $year_data['mar'];
      $apr = (float) $year_data['apr'];
      $may = (float) $year_data['may'];
      $jun = (float) $year_data['jun'];
      $jul = (float) $year_data['jul'];
      $aug = (float) $year_data['aug'];
      $sep = (float) $year_data['sep'];
      $oct = (float) $year_data['oct'];
      $nov = (float) $year_data['nov'];
      $dec = (float) $year_data['dec'];

      // Calculate quarters.
      $q1 = $jan + $feb + $mar;
      $q2 = $apr + $may + $jun;
      $q3 = $jul + $aug + $sep;
      $q4 = $oct + $nov + $dec;

      // Calculate YTD.
      $ytd = $q1 + $q2 + $q3 + $q4;

      $this->logger('matthew_tables')->error('year: @year | q1: @q1 | q2: @q2 | q3: @q3 | q4: @q4 | ytd: @ytd', [
        '@year' => $year,
        '@q1' => $q1,
        '@q2' => $q2,
        '@q3' => $q3,
        '@q4' => $q4,
        '@ytd' => $ytd,
      ]);

      // Set the calculated values.
      $form_state->setValue(['table', $year, 'q1'], number_format($q1, 2));
      $form_state->setValue(['table', $year, 'q2'], number_format($q2, 2));
      $form_state->setValue(['table', $year, 'q3'], number_format($q3, 2));
      $form_state->setValue(['table', $year, 'q4'], number_format($q4, 2));
      $form_state->setValue(['table', $year, 'ytd'], number_format($ytd, 2));
    }

    $form_state->setRebuild(TRUE);
    $this->messenger()->addMessage($this->t('Calculations completed successfully.'));
  }

  /**
   * Submit handler for the "Add Year" button.
   */
  public function addYear(array &$form, FormStateInterface $form_state): void {
    $min_year = $form_state->get('min_year') ?: date('Y');
    $form_state->set('min_year', $min_year - 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for the "Add Year" button.
   */
  public function addYearAjax(array &$form, FormStateInterface $form_state) {
    return $form['table_wrapper'];
  }

}
