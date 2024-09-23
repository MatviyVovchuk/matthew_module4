<?php

namespace Drupal\matthew_tables\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\matthew_tables\Traits\MatthewTablesFormTrait;

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
    try {
      $table_data = $form_state->getValue('table');

      foreach ($table_data as $table_index => $table) {
        if (isset($table['years'])) {
          foreach ($table['years'] as $year => $year_data) {
            $result = $this->tableService->calculateQuarterlyValues($year_data);
            $this->tableService->storeValues($form_state, $result, $table_index, $year);
          }
        }
      }

      $form_state->setRebuild(TRUE);
      $this->messenger()->addMessage($this->t('Calculations completed successfully.'));
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to calculate values. Error: @message', [
        '@message' => $e->getMessage(),
      ]);

      $this->messenger()->addError($this->t('An error occurred during calculations. Please try again or contact support.'));
    }
  }

}
