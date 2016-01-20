<?php
/**
 * @file
 * Contains \Drupal\exif\Plugin\Field\FieldWidget\ExifReadonlytWidget.
 */

namespace Drupal\exif\Plugin\Field\FieldWidget;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\exif\Exif;
use Drupal\field\Entity\FieldConfig;


/**
 * Plugin implementation of the 'exif_readonly' widget.
 *
 * @FieldWidget(
 *   id = "exif_readonly",
 *   label = @Translation("metadata from image"),
 *   description = @Translation("field content is calculated from image field in the same content type (read only)"),
 *   multiple_values = true,
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "entity_reference",
 *     "date",
 *     "datetime",
 *     "datestamp"
 *   }
 * )
 */
class ExifReadonlyWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $exif_fields = $this->retrieveExifFields();
    $default_exif_value = $this->retrieveExifFieldDefaultValue();
    if ($form['#entity_type'] == "node") {
      $image_fields = $this->retrieveImageFieldFromBundle($form['#entity_type'],$form['#bundle']);
      $default_image_value = $this->retrieveImageFieldDefaultValue($element,$image_fields);
      $element['image_field'] = array(
        '#type' => 'radios',
        '#title' => t('image field to use to retreive data'),
        '#description' => t('determine the image used to look for exif and iptc metadata'),
        '#options' => $image_fields,
        '#default_value' => $default_image_value,
        '#element_validate' => array(array(get_class($this),'validateImageField'))
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
      '#options' => array_merge(array('naming_convention' => 'name of the field is used as the exif field name'),$exif_fields),
      '#default_value' => $default_exif_value,
      '#element_validate' => array(array(get_class($this), 'validateExifField'))
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $exif_field = $this->getSetting('exif_field');
    if (isset($exif_field) && $exif_field!='naming_convention') {
      $exif_field_msg = t('exif data will be extracted from image metadata field @metadata', array('@metadata' => $exif_field));
    } else {
      $fieldname = $this->fieldDefinition->getName();
      $exif_field=$fieldname.substr_replace("field_","",0);
      $exif_field_msg = t('Using naming convention. so the exif data will be extracted from image metadata field @metadata', array('@metadata' => $exif_field));
    }
    array_unshift($summary, $exif_field_msg);

    $image_field = $this->getSetting('image_field');
    if (isset($image_field)) {
      $image_field_msg = t('exif will be extracted from image @image', array('@image' => $image_field));
    }else {
      $image_field_msg = t('No image chosen. field will stay empty.');
    }
    array_unshift($summary, $image_field_msg);


    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    //$form['#attached']['css'][] = drupal_get_path('module', 'exif') . '/exif.css';
    $element += array(
      '#type' => '#hidden',
      '#value' => '',
      '#process' => array(array(get_class($this), 'process')),
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'exif_field' => 'naming_convention',
      'exif_update' => TRUE,
      'image_field' => NULL
    ) + parent::defaultSettings();
  }



  function process($element, FormStateInterface $form_state, $form) {

    $element['tid'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
    $element['value'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
    $element['timezone'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
    $element['value2'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );

    $element['display'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
    return $element;
  }

  function validateImageField($element, FormStateInterface $form_state, $form) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if ( !$elementSettings ) {
      //$form_state->setErrorByName('image_field', t('you must choose at least one image field to retreive metadata.'));
      $field_storage_definitions = \Drupal::entityManager()->getFieldStorageDefinitions($form['#entity_type']);
      $field_storage = $field_storage_definitions[$element['#field_name']];
      $args = array('%field' => $field_storage->getName());
      $message = t('Field %field must be link to an image field.', $args);
      $form_state->setErrorByName('image_field',$message);
    }
  }

  function validateExifField($element, FormStateInterface $form_state, $form) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if ( !$elementSettings ) {
      $message = t('you must choose at least one method to retreive image metadata.');
      $form_state->setErrorByName('exif_field',$message);
    }
  }

  /**
   * @return array of possible exif fields
   */
  private function retrieveExifFields() {
    $exif = Exif::getInstance();
    return $exif->getFieldKeys();
  }


  private function retrieveExifFieldDefaultValue() {
      $result = $this->getSetting('exif_field');
      if (empty($result) ) {
        $result='naming_convention';
      }
      return $result;
  }

  /**
   * calculate default value for settings form (more precisely image_field setting) of widget.
   * @param $widget
   * @param $image_fields
   */
  function retrieveImageFieldDefaultValue($widget,$image_fields) {
    $result = $widget['settings']['image_field'];
    if ( empty($result) ) {
      $temp = array_keys($image_fields);
      if (!empty($temp) && is_array($temp)) {
        $result= $temp[0];
      }
    }
    return $result;
  }

  function retrieveImageFieldFromBundle($entity_type, $bundle_name) {
    $fields_of_bundle = \Drupal::entityManager()->getFieldDefinitions($entity_type,$bundle_name);
    $result = array();
    foreach ($fields_of_bundle as $key => $value) {
      if ($value instanceof FieldConfig) {
        if ($value->getType() == "image" || $value->getType() == "media") {
          $result[$key] = $value->getLabel()." (".$key.")";
        }
      }
    }
    return $result;
  }

}
?>
