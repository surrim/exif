<?php

namespace Drupal\exif\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'Exif Field widget' plugin implementations.
 *
 * @ingroup field_widget
 */
abstract class ExifWidgetBase extends WidgetBase {

  const EXIF_BASE_DEFAULT_SETTINGS = ['image_field' => NULL];

  use StringTranslationTrait;

  /**
   * Service that manages the discovery of entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a ExifWidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityFieldManagerInterface $entity_field_manager
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ExifWidgetBase::EXIF_BASE_DEFAULT_SETTINGS
      + parent::defaultSettings();
  }

  /**
   * Validate field to ensure it is linked to a image field.
   *
   * Use in settingsForm callback.
   *
   * @param array $element
   *   A form element array containing basic properties for the widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form
   *   The form structure where widgets are being attached to.
   */
  public static function validateImageField(array $element, FormStateInterface $form_state, array $form) {
    $elementSettings = $form_state->getValue($element['#parents']);
    if (!$elementSettings) {
      $field_storage_definitions = \Drupal::getContainer()
        ->get('entity_field.manager')
        ->getFieldStorageDefinitions($form['#entity_type']);
      $field_storage = $field_storage_definitions[$element['#field_name']];
      if ($field_storage) {
        $args = ['%field' => $field_storage->getName()];
        $message = t('Field %field must be link to an image field.', $args);
      }
      else {
        $message = t('Field must be link to an image field.');
      }
      $form_state->setErrorByName('image_field', $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    if ($form['#entity_type'] == "node" || $form['#entity_type'] == "media" || $form['#entity_type'] == "photos_image") {
      $image_fields = $this->retrieveImageFieldFromBundle($form['#entity_type'], $form['#bundle']);
      $default_image_value = $this->retrieveImageFieldDefaultValue($element, $image_fields);
      $element['image_field'] = [
        '#type' => 'radios',
        '#title' => $this->t('image field to use to retrieve data'),
        '#description' => $this->t('determine the image used to look for exif and iptc metadata'),
        '#options' => $image_fields,
        '#default_value' => $default_image_value,
        '#element_validate' => [
          [
            get_class($this),
            'validateImageField',
          ],
        ],
      ];
    }
    if ($form['#entity_type'] == "file") {
      $element['image_field'] = [
        '#type' => 'hidden',
        '#default_value' => "file",
        '#value' => "file",
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $image_field = $this->getSetting('image_field');
    if (isset($image_field)) {
      $bundle_name = $this->fieldDefinition->getTargetBundle();
      $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
      $image_field_config = \Drupal::getContainer()
        ->get('entity_field.manager')
        ->getFieldDefinitions($entity_type, $bundle_name)[$image_field];
      $label = $image_field;
      if ($image_field_config instanceof FieldConfig) {
        if ($image_field_config->getType() == "image" || $image_field_config->getType() == "media") {
          $label = $this->t("'@image_linked_label' (id: @image_linked_id)", [
            '@image_linked_label' => $image_field_config->getLabel(),
            '@image_linked_id' => $image_field,
          ]);
        }
      }
      $image_field_msg = $this->t("exif will be extracted from image field @image", [
        '@image' => $label,
      ]);
    }
    else {
      $image_field_msg = $this->t('No image chosen. field will stay empty.');
    }
    array_unshift($summary, $image_field_msg);
    return $summary;
  }

  /**
   * Retrieve list of image field labels by key of image field.
   *
   * @param string $entity_type
   *   Entity Type name.
   * @param string $bundle_name
   *   Name bundle.
   *
   * @return array
   *   Map of all images fields contained in this bundle by key and description.
   */
  protected function retrieveImageFieldFromBundle($entity_type, $bundle_name) {
    $fields_of_bundle = \Drupal::getContainer()
      ->get('entity_field.manager')
      ->getFieldDefinitions($entity_type, $bundle_name);
    $result = [];
    foreach ($fields_of_bundle as $key => $value) {
      if ($value instanceof FieldConfig) {
        if (in_array($value->getType(), ['image', 'media', 'file'])) {
          $result[$key] = $value->getLabel() . " (" . $key . ")";
        }
      }
    }
    return $result;
  }

  /**
   * Calculate default value for settings form.
   *
   * More precisely, it calculate default value
   * for image_field setting of widget.
   *
   * simple implementation: Look for the first image field found.
   *
   * @param array $widget
   *   Widget we are checking.
   * @param array $image_fields
   *   Image fields links to this widget.
   *
   * @return string
   *   First image field found or NULL if none.
   */
  protected function retrieveImageFieldDefaultValue(array $widget, array $image_fields) {
    if (array_key_exists('settings', $widget) && array_key_exists('image_field', $widget['settings'])) {
      $result = $widget['settings']['image_field'];
    }
    else {
      $result = NULL;
    }
    if (empty($result)) {
      // Look for the first image field found.
      $temp = array_keys($image_fields);
      if (!empty($temp) && is_array($temp)) {
        $result = $temp[0];
      }
    }
    return $result;
  }

}
