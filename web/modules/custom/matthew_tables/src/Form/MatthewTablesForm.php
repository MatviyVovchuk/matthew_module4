<?php

namespace Drupal\matthew_tables\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\matthew_tables\Traits\MatthewTablesFormTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Matthew Tables form.
 */
class MatthewTablesForm extends FormBase {
  use MatthewTablesFormTrait;

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
    return $this->buildFormStructure($form, $form_state);
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

}
