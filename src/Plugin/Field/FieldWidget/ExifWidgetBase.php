<?php
/**
 * @file
 * Contains \Drupal\exif\Plugin\Field\FieldWidget\ExifReadonlytWidget.
 */

namespace Drupal\exif\Plugin\Field\FieldWidget;

use Drupal;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exif\ExifFactory;
use Drupal\field\Entity\FieldConfig;


/**
 * Base class for 'Exif Field widget' plugin implementations.
 *
 * @ingroup field_widget
 */
abstract class ExifWidgetBase extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $exif_fields = $this->retrieveExifFields();
    $default_exif_value = $this->retrieveExifFieldDefaultValue();
    $default_exif_separator_value = $this->retrieveExifFieldDefaultSeparatorValue();
    if ($form['#entity_type'] == "node") {
      $image_fields = $this->retrieveImageFieldFromBundle($form['#entity_type'], $form['#bundle']);
      $default_image_value = $this->retrieveImageFieldDefaultValue($element, $image_fields);
      $element['image_field'] = array(
        '#type' => 'radios',
        '#title' => t('image field to use to retrieve data'),
        '#description' => t('determine the image used to look for exif and iptc metadata'),
        '#options' => $image_fields,
        '#default_value' => $default_image_value,
        '#element_validate' => array(
          array(
            get_class($this),
            'validateImageField'
          )
        )
      );
    }
    if ($form['#entity_type'] == "file") {
      $element['image_field'] = array(
        '#type' => 'hidden',
        '#default_value' => "file",
        '#value' => "file",
      );
    }
    $element['exif_field'] = array(
      '#type' => 'select',
      '#title' => t('exif field data'),
      '#description' => t('choose to retrieve data from the image field referenced with the selected name or by naming convention.'),
      '#options' => array_merge(array('naming_convention' => 'name of the field is used as the exif field name'), $exif_fields),
      '#default_value' => $default_exif_value,
      '#element_validate' => array(
        array(
          get_class($this),
          'validateExifField'
        )
      )
    );
    $element['exif_field_separator'] = array(
      '#type' => 'textfield',
      '#title' => t('exif field separator'),
      '#description' => t('separator used to split values (if field definition support several values). let it empty if you do not want to split values.'),
      '#default_value' => $default_exif_separator_value,
      '#element_validate' => array(
        array(
          get_class($this),
          'validateExifFieldSeparator'
        )
      )
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $exif_field_separator = $this->getSetting('exif_field_separator');
    if (isset($exif_field_separator) && strlen($exif_field_separator) == 1) {
      $exif_field_msg = t("exif value will be split using character separator '@separator'", array('@separator' => $exif_field_separator));
    }
    else {
      $exif_field_msg = t('exif value will be extracted as one value');
    }
    array_unshift($summary, $exif_field_msg);

    $exif_field = $this->getSetting('exif_field');
    if (isset($exif_field) && $exif_field != 'naming_convention') {
      $exif_field_msg = t("exif data will be extracted from image metadata field '@metadata'", array('@metadata' => $exif_field));
    }
    else {
      $fieldname = $this->fieldDefinition->getName();
      $exif_field = str_replace("field_", "", $fieldname);
      $exif_field_msg = t("Using naming convention. so the exif data will be extracted from image metadata field '@metadata'", array('@metadata' => $exif_field));
    }
    array_unshift($summary, $exif_field_msg);

    $image_field = $this->getSetting('image_field');
    if (isset($image_field)) {
      $bundle_name = $this->fieldDefinition->getTargetBundle();
      $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
      $image_field_config = \Drupal::entityManager()->getFieldDefinitions($entity_type, $bundle_name)[$image_field];
      if ($image_field_config instanceof FieldConfig) {
        if ($image_field_config->getType() == "image" || $image_field_config->getType() == "media") {
          $label = t("'@image_linked_label' (id: @image_linked_id)",array('@image_linked_label' => $image_field_config->getLabel(), '@image_linked_id' => $image_field));
        } else {
          $label = $image_field;
        }
      }
      $image_field_msg = t("exif will be extracted from image field @image", array('@image' => $label));
    }
    else {
      $image_field_msg = t('No image chosen. field will stay empty.');
    }
    array_unshift($summary, $image_field_msg);


    return $summary;
  }
  

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'exif_field_separator' => '',
      'exif_field' => 'naming_convention',
      'exif_update' => TRUE,
      'image_field' => NULL
    ) + parent::defaultSettings();
  }




  function validateImageField($element, FormStateInterface $form_state, $form) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if (!$elementSettings) {
      //$form_state->setErrorByName('image_field', t('you must choose at least one image field to retrieve metadata.'));
      $field_storage_definitions = Drupal::entityManager()
        ->getFieldStorageDefinitions($form['#entity_type']);
      $field_storage = $field_storage_definitions[$element['#field_name']];
      if ($field_storage) {
        $args = array('%field' => $field_storage->getName());
        $message = t('Field %field must be link to an image field.', $args);
      } else {
        $message = t('Field must be link to an image field.');
      }
      $form_state->setErrorByName('image_field', $message);
    }
  }

  function validateExifField($element, FormStateInterface $form_state, $form) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if (!$elementSettings) {
      $message = t('you must choose at least one method to retrieve image metadata.');
      $form_state->setErrorByName('exif_field', $message);
    }
  }

  function validateExifFieldSeparator($element, &$form_state) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if (!empty($elementSettings) && strlen($elementSettings) > 1) {
      $message = t('the separator is only one character long.');
      $form_state->setErrorByName('exif_field_separator', $message);
    }
  }

  /**
   * @return array of possible exif fields
   */
  private function retrieveExifFields() {
    $exif = ExifFactory::getExifInterface();
    return $exif->getFieldKeys();
  }


  private function retrieveExifFieldDefaultValue() {
    $result = $this->getSetting('exif_field');
    if (empty($result)) {
      $result = 'naming_convention';
    }
    return $result;
  }

  private function retrieveExifFieldDefaultSeparatorValue() {
    $result = $this->getSetting('exif_field_separator');
    if (empty($result)) {
      $result = '';
    }
    return $result;
  }


  /**
   * calculate default value for settings form (more precisely image_field setting) of widget.
   * @param $widget
   * @param $image_fields
   */
  function retrieveImageFieldDefaultValue($widget, $image_fields) {
    $result = $widget['settings']['image_field'];
    if (empty($result)) {
      $temp = array_keys($image_fields);
      if (!empty($temp) && is_array($temp)) {
        $result = $temp[0];
      }
    }
    return $result;
  }

  function retrieveImageFieldFromBundle($entity_type, $bundle_name) {
    $fields_of_bundle = \Drupal::entityManager()
      ->getFieldDefinitions($entity_type, $bundle_name);
    $result = array();
    foreach ($fields_of_bundle as $key => $value) {
      if ($value instanceof FieldConfig) {
        if ($value->getType() == "image" || $value->getType() == "media") {
          $result[$key] = $value->getLabel() . " (" . $key . ")";
        }
      }
    }
    return $result;
  }

}

?>
