<?php

namespace Drupal\matthew_tables\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Yearly Data entity.
 *
 * @ingroup matthew_tables
 *
 * @ContentEntityType(
 *   id = "yearly_data",
 *   label = @Translation("Yearly Data"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\matthew_tables\YearlyDataListBuilder",
 *     "form" = {
 *       "default" = "Drupal\matthew_tables\Form\YearlyDataForm",
 *       "add" = "Drupal\matthew_tables\Form\YearlyDataForm",
 *       "edit" = "Drupal\matthew_tables\Form\YearlyDataForm",
 *       "delete" = "Drupal\matthew_tables\Form\YearlyDataDeleteForm",
 *     },
 *     "access" = "Drupal\matthew_tables\YearlyDataAccessControlHandler",
 *   },
 *   base_table = "yearly_data",
 *   admin_permission = "administer yearly data",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "year" = "year",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/yearly_data/{yearly_data}",
 *     "add-form" = "/admin/structure/yearly_data/add",
 *     "edit-form" = "/admin/structure/yearly_data/{yearly_data}/edit",
 *     "delete-form" = "/admin/structure/yearly_data/{yearly_data}/delete",
 *     "collection" = "/admin/structure/yearly_data",
 *   },
 * )
 */
class MatthewTablesEntry extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year'))
      ->setDescription(t('The year for this data.'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $months = [
      'jan' => 'January', 'feb' => 'February', 'mar' => 'March',
      'apr' => 'April', 'may' => 'May', 'jun' => 'June',
      'jul' => 'July', 'aug' => 'August', 'sep' => 'September',
      'oct' => 'October', 'nov' => 'November', 'dec' => 'December',
    ];

    foreach ($months as $key => $label) {
      $fields[$key] = BaseFieldDefinition::create('float')
        ->setLabel(t($label))
        ->setDescription(t('The value for @month.', ['@month' => $label]))
        ->setDefaultValue(0)
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'number_decimal',
          'weight' => 0,
        ])
        ->setDisplayOptions('form', [
          'type' => 'number',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    }

    $quarters = ['q1', 'q2', 'q3', 'q4'];
    foreach ($quarters as $quarter) {
      $fields[$quarter] = BaseFieldDefinition::create('float')
        ->setLabel(t(strtoupper($quarter)))
        ->setDescription(t('The total for @quarter.', ['@quarter' => strtoupper($quarter)]))
        ->setComputed(TRUE)
        ->setClass('\Drupal\matthew_tables\Plugin\Field\QuarterlyTotalField')
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'number_decimal',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('view', TRUE);
    }

    $fields['ytd'] = BaseFieldDefinition::create('float')
      ->setLabel(t('YTD'))
      ->setDescription(t('The year-to-date total.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\matthew_tables\Plugin\Field\YTDTotalField')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
