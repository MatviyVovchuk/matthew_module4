<?php

namespace Drupal\matthew_tables\Service;

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
      $ytd_total = 0;

      foreach ($months as $i => $month) {
        $value = (float) ($monthly_data[$month] ?? 0);
        $quarter_index = floor($i / 3);
        $quarters[$quarter_index] += $value;
        $ytd_total += $value;
      }

      // Calculate quarter values using the provided formula.
      for ($q = 0; $q < 4; $q++) {
        $start_month = $q * 3;
        $quarter_sum = 0;
        for ($m = 0; $m < 3; $m++) {
          $month_value = (float) ($monthly_data[$months[$start_month + $m]] ?? 0);
          $quarter_sum += $month_value;
        }
        $quarters[$q] = number_format((($quarter_sum + 1) / 3), 2);
      }

      $result = [
        'quarters' => $quarters,
        'ytd' => number_format($ytd_total, 2),
        'monthly_data' => array_map(function ($value) {
          return $value ?? '';
        }, $monthly_data),
      ];

      return $result;
    }
    catch (\Exception $e) {
      $this->logger->error('Error in quarterly calculations: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
