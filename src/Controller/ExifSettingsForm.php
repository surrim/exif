<?php
/**
 * Created by IntelliJ IDEA.
 * User: jphautin
 * Date: 10/12/15
 * Time: 17:22
 */

namespace Drupal\exif\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;


class ExifSettingsForm extends ConfigFormBase implements ContainerInjectionInterface {
  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the ExifSettingsForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }


  public function getFormId() {
    return 'exif_settings';
  }

  protected function getEditableConfigNames() {
    return [
      'exif.settings',
    ];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('exif.settings');

    $form['granularity'] = array(
      '#type' => 'select',
      '#title' => t('Granularity'),
      '#options' => array(0 => t('Default'), 1 => ('Day')),
      '#default_value' => $config->get('granularity', 0),
      '#description' => t('If a timestamp is selected (for example the date the picture was taken), you can specify here how granular the timestamp should be. If you select default it will just take whatever is available in the picture. If you select Day, the Date saved will look something like 13-12-2008. This can be useful if you want to use some kind of grouping on the data.'),
    );

    $all_nodetypes = $this->entityTypeManager->getStorage('node_type')
      ->loadMultiple();
    $all_nt = array();
    //$all_nt[0] = 'None';
    foreach ($all_nodetypes as $item) {
      //$all_nt[$item->type] = $item->name;
      $all_nt[$item->id()] = $item->label();
    }
    $form['nodetypes'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Nodetypes'),
      '#options' => $all_nt,
      '#default_value' => $config->get('nodetypes', array()),
      '#description' => t('Select nodetypes which should be checked for iptc & exif data. In case the nodetypes contains more than one image field, the module will use the first one. If you think no type is usable, take a look at <a href="/admin/config/media/exif/helper">the quick start page</a>.'),
    );
    if (Drupal::moduleHandler()->moduleExists("media")) {
      $all_mt = array();
      // Setup media types
      if (function_exists('file_type_get_enabled_types')) {
        $types = file_type_get_enabled_types();
        foreach ($types as $key) {
          $all_mt[$key->type] = $key->label;
        }
      }
      else {
        if (function_exists('media_type_get_types')) {
          $types = media_type_get_types();
          foreach ($types as $key) {
            $all_mt[$key->name] = $key->name;
          }
        }
      }

      $form['mediatypes'] = array(
        '#type' => 'checkboxes',
        '#title' => t('Mediatypes'),
        '#options' => $all_mt,
        '#default_value' => $config->get('mediatypes', array()),
        '#description' => t('Select mediatypes which should be checked for itpc & exif data.'),
      );
    }


    $form['update_metadata'] = array(
      '#type' => 'checkbox',
      '#title' => t('Refresh on node update'),
      '#default_value' => $config->get('update_metadata', TRUE),
      '#description' => t('If media/exif enable this option, Exif data is being updated when the node is being updated.'),
    );

    $form['write_empty_values'] = array(
      '#type' => 'checkbox',
      '#title' => t('Write empty image data?'),
      '#default_value' => $config->get('write_empty_values', TRUE),
      '#description' => t("If checked all values will be written. So for example if you want to read the creation date from EXIF, but it's not available, it will just write an empty string. If unchecked, empty strings will not be written. This might be the desired behavior, if you have a default value for the CCK field."),
    );

    $all_vocabularies = Vocabulary::loadMultiple();
    $all_vocs = array();
    $all_vocs[0] = 'None';
    foreach ($all_vocabularies as $item) {
      //$all_vocs[$item->vid] = $item->name;
      $all_vocs[$item->id()] = $item->label();
    }
    $form['vocabulary'] = array(
      '#type' => 'select',
      '#title' => t('Vocabulary'),
      '#options' => $all_vocs,
      '#default_value' => $config->get('vocabulary', array()),
      '#description' => t('Select vocabulary which should be used for iptc & exif data.If you think no vocabulary is usable for the purpose, take a look at <a href="/admin/config/media/exif/helper">the quick start page</a>'),
    );
    //TODO: add a button to create a vocabulary "photographies'metadata" (exif,iptc and xmp data contains in jpeg file)
    //TODO : add a button to create an Photography node type with default exif field (title,model,keywords) and default behavior but 'promoted to front'
    //TODO : Check if the media module is install to add automatically the image type active and add active default exif field (title,model,keywords).
    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /*
    if (strlen($form_state->getValue('phone_number')) < 3) {
        $form_state->setErrorByName('phone_number', $this->t('The phone number is too short. Please enter a full phone number.'));
    }*/
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('exif.settings')
      ->set('nodetypes', $form_state->getValue('nodetypes'))
      ->set('mediatypes', $form_state->getValue('mediatypes'))
      ->set('update_metadata', $form_state->getValue('update_metadata'))
      ->set('write_empty_values', $form_state->getValue('write_empty_values'))
      ->set('vocabulary', $form_state->getValue('vocabulary'))
      ->set('granularity', $form_state->getValue('granularity'))
      ->set('date_format_exif', $form_state->getValue('date_format_exif'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
