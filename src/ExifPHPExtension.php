<?php

namespace Drupal\exif;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class ExifPHPExtension Parser implementation base d on PHP Exif extension.
 *
 * @package Drupal\exif
 */
class ExifPHPExtension implements ExifInterface {

  static private $instance = NULL;

  use StringTranslationTrait;

  /**
   * ExifPHPExtension constructor.
   */
  private function __construct() {}

  /**
   * Return the singleton.
   *
   * @return \Drupal\exif\ExifPHPExtension
   *   the singleton implementing the parser
   */
  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Check if the exif php extension is installed.
   *
   * @return bool
   *   true if extension is installed.
   */
  public static function checkConfiguration() {
    return function_exists('exif_read_data') && function_exists('iptcparse');
  }

  /**
   * Return drupal fields related to this extension.
   *
   * @inheritdoc
   */
  public function getMetadataFields(array $arCckFields = []) {
    $arSections = self::getMetadataSections();
    foreach ($arCckFields as $drupal_field => $metadata_settings) {
      $metadata_field = $metadata_settings['metadata_field'];
      $ar = explode("_", $metadata_field);
      if (isset($ar[0]) && (in_array($ar[0], $arSections) || $ar[0] == 'all')) {
        $section = $ar[0];
        unset($ar[0]);
        $arCckFields[$drupal_field]['metadata_field'] = [
          'section' => $section,
          'tag' => implode("_", $ar),
        ];
      }
      else {
        // Remove from the list a non usable description.
        unset($arCckFields[$drupal_field]);
        \Drupal::logger('exif')
          ->warning($this->t("not able to understand exif field settings @metadata", ['@metadata' => $metadata_field]));
      }
    }
    return $arCckFields;
  }

  /**
   * Return known metadata sections.
   *
   * @return array
   *   list of sections (as string)
   */
  public static function getMetadataSections() {
    $sections = [
      'exif',
      'file',
      'computed',
      'ifd0',
      'gps',
      'winxp',
      'iptc',
      'xmp',
    ];
    return $sections;
  }

  /**
   * Encode read value as UTF8 string.
   *
   * @param string $value
   *   Bytes to encode.
   *
   * @return string
   *   encoded value
   */
  protected function reEncodeToUtf8($value) {
    $unicode_list = unpack("v*", $value);
    $result = "";
    foreach ($unicode_list as $key => $value) {
      if ($value != 0) {
        $one_character = pack("C", $value);
        $temp = mb_convert_encoding('&#' . $value . ';', 'UTF-8', 'HTML-ENTITIES');
        $result .= $temp;
      }
    }
    return $result;
  }

  /**
   * Read all metadata tags.
   *
   * @param string $file
   *   Path to file.
   * @param bool $enable_sections
   *   Indicate if the information is retrieve by sections or flatten.
   *
   * @return array
   *   Values by key and optionally sections.
   */
  public function readMetadataTags($file, $enable_sections = TRUE) {
    if (!file_exists($file)) {
      return [];
    }
    $data1 = $this->readExifTags($file, $enable_sections);
    $data2 = $this->readIptcTags($file, $enable_sections);
    $data = array_merge($data1, $data2);

    if (is_array($data)) {
      foreach ($data as $section => $section_data) {
        $section_data = $this->reformat($section_data);
        $data[$section] = $section_data;
      }
    }
    return $data;
  }

  /**
   * Read Exif Information from a file.
   *
   * @param string $file
   *   Path to file.
   * @param bool $enable_sections
   *   Indicate if the information is retrieve by sections or flatten.
   *
   * @return array
   *   Values by key and optionally sections.
   */
  public function readExifTags($file, $enable_sections = TRUE) {
    $ar_supported_types = ['jpg', 'jpeg'];
    if (!in_array(strtolower($this->getFileType($file)), $ar_supported_types)) {
      return [];
    }
    $exif = [];
    try {
      $exif = @exif_read_data($file, 0, $enable_sections);
    }
    catch (\Exception $e) {
      // Logs a notice.
      \Drupal::logger('exif')
        ->warning($this->t("Error while reading EXIF tags from image."), $e);
    }
    $arSmallExif = [];
    foreach ((array) $exif as $key1 => $value1) {
      if (is_array($value1)) {
        $value = [];
        foreach ((array) $value1 as $key3 => $value3) {
          $value[strtolower($key3)] = $value3;
        }
      }
      else {
        $value = $value1;
      }
      $arSmallExif[strtolower($key1)] = $value;
    }
    return $arSmallExif;
  }

  /**
   * Get extension from a path.
   *
   * @param string $file
   *   Path to file.
   *
   * @return string
   *   the extension.
   */
  private function getFileType($file) {
    $ar = explode('.', $file);
    $ending = $ar[count($ar) - 1];
    return $ending;
  }

  const IPTC_KEYWORD_KEY = "2#025";

  /**
   * Read IPTC Information from a file.
   *
   * @param string $file
   *   Path to file.
   * @param bool $enable_sections
   *   Indicate if the information is retrieve by sections or flatten.
   *
   * @return array
   *   Values by key and optionally sections.
   */
  private function readIptcTags($file, $enable_sections) {
    $humanReadableKey = $this->getHumanReadableIptcDescriptions();
    $infoImage = [];
    getimagesize($file, $infoImage);
    $iptc = empty($infoImage["APP13"]) ? [] : iptcparse($infoImage["APP13"]);
    $arSmallIPTC = [];
    if (is_array($iptc)) {
      if (array_key_exists(ExifPHPExtension::IPTC_KEYWORD_KEY, $iptc)) {
        $iptc[ExifPHPExtension::IPTC_KEYWORD_KEY] = $this->checkKeywordString($iptc[ExifPHPExtension::IPTC_KEYWORD_KEY]);
        $iptc[ExifPHPExtension::IPTC_KEYWORD_KEY] = $this->removeEmptyIptcKeywords($iptc[ExifPHPExtension::IPTC_KEYWORD_KEY]);
      }
      foreach ($iptc as $key => $value) {
        if (count($value) == 1) {
          $resultTag = $value[0];
        }
        else {
          $resultTag = $value;
        }
        if (array_key_exists($key, $humanReadableKey)) {
          $humanKey = $humanReadableKey[$key];
          $arSmallIPTC[$humanKey] = $resultTag;
        }
        else {
          $arSmallIPTC[$key] = $resultTag;
        }
      }
    }
    if ($enable_sections) {
      return ['iptc' => $arSmallIPTC];
    }
    else {
      return $arSmallIPTC;
    }
  }

  /**
   * Just some little helper function to get the iptc fields.
   *
   * @return array
   *   Map of IPTC key with the associated description.
   */
  public function getHumanReadableIptcDescriptions() {
    return [
      "2#202" => "object_data_preview_data",
      "2#201" => "object_data_preview_file_format_version",
      "2#200" => "object_data_preview_file_format",
      "2#154" => "audio_outcue",
      "2#153" => "audio_duration",
      "2#152" => "audio_sampling_resolution",
      "2#151" => "audio_sampling_rate",
      "2#150" => "audio_type",
      "2#135" => "language_identifier",
      "2#131" => "image_orientation",
      "2#130" => "image_type",
      "2#125" => "rasterized_caption",
      "2#122" => "writer",
      "2#120" => "caption",
      "2#118" => "contact",
      "2#116" => "copyright_notice",
      "2#115" => "source",
      "2#110" => "credit",
      "2#105" => "headline",
      "2#103" => "original_transmission_reference",
      "2#101" => "country_name",
      "2#100" => "country_code",
      "2#095" => "state",
      "2#092" => "sublocation",
      "2#090" => "city",
      "2#085" => "by_line_title",
      "2#080" => "by_line",
      "2#075" => "object_cycle",
      "2#070" => "program_version",
      "2#065" => "originating_program",
      "2#063" => "digital_creation_time",
      "2#062" => "digital_creation_date",
      "2#060" => "creation_time",
      "2#055" => "creation_date",
      "2#050" => "reference_number",
      "2#047" => "reference_date",
      "2#045" => "reference_service",
      "2#042" => "action_advised",
      "2#040" => "special_instruction",
      "2#038" => "expiration_time",
      "2#037" => "expiration_date",
      "2#035" => "release_time",
      "2#030" => "release_date",
      "2#027" => "content_location_name",
      "2#026" => "content_location_code",
      "2#025" => "keywords",
      "2#022" => "fixture_identifier",
      "2#020" => "supplemental_category",
      "2#015" => "category",
      "2#010" => "subject_reference",
      "2#008" => "editorial_update",
      "2#007" => "edit_status",
      "2#005" => "object_name",
      "2#004" => "object_attribute_reference",
      "2#003" => "object_type_reference",
      "2#000" => "record_version",
      "1#090" => "envelope_character_set",
    ];
  }

  /**
   * Helper function to split keyword separated by ';' in array of keywords.
   *
   * @param string $keyword
   *   String with all keywords.
   *
   * @return array
   *   the array of keywords.
   */
  private function checkKeywordString($keyword) {
    return (strpos($keyword[0], ';') !== FALSE) ? explode(';', $keyword[0]) : $keyword;
  }

  /**
   * Helper function to remove empty IPTC keywords.
   *
   * @param array $data
   *   List of keywords.
   *
   * @return array
   *   List of keywords.
   */
  private function removeEmptyIptcKeywords(array $data) {
    if (!in_array('', $data, TRUE)) {
      return $data;
    }
    foreach ($data as $key => $value) {
      if (empty($value)) {
        unset($data[$key]);
      }
    }
    return $data;
  }

  /**
   * Helper function to reformat fields where required.
   *
   * Some values (lat/lon) break down into structures, not strings.
   * Dates should be parsed nicely.
   *
   * @param array $data
   *   Section data containing all keyword woth associated value.
   *
   * @return array
   *   section data containing all keyword woth associated value.
   */
  private function reformat(array $data) {
    // Make the key lowercase as field names must be.
    $data = array_change_key_case($data, CASE_LOWER);
    foreach ($data as $key => &$value) {
      if (is_array($value)) {
        $value = array_change_key_case($value, CASE_LOWER);
        switch ($key) {
          // GPS values.
          case 'gps_latitude':
          case 'gps_longitude':
          case 'gpslatitude':
          case 'gpslongitude':
            $value = $this->exifReformatGps($value, $data[$key . 'ref']);
            break;
        }
      }
      else {
        if (is_string($value)) {
          $value = trim($value);
        }
        if (!Unicode::validateUtf8($value)) {
          $value = utf8_encode($value);
        }
        switch ($key) {
          // String values.
          case 'usercomment':
          case 'title':
          case 'comment':
          case 'author':
          case 'subject':
            if ($this->startswith($value, 'UNICODE')) {
              $value = substr($value, 8);
            }
            $value = $this->reEncodeToUtf8($value);
            break;

          // Date values.
          case 'filedatetime':
            $value = date('c', $value);
            break;

          case 'datetimeoriginal':
          case 'datetime':
          case 'datetimedigitized':
            // In case we get a datefield,
            // we need to reformat it to the ISO 8601 standard,
            // which will look something like 2004-02-12T15:19:21.
            $date_time = explode(" ", $value);
            $date_time[0] = str_replace(":", "-", $date_time[0]);
            // @todo Refactor or remove below code.
            // if (variable_get('exif_granularity', 0) == 1) {
            // $date_time[1] = "00:00:00";.
            // }.
            $value = implode("T", $date_time);
            break;

          // GPS values.
          case 'gpsaltitude':
          case 'gpsimgdirection':
            if (!isset($data[$key . 'ref'])) {
              $data[$key . 'ref'] = 0;
            }
            $value = $this->exifReformatGps($value, $data[$key . 'ref']);
            break;

          // Flash values.
          case 'componentsconfiguration':
          case 'compression':
          case 'contrast':
          case 'exposuremode':
          case 'exposureprogram':
          case 'flash':
          case 'focalplaneresolutionunit':
          case 'gaincontrol':
          case 'lightsource':
          case 'meteringmode':
          case 'orientation':
          case 'resolutionunit':
          case 'saturation':
          case 'scenecapturetype':
          case 'sensingmethod':
          case 'sensitivitytype':
          case 'sharpness':
          case 'subjectdistancerange':
          case 'whitebalance':
            $human_descriptions = $this->getHumanReadableDescriptions()[$key];
            if (isset($human_descriptions[$value])) {
              $value = $human_descriptions[$value];
            }
            break;

          // Exposure values.
          case 'exposuretime':
            if (strpos($value, '/') !== FALSE) {
              $value = $this->normaliseFraction($value) . 's';
            }
            break;

          // Focal Length values.
          case 'focallength':
            if (strpos($value, '/') !== FALSE) {
              $value = $this->normaliseFraction($value) . 'mm';
            }
            break;
        }
      }
    }
    return $data;
  }

  /**
   * Helper function to change GPS co-ords into decimals.
   *
   * @param mixed $value
   *   Raw value as array or string.
   * @param string $ref
   *   Direction as a char (S/N/E/W)
   *
   * @return float
   *   Calculated decimal value
   */
  private function exifReformatGps($value, $ref) {
    if (!is_array($value)) {
      $value = [$value];
    }
    $dec = 0;
    $granularity = 0;
    foreach ($value as $element) {
      $parts = explode('/', $element);
      $dec += (float) (((float) $parts[0] / (float) $parts[1]) / pow(60, $granularity));
      $granularity++;
    }
    if ($ref == 'S' || $ref == 'W') {
      $dec *= -1;
    }
    return $dec;
  }

  /**
   * Helper function to know if a substring start a string.
   *
   * Used internally and in help page (so should be public).
   *
   * @param string $hay
   *   The string where we look for.
   * @param string $needle
   *   The string to look for.
   *
   * @return bool
   *   if condition is valid.
   */
  public function startswith($hay, $needle) {
    return substr($hay, 0, strlen($needle)) === $needle;
  }

  /**
   * Convert machine tag values to their human-readable descriptions.
   *
   * Sources:
   *    http://www.sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html.
   */
  public function getHumanReadableDescriptions() {
    $machineToHuman = [];
    $machineToHuman['componentsconfiguration'] = [
      '0' => $this->t('-'),
      '1' => $this->t('Y'),
      '2' => $this->t('Cb'),
      '3' => $this->t('Cr'),
      '4' => $this->t('R'),
      '5' => $this->t('G'),
      '6' => $this->t('B'),
    ];
    $machineToHuman['compression'] = [
      '1' => $this->t('Uncompressed'),
      '2' => $this->t('CCITT 1D'),
      '3' => $this->t('T4/Group 3 Fax'),
      '4' => $this->t('T6/Group 4 Fax'),
      '5' => $this->t('LZW'),
      '6' => $this->t('JPEG (old-style)'),
      '7' => $this->t('JPEG'),
      '8' => $this->t('Adobe Deflate'),
      '9' => $this->t('JBIG B&W'),
      '10' => $this->t('JBIG Color'),
      '99' => $this->t('JPEG'),
      '262' => $this->t('Kodak 262'),
      '32766' => $this->t('Next'),
      '32767' => $this->t('Sony ARW Compressed'),
      '32769' => $this->t('Packed RAW'),
      '32770' => $this->t('Samsung SRW Compressed'),
      '32771' => $this->t('CCIRLEW'),
      '32773' => $this->t('PackBits'),
      '32809' => $this->t('Thunderscan'),
      '32867' => $this->t('Kodak KDC Compressed'),
      '32895' => $this->t('IT8CTPAD'),
      '32896' => $this->t('IT8LW'),
      '32897' => $this->t('IT8MP'),
      '32898' => $this->t('IT8BL'),
      '32908' => $this->t('PixarFilm'),
      '32909' => $this->t('PixarLog'),
      '32946' => $this->t('Deflate'),
      '32947' => $this->t('DCS'),
      '34661' => $this->t('JBIG'),
      '34676' => $this->t('SGILog'),
      '34677' => $this->t('SGILog24'),
      '34712' => $this->t('JPEG 2000'),
      '34713' => $this->t('Nikon NEF Compressed'),
      '34715' => $this->t('JBIG2 TIFF FX'),
      '34718' => $this->t('Microsoft Document Imaging (MDI) Binary Level Codec'),
      '34719' => $this->t('Microsoft Document Imaging (MDI) Progressive Transform Codec'),
      '34720' => $this->t('Microsoft Document Imaging (MDI) Vector'),
      '65000' => $this->t('Kodak DCR Compressed'),
      '65535' => $this->t('Pentax PEF Compressed'),
    ];
    $machineToHuman['contrast'] = [
      '0' => $this->t('Normal'),
      '1' => $this->t('Low'),
      '2' => $this->t('High'),
    ];
    $machineToHuman['exposuremode'] = [
      '0' => $this->t('Auto'),
      '1' => $this->t('Manual'),
      '2' => $this->t('Auto bracket'),
    ];
    // (the value of 9 is not standard EXIF, but is used by the Canon EOS 7D)
    $machineToHuman['exposureprogram'] = [
      '0' => $this->t('Not Defined'),
      '1' => $this->t('Manual'),
      '2' => $this->t('Program AE'),
      '3' => $this->t('Aperture-priority AE'),
      '4' => $this->t('Shutter speed priority AE'),
      '5' => $this->t('Creative (Slow speed)'),
      '6' => $this->t('Action (High speed)'),
      '7' => $this->t('Portrait'),
      '8' => $this->t('Landscape'),
      '9' => $this->t('Bulb'),
    ];
    $machineToHuman['flash'] = [
      '0' => $this->t('Flash did not fire'),
      '1' => $this->t('Flash fired'),
      '5' => $this->t('Strobe return light not detected'),
      '7' => $this->t('Strobe return light detected'),
      '9' => $this->t('Flash fired, compulsory flash mode'),
      '13' => $this->t('Flash fired, compulsory flash mode, return light not detected'),
      '15' => $this->t('Flash fired, compulsory flash mode, return light detected'),
      '16' => $this->t('Flash did not fire, compulsory flash mode'),
      '24' => $this->t('Flash did not fire, auto mode'),
      '25' => $this->t('Flash fired, auto mode'),
      '29' => $this->t('Flash fired, auto mode, return light not detected'),
      '31' => $this->t('Flash fired, auto mode, return light detected'),
      '32' => $this->t('No flash function'),
      '65' => $this->t('Flash fired, red-eye reduction mode'),
      '69' => $this->t('Flash fired, red-eye reduction mode, return light not detected'),
      '71' => $this->t('Flash fired, red-eye reduction mode, return light detected'),
      '73' => $this->t('Flash fired, compulsory flash mode, red-eye reduction mode'),
      '77' => $this->t('Flash fired, compulsory flash mode, red-eye reduction mode, return light not detected'),
      '79' => $this->t('Flash fired, compulsory flash mode, red-eye reduction mode, return light detected'),
      '89' => $this->t('Flash fired, auto mode, red-eye reduction mode'),
      '93' => $this->t('Flash fired, auto mode, return light not detected, red-eye reduction mode'),
      '95' => $this->t('Flash fired, auto mode, return light detected, red-eye reduction mode'),
    ];
    // (values 1, 4 and 5 are not standard EXIF)
    $machineToHuman['focalplaneresolutionunit'] = [
      '1' => $this->t('None'),
      '2' => $this->t('inches'),
      '3' => $this->t('cm'),
      '4' => $this->t('mm'),
      '5' => $this->t('um'),
    ];
    $machineToHuman['gaincontrol'] = [
      '0' => $this->t('None'),
      '1' => $this->t('Low gain up'),
      '2' => $this->t('High gain up'),
      '3' => $this->t('Low gain down'),
      '4' => $this->t('High gain down'),
    ];
    $machineToHuman['lightsource'] = [
      '0' => $this->t('Unknown'),
      '1' => $this->t('Daylight'),
      '2' => $this->t('Fluorescent'),
      '3' => $this->t('Tungsten (Incandescent)'),
      '4' => $this->t('Flash'),
      '9' => $this->t('Fine Weather'),
      '10' => $this->t('Cloudy'),
      '11' => $this->t('Shade'),
      '12' => $this->t('Daylight Fluorescent'),
      '13' => $this->t('Day White Fluorescent'),
      '14' => $this->t('Cool White Fluorescent'),
      '15' => $this->t('White Fluorescent'),
      '16' => $this->t('Warm White Fluorescent'),
      '17' => $this->t('Standard Light A'),
      '18' => $this->t('Standard Light B'),
      '19' => $this->t('Standard Light C'),
      '20' => $this->t('D55'),
      '21' => $this->t('D65'),
      '22' => $this->t('D75'),
      '23' => $this->t('D50'),
      '24' => $this->t('ISO Studio Tungsten'),
      '255' => $this->t('Other'),
    ];
    $machineToHuman['meteringmode'] = [
      '0' => $this->t('Unknown'),
      '1' => $this->t('Average'),
      '2' => $this->t('Center-weighted average'),
      '3' => $this->t('Spot'),
      '4' => $this->t('Multi-spot'),
      '5' => $this->t('Multi-segment'),
      '6' => $this->t('Partial'),
      '255' => $this->t('Other'),
    ];
    $machineToHuman['orientation'] = [
      '1' => $this->t('Horizontal (normal)'),
      '2' => $this->t('Mirror horizontal'),
      '3' => $this->t('Rotate 180'),
      '4' => $this->t('Mirror vertical'),
      '5' => $this->t('Mirror horizontal and rotate 270 CW'),
      '6' => $this->t('Rotate 90 CW'),
      '7' => $this->t('Mirror horizontal and rotate 90 CW'),
      '8' => $this->t('Rotate 270 CW'),
    ];
    // (the value 1 is not standard EXIF)
    $machineToHuman['resolutionunit'] = [
      '1' => $this->t('None'),
      '2' => $this->t('inches'),
      '3' => $this->t('cm'),
    ];
    $machineToHuman['saturation'] = [
      '0' => $this->t('Normal'),
      '1' => $this->t('Low'),
      '2' => $this->t('High'),
    ];
    $machineToHuman['scenecapturetype'] = [
      '0' => $this->t('Standard'),
      '1' => $this->t('Landscape'),
      '2' => $this->t('Portrait'),
      '3' => $this->t('Night'),
    ];
    // (values 1 and 6 are not standard EXIF)
    $machineToHuman['sensingmethod'] = [
      '1' => $this->t('Monochrome area'),
      '2' => $this->t('One-chip color area'),
      '3' => $this->t('Two-chip color area'),
      '4' => $this->t('Three-chip color area'),
      '5' => $this->t('Color sequential area'),
      '6' => $this->t('Monochrome linear'),
      '7' => $this->t('Trilinear'),
      '8' => $this->t('Color sequential linear'),
    ];
    // (applies to EXIF:ISO tag)
    $machineToHuman['sensitivitytype'] = [
      '0' => $this->t('Unknown'),
      '1' => $this->t('Standard Output Sensitivity'),
      '2' => $this->t('Recommended Exposure Index'),
      '3' => $this->t('ISO Speed'),
      '4' => $this->t('Standard Output Sensitivity and Recommended Exposure Index'),
      '5' => $this->t('Standard Output Sensitivity and ISO Speed'),
      '6' => $this->t('Recommended Exposure Index and ISO Speed'),
      '7' => $this->t('Standard Output Sensitivity, Recommended Exposure Index and ISO Speed'),
    ];
    $machineToHuman['sharpness'] = [
      '0' => $this->t('Normal'),
      '1' => $this->t('Soft'),
      '2' => $this->t('Hard'),
    ];
    $machineToHuman['subjectdistancerange'] = [
      '0' => $this->t('Unknown'),
      '1' => $this->t('Macro'),
      '2' => $this->t('Close'),
      '3' => $this->t('Distant'),
    ];
    $machineToHuman['uncompressed'] = [
      '0' => $this->t('No'),
      '1' => $this->t('Yes'),
    ];
    $machineToHuman['whitebalance'] = [
      '0' => $this->t('Auto'),
      '1' => $this->t('Manual'),
    ];
    return $machineToHuman;

  }

  /**
   * Normalise fractions.
   */
  private function normaliseFraction($fraction) {
    $parts = explode('/', $fraction);
    $top = $parts[0];
    $bottom = $parts[1];

    if ($top > $bottom) {
      // Value > 1.
      if (($top % $bottom) == 0) {
        $value = (float) $top / (float) $bottom;
      }
      else {
        $value = round(((float) $top / (float) $bottom), 2);
      }
    }
    else {
      if ($top == $bottom) {
        // Value = 1.
        $value = '1';
      }
      else {
        // Value < 1.
        if ($top == 1) {
          $value = '1/' . $bottom;
        }
        else {
          if ($top != 0) {
            $value = '1/' . round(((float) $bottom / (float) $top), 0);
          }
          else {
            $value = '0';
          }
        }
      }
    }
    return $value;
  }

  /**
   * List of known metadata keys.
   *
   * Metadata combine EXIF and IPTC keys.
   *
   * @return array
   *   Metadata Keys.
   */
  public function getFieldKeys() {
    $exif_keys_temp = $this->getHumanReadableExifKeys();
    $exif_keys = [];
    foreach ($exif_keys_temp as $value) {
      $exif_keys[$value] = $value;
    }
    $iptc_keys_temp = array_values($this->getHumanReadableIptcDescriptions());
    $iptc_keys = [];
    foreach ($iptc_keys_temp as $value) {
      $current_value = "iptc_" . $value;
      $iptc_keys[$current_value] = $current_value;
    }
    $fields = array_merge($exif_keys, $iptc_keys);
    ksort($fields);
    return $fields;
  }

  /**
   * List of known EXIF keys.
   *
   * @return array
   *   Exif keys
   */
  public function getHumanReadableExifKeys() {
    return [
      "file_filename",
      "file_filedatetime",
      "file_filesize",
      "file_filetype",
      "file_mimetype",
      "file_sectionsfound",
      "computed_filename",
      "computed_filedatetime",
      "computed_filesize",
      "computed_filetype",
      "computed_mimetype",
      "computed_sectionsfound",
      "computed_html",
      "computed_height",
      "computed_width",
      "computed_iscolor",
      "computed_copyright",
      "computed_byteordermotorola",
      "computed_ccdwidth",
      "computed_aperturefnumber",
      "computed_usercomment",
      "computed_usercommentencoding",
      "computed_thumbnail.filetype",
      "computed_thumbnail.mimetype",
      "ifd0_filename",
      "ifd0_filedatetime",
      "ifd0_filesize",
      "ifd0_filetype",
      "ifd0_mimetype",
      "ifd0_sectionsfound",
      "ifd0_html",
      "ifd0_height",
      "ifd0_width",
      "ifd0_iscolor",
      "ifd0_byteordermotorola",
      "ifd0_ccdwidth",
      "ifd0_aperturefnumber",
      "ifd0_usercomment",
      "ifd0_usercommentencoding",
      "ifd0_thumbnail.filetype",
      "ifd0_thumbnail.mimetype",
      "ifd0_imagedescription",
      "ifd0_make",
      "ifd0_model",
      "ifd0_orientation",
      "ifd0_xresolution",
      "ifd0_yresolution",
      "ifd0_resolutionunit",
      "ifd0_software",
      "ifd0_datetime",
      "ifd0_artist",
      "ifd0_ycbcrpositioning",
      "ifd0_title",
      "ifd0_comments",
      "ifd0_author",
      "ifd0_subject",
      "ifd0_exif_ifd_pointer",
      "ifd0_gps_ifd_pointer",
      "thumbnail_filename",
      "thumbnail_filedatetime",
      "thumbnail_filesize",
      "thumbnail_filetype",
      "thumbnail_mimetype",
      "thumbnail_sectionsfound",
      "thumbnail_html",
      "thumbnail_height",
      "thumbnail_width",
      "thumbnail_iscolor",
      "thumbnail_byteordermotorola",
      "thumbnail_ccdwidth",
      "thumbnail_aperturefnumber",
      "thumbnail_usercomment",
      "thumbnail_usercommentencoding",
      "thumbnail_thumbnail.filetype",
      "thumbnail_thumbnail.mimetype",
      "thumbnail_imagedescription",
      "thumbnail_make",
      "thumbnail_model",
      "thumbnail_orientation",
      "thumbnail_xresolution",
      "thumbnail_yresolution",
      "thumbnail_resolutionunit",
      "thumbnail_software",
      "thumbnail_datetime",
      "thumbnail_artist",
      "thumbnail_ycbcrpositioning",
      "thumbnail_title",
      "thumbnail_comments",
      "thumbnail_author",
      "thumbnail_subject",
      "thumbnail_exif_ifd_pointer",
      "thumbnail_gps_ifd_pointer",
      "thumbnail_compression",
      "thumbnail_jpeginterchangeformat",
      "thumbnail_jpeginterchangeformatlength",
      "exif_filename",
      "exif_filedatetime",
      "exif_filesize",
      "exif_filetype",
      "exif_mimetype",
      "exif_sectionsfound",
      "exif_html",
      "exif_height",
      "exif_width",
      "exif_iscolor",
      "exif_byteordermotorola",
      "exif_ccdwidth",
      "exif_aperturefnumber",
      "exif_usercomment",
      "exif_usercommentencoding",
      "exif_thumbnail.filetype",
      "exif_thumbnail.mimetype",
      "exif_imagedescription",
      "exif_make",
      "exif_model",
      "exif_lens",
      "exif_lensid",
      "exif_orientation",
      "exif_xresolution",
      "exif_yresolution",
      "exif_resolutionunit",
      "exif_software",
      "exif_datetime",
      "exif_artist",
      "exif_ycbcrpositioning",
      "exif_title",
      "exif_comments",
      "exif_author",
      "exif_subject",
      "exif_exif_ifd_pointer",
      "exif_gps_ifd_pointer",
      "exif_compression",
      "exif_jpeginterchangeformat",
      "exif_jpeginterchangeformatlength",
      "exif_exposuretime",
      "exif_fnumber",
      "exif_exposureprogram",
      "exif_isospeedratings",
      "exif_exifversion",
      "exif_datetimeoriginal",
      "exif_datetimedigitized",
      "exif_componentsconfiguration",
      "exif_shutterspeedvalue",
      "exif_aperturevalue",
      "exif_exposurebiasvalue",
      "exif_meteringmode",
      "exif_flash",
      "exif_focallength",
      "exif_flashpixversion",
      "exif_colorspace",
      "exif_exifimagewidth",
      "exif_exifimagelength",
      "exif_interoperabilityoffset",
      "exif_focalplanexresolution",
      "exif_focalplaneyresolution",
      "exif_focalplaneresolutionunit",
      "exif_imageuniqueid",
      "gps_filename",
      "gps_filedatetime",
      "gps_filesize",
      "gps_filetype",
      "gps_mimetype",
      "gps_sectionsfound",
      "gps_html",
      "gps_height",
      "gps_width",
      "gps_iscolor",
      "gps_byteordermotorola",
      "gps_ccdwidth",
      "gps_aperturefnumber",
      "gps_usercomment",
      "gps_usercommentencoding",
      "gps_thumbnail.filetype",
      "gps_thumbnail.mimetype",
      "gps_imagedescription",
      "gps_make",
      "gps_model",
      "gps_orientation",
      "gps_xresolution",
      "gps_yresolution",
      "gps_resolutionunit",
      "gps_software",
      "gps_datetime",
      "gps_artist",
      "gps_ycbcrpositioning",
      "gps_title",
      "gps_comments",
      "gps_author",
      "gps_subject",
      "gps_exif_ifd_pointer",
      "gps_gps_ifd_pointer",
      "gps_compression",
      "gps_jpeginterchangeformat",
      "gps_jpeginterchangeformatlength",
      "gps_exposuretime",
      "gps_fnumber",
      "gps_exposureprogram",
      "gps_isospeedratings",
      "gps_exifversion",
      "gps_datetimeoriginal",
      "gps_datetimedigitized",
      "gps_componentsconfiguration",
      "gps_shutterspeedvalue",
      "gps_aperturevalue",
      "gps_exposurebiasvalue",
      "gps_meteringmode",
      "gps_flash",
      "gps_focallength",
      "gps_flashpixversion",
      "gps_colorspace",
      "gps_exifimagewidth",
      "gps_exifimagelength",
      "gps_interoperabilityoffset",
      "gps_gpsimgdirectionref",
      "gps_gpsimgdirection",
      "gps_focalplanexresolution",
      "gps_focalplaneyresolution",
      "gps_focalplaneresolutionunit",
      "gps_imageuniqueid",
      "gps_gpsversion",
      "gps_gpslatituderef",
      "gps_gpslatitude",
      "gps_gpslongituderef",
      "gps_gpslongitude",
      "gps_gpsaltituderef",
      "gps_gpsaltitude",
      "interop_filename",
      "interop_filedatetime",
      "interop_filesize",
      "interop_filetype",
      "interop_mimetype",
      "interop_sectionsfound",
      "interop_html",
      "interop_height",
      "interop_width",
      "interop_iscolor",
      "interop_byteordermotorola",
      "interop_ccdwidth",
      "interop_aperturefnumber",
      "interop_usercomment",
      "interop_usercommentencoding",
      "interop_thumbnail.filetype",
      "interop_thumbnail.mimetype",
      "interop_imagedescription",
      "interop_make",
      "interop_model",
      "interop_orientation",
      "interop_xresolution",
      "interop_yresolution",
      "interop_resolutionunit",
      "interop_software",
      "interop_datetime",
      "interop_artist",
      "interop_ycbcrpositioning",
      "interop_title",
      "interop_comments",
      "interop_author",
      "interop_subject",
      "interop_exif_ifd_pointer",
      "interop_gps_ifd_pointer",
      "interop_compression",
      "interop_jpeginterchangeformat",
      "interop_jpeginterchangeformatlength",
      "interop_exposuretime",
      "interop_fnumber",
      "interop_exposureprogram",
      "interop_isospeedratings",
      "interop_exifversion",
      "interop_datetimeoriginal",
      "interop_datetimedigitized",
      "interop_componentsconfiguration",
      "interop_shutterspeedvalue",
      "interop_aperturevalue",
      "interop_exposurebiasvalue",
      "interop_meteringmode",
      "interop_flash",
      "interop_focallength",
      "interop_flashpixversion",
      "interop_colorspace",
      "interop_exifimagewidth",
      "interop_exifimagelength",
      "interop_interoperabilityoffset",
      "interop_focalplanexresolution",
      "interop_focalplaneyresolution",
      "interop_focalplaneresolutionunit",
      "interop_imageuniqueid",
      "interop_gpsversion",
      "interop_gpslatituderef",
      "interop_gpslatitude",
      "interop_gpslongituderef",
      "interop_gpslongitude",
      "interop_gpsaltituderef",
      "interop_gpsaltitude",
      "interop_interoperabilityindex",
      "interop_interoperabilityversion",
      "winxp_filename",
      "winxp_filedatetime",
      "winxp_filesize",
      "winxp_filetype",
      "winxp_mimetype",
      "winxp_sectionsfound",
      "winxp_html",
      "winxp_height",
      "winxp_width",
      "winxp_iscolor",
      "winxp_byteordermotorola",
      "winxp_ccdwidth",
      "winxp_aperturefnumber",
      "winxp_usercomment",
      "winxp_usercommentencoding",
      "winxp_thumbnail.filetype",
      "winxp_thumbnail.mimetype",
      "winxp_imagedescription",
      "winxp_make",
      "winxp_model",
      "winxp_orientation",
      "winxp_xresolution",
      "winxp_yresolution",
      "winxp_resolutionunit",
      "winxp_software",
      "winxp_datetime",
      "winxp_artist",
      "winxp_ycbcrpositioning",
      "winxp_title",
      "winxp_comments",
      "winxp_author",
      "winxp_subject",
      "winxp_exif_ifd_pointer",
      "winxp_gps_ifd_pointer",
      "winxp_compression",
      "winxp_jpeginterchangeformat",
      "winxp_jpeginterchangeformatlength",
      "winxp_exposuretime",
      "winxp_fnumber",
      "winxp_exposureprogram",
      "winxp_isospeedratings",
      "winxp_exifversion",
      "winxp_datetimeoriginal",
      "winxp_datetimedigitized",
      "winxp_componentsconfiguration",
      "winxp_shutterspeedvalue",
      "winxp_aperturevalue",
      "winxp_exposurebiasvalue",
      "winxp_meteringmode",
      "winxp_flash",
      "winxp_focallength",
      "winxp_flashpixversion",
      "winxp_colorspace",
      "winxp_exifimagewidth",
      "winxp_exifimagelength",
      "winxp_interoperabilityoffset",
      "winxp_focalplanexresolution",
      "winxp_focalplaneyresolution",
      "winxp_focalplaneresolutionunit",
      "winxp_imageuniqueid",
      "winxp_gpsversion",
      "winxp_gpslatituderef",
      "winxp_gpslatitude",
      "winxp_gpslongituderef",
      "winxp_gpslongitude",
      "winxp_gpsaltituderef",
      "winxp_gpsaltitude",
      "winxp_interoperabilityindex",
      "winxp_interoperabilityversion",
    ];
  }

}
