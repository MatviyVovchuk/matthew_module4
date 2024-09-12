<?php

namespace Drupal\matthew_tables\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Matthew Tables form.
 */
class MatthewTablesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'matthew_tables_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_year = date('Y');
    $years = range($current_year - 3, $current_year);

    $form['table'] = [
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
        ];

        // Add quarter after every 3 months.
        if (($i + 1) % 3 == 0) {
          $quarter = $quarters[($i + 1) / 3 - 1];
          $row[$quarter] = [
            '#markup' => '<span class="quarter-total" data-quarter="' . $quarter . '">0.00</span>',
          ];
        }
      }

      // Add YTD at the end.
      $row['ytd'] = [
        '#markup' => '<span class="ytd-total">0.00</span>',
      ];

      $form['table'][$year] = $row;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#attached']['library'][] = 'matthew_tables/matthew_tables';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Process the form submission here.
    $this->messenger()->addMessage($this->t('The form submitted successfully.'));
  }

}
