<?php
/**
 * @file
 * Contains \Drupal\exif\ExifFactory
 */

namespace Drupal\exif;


class ExifFactory
{

  static private $instance = NULL;

  /**
   * We are implementing a singleton pattern
   */
  private function __construct()
  {
  }

  public static function getInstance()
  {
    if (is_null(self::$instance)) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * For now, the Factory return the only implementation based on PHP Exif Extension
   * @return ExifInterface|null
   */
  public static function getExifInterface()
  {
    return ExifFactory::getInstance()->createNewImplementation();
  }

  private function createNewImplementation()
  {
    return ExifPHPExtension::getInstance();
  }

}
