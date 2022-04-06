<?php

namespace Drupal\exif\Plugin\migrate\field\d7;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * @MigrateField(
 *   id = "exif_read_only",
 *   core = {7},
 *   source_module = "exif",
 *   destination_module = "exif"
 * )
 */
class ExifReadOnly extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'exif_readonly' => 'exif_readonly',
    ];
  }

}
