README file for the Exif Drupal module.


Description
***********

The Exif module allows to display Exif metadata on image nodes. Exif is a
specification for the image file format used by digital cameras.

The metadata tags defined in the Exif standard cover a broad spectrum including [1]:

 * Date and time information. Digital cameras will record the current date and
   time and save this in the metadata.
 * Camera settings. This includes static information such as the camera model
   and make, and information that varies with each image such as orientation,
   aperture, shutter speed, focal length, metering mode, and film speed
   information.
 * Location information, which could come from a GPS receiver connected to the
   camera.
 * Descriptions and copyright information.

Administrators can choose what Exif tags they want to display, and control the
order of appearance. 

At this time, this module supports Exif information only with JPEG files.

[1] Reference: http://en.wikipedia.org/wiki/Exchangeable_image_file_format


Requirements
************

This module requires the PHP Exif Library (PEL), http://sourceforge.net/projects/pel

PEL itself requires PHP version 5.
It does NOT work under PHP 4.

This module has been tested with PEL version 0.9.

And of course, this module also requires Drupal (version 5.0). This module won't
do anything without the Image module (http://drupal.org/project/image), as Exif
data is displayed only on image nodes.


Installation
************

1. Extract the 'exif' module directory, including all its subdirectories, into
   your Drupal modules directory.

2. Download and extract the PEL archive into the modules/exif/pel
   directory. When you're finished the directory structure should look something
   like:

   drupal/
     modules/
       exif/
         pel/
           README
           INSTALL
           Pel.php
           PelJpeg.php
           ...
             
3. Enable the Exif module on your site's administer > site building > modules
   page. A database table will automagically be created at this point.

4. Go to administer > site configuration > exif settings, and select what Exif
   tags to display.


Credits
*******

David Lesieur <david [at] davidlesieur [dot] com>

PEL is written by Martin Geisler <mgeisler [at] users.sourceforge [dot] net>
PEL originally started out as a port of libexif.
