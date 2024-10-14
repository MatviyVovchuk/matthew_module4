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
    $triggering_element = $form_state->getTriggeringElement();

    // Skip the validation if the “Add year” button is pressed.
    if (isset($triggering_element['#name']) && str_starts_with($triggering_element['#name'], 'add_year_')) {
      return;
    }

    $values = $form_state->getValue('table');
    if (empty($values)) {
      return;
    }

    $first_table = reset($values);
    $first_table_index = key($values);

    // Validate the first table.
    $this->validateFirstTable($first_table, $first_table_index, $form_state);

    // If the first table is valid, validate other tables against it.
    if (!$form_state->getErrors()) {
      $this->validateOtherTables($values, $first_table, $first_table_index, $form_state);
    }
  }

  /**
   * Validates the first table for all conditions.
   */
  protected function validateFirstTable(array $table, int $table_index, FormStateInterface $form_state): void {
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $years = $this->sortYearsDescending($table['years']);

    $filled_cells = $this->getFilledCells($years, $months);

    $this->validateNoGaps($filled_cells, $table_index, $form_state);
    $this->validateConsecutiveYears($years, $table_index, $form_state);
  }

  /**
   * Validates other tables against the first table.
   */
  protected function validateOtherTables(array $all_tables, array $first_table, int $first_table_index, FormStateInterface $form_state): void {
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $reference_filled_cells = $this->getFilledCells($first_table['years'], $months);

    foreach ($all_tables as $table_index => $table) {
      if ($table_index === $first_table_index) {
        continue;
      }

      $this->validateTableAgainstReference($table, $table_index, $reference_filled_cells, $form_state);
    }
  }

  /**
   * Gets filled cells from a table.
   */
  protected function getFilledCells(array $years, array $months): array {
    $filled_cells = [];

    foreach ($years as $year => $year_data) {
      foreach ($months as $month_index => $month) {
        if ($year_data[$month] !== '') {
          $filled_cells[] = [
            'year' => $year,
            'month' => $month_index,
          ];
        }
      }
    }

    return $filled_cells;
  }

  /**
   * Validates that there are no gaps in filled cells.
   */
  protected function validateNoGaps(array $filled_cells, int $table_index, FormStateInterface $form_state): void {
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    if (empty($filled_cells)) {
      return;
    }

    usort($filled_cells, function ($a, $b) {
      return ($a['year'] * 12 + $a['month']) - ($b['year'] * 12 + $b['month']);
    });

    $first_cell = reset($filled_cells);
    $last_cell = end($filled_cells);

    $current_year = $first_cell['year'];
    $current_month = $first_cell['month'];

    $gaps = [];

    while ($current_year < $last_cell['year'] || ($current_year == $last_cell['year'] && $current_month <= $last_cell['month'])) {
      $cell_exists = FALSE;
      foreach ($filled_cells as $cell) {
        if ($cell['year'] == $current_year && $cell['month'] == $current_month) {
          $cell_exists = TRUE;
          break;
        }
      }

      if (!$cell_exists) {
        $gaps[] = [
          'year' => $current_year,
          'month' => $months[$current_month],
        ];
      }

      $current_month++;
      if ($current_month == 12) {
        $current_month = 0;
        $current_year++;
      }
    }

    foreach ($gaps as $gap) {
      $this->setFieldError($form_state, $table_index, $gap['year'], $gap['month'], 'gap');
    }
  }

  /**
   * Validates that years are consecutive.
   */
  protected function validateConsecutiveYears(array $years, int $table_index, FormStateInterface $form_state): void {
    $year_keys = array_keys($years);
    if (count($year_keys) <= 1) {
      return;
    }

    $min_year = min($year_keys);
    $max_year = max($year_keys);

    $missing_years = [];

    for ($year = $min_year; $year <= $max_year; $year++) {
      if (!isset($years[$year])) {
        $missing_years[] = $year;
      }
    }

    foreach ($missing_years as $year) {
      $this->setFieldError($form_state, $table_index, $year, 'jan', 'missing_year');
    }
  }

  /**
   * Validates a table against the reference (first) table.
   */
  protected function validateTableAgainstReference(array $table, int $table_index, array $reference_filled_cells, FormStateInterface $form_state): void {
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $current_filled_cells = $this->getFilledCells($table['years'], $months);

    $inconsistent_cells = [];
    $extra_cells = [];

    foreach ($reference_filled_cells as $ref_cell) {
      $cell_filled = FALSE;
      foreach ($current_filled_cells as $curr_cell) {
        if ($curr_cell['year'] == $ref_cell['year'] && $curr_cell['month'] == $ref_cell['month']) {
          $cell_filled = TRUE;
          break;
        }
      }

      if (!$cell_filled) {
        $inconsistent_cells[] = $ref_cell;
      }
    }

    foreach ($current_filled_cells as $curr_cell) {
      $cell_in_reference = FALSE;
      foreach ($reference_filled_cells as $ref_cell) {
        if ($curr_cell['year'] == $ref_cell['year'] && $curr_cell['month'] == $ref_cell['month']) {
          $cell_in_reference = TRUE;
          break;
        }
      }

      if (!$cell_in_reference) {
        $extra_cells[] = $curr_cell;
      }
    }

    foreach ($inconsistent_cells as $cell) {
      $this->setFieldError($form_state, $table_index, $cell['year'], $months[$cell['month']], 'inconsistent_data');
    }

    foreach ($extra_cells as $cell) {
      $this->setFieldError($form_state, $table_index, $cell['year'], $months[$cell['month']], 'extra_data');
    }
  }

  /**
   * Sorts years in descending order.
   */
  protected function sortYearsDescending(array $years): array {
    krsort($years);
    return $years;
  }

  /**
   * Sets an error for a specific field in the form.
   */
  protected function setFieldError(FormStateInterface $form_state, int $table_index, int $year, string $month, string $error_type): void {
    $error_message = match ($error_type) {
      'gap' => $this->t('There is a gap in the data for @month @year in table @table. Please fill in all months consecutively.', [
        '@month' => ucfirst($month),
        '@year' => $year,
        '@table' => $table_index + 1,
      ]),
      'missing_year' => $this->t('Year @year is missing in table @table. Please ensure all years are consecutive.', [
        '@year' => $year,
        '@table' => $table_index + 1,
      ]),
      'inconsistent_data' => $this->t('The data for @month @year in table @table does not match the reference table. Please ensure all tables have the same filled periods.', [
        '@month' => ucfirst($month),
        '@year' => $year,
        '@table' => $table_index + 1,
      ]),
      'extra_data' => $this->t('Table @table has extra data for @month @year. Please remove this data to match the reference table.', [
        '@table' => $table_index + 1,
        '@month' => ucfirst($month),
        '@year' => $year,
      ]),
      'required' => $this->t('The field for @month @year in table @table is required.', [
        '@month' => ucfirst($month),
        '@year' => $year,
        '@table' => $table_index + 1,
      ]),
      default => $this->t('An error occurred in the field for @month @year in table @table.', [
        '@month' => ucfirst($month),
        '@year' => $year,
        '@table' => $table_index + 1,
      ]),
    };

    $form_state->setErrorByName("table][$table_index][years][$year][$month", $error_message);
  }

  /**
   * Validates that all required fields in the tables are filled.
   *
   * @param array $values
   *   The form values to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateTableFields(array $values, FormStateInterface $form_state): void {
    foreach ($values as $table_index => $table) {
      if (isset($table['years'])) {
        $this->validateYearFields($table['years'], $table_index, $form_state);
      }
    }
  }

  /**
   * Validates that all required fields for each year are filled.
   *
   * @param array $years
   *   The year data to validate.
   * @param int $table_index
   *   The index of the current table.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateYearFields(array $years, int $table_index, FormStateInterface $form_state): void {
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    foreach ($years as $year => $year_data) {
      foreach ($months as $month) {
        if (!isset($year_data[$month]) || $year_data[$month] === '') {
          $this->setFieldError($form_state, $table_index, $year, $month, 'required');
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
    $new_min_year = $min_year - 1;
    $form_state->set(['min_year', $table_index], $new_min_year);

    // Add the new year to the form values.
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    $values = $form_state->getValue(['table', $table_index, 'years']) ?: [];
    $values[$new_min_year] = array_fill_keys($months, '');
    $form_state->setValue(['table', $table_index, 'years'], $values);

    // Clear all existing errors.
    $form_state->clearErrors();

    // Add a message to inform the user that a new year has been added.
    $this->messenger()->addMessage($this->t('A new year (@year) has been added to table @table.', [
      '@year' => $new_min_year,
      '@table' => (int) $table_index + 1,
    ]));

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

    // Add a message to inform the user that a new year has been added.
    $this->messenger()->addMessage($this->t('A new table (@table) has been added.', [
      '@table' => $new_table_index + 1,
    ]));

    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback for the "Add Table" button.
   */
  public function addTableAjax(array &$form, FormStateInterface $form_state) {
    return $form['table_wrapper'];
  }

}
