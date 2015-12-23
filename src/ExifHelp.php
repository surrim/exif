<?php
/**
 * @file
plain
 * kk * Contains \Drupal\exif\ExifHelp
 */

namespace Drupal\exif;

use Drupal\exif\Exif;
use Drupal\Core\Url;
use Drupal\Component\Utility\SafeMarkup;

class ExifHelp
{

    /**
     * Just some help page. Gives you an overview over the available tags
     * @return string html
     */
    function content()
    {
        $filepath = drupal_get_path('module', 'exif') . '/sample.jpg';
        //$url = \Drupal::url($filepath);

        $output = '';
        $output .= '<h3>' . t('About') . '</h3>';
        $output .= '<p>' . t('The Exif module allows you :');
        $output .= '<ul><li>' . t('extract metadata from an image') . '</li>';
        $output .= '<li>' . t('to classify your images by settings terms in taxonamy vocabulary') . '</li></ul>';
        $output .= t('To classify images, you define <em>vocabularies</em> that contain related <em>terms</em>, and then assign the vocabularies to content types. For more information, see the online handbook entry for the <a href="@taxonomy">Taxonomy module</a>.', array('@taxonomy' => 'http://drupal.org/handbook/modules/taxonomy/'));
        $output .= '</p>';
        $output .= '<h3>' . t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . t('Creating vocabularies') . '</dt>';
        $output .= '<dd>' . t('Users with sufficient <a href="@perm">permissions</a> can create <em>vocabularies</em> through the <a href="@taxo">Taxonomy page</a>. The page listing the terms provides a drag-and-drop interface for controlling the order of the terms and sub-terms within a vocabulary, in a hierarchical fashion.', array('@taxo' => \Drupal::url('entity.taxonomy_vocabulary.collection'), '@perm' => \Drupal::url('user.admin_permissions')));
        $output .= t('This module will automatically create in the chosen vocabulary (by default "Photographies\' metadata"), the following structure:');
        $output .= '<ul><li>' . t('<em>vocabulary</em>: Photographies\'metadata') . '</li>';
        $output .= '<ul><li>' . t('<em>term</em>: iptc') . '</li>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: keywords') . '</li>';
        $output .= '<ul><li>' . t('<em>ursub-term</em>: Paris') . '</li>';
        $output .= '<li>' . t('<em>sub-term</em>: Friends') . '</li>';
        $output .= '</ul></ul>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: caption') . '</li>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: Le louvre') . '</li>';
        $output .= '</ul></ul></ul>';
        $output .= '<ul><li>' . t('<em>term</em>: exif') . '</li>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: model') . '</li>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: KINON DE800') . '</li>';
        $output .= '</ul></ul>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: isospeedratings') . '</li>';
        $output .= '<ul><li>' . t('<em>sub-term</em>: 200') . '</li>';
        $output .= '</ul></ul></ul></ul>';
        $output .= '<dd>' . t('To get metadata information of an image, you have to choose on which node type the extraction should be made.');
        $output .= t('You also have to create fields with specific names using the new Field UI.');
        $output .= t('The type of the field can be :');
        $output .= '<ul><li>' . t('<em>text field</em>: extract information and put it in the text field.') . '</li>';
        $output .= '<li>' . t('<em>term reference field</em>: extract information, create terms and sub-terms if needed and put it in the field.') . '</li>';
        $output .= '</ul>';
        $output .= t('Please, if you want to use term reference field, ensure :');
        $output .= '<ul><li>' . t('you choose the autocompletion widget and') . '</li>';
        $output .= '<li>' . t('the "Images" Vocabulary exist.') . '</li>';
        $output .= '</ul>';
        $output .= t('TIPS : Note for iptc and exif fields that have several values (like field iptc "keywords" as an example), ');
        $output .= t('if you want to get all the values, do not forget to configure the field to use unlimited number of values (by default, set to 1).');
        $output .= '</dd>';
        $output .= '</dl>';
        $output .= '<div class="sample-image">';
        $output .= '<h3 class="sample-image">';
        $output .= t('Example of field name and the metadata extracted');
        $output .= '</h3>';
        $output .= '<img class="sample-image" src="/' . $filepath . '"/>';
        $output .= '</div>';
        $rows = array();
        $help = '';
        //TODO drupal_add_css(drupal_get_path('module', 'exif') . '/exif.admin.css');
        $exif = Exif::getInstance();
        $fullmetadata = $exif->readMetadataTags($filepath);
        if (is_array($fullmetadata)) {
            foreach ($fullmetadata as $section => $section_data) {
                $rows[] = array('data' => array($section, $help), 'class' => array('tag_type'));
                foreach ($section_data as $key => $value) {
                    if ($value != NULL && $value != '' && !$exif->startswith($key, 'undefinedtag')) {
                        $resultTag = "";
                        if (is_array($value)) {
                            foreach ($value as $innerkey => $innervalue) {
                                if (($innerkey + 1) != count($value)) {
                                    $resultTag .= $innervalue . "; ";
                                } else {
                                    $resultTag .= $innervalue;
                                }
                            }
                        } else {
                            $resultTag = SafeMarkup::checkPlain($value);
                        }
                        $rows[] = array('data' => array("field_" . $section . "_" . $key, $resultTag), 'class' => array('tag'));
                    }
                }
            }
        }
        $header = array(t('Key'), t('Value'));
        $output .= '<p>';
        $variables = array("header" => $header, "rows" => $rows, "attributes" => array(), "caption" => "", "sticky" => array(), "colgroups" => array(), "empty" => array());
        $output .= "";
        $output .= '</p>';
        return $output;
    }


    /*
     *     $content = array();

    $content['message'] = array(
      '#markup' => $this->t('A more complex list of entries in the database.') . ' ' .
      $this->t('Only the entries with name = "John" and age older than 18 years are shown, the username of the person who created the entry is also shown.'),
    );

    $headers = array(
      t('Id'),
      t('Created by'),
      t('Name'),
      t('Surname'),
      t('Age'),
    );

    $rows = array();
    foreach ($entries = DBTNGExampleStorage::advancedLoad() as $entry) {
      // Sanitize each entry.
      $rows[] = array_map('Drupal\Component\Utility\SafeMarkup::checkPlain', $entry);
    }
    $content['table'] = array(
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#attributes' => array('id' => 'dbtng-example-advanced-list'),
      '#empty' => t('No entries available.'),
    );
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;
    return $content;
  }
     */
}