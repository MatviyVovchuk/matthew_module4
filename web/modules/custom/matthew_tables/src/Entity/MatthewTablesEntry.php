<?php

namespace Drupal\matthew_tables\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Matthew Tables Entry entity.
 *
 * @ingroup matthew_tables
 *
 * @ContentEntityType(
 *   id = "matthew_tables_entry",
 *   label = @Translation("Matthew Tables Entry"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\matthew_tables\MatthewTablesEntryListBuilder",
 *     "form" = {
 *       "default" = "Drupal\matthew_tables\Form\MatthewTablesEntryForm",
 *       "add" = "Drupal\matthew_tables\Form\MatthewTablesEntryForm",
 *       "edit" = "Drupal\matthew_tables\Form\MatthewTablesEntryForm",
 *       "delete" = "Drupal\matthew_tables\Form\MatthewTablesEntryDeleteForm",
 *     },
 *     "access" = "Drupal\matthew_tables\MatthewTablesEntryAccessControlHandler",
 *   },
 *   base_table = "matthew_tables_entry",
 *   admin_permission = "administer matthew tables entries",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "year" = "year",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/matthew_tables_entry/{matthew_tables_entry}",
 *     "add-form" = "/admin/structure/matthew_tables_entry/add",
 *     "edit-form" = "/admin/structure/matthew_tables_entry/{matthew_tables_entry}/edit",
 *     "delete-form" = "/admin/structure/matthew_tables_entry/{matthew_tables_entry}/delete",
 *     "collection" = "/admin/structure/matthew_tables_entry",
 *   },
 * )
 */
class MatthewTablesEntry extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['year'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year'))
      ->setDescription(t('The year for this entry.'))
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
      'jan' => 'January',
      'feb' => 'February',
      'mar' => 'March',
      'apr' => 'April',
      'may' => 'May',
      'jun' => 'June',
      'jul' => 'July',
      'aug' => 'August',
      'sep' => 'September',
      'oct' => 'October',
      'nov' => 'November',
      'dec' => 'December',
    ];

    foreach ($months as $key => $label) {
      $fields[$key] = BaseFieldDefinition::create('float')
        ->setLabel($label)
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
        ->setLabel(strtoupper($quarter))
        ->setDescription(t('The total for @quarter.', ['@quarter' => strtoupper($quarter)]))
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

    $fields['ytd'] = BaseFieldDefinition::create('float')
      ->setLabel(t('YTD'))
      ->setDescription(t('The year-to-date total.'))
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

    return $fields;
  }

}
