<?php

use \Drupal\node\Entity\Node;
use \Drupal\file\Entity\File;
use \Drupal\media_entity\Entity\Media;

const ENTITY_TYPE_SUPPORTED = [ 'file', 'media', 'node'];

/**
 * Implementation of hook_drush_command().
 */
function exif_drush_command() {
  $items['exif-list'] = array(
    'description' => 'list entity type where exif is enabled.',
    'arguments' => array(
      '$entity_type' => 'Optional. The entity manager to use (media|file|node). (all other entity manager will be ignored)',
    ),
  );
  $items['exif-update'] = array(
    'description' => 'Update all entity where exif is enabled.',
    'arguments' => array(
      '$entity_type' => 'Optional. The entity manager to use (media|file|node). (all other entity manager will be ignored)',
       'type' => 'Optional. The type to update (all other type will be ignored).',
    ),
  );
  $items['exif-import'] = array(
    'description' => 'Import a directory tree in drupal using requested type.',
    'arguments' => array(
      '$entity_type' => 'Required. The entity type (media|file|node) to insert photo with metadata.',
      'type' => 'Required. The type to insert photo with relative metadata.',
      'field' => 'Required. the field to insert the photo',
      'path' => 'Required. a path to a image or a directory',
    ),
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
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
}

/**
 * Drush callback;
 */
function drush_exif_list($entity_type = '') {
  $entity_types = __check_entity_type($entity_type);
  $types = __drush_exif_list_active_types($entity_types);
  drush_log(dt('listing %count content types.', array('%count' =>  count($types))),'ok');
  foreach ($types as $type) {
    $params = array('%entity' => $type['entity'], '%type' => $type['type']);
    drush_log(dt('  * %entity, %type', $params), 'ok');
  }
}

/**
 * Drush callback;
 */
function drush_exif_update($entity_type = '', $type = '') {
  $entity_types = __check_entity_type($entity_type);
  if (count($entity_types)==0) {
    drush_log(dt('invalid entity type %type.', array('%type' => $entity_type)),"error");
    return;
  }
  $selected_types = __check_bundle($entity_types, $type);
  if (count($selected_types)==0) {
    drush_log(dt('invalid type %type.', array('%type' => $type)),"error");
    return;
  }
  drush_log(dt('Need to update %count types.', array('%count' =>  count($selected_types))));
  foreach ( $selected_types as $type) {
    if ($type['entity']=='node') {
      $count = __drush_exif_node_update($type['type']);
    }
    if ($type['entity']=='file') {
      $count = __drush_exif_file_update($type['type']);
    }
    if ($type['entity']=='media') {
      $count = __drush_exif_media_update($type['type']);
    }
  }
}


function drush_exif_import($entity_type = '', $type = '', $field = NULL, $path = NULL, $langcode = NULL) {
  if ($path==NULL or $field==NULL) {
    drush_log(dt('missing at least one parameter.'),"error");
    drush_log(dt('usage: drush exif-import <entity type:(file|media|node)> <type> <fieldname for photo> <path to files>'),"error");
    return;
  }
  //check path
  if (!file_exists($path)) {
    drush_log(dt('invalid path %path.', array('%path' => $path)),"error");
    return;
  }
  //check entity type
  $entity_types = __check_entity_type($entity_type);
  if (count($entity_types)==0) {
    return;
  }
  //check type
  $selected_types = __check_bundle($entity_types, $type);
  if (count($selected_types)==0) {
    return;
  }
  //check field
  $fields_of_bundle = Drupal::getContainer()
    ->get('entity_field.manager')
    ->getFieldDefinitions($entity_type, $type);
  $selected_field = NULL;
  foreach ($fields_of_bundle as $key => $value) {
    if ($key===$field) {
      $selected_field=$value;
    }
  }
  if ($selected_field==NULL) {
    drush_log(dt('invalid field name %field', array('%field' => $field)),"error");
    drush_log(dt("valid field are"),"error");
    foreach ($fields_of_bundle as $key => $value) {
      drush_log(dt("%key",array("%key" => $key)),"error");
    }
    return;
  }
  if ( $selected_field->getType() !== "image" && $selected_field->getType() !== "media") {
    drush_log(dt('field name %field is not a image field', array('%field' => $field)),"error");
    return;
  }
  //find files
  $files = array();
  if (is_file($path)) {
    $files[]=$path;
  } else {
    $paths[] = $path;
    while(count($paths) != 0)
    {
      $v = array_shift($paths);
      drush_log(dt('looking in path %path.', array('%path' => $v)),"ok");
      foreach(glob($v.'/*') as $item) {
        if ($item!='.' and $item!='..' and is_dir($item)) {
          $paths[] = $item;
        } elseif (is_file($item) && exif_imagetype($item) == IMAGETYPE_JPEG) {
          $files[]=$item;
        }
      }
    }
  }
  //import
  drush_log(dt('importing %count files.', array('%count' => count($files))),"ok");
  foreach ($files as $file) {
    __drush_exif_entity_import($entity_type, $type,1,$field,$file,$langcode);
  }
}

/**
 * internal functions
 */
function __check_entity_type($entity_type = '') {
  $entity_types = array();
  if ( in_array($entity_type, ENTITY_TYPE_SUPPORTED) ) {
    $entity_types[] = $entity_type;
  } else if ($entity_type == '') {
    $entity_types = ENTITY_TYPE_SUPPORTED;
  } else {
    drush_log(dt('entity %entity is not supported.', array('%entity' => $entity_type)),"error");
  }
  return $entity_types;
}

function __check_bundle($entity_types, $type) {
  $types = __drush_exif_list_active_types($entity_types);
  $selected_types = array();
  if ($type === '') {
    $selected_types = $types;
  } else {
    foreach ($entity_types as $entity_type) {
      $item = array(
        'entity' => $entity_type,
        'type' => $type
      );
      if (in_array($item, $types)) {
        $selected_types[] = $item;
      }
    }
    if (count($selected_types)==0) {
      drush_log(dt('type %type is not in exif active types.', array('%type' =>  $type)),"error");
      drush_log(dt('exif active types are  :'),"error");
      foreach ($types as $type) {
        $params = array('%entity' => $type['entity'], '%type' => $type['type']);
        drush_log(dt('  * %entity, %type', $params), 'error');
      }
    }
  }
  return $selected_types;
}

function __drush_exif_list_active_types($entity_types = array()) {
  $config = Drupal::configFactory()->get('exif.settings');
  $types = array();
  foreach ($entity_types as $entity_type) {
      $exif_entitytypes = $config->get($entity_type.'types');
      if ($exif_entitytypes==NULL) {
        $exif_entitytypes=array();
      }
    //fill up array with checked nodetypes
    foreach ($exif_entitytypes as $type) {
      if ($type != "0" ) {
        $types[] = array(
          'entity' => $entity_type,
          'type' => $type
        );
      }
    }
  }
  return $types;
}

function __drush_exif_node_update($type = '') {
  $query = "SELECT n.nid FROM {node} n WHERE n.type = :type";
  $result =  db_query($query, array(':type' => $type));
  $count = 0;
  foreach ($result as $record) {
    // Load the node object from the database
    $node = Node::load($record->nid);
    // Resave the node to make exif changes.
    $node->save();
    $count++;
  }
  drush_log(dt('Updated %count %type nodes.', array('%count' => $count, '%type' => $type)),"ok");
  return $count;
}

function __drush_exif_file_update($type = '') {
  $query = "SELECT n.fid FROM {file_managed} n WHERE n.type = :type";
  $result =  db_query($query, array(':type' => $type));
  $count = 0;
  foreach ($result as $record) {
    // Load the node object from the database
    $file = File::load($record->fid);
    // Resave the node to make exif changes.
    $file->save();
    $count++;
  }
  drush_log(dt('Updated %count %type files.', array('%count' => $count, '%type' => $type)),"ok");
  return $count;
}

function __drush_exif_media_update($type = '') {
  $query = "SELECT m.mid FROM {media} m WHERE m.bundle = :type";
  $result =  db_query($query, array(':type' => $type));
  $count = 0;
  foreach ($result as $record) {
    // Load the media object from the database
    $media = Media::load($record->mid);
    // Resave the media to make exif changes.
    $media->save();
    $count++;
  }
  drush_log(dt('Updated %count %type medias.', array('%count' => $count, '%type' => $type)),"ok");
  return $count;
}

function __drush_exif_entity_import($entity_type, $type, $uid, $field, $file, $langcode) {
  $title = basename($file);
  $languageManager =  \Drupal::getContainer()->get('language_manager');
  if ($langcode == NULL) {
    $langcode =  $languageManager->getDefaultLanguage()->getId();
  }
  drush_log(dt('start import of %file as %type entity with title "%title"', array('%file' => $file, '%type' => $type, '%title' => $title)),"ok");

  //Saving file. automatically added to file entity if present
  $file_content = file_get_contents($file);
  //Saves a file to the specified destination and creates a database entry.
  $file_temp = file_save_data($file_content, 'public://'.$title, FILE_EXISTS_RENAME);

  //if not file entity, create associated element
  if ($file_temp && $entity_type!='file') {
    $entityTypeManager = \Drupal::getContainer()->get('entity_type.manager');
    $entityStorage = $entityTypeManager->getStorage($entity_type);
    //for nodes
    $attributes = NULL;
    if ($entity_type=='node') {
    $attributes = array(
      'nid' => NULL,
      'type' => $type,
      'title' => $title,
      'alt' => $title,
      'uid' => $uid,
      'revision' => 1,
      'status' => TRUE,
      'promote' => 0,
      'created' => REQUEST_TIME,
      'langcode' => $langcode,
      $field => [
        'target_id' => $file_temp->id(),
      ]);
    }
    // does not work :(
    if ($entity_type=='media') {

      $attributes = array(
        'mid' => NULL,
        'bundle' => $type,
        'name' => $title,
        'label' => $title,
        'title' => $title,
        'alt' => $title,
        'uid' => $uid,
        'revision' => 1,
        'status' => TRUE,
        'created' => REQUEST_TIME,
        'langcode' => $langcode,
        $field => [
          'target_id' => $file_temp->id(),
        ]);

    }
    if  ($attributes == NULL) {
      drush_log(dt('entity type %entity_type is not supported. %file is not imported.', array('%file' => $file, '%entity_type' => $entity_type)),"ko");
    }  else {
      // Load the node object from the database
      $entity = $entityStorage->create($attributes);
      $entity->save();
      drush_log(dt('imported %file as %type entity.', array('%file' => $file, '%type' => $type)),"ok");
    }
  } else {
    drush_log(dt('failed to import %file as %type entity.', array('%file' => $file, '%type' => $type)),"ko");
  }


}

  /**
   * Determine language based on $results.
   */
function __drush_exif_getLangcode($results) {
  if (isset($results['add_language'])) {
    $langcodes = $results['add_language'];
    $langcode = $langcodes[array_rand($langcodes)];
  }
  return $langcode;
}

