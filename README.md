# Exif Module

The Exif module allows displaying Exif metadata on image nodes. Exif is a
specification for the image file format used by digital cameras.

The metadata tags defined in [the Exif
standard](http://en.wikipedia.org/wiki/Exchangeable_image_file_format) cover a
broad spectrum including:

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


## REQUIREMENTS

Drupal 9 or 10 with at least node enabled. There can be several image per node.
each Exif field must be linked to the chosen image of the node.

If taxonomy module is enabled, the information is saved as a vocabulary. A
specific vocabulary must be set in the exif plugin configuration page.

## Constraints

* with Image Processing utility:

Note that, it has been reported that when using GD, it strips some fields
(like field_gps_gpslatitude or field_gps_gpslongitude) from uploaded files.
Imagemagick seems not to have this limitation and does not strip these fields.
So Imagemagick is recommended when access to GPS data.

## CONFIGURATION

### Create a content type

After installing it, you can go to your structure administration page.
Let's say there is a content type "photography". Go to the content settings and
add a new type of content 'photography' with default fields. Then, add exif fields. For the name of the field follow some naming conventions.

### Naming convention

The *general rule* is: `[field]_[section]_[name]`

Examples:

- `field_exif_exposuretime` -> this would read the ExposureTime of the
  image and save it in this field.
- `field_ifd0_datetime` -> this would read the date time (2009:01:23 08:52:43)
  of the image. The field_type could be a normal textfield, but also a date
  field would be possible.

### Sample page

On the Exif quickstart page (`admin/config/media/exif/helper`) there is a list
of all possible information. The information is retrieved from the image
"sample.jpg" and may not contain all tags available. If you are looking for
some specific tags you can just replace this image with your own image.

## Most used values

- Exif
  - Make
  - Model
  - ExposureTime
  - FocalLength
  - ISO
  - FNumber
  - DateTimeOriginal
  - ApertureValue
  - Flash
  - UserComment
  - ExposureCompensation
  - ShutterSpeedValue
  - ExposureMode
  - WhiteBalance
  - GPS*

- XMP
  - Artist
  - Orientation
  - ColorMode
  - FlashMode
  - FlashFired
  - Title
  - Keywords
