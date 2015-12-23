<?php
/**
 * @file
 * Contains \Drupal\content_entity_example\ContactInterface.
 */

namespace Drupal\content_entity_example;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Photography entity.
 * @ingroup exif
 */
interface PhotographyInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
?>
