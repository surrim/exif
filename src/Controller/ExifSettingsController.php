<?php
/**
 * @file
 * Contains \Drupal\exif\Controller\ExifController
 */

namespace Drupal\exif\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ExifSettingsController extends ControllerBase
{



  /**
   * button to create a vocabulary "photographies'metadata" (exif,iptc and xmp data contains in jpeg file)
   * @return Response
   */
  public function showGuide()
  {
    return [
      '#message' => "",
      '#theme' => 'exif_helper_page'
    ];
  }

    /**
     * button to create a vocabulary "photographies'metadata" (exif,iptc and xmp data contains in jpeg file)
     * @return Response
     */
    public function createPhotographyVocabulary()
    {
      //TODO
      return [
        '#message' => $this->t('The new vocabulary photography has been fully created'),
        '#theme' => 'exif_helper_page'
      ];
    }

  protected function configureEntityFormDisplay($field_name, $widget_id = NULL) {
    // Make sure the field is displayed in the 'default' form mode (using
    // default widget and settings). It stays hidden for other form modes
    // until it is explicitly configured.
    $options = $widget_id ? ['type' => $widget_id] : [];
    entity_get_form_display($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($field_name, $options)
      ->save();
  }

  protected function configureEntityViewDisplay($field_name, $formatter_id = NULL) {
    // Make sure the field is displayed in the 'default' view mode (using
    // default formatter and settings). It stays hidden for other view
    // modes until it is explicitly configured.
    $options = $formatter_id ? ['type' => $formatter_id] : [];
    entity_get_display($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($field_name, $options)
      ->save();
  }

  protected function addExistingFieldToContentType(NodeInterface $contentType,$fieldLabel,$fieldName,$fieldWidget,$fieldSettings = array()) {
    $field = $this->entityTypeManager()->getStorage('field_config')->create(array(
      'field_name' => $fieldName,
      'entity_type' => 'node',
      'bundle' => $contentType->bundle(),
      'label' => $fieldLabel,
    ));
    $field->save();

    $this->configureEntityFormDisplay($fieldName);
    $this->configureEntityViewDisplay($fieldName);
  }

  protected function addNewFieldToContentType(NodeInterface $contenttype,$fieldLabel,$fieldName,$fieldType,$fieldWidget,$fieldSettings = array(),$settings = array()) {
    /*
    $edit = array();
    $edit["fields[_add_new_field][label]"]=$fieldLabel;
    $edit["fields[_add_new_field][field_name]"]=$fieldName;
    $edit["fields[_add_new_field][type]"]=$fieldType;

    $edit["fields[_add_new_field][widget_type]"]=$fieldWidget;
    $this->drupalPost("admin/structure/types/manage/{$contenttype->type}/fields",$edit,t("Save"));
    $this->drupalPost(NULL,$fieldSettings,"Save field settings");
    $this->drupalPost(NULL,$settings,"Save settings");
    */

    $field_storage_values = [
      'field_name' => $fieldName,
      'entity_type' => $contenttype->getType(),
      'type' => $fieldType,
      'translatable' => FALSE,
    ];
    $field_values = [
      'field_name' => $fieldName,
      'entity_type' => $contenttype->getType(),
      'bundle' => $contenttype->bundle(),
      'label' => $fieldLabel,
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
    ];

    $this->entityTypeManager()->getStorage('field_storage_config')->create($field_storage_values)->save();
    $field = $this->entityTypeManager()->getStorage('field_config')->create($field_values)->save();


  }

    /**
     * Button to create an Photography node type with default exif field (title,model,keywords) and default behavior but 'promoted to front'
     * @return Response
     */
    public function createPhotographyNodeType() {
      $nodeTypeName='Photography';
      $entity_type='node';
      //first create photography node type
      /*
      $node = Node::create(array(
        'type' => $entity_type,
        'bundle' => $nodeTypeName,
        'title' => 'photography',
        'langcode' => 'en',
        'status' => 1,
        'field_fields' => array(),
      ));
      $node->save();
      */
      node_type_save()

      //add default display
      $values = array(
        'targetEntityType' => $entity_type,
        'bundle' => $nodeTypeName,
        'mode' => 'default',
        'status' => TRUE
      );
      $this->entityTypeManager()->getStorage('entity_view_display')->create($values);

      //add default form display
      $values = array(
        'targetEntityType' => $entity_type,
        'bundle' => $nodeTypeName,
        'mode' => 'default',
        'status' => TRUE,
      );
      $this->entityTypeManager()->getStorage('entity_form_display')->create($values);

      //$this->configureEntityFormDisplay($fieldName);
      //$this->configureEntityViewDisplay($fieldName);

      //then add fields
      //add photo field of type image_field
      //$this->addNewFieldToContentType($node,'photo','image','image','exif_readonly');
      //$this->addNewFieldToContentType($node,'model','exif_model','taxonomy_term_reference','exif_readonly');

      //$this->createTypeField($node,'keywords');
      //$this->createTypeField($node,'model');
      //$this->createField('Photography','keywords','text');
      //TODO
      return [
        '#message' => $this->t('The node type photography has been fully created'),
        '#theme' => 'exif_helper_page'
      ];
    }



  /**
   *  var $field = array(
  'name' => 'myfield',
  'entity_type' => 'myentitytype',
  'type' => 'text_long',
  'module' => 'text',
  'settings' => array(),
  'cardinality' => 1,
  'locked' => FALSE,
  'indexes' => array(),
  );
  //entity_create('field_entity', $field)->save();
  $this->entityTypeManager()->getStorage('field_entity')->create($field)->save();

  var $instance = array(
  'field_name' => 'myfield',
  'entity_type' => 'myentitytype',
  'bundle' => 'myentitytype',
  'label' => 'Field title',
  'description' => t('Field description.'),
  'required' => TRUE,
  'default_value' => array(),
  'settings' => array(
  'text_processing' => '0'
  ),
  );
  //entity_create('field_instance', $instance)->save();
  $this->entityTypeManager()->getStorage('field_instance')->create($instance)->save();

  var $values = array(
  'targetEntityType' => 'myentitytype',
  'bundle' => 'myentitytype',
  'mode' => 'default',
  'status' => TRUE,
  );

  $this->entityTypeManager()->getStorage('entity_form_display')->create($values)->save();

  var entity_form_display = getStorage('entity_form_display')->load($entity_type . '.' . $bundle . '.' . $form_mode);

  entity_form_display->setComponent('myfield', array(
  'type' => 'text_long',
  'settings' => array(
  'text_processing' => '0'
  ),
  'weight' => 5,
  ))->save();
   */


    /**
     * Check if the media module is install to add automatically the image type active and add active default exif field (title,model,keywords).
     */
    public function setupImageMediaType() {
      return array(
        '#markup' => '<p>' . $this->t('The new node type photography has been fully created') . '</p>',
      );
    }


    public function showSample() {
        $twig = $this->container->get('twig');
        $twigFilePath = drupal_get_path('module', 'exif') . '/templates/exif_sample.html.twig';
        $sampleImageFilePath = drupal_get_path('module', 'exif') . '/sample.jpg';
        $template = $this->twig->loadTemplate($twigFilePath);

        $markup = $template->render(
            array(
                'imagePath' => $sampleImageFilePath,
                'taxonomyLink' => url('admin/structure/taxonomy'),
                'permissionLink' => url('admin/config/people/permissions'),
                'taxonomyFragment' => module - taxonomy
            ));
        return new Response($markup);
    }

}
?>
