<?php

namespace Drupal\exif;

/**
 * Class ExifFactory allow to get right implementation.
 *
 * @todo Rewrite this as a plugin system.
 *
 * @package Drupal\exif
 */
class ExifFactory {

  /**
   * Return description of exif parser implementations.
   *
   * @return array
   *   List names of parser implementations
   */
  public static function getExtractionSolutions() {
    return [
      'php_extensions' => 'php extensions',
      'simple_exiftool' => 'exiftool',
    ];
  }

  /**
   * Return configured exif parser.
   *
   * @return \Drupal\exif\ExifInterface
   *   cCnfigured exif parser
   */
  public static function getExifInterface() {
    $config = \Drupal::config('exif.settings');
    $extractionSolution = $config->get('extraction_solution');
    if ($extractionSolution == 'simple_exiftool' && SimpleExifToolFacade::checkConfiguration()) {
      return SimpleExifToolFacade::getInstance();
    }
    else {
      // Default case for now (same behavior as previous versions)
      return ExifPHPExtension::getInstance();
    }
  }

}
