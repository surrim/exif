<?php

namespace Drupal\exif\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\exif\ExifFactory;
use Drupal\file_entity\Entity\FileType;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manage Settings forms.
 *
 * @package Drupal\exif\Controller
 */
class ExifSettingsForm extends ConfigFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the ExifSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entity_type_manager) {
    ConfigFormBase::__construct($configFactory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exif_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('exif.settings');

    $form['information1'] = [
      '#type' => 'item',
      '#title' => 'Informations',
      '#markup' => $this->t('If you have not create a media/content type for your photographies, take a look at <a href="/admin/config/media/exif/helper">the quick start page</a>.'),
    ];

    $form['information2'] = [
      '#type' => 'item',
      '#title' => '',
      '#markup' => $this->t('To have a sample of metadata content, take a look at <a href="/admin/config/media/exif/sample">the sample page</a>.'),
    ];

    $form['exif'] = [
      '#type' => 'vertical_tabs',
      '#prefix' => '<div class="exif">',
      '#suffix' => '</div>',
      '#title' => $this->t('Image Metadata Settings'),
      '#description' => $this->t('If you have not create a media/content type for your photographies, take a look at <a href="/admin/config/media/exif/helper">the quick start page</a>.'),
    ];

    $form['global'] = [
      '#type' => 'details',
      '#title' => $this->t('Global Settings'),
      '#group' => 'exif',
    ];

    $form['global']['granularity'] = [
      '#type' => 'select',
      '#title' => $this->t('Granularity'),
      '#options' => [0 => $this->t('Default'), 1 => ('Day')],
      '#default_value' => $config->get('granularity'),
      '#description' => $this->t('If a timestamp is selected (for example the date the picture was taken), you can specify here how granular the timestamp should be. If you select default it will just take whatever is available in the picture. If you select Day, the Date saved will look something like 13-12-2008. This can be useful if you want to use some kind of grouping on the data.'),
    ];

    $form['fieldname'] = [
      '#type' => 'markup',
      '#value' => "My Value Goes Here",
    ];

    $form['node'] = [
      '#type' => 'details',
      '#title' => $this->t('Content types'),
      '#group' => 'exif',
    ];
    $all_nodetypes = $this->entityTypeManager->getStorage('node_type')
      ->loadMultiple();
    $all_nt = [];
    foreach ($all_nodetypes as $item) {
      $all_nt[$item->id()] = $item->label();
    }
    $form['node']['nodetypes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Nodetypes'),
      '#options' => $all_nt,
      '#default_value' => $config->get('nodetypes'),
      '#description' => $this->t('Select nodetypes which should be checked for iptc & exif data.'),
    ];

    // The old way (still in use so keep it).
    if (\Drupal::moduleHandler()->moduleExists("file_entity")) {
      $form['file'] = [
        '#type' => 'details',
        '#title' => $this->t('File types'),
        '#group' => 'exif',
      ];

      $all_mt = [];
      $all_filetypes = FileType::loadEnabled();
      // Setup file types.
      foreach ($all_filetypes as $item) {
        $all_mt[$item->id()] = $item->label();
      }
      $form['file']['filetypes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Filetypes'),
        '#options' => $all_mt,
        '#default_value' => $config->get('filetypes'),
        '#description' => $this->t('Select filetypes which should be checked for itpc & exif data.'),
      ];
    }
    else {
      $form['file']['filetypes'] = [
        '#type' => 'hidden',
        '#value' => [],
      ];
    }

    if (interface_exists('\Drupal\media\MediaInterface')) {
      $form['media'] = [
        '#type' => 'details',
        '#title' => $this->t('Media types'),
        '#group' => 'exif',
      ];

      $all_mediatypes = $this->entityTypeManager->getStorage('media_type')
        ->loadMultiple();
      $all_mt = [];
      foreach ($all_mediatypes as $item) {
        $all_mt[$item->id()] = $item->label();
      }
      $form['media']['mediatypes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Mediatypes'),
        '#options' => $all_mt,
        '#default_value' => $config->get('mediatypes'),
        '#description' => $this->t('Select mediatypes which should be checked for iptc & exif data.'),
      ];
    }
    else {
      $form['media']['mediatypes'] = [
        '#type' => 'hidden',
        '#default_value' => $config->get('mediatypes'),
      ];
    }

    $form['global']['update_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Refresh on node update'),
      '#default_value' => $config->get('update_metadata'),
      '#description' => $this->t('If media/exif enable this option, Exif data is being updated when the node is being updated.'),
    ];

    $form['global']['extraction_solution'] = [
      '#type' => 'select',
      '#title' => $this->t('which extraction solution to use on node update'),
      '#options' => ExifFactory::getExtractionSolutions(),
      '#default_value' => $config->get('extraction_solution'),
      '#description' => $this->t('If media/exif enable this option, Exif data is being updated when the node is being updated.'),
    ];

    $form['global']['exiftool_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('location of exiftool binary'),
      '#default_value' => $config->get('exiftool_location'),
      '#description' => $this->t('where is the exiftool binaries (only needed if extraction solution chosen is exiftool)'),
    ];

    $form['global']['write_empty_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Write empty image data?'),
      '#default_value' => $config->get('write_empty_values'),
      '#description' => $this->t("If checked all values will be written. So for example if you want to read the creation date from EXIF, but it's not available, it will just write an empty string. If unchecked, empty strings will not be written. This might be the desired behavior, if you have a default value for the CCK field."),
    ];

    $all_vocabularies = Vocabulary::loadMultiple();
    $all_vocs = [];
    $all_vocs[0] = 'None';
    foreach ($all_vocabularies as $item) {
      $all_vocs[$item->id()] = $item->label();
    }
    $form['global']['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Vocabulary'),
      '#options' => $all_vocs,
      '#default_value' => $config->get('vocabulary'),
      '#description' => $this->t('Select vocabulary which should be used for iptc & exif data. If you think no vocabulary is usable for the purpose, take a look at <a href="/admin/config/media/exif/helper">the quick start page</a>.'),
    ];
    // @todo Check if the media module is install to add automatically
    // @todo the image type active and add active default exif field.
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('extraction_solution') == 'simple_exiftool' && !is_executable($form_state->getValue('exiftool_location'))) {
      $form_state->setErrorByName('exiftool_location', $this->t('The location provided for exiftool is not correct. Please ensure the exiftool location exists and is executable.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    foreach (['nodetypes', 'filetypes', 'mediatypes'] as $entity_type) {
      $value = $form_state->getValue($entity_type);
      if (empty($value)) {
        $value = [];
      }
      $this->config('exif.settings')->set($entity_type, $value);
    }

    $this->config('exif.settings')
      ->set('update_metadata', $form_state->getValue('update_metadata', TRUE))
      ->set('write_empty_values', $form_state->getValue('write_empty_values', FALSE))
      ->set('vocabulary', $form_state->getValue('vocabulary', "0"))
      ->set('granularity', $form_state->getValue('granularity'))
      ->set('date_format_exif', $form_state->getValue('date_format_exif'))
      ->set('extraction_solution', $form_state->getValue('extraction_solution'))
      ->set('exiftool_location', $form_state->getValue('exiftool_location'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exif.settings',
    ];
  }

}
