<?php
/**
 * @file
 * Contains \Drupal\exif\Controller\ExifController
 */

namespace Drupal\exif\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ExifSettingsController extends ControllerBase {


  /**
   * button to create a vocabulary "photographies'metadata" (exif,iptc and xmp data contains in jpeg file)
   * @return Response
   */
  public function showGuide() {
    return [
      '#message' => "",
      '#taxonomy' => 'http://drupal.org/handbook/modules/taxonomy/',
      '#theme' => 'exif_helper_page',
      '#attached' => array(
        'library' =>  array(
          'exif/exif-admin'
        ),
      ),
    ];
  }

  /**
   * button to create a vocabulary "photographies'metadata" (exif,iptc and xmp data contains in jpeg file)
   * @return Response
   */
  public function createPhotographyVocabulary() {
    $values = array(
      "name" => "photographs metadata",
      "vid" => "photographs_metadata",
      "description" => "information related to photographs"
    );
    $voc = Vocabulary::load("photographs_metadata");
    if (!$voc) {
      Vocabulary::create($values)->save();
      $message = $this->t('The  vocabulary photography has been created');
    } else {
      $message = $this->t('The  vocabulary photography is already created. nothing to do');
    }
    drupal_set_message($message);
    $response = new RedirectResponse('/admin/config/media/exif/helper');
    $response->send();
    exit();
  }

  protected function configureEntityFormDisplay($field_name, $widget_id = NULL) {
    // Make sure the field is displayed in the 'default' form mode (using
    // default widget and settings). It stays hidden for other form modes
    // until it is explicitly configured.
    $options = $widget_id ? ['type' => $widget_id] : [];
    $this->entity_get_form_display($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($field_name, $options)
      ->save();
  }

  protected function configureEntityViewDisplay($field_name, $formatter_id = NULL) {
    // Make sure the field is displayed in the 'default' view mode (using
    // default formatter and settings). It stays hidden for other view
    // modes until it is explicitly configured.
    $options = $formatter_id ? ['type' => $formatter_id] : [];
    $this->entity_get_display($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($field_name, $options)
      ->save();
  }

  function entity_get_display($entity_type, $bundle, $view_mode) {
    // Try loading the display from configuration.
    $display = EntityViewDisplay::load($entity_type . '.' . $bundle . '.' . $view_mode);

    // If not found, create a fresh display object. We do not preemptively create
    // new entity_view_display configuration entries for each existing entity type
    // and bundle whenever a new view mode becomes available. Instead,
    // configuration entries are only created when a display object is explicitly
    // configured and saved.
    if (!$display) {
      $display = EntityViewDisplay::create(array(
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $view_mode,
        'status' => TRUE,
      ));
    }

    return $display;
  }

  function entity_get_form_display($entity_type, $bundle, $form_mode) {
    // Try loading the entity from configuration.
    $entity_form_display = EntityFormDisplay::load($entity_type . '.' . $bundle . '.' . $form_mode);

    // If not found, create a fresh entity object. We do not preemptively create
    // new entity form display configuration entries for each existing entity type
    // and bundle whenever a new form mode becomes available. Instead,
    // configuration entries are only created when an entity form display is
    // explicitly configured and saved.
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create(array(
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $form_mode,
        'status' => TRUE,
      ));
    }

    return $entity_form_display;
  }

  function get_field_storage_config() {
    return $this->entityTypeManager()
      ->getStorage('field_storage_config');
  }

  function get_field_config() {
    return $this->entityTypeManager()
      ->getStorage('field_config');
  }

  protected function addFieldToEntityType($entity_type, EntityInterface $type, $fieldLabel, $fieldName, $fieldType, $fieldWidget, $widgetSettings = array(), $settings = array()) {
    $realFieldName = 'field_' . $fieldName;
    $storage = $this->get_field_storage_config();
    $field_storage = $storage->load($entity_type . '.' . $realFieldName);
    if (empty($field_storage)) {
      $field_storage_values = [
        'field_name' => $realFieldName,
        'field_label' => $fieldLabel,
        'entity_type' => $entity_type,
        'bundle' => $type->id(),
        'type' => $fieldType,
        'translatable' => FALSE,
      ];
      $storage->create($field_storage_values)->save();
    }
    $fieldSettings =  array('display_summary' => TRUE);
    $this->entity_add_extra_field($entity_type, $type, $realFieldName, $fieldLabel, $fieldSettings, $fieldWidget, $widgetSettings);
  }

  protected function addReferenceToEntityType($entity_type, EntityInterface $type, $fieldLabel, $fieldName, $fieldType, $fieldTypeBundle, $fieldWidget, $widgetSettings = array(), $settings = array()) {
    $realFieldName = 'field_' . $fieldName;
    $storage = $this->get_field_storage_config();
    $field_storage = $storage->load($entity_type . '.' . $realFieldName);
    if (empty($field_storage)) {
      $field_storage_values = array(
        'field_name' => $realFieldName,
        'field_label' => $fieldLabel,
        'entity_type' => $entity_type,
        'bundle' => $type->id(),
        'type' => 'entity_reference',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        'translatable' => FALSE,
        'settings' => array(
          'target_type' => $fieldType
        )
      );
      $temp = $storage->create($field_storage_values);
      $temp->save();
    }
    $fieldSettings = array(
      'handler' => 'default:'.$fieldType,
      'handler_settings'=> array(
        'target_bundles' =>  array($fieldTypeBundle => $fieldTypeBundle),
      ),
      'sort' => array('field' => '_none'),
      'auto_create' => FALSE,
       'auto_create_bundle' => ''
    );
    $this->entity_add_extra_field($entity_type, $type, $realFieldName, $fieldLabel, $fieldSettings, $fieldWidget, $widgetSettings);
  }


  protected function entity_add_extra_field($entity_type, EntityInterface $type, $name, $fieldLabel, $fieldSettings, $fieldWidget, $widgetSettings) {
    $machinename = strtolower($name);
    // Add or remove the body field, as needed.
    $storage = $this->get_field_storage_config();
    $field_storage = $storage->load($entity_type . '.' . $machinename);
    $field_config = $this->get_field_config();
    $field = $field_config->load($entity_type . '.' . $type->id() . '.' . $machinename);
    if (empty($field)) {
      $field = $field_config->create(array(
        'field_storage' => $field_storage,
        'bundle' => $type->id(),
        'label' => $fieldLabel,
        'settings' => $fieldSettings,
      ));
      $field->save();
    }

    // Assign widget settings for the 'default' form mode.
    $this->entity_get_form_display($entity_type, $type->id(), 'default')
      ->setComponent($machinename, array(
        'type' => $fieldWidget,
        'settings' => $widgetSettings
      ))
      ->save();

    // Assign display settings for the 'default' and 'teaser' view modes.
    $this->entity_get_display($entity_type, $type->id(), 'default')
      ->setComponent($machinename, array(
        'label' => 'hidden',
        'type' => 'text_default',
      ))
      ->save();

    // The teaser view mode is created by the Standard profile and therefore
    // might not exist.
    $view_modes = Drupal::entityManager()->getViewModes($entity_type);
    if (isset($view_modes['teaser'])) {
      $this->entity_get_display($entity_type, $type->id(), 'teaser')
        ->setComponent($machinename, array(
          'label' => 'hidden',
          'type' => 'text_summary_or_trimmed',
        ))
        ->save();
    }


    return $field;
  }

  /**
   * Button to create an Photography node type with default exif field (title,model,keywords) and default behavior but 'promoted to front'
   * @return Response
   */
  public function createPhotographyNodeType() {
    $typeName = 'Photography';
    $entity_type = 'node';
    $machinename = strtolower($typeName);
    try {
      $storage = $this->entityTypeManager()->getStorage('node_type');
      $type_definition = $storage->load($machinename);
      if (!$type_definition) {
        $type_definition = $storage->create(
          [
            'name' => $typeName,
            'type' => $machinename,
            'description' => 'Use Photography for content where the photo is the main content. You still can have some other information related to the photo itself.',
          ]);
        $type_definition->save();
        //specific to node type interface
        //node_add_body_field( $type_definition );
      }

      //add default display
      $values = array(
        'targetEntityType' => $entity_type,
        'bundle' => $machinename,
        'mode' => 'default',
        'status' => TRUE
      );
      $this->entityTypeManager()
        ->getStorage('entity_view_display')
        ->create($values);

      //add default form display
      $values = array(
        'targetEntityType' => $entity_type,
        'bundle' => $machinename,
        'mode' => 'default',
        'status' => TRUE,
      );
      $this->entityTypeManager()
        ->getStorage('entity_form_display')
        ->create($values);

      //$this->configureEntityFormDisplay($fieldName);
      //$this->configureEntityViewDisplay($fieldName);

      //then add fields
      $this->add_fields($entity_type, $type_definition);
      $message = $this->t('The %entitytype type %type has been fully created',array( '%entitytype' => $entity_type, '%type' => $typeName));

    } catch (FieldException $fe) {
      $message = $this->t('An unexpected error was thrown during creation : ') . $fe->getMessage();
    }
    drupal_set_message($message);
    $response = new RedirectResponse('/admin/config/media/exif/helper');
    $response->send();
    exit();
  }

  /**
   * Button to create an Photography node type with default exif field (title,model,keywords) and default behavior but 'promoted to front'
   * @return Response
   */
  public function createPhotographyMediaType() {
    $typeName = 'Photography';
    $entity_type = 'media';
    $machinename = strtolower($typeName);

    try {

      if (Drupal::moduleHandler()->moduleExists("media_entity")) {
        $storage = $this->entityTypeManager()->getStorage($entity_type);
        $type_definition = $storage->load($machinename);
        if (!$type_definition) {
          $type_definition = $storage->create(
            [
              'name' => $typeName,
              'type' => $machinename,
              'description' => 'Use Photography for content where the photo is the main content. You still can have some other information related to the photo itself.',
            ]);
          $type_definition->save();
        }

        //add default display
        $values = array(
          'targetEntityType' => $entity_type,
          'bundle' => $machinename,
          'mode' => 'default',
          'status' => TRUE
        );
        $this->entityTypeManager()
          ->getStorage('entity_view_display')
          ->create($values);

        //add default form display
        $values = array(
          'targetEntityType' => $entity_type,
          'bundle' => $machinename,
          'mode' => 'default',
          'status' => TRUE,
        );
        $this->entityTypeManager()
          ->getStorage('entity_form_display')
          ->create($values);

        //$this->configureEntityFormDisplay($fieldName);
        //$this->configureEntityViewDisplay($fieldName);

        //then add fields
        $this->add_fields($entity_type,$type_definition);
        $message = $this->t('The %entitytype type %type has been fully created',array( '%entitytype' => $entity_type, '%type' => $typeName));
      } else {
        $message = 'Nothing done. Media modules not present.';
      }
    } catch (FieldException $fe) {
      $message = $this->t('An unexpected error was thrown during creation : ') . $fe->getMessage();
    }
    drupal_set_message($message);
    $response = new RedirectResponse('/admin/config/media/exif/helper');
    $response->send();
    exit();
  }


  function sanitize_value($exif_value) {
    if (!Drupal\Component\Utility\Unicode::validateUtf8($exif_value)) {
      $exif_value = Drupal\Component\Utility\Html::escape(utf8_encode($exif_value));
    }
    return $exif_value;
  }

  public function showSample() {
    $sampleImageFilePath = drupal_get_path('module', 'exif') . '/sample.jpg';
    $exif = Drupal\exif\ExifFactory::getExifInterface();
    $fullmetadata = $exif->readMetadataTags($sampleImageFilePath);
    $html = '<table class="metadata-table"><tbody>';
    foreach ($fullmetadata as $currentSection => $currentValues) {
      $html .= '<tr class="metadata-section"><td colspan="2">'.$currentSection.'</td></tr>';
      foreach ($currentValues as $currentKey => $currentValue) {
        $exif_value = $this->sanitize_value($currentValue);
        $html .= '<tr class="metadata-row '.$currentKey.'"><td class="metadata-key">'.$currentKey.'</td><td class="metadata-value">'.$exif_value.'</td></tr>';
      }
    }
    $html .= '</tbody><tfoot></tfoot></table>';
    return [
      '#metadata' => $html,
      '#image_path' => '/'.$sampleImageFilePath,
      '#taxo' => '',//url('admin/structure/taxonomy'),
      '#permissionLink' => '',//url('admin/config/people/permissions'),
      '#taxonomyFragment' => '', //module - taxonomy
      '#theme' => 'exif_sample',
      '#attached' => array(
        'library' =>  array(
          'exif/exif-sample'
        ),
      ),
    ];
  }

  public function add_fields($entity_type, EntityInterface $type_definition) {
    //first, add image field
    $this->addFieldToEntityType($entity_type, $type_definition, 'Photo', 'image', 'image', 'exif_readonly');
    $widget_settings = [
      'image_field' => 'field_image',
      'exif_field' => 'naming_convention'
    ];

    //then add all extra fields (metadata)
    //date
    $this->addFieldToEntityType($entity_type, $type_definition, 'Creation date', 'exif_datetime', 'datetime', 'exif_readonly', $widget_settings);
    //text
    $this->addFieldToEntityType($entity_type, $type_definition, 'Photo Comment', 'exif_comments', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Photo Description', 'exif_imagedescription', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Photo Title', 'exif_title', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Make', 'exif_make', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Aperture', 'exif_aperturefnumber', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Exposure', 'exif_exposuretime', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'ISO', 'exif_isospeedratings', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Focal', 'exif_focallength', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Flash', 'exif_flash', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Exposure Program', 'exif_exposureprogram', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'Exposure Mode', 'exif_exposuremode', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'White Balance Mode', 'exif_whitebalance', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'scene Mode', 'exif_scenecapturetype', 'text', 'exif_readonly', $widget_settings);
    $this->addFieldToEntityType($entity_type, $type_definition, 'orientation', 'exif_orientation', 'text', 'exif_readonly', $widget_settings);
    //terms
    $this->addReferenceToEntityType($entity_type, $type_definition, 'Photographer', 'exif_author', 'taxonomy_term','photographs_metadata', 'exif_readonly', $widget_settings);
    $this->addReferenceToEntityType($entity_type, $type_definition, 'Camera', 'exif_model', 'taxonomy_term','photographs_metadata', 'exif_readonly', $widget_settings);
    $this->addReferenceToEntityType($entity_type, $type_definition, 'ISO', 'exif_isospeedratings', 'taxonomy_term','photographs_metadata', 'exif_readonly', $widget_settings);
    $widget_settings_for_tags =  [
      'image_field' => 'field_image',
      'exif_field' => 'naming_convention' ,
      'exif_field_separator' => ';',
    ];
    $this->addReferenceToEntityType($entity_type, $type_definition, 'Tags', 'exif_keywords', 'taxonomy_term','photographs_metadata', 'exif_readonly', $widget_settings_for_tags);


  }

}

?>
