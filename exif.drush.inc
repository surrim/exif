<?php

/**
 * @file
 * Drush extension allowing to run some tasks related to exif.
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

const ENTITY_TYPE_SUPPORTED = ['file', 'media', 'node'];

/**
 * Implements hook_drush_command().
 */
function exif_drush_command() {
  $items['exif-list'] = [
    'description' => 'list entity type where exif is enabled.',
    'arguments' => [
      '$entity_type' => 'Optional. The entity manager to use (media|file|node). (all other entity manager will be ignored)',
    ],
  ];
  $items['exif-update'] = [
    'description' => 'Update all entity where exif is enabled.',
    'arguments' => [
      '$entity_type' => 'Optional. The entity manager to use (media|file|node). (all other entity manager will be ignored)',
      'type' => 'Optional. The type to update (all other type will be ignored).',
    ],
  ];
  $items['exif-import'] = [
    'description' => 'Import a directory tree in drupal using requested type.',
    'arguments' => [
      '$entity_type' => 'Required. The entity type (media|file|node) to insert photo with metadata.',
      'type' => 'Required. The type to insert photo with relative metadata.',
      'field' => 'Required. the field to insert the photo',
      'path' => 'Required. a path to a image or a directory',
    ],
  ];

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function exif_drush_help($section) {
  switch ($section) {
    case 'drush:exif-list':
      return dt('list content type where exif is enabled.');

    case 'drush:exif-update':
      return dt('Update all nodes where exif is enabled.');

    case 'drush:exif-import':
      return dt('Import all nodes where exif is enabled.');
  }
  return '';
}

/**
 * Implements Drush callback.
 *
 * List all entity types supported by this module or check
 * an entity type is supported.
 *
 * @param string $entity_type
 *   Name of the entity type to check or empty if you want all entity types.
 */
function drush_exif_list($entity_type = '') {
  $entity_types = __check_entity_type($entity_type);
  $types = __drush_exif_list_active_types($entity_types);
  \Drupal::logger(dt('listing %count content types.', ['%count' => count($types)]));
  foreach ($types as $type) {
    $params = ['%entity' => $type['entity'], '%type' => $type['type']];
    \Drupal::logger(dt('  * %entity, %type', $params));
  }
}

/**
 * Implements Drush callback.
 */
function drush_exif_update($entity_type = '', $type = '') {
  $entity_types = __check_entity_type($entity_type);
  if (count($entity_types) == 0) {
    \Drupal::logger(dt('invalid entity type %type.', ['%type' => $entity_type]));
    return;
  }
  $selected_types = __check_bundle($entity_types, $type);
  if (count($selected_types) == 0) {
    \Drupal::logger(dt('invalid type %type.', ['%type' => $type]));
    return;
  }
  \Drupal::logger(dt('Need to update %count types.', ['%count' => count($selected_types)]));
  foreach ($selected_types as $type) {
    if ($type['entity'] == 'node') {
      $count = __drush_exif_node_update($type['type']);
    }
    if ($type['entity'] == 'file') {
      $count = __drush_exif_file_update($type['type']);
    }
    if ($type['entity'] == 'media') {
      $count = __drush_exif_media_update($type['type']);
    }
  }
}

/**
 * The drush import.
 *
 * @param string $entity_type
 *   The entity type.
 * @param string $type
 *   The type.
 * @param mixed $field
 *   The specific field.
 * @param mixed $path
 *   The path.
 * @param mixed $langcode
 *   The lang code.
 */
function drush_exif_import($entity_type = '', $type = '', $field = NULL, $path = NULL, $langcode = NULL) {
  if ($path == NULL or $field == NULL) {
    \Drupal::logger(dt('missing at least one parameter.'));
    \Drupal::logger(dt('usage: drush exif-import <entity type:(file|media|node)> <type> <fieldname for photo> <path to files>'));
    return;
  }
  // Check path.
  if (!file_exists($path)) {
    drush_log(dt('invalid path %path.', ['%path' => $path]), "error");
    return;
  }
  // Check entity type.
  $entity_types = __check_entity_type($entity_type);
  if (count($entity_types) == 0) {
    return;
  }
  // Check type.
  $selected_types = __check_bundle($entity_types, $type);
  if (count($selected_types) == 0) {
    return;
  }
  // Check field.
  $fields_of_bundle = \Drupal::getContainer()
    ->get('entity_field.manager')
    ->getFieldDefinitions($entity_type, $type);
  $selected_field = NULL;
  foreach ($fields_of_bundle as $key => $value) {
    if ($key === $field) {
      $selected_field = $value;
    }
  }
  if ($selected_field == NULL) {
    drush_log(dt('invalid field name %field', ['%field' => $field]), "error");
    drush_log(dt("valid field are"), "error");
    foreach ($fields_of_bundle as $key => $value) {
      drush_log(dt("%key", ["%key" => $key]), "error");
    }
    return;
  }
  if ($selected_field->getType() !== "image" && $selected_field->getType() !== "media") {
    drush_log(dt('field name %field is not a image field', ['%field' => $field]), "error");
    return;
  }
  // Find files.
  $files = [];
  if (is_file($path)) {
    $files[] = $path;
  }
  else {
    $paths[] = $path;
    while (count($paths) != 0) {
      $v = array_shift($paths);
      drush_log(dt('looking in path %path.', ['%path' => $v]), "ok");
      foreach (glob($v . '/*') as $item) {
        if ($item != '.' and $item != '..' and is_dir($item)) {
          $paths[] = $item;
        }
        elseif (is_file($item) && exif_imagetype($item) == IMAGETYPE_JPEG) {
          $files[] = $item;
        }
      }
    }
  }
  // Import.
  drush_log(dt('importing %count files.', ['%count' => count($files)]), "ok");
  foreach ($files as $file) {
    __drush_exif_entity_import($entity_type, $type, 1, $field, $file, $langcode);
  }
}

/**
 * Check an entity type is supported by this module.
 *
 * @param string $entity_type
 *   Name of the entity type.
 *
 * @return array
 *   Returns entity types.
 */
function __check_entity_type($entity_type = '') {
  $entity_types = [];
  if (in_array($entity_type, ENTITY_TYPE_SUPPORTED)) {
    $entity_types[] = $entity_type;
  }
  else {
    if ($entity_type == '') {
      $entity_types = ENTITY_TYPE_SUPPORTED;
    }
    else {
      \Drupal::logger(dt('entity %entity is not supported.', ['%entity' => $entity_type]));
    }
  }
  return $entity_types;
}

/**
 * Look for exif entity types.
 *
 * @param array $entity_types
 *   Names of entity_types to check.
 * @param string $type
 *   Type to check.
 *
 * @return array
 *   Returns an array of the selected types.
 */
function __check_bundle(array $entity_types, $type) {
  $types = __drush_exif_list_active_types($entity_types);
  $selected_types = [];
  if ($type === '') {
    $selected_types = $types;
  }
  else {
    foreach ($entity_types as $entity_type) {
      $item = [
        'entity' => $entity_type,
        'type' => $type,
      ];
      if (in_array($item, $types)) {
        $selected_types[] = $item;
      }
    }
    if (count($selected_types) == 0) {
      \Drupal::logger(dt('type %type is not in exif active types.', ['%type' => $type]));
      \Drupal::logger(dt('exif active types are  :'));
      foreach ($types as $type) {
        $params = ['%entity' => $type['entity'], '%type' => $type['type']];
        \Drupal::logger(dt('  * %entity, %type', $params));
      }
    }
  }
  return $selected_types;
}

/**
 * List all entity types supported by this module.
 *
 * @param array $entity_types
 *
 * @return array
 */
function __drush_exif_list_active_types($entity_types = []) {
  $config = \Drupal::config('exif.settings');
  $types = [];
  foreach ($entity_types as $entity_type) {
    $exif_entitytypes = $config->get($entity_type . 'types');
    if ($exif_entitytypes == NULL) {
      $exif_entitytypes = [];
    }
    // Fill up array with checked nodetypes.
    foreach ($exif_entitytypes as $type) {
      if ($type != "0") {
        $types[] = [
          'entity' => $entity_type,
          'type' => $type,
        ];
      }
    }
  }
  return $types;
}

/**
 * Update all node of specified type.
 *
 * @param string $type
 *   Name of the node type.
 *
 * @return int
 *   Node count updated.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function __drush_exif_node_update($type = '') {
  $query = "SELECT n.nid FROM {node} n WHERE n.type = :type";
  $result = \Drupal::database()->query($query, [':type' => $type]);
  $count = 0;
  foreach ($result as $record) {
    // Load the node object from the database.
    $node = Node::load($record->nid);
    // Resave the node to make exif changes.
    $node->save();
    $count++;
  }
  \Drupal::logger(dt('Updated %count %type nodes.', [
    '%count' => $count,
    '%type' => $type,
  ]));
  return $count;
}

/**
 * The drush file update.
 *
 * @param string $type
 *   The type.
 *
 * @return int
 *   Returns The $count;
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function __drush_exif_file_update($type = '') {
  $query = "SELECT n.fid FROM {file_managed} n WHERE n.type = :type";
  $result = \Drupal::database()->query($query, [':type' => $type]);
  $count = 0;
  foreach ($result as $record) {
    // Load the node object from the database.
    $file = File::load($record->fid);
    // Resave the node to make exif changes.
    $file->save();
    $count++;
  }
  \Drupal::logger(dt('Updated %count %type files.', [
    '%count' => $count,
    '%type' => $type,
  ]));
  return $count;
}

/**
 * The drush media update.
 *
 * @param string $type
 *   The type.
 *
 * @return int
 *   Returns $count.
 */
function __drush_exif_media_update($type = '') {
  $query = "SELECT m.mid FROM {media} m WHERE m.bundle = :type";
  $result = \Drupal::database()->query($query, [':type' => $type]);
  $count = 0;
  foreach ($result as $record) {
    // Load the media object from the database.
    $media = Media::load($record->mid);
    // Resave the media to make exif changes.
    $media->save();
    $count++;
  }
  \Drupal::logger(dt('Updated %count %type medias.', [
    '%count' => $count,
    '%type' => $type,
  ]));
  return $count;
}

/**
 * The drush entity.
 *
 * @param string $entity_type
 *   The entity type.
 * @param string $type
 *   The type.
 * @param int $uid
 *   The user id.
 * @param string $field
 *   The field.
 * @param mixed $file
 *   THe file.
 * @param string $langcode
 *   The lang code.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function __drush_exif_entity_import($entity_type, $type, $uid, $field, $file, $langcode) {
  $title = basename($file);
  $languageManager = \Drupal::getContainer()->get('language_manager');
  if ($langcode == NULL) {
    $langcode = $languageManager->getDefaultLanguage()->getId();
  }
  \Drupal::logger(dt('start import of %file as %type entity with title "%title"', [
    '%file' => $file,
    '%type' => $type,
    '%title' => $title,
  ]));

  // Saving file. automatically added to file entity if present.
  $file_content = file_get_contents($file);
  // Saves a file to the specified destination and creates a database entry.
  $file_temp = \Drupal::service('file.repository')->writeData($file_content, 'public://' . $title, FileSystemInterface::EXISTS_RENAME);

  // If not file entity, create associated element.
  if ($file_temp && $entity_type != 'file') {
    $entityTypeManager = \Drupal::getContainer()->get('entity_type.manager');
    $entityStorage = $entityTypeManager->getStorage($entity_type);
    // For nodes.
    $attributes = NULL;
    if ($entity_type == 'node') {
      $attributes = [
        'nid' => NULL,
        'type' => $type,
        'title' => $title,
        'alt' => $title,
        'uid' => $uid,
        'revision' => 1,
        'status' => TRUE,
        'promote' => 0,
        'created' => \Drupal::time()->getRequestTime(),
        'langcode' => $langcode,
        $field => [
          'target_id' => $file_temp->id(),
        ],
      ];
    }
    // Does not work :( .
    if ($entity_type == 'media') {

      $attributes = [
        'mid' => NULL,
        'bundle' => $type,
        'name' => $title,
        'label' => $title,
        'title' => $title,
        'alt' => $title,
        'uid' => $uid,
        'revision' => 1,
        'status' => TRUE,
        'created' => \Drupal::time()->getRequestTime(),
        'langcode' => $langcode,
        $field => [
          'target_id' => $file_temp->id(),
        ],
      ];

    }
    if ($attributes == NULL) {
      \Drupal::logger(dt('entity type %entity_type is not supported. %file is not imported.', [
        '%file' => $file,
        '%entity_type' => $entity_type,
      ]));
    }
    else {
      // Load the node object from the database.
      $entity = $entityStorage->create($attributes);
      $entity->save();
      \Drupal::logger(dt('imported %file as %type entity.', [
        '%file' => $file,
        '%type' => $type,
      ]));
    }
  }
  else {
    \Drupal::logger(dt('failed to import %file as %type entity.', [
      '%file' => $file,
      '%type' => $type,
    ]));
  }

}

/**
 * Determine language based on $results.
 */
function __drush_exif_getlangcode($results) {
  $langcode = NULL;
  if (isset($results['add_language'])) {
    $langcodes = $results['add_language'];
    $langcode = $langcodes[array_rand($langcodes)];
  }
  return $langcode;
}
