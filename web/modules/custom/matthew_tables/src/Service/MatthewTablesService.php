<?php

namespace Drupal\matthew_tables\Service;

use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a service for handling table calculations.
 */
class MatthewTablesService {
  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new MatthewTablesService object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Calculates quarterly values based on monthly data.
   *
   * @param array $monthly_data
   *   An array of monthly values.
   *
   * @return array
   *   An array containing quarterly values and the calculated monthly data.
   *
   * @throws \Exception
   *   If there's an error in calculations.
   */
  public function calculateQuarterlyValues(array $monthly_data): array {
    try {
      $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
      $quarters = [0, 0, 0, 0];

      // Calculate quarter values.
      foreach ($months as $i => $month) {
        $value = (float) ($monthly_data[$month] ?? 0);
        $quarters[floor($i / 3)] += $value;
      }

      // Formula for quarterly values: ((М1+М2+М3)+1)/3
      // Where М* is the value of the corresponding month of the quarter.
      for ($q = 0; $q < 4; $q++) {
        $calculated_value = (($quarters[$q] + 1) / 3);
        $rounded_value = round($calculated_value, 2);

        if (abs($rounded_value - $calculated_value) > 0.05) {
          throw new \Exception("Quarterly calculated value exceeds allowed deviation for Q" . ($q + 1));
        }

        $quarters[$q] = $rounded_value;
      }

      // Formula for yearly value: ((К1+К2+К3+К4)+1)/4
      // Where К* is the value of the corresponding quarter.
      $yearly_sum = array_sum($quarters);
      $calculated_yearly = (($yearly_sum + 1) / 4);
      $rounded_yearly = round($calculated_yearly, 2);

      if (abs($rounded_yearly - $calculated_yearly) > 0.05) {
        throw new \Exception("Yearly calculated value exceeds allowed deviation");
      }

      return [
        'quarters' => $quarters,
        'ytd' => $rounded_yearly,
        'monthly_data' => array_map(function ($value) {
          return $value ?? '';
        }, $monthly_data),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Error in quarterly/yearly calculations: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Stores calculated and input values in the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $result
   *   The result array containing calculated values and monthly data.
   * @param string $table_index
   *   The index of the current table.
   * @param string $year
   *   The year for which the values are being stored.
   */
  public function storeValues(FormStateInterface $form_state, array $result, string $table_index, string $year): void {
    // Store the calculated quarterly values.
    for ($quarter = 0; $quarter < 4; $quarter++) {
      $form_state->set(
        ['values', $table_index, $year, 'q' . ($quarter + 1)],
        $result['quarters'][$quarter]
      );
    }

    // Store the year-to-date (YTD) value.
    $form_state->set(['values', $table_index, $year, 'ytd'], $result['ytd']);

    // Store the input monthly values.
    foreach ($result['monthly_data'] as $month => $value) {
      $form_state->set(['values', $table_index, $year, $month], $value);
    }
  }

}
