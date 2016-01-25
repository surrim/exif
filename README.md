Exif
====

README file for the Exif Drupal module.


Description
------------

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

Administrators can choose via fields GUI which Exif information are read.

At this time, this module supports Exif information only with JPEG files.

[1] Reference: http://en.wikipedia.org/wiki/Exchangeable_image_file_format


Requirements and Constraints
----------------------------

Drupal 8 with at least node enabled.
You can have several image per node. each exif field must be linked to the chosen image
of the node.

If taxonomy module is enabled, you can save the information as a vocabulary.
A specific vocabulary must be set in the exif plugin configuration page.

Usage
-----

After installing it you can go to your structure administration page.
Let's say you have an content type "photography". Go to your
content settings and add a new type of content 'photography' with default fields.
Then, add exif fields. For the name of the field you need to follow the following
naming conventions:

Example:
#1 field_exif_exposuretime -> this would read the ExposureTime of the image and save it
in this field.

#2 field_ifd0_datetime ->	this would read the date time (2009:01:23 08:52:43) of the image.
as a field_type you can take for example a normal textfield, but also a date field would be
possible.

General rule is: [field]_[section]_[name]

Under the exif quickstart (admin/config/media/exif/helper), you can see a list of all
possible information. These informations are taken from the image "sample.jpg".
I may not contain all tags available. If you are looking for some specific tags you
can just replace this image with your own image.
