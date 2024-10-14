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
    $table_count = $form_state->get('table_count') ?: 1;

    $form['add_table'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Table'),
      '#submit' => ['::addTable'],
      '#ajax' => [
        'callback' => '::addTableAjax',
        'wrapper' => 'matthew-tables-wrapper',
      ],
    ];

    $form['table_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'matthew-tables-wrapper'],
    ];

    $form['table_wrapper']['table'] = [
      '#tree' => TRUE,
    ];

    for ($table_index = 0; $table_index < $table_count; $table_index++) {
      $table_and_button = $this->buildTable($form_state, $table_index);
      $form['table_wrapper']['table'][$table_index]['years'] = $table_and_button['table'];
      $form['table_wrapper']['table'][$table_index]['add_year'] = $table_and_button['add_year'];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#attached']['library'][] = 'matthew_tables/matthew_tables';

    // Disable caching for this form.
    $form['#cache'] = [
      'max-age' => 0,
    ];

    return $form;
  }

  /**
   * Builds a single table.
   */
  private function buildTable(FormStateInterface $form_state, $table_index): array {
    $current_year = date('Y');
    $min_year = $form_state->get(['min_year', $table_index]) ?: $current_year;
    $years = range($current_year, $min_year);

    $table_wrapper = [
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Year'),
          'Jan', 'Feb', 'Mar', 'Q1',
          'Apr', 'May', 'Jun', 'Q2',
          'Jul', 'Aug', 'Sep', 'Q3',
          'Oct', 'Nov', 'Dec', 'Q4',
          'YTD',
        ],
      ],
    ];

    foreach ($years as $year) {
      $row = $this->buildYearRow($year, $form_state, $table_index);
      $table_wrapper['table'][$year] = $row;
    }

    $table_wrapper['add_year'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Year'),
      '#submit' => ['::addYear'],
      '#ajax' => [
        'callback' => '::addYearAjax',
        'wrapper' => 'matthew-tables-wrapper',
      ],
      '#name' => 'add_year_' . $table_index,
    ];

    return $table_wrapper;
  }

  /**
   * Builds a table row for a given year.
   */
  private function buildYearRow($year, FormStateInterface $form_state, $table_index): array {
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
        '#default_value' => $form_state->get(['values', $table_index, $year, $month]) ?? '',
        '#name' => "table[$table_index][years][$year][$month]",
      ];

      // Add quarter after every 3 months.
      if (($i + 1) % 3 == 0) {
        $quarter = $quarters[($i + 1) / 3 - 1];
        $row[$quarter] = [
          '#markup' => $form_state->get(['values', $table_index, $year, $quarter]) ?? '0.00',
        ];
      }
    }

    // Add YTD at the end.
    $row['ytd'] = [
      '#markup' => $form_state->get(['values', $table_index, $year, 'ytd']) ?? '0.00',
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('table');

    foreach ($values as $table_index => $table) {
      if (isset($table['years'])) {
        foreach ($table['years'] as $year => $year_data) {
          $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

          foreach ($months as $month) {
            if (!isset($year_data[$month]) || $year_data[$month] === '') {
              $form_state->setErrorByName("table][$table_index][years][$year][$month", $this->t('The field for @month @year in table @table is required.', [
                '@month' => ucfirst($month),
                '@year' => $year,
                '@table' => $table_index + 1,
              ]));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $table_data = $form_state->getValue('table');

    foreach ($table_data as $table_index => $table) {
      if (isset($table['years'])) {
        foreach ($table['years'] as $year => $year_data) {
          // Process the data for each year.
          $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
          $quarter_totals = [0, 0, 0, 0];
          $ytd_total = 0;

          foreach ($months as $i => $month) {
            $value = (float) ($year_data[$month] ?? 0);
            $quarter_totals[floor($i / 3)] += $value;
            $ytd_total += $value;
          }

          // Store the calculated values.
          for ($q = 0; $q < 4; $q++) {
            $form_state->set(['values', $table_index, $year, 'q' . ($q + 1)], number_format($quarter_totals[$q], 2));
          }
          $form_state->set(['values', $table_index, $year, 'ytd'], number_format($ytd_total, 2));

          // Store the input values.
          foreach ($months as $month) {
            $form_state->set(['values', $table_index, $year, $month], $year_data[$month] ?? '');
          }
        }
      }
    }

    $form_state->setRebuild(TRUE);
    $this->messenger()->addMessage($this->t('Calculations completed successfully.'));
  }

  /**
   * Submit handler for the "Add Year" button.
   */
  public function addYear(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $table_index = substr($triggering_element['#name'], strlen('add_year_'));

    $min_year = $form_state->get(['min_year', $table_index]) ?: date('Y');
    $form_state->set(['min_year', $table_index], $min_year - 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for the "Add Year" button.
   */
  public function addYearAjax(array &$form, FormStateInterface $form_state) {
    return $form['table_wrapper'];
  }

  /**
   * Submit handler for the "Add Table" button.
   */
  public function addTable(array &$form, FormStateInterface $form_state): void {
    $table_count = $form_state->get('table_count') ?: 1;
    $new_table_index = $table_count;
    $form_state->set('table_count', $table_count + 1);

    // Initialize the new table with the current year.
    $current_year = date('Y');
    $form_state->set(['min_year', $new_table_index], $current_year);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for the "Add Table" button.
   */
  public function addTableAjax(array &$form, FormStateInterface $form_state) {
    return $form['table_wrapper'];
  }

}
