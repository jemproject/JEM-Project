<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\CMS\Language\Text;

require_once(JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
require_once(JPATH_SITE.'/components/com_jem/classes/imageresourcepolicy.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/imageprofilepolicy.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/eventimagepath.class.php');
require_once(JPATH_SITE.'/components/com_jem/classes/venueimagepath.class.php');

/**
 * Holds the logic for image manipulation
 *
 * @package JEM
 */
class JemImage
{
    /**
     * Build or return a thumbnail for an event link image.
     *
     * @param   string   $image      Relative local image path.
     * @param   integer  $maxWidth   Maximum thumbnail width.
     * @param   integer  $maxHeight  Maximum thumbnail height.
     * @param   boolean  $create     Create the thumbnail when missing.
     *
     * @return  string  Relative thumbnail path, or the original image path on fallback.
     */
    static public function linkThumbnail($image, $maxWidth = 0, $maxHeight = 0, $create = true)
    {
        $image = trim((string) $image);

        if ($image === '') {
            return '';
        }

        if (strpos($image, '#') !== false) {
            $image = explode('#', $image, 2)[0];
        }

        if (preg_match('#^(?:https?:)?//#i', $image)) {
            return $image;
        }

        $image = ltrim($image, '/\\');
        $maxWidth = max(0, min((int) $maxWidth, 2000));
        $maxHeight = max(0, min((int) $maxHeight, 2000));

        if ($maxWidth < 1 && $maxHeight < 1) {
            return $image;
        }

        $sitePath = rtrim(Path::clean(JPATH_SITE), '\\/');
        $source = Path::clean(JPATH_SITE . '/' . $image);

        if (strpos(strtolower($source), strtolower($sitePath) . DIRECTORY_SEPARATOR) !== 0 || !is_file($source)) {
            return $image;
        }

        $extension = strtolower(File::getExt($image));
        $basename = File::makeSafe(pathinfo($image, PATHINFO_FILENAME));

        if ($extension === '' || $basename === '') {
            return $image;
        }

        $resource = JemImageResourcePolicy::inspect(
            $source,
            $extension,
            JemImageResourcePolicy::DEFAULT_MAX_DIMENSION,
            $maxWidth,
            $maxHeight
        );

        if (!$resource['accepted']) {
            return '';
        }

        $thumbName = sha1($image . '|' . $maxWidth . '|' . $maxHeight) . '-' . $basename . '.' . $extension;
        $thumbRelative = 'images/jem/links/small/' . $thumbName;
        $thumbFolder = Path::clean(JPATH_SITE . '/images/jem/links/small');
        $thumbPath = Path::clean(JPATH_SITE . '/' . $thumbRelative);

        if (!File::exists($thumbPath) && $create) {
            if (!Folder::exists($thumbFolder)) {
                Folder::create($thumbFolder);
            }

            JemImage::thumb($source, $thumbPath, $maxWidth, $maxHeight);
        }

        return File::exists($thumbPath) ? $thumbRelative : $image;
    }

    static public function thumb($name,$filename,$new_w,$new_h,$maxDimension = JemImageResourcePolicy::DEFAULT_MAX_DIMENSION)
    {
        $resource = JemImageResourcePolicy::inspect(
            (string) $name,
            strtolower(File::getExt((string) $name)),
            (int) $maxDimension,
            (int) $new_w,
            (int) $new_h
        );

        if (!$resource['accepted']) {
            return false;
        }

        // load the image manipulation class
        //require 'path/to/Zebra_Image.php';

        // create a new instance of the class
        $image = new \stefangabos\Zebra_Image\Zebra_Image();

        // indicate a source image (a GIF, PNG, JPEG or WEBP file)
        $image->source_path = $name;

        // indicate a target image
        // note that there's no extra property to set in order to specify the target
        // image's type -simply by writing '.jpg' as extension will instruct the script
        // to create a 'jpg' file
        $image->target_path = $filename;

        // since in this example we're going to have a jpeg file, let's set the output
        // image's quality (95% has no visible effect but saves some bytes)
        $image->jpeg_quality = 95;

        // some additional properties that can be set
        // read about them in the documentation
        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = false;
        $image->preserve_time = true;
        $image->auto_handle_exif_orientation = true;

        // resize the image to at best 100x100 pixels by using the "not boxed" method
        // (read more in the overview section or in the documentation)
        // and if there is an error, check what the error is about
        if (!$image->resize($new_w, $new_h, ZEBRA_IMAGE_NOT_BOXED, -1)) {

            //only admins will see these errors
            if (Factory::getApplication()->getIdentity()->authorise('core.manage')) {

                // if there was an error, let's see what the error is about
                switch ($image->error) {
                case 1:
                    Factory::getApplication()->enqueueMessage("Source file $name could not be found!", 'warning');
                    break;
                case 2:
                    Factory::getApplication()->enqueueMessage("Source file $name is not readable!", 'warning');
                    break;
                case 3:
                    Factory::getApplication()->enqueueMessage("Could not write target file $filename !", 'warning');
                    break;
                case 4:
                    Factory::getApplication()->enqueueMessage('Unsupported source file format!', 'warning');
                    break;
                case 5:
                    Factory::getApplication()->enqueueMessage('Unsupported target file format!', 'warning');
                    break;
                case 6:
                    Factory::getApplication()->enqueueMessage('GD library version does not support target file format!', 'warning');
                    break;
                case 7:
                    Factory::getApplication()->enqueueMessage('GD library is not installed!', 'warning');
                    break;
                case 8:
                    Factory::getApplication()->enqueueMessage('"chmod" command is disabled via configuration', 'warning');
                    break;
                case 9:
                    Factory::getApplication()->enqueueMessage('"exif_read_data" function is not available', 'warning');
                    break;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Validate, optionally normalise and publish a newly uploaded profile image.
     * The original and thumbnail become visible only after every processing step succeeds.
     */
    static public function uploadProfileImage($file, $target, $thumbnail, $jemsettings, $profile)
    {
        if (!JemImageProfilePolicy::isProfile((string) $profile)
            || JemImage::check($file, $jemsettings, $profile) === false) {
            return false;
        }

        $target = Path::clean((string) $target);
        $thumbnail = trim((string) $thumbnail) !== '' ? Path::clean((string) $thumbnail) : '';
        $targetFolder = dirname($target);

        if ((!Folder::exists($targetFolder) && !Folder::create($targetFolder))
            || ($thumbnail !== '' && !Folder::exists(dirname($thumbnail)) && !Folder::create(dirname($thumbnail)))) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'warning');

            return false;
        }

        $working = self::temporaryImagePath($target, 'upload');
        $thumbnailWorking = '';

        try {
            if (!File::upload((string) $file['tmp_name'], $working)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'warning');

                return false;
            }

            $analysis = self::analyseStoredImage($working, $jemsettings, (string) $profile, false);
            if (!$analysis['accepted'] || !self::prepareWorkingImage($working, $jemsettings, (string) $profile, $analysis)) {
                return false;
            }

            $maxBytes = max(0, (int) ($jemsettings->sizelimit ?? 0)) * 1024;
            $finalBytes = @filesize($working);
            if ($finalBytes === false || $finalBytes < 1 || ($maxBytes > 0 && $finalBytes > $maxBytes)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_FILE_SIZE'), 'warning');

                return false;
            }

            if (!self::validatePreparedImage($working, $jemsettings, (string) $profile)) {
                return false;
            }

            if ($thumbnail !== '' && (int) ($jemsettings->gddisabled ?? 0) === 1) {
                $thumbnailWorking = self::temporaryImagePath($thumbnail, 'thumb');
                if (!self::thumb(
                    $working,
                    $thumbnailWorking,
                    (int) ($jemsettings->imagewidth ?? 0),
                    (int) ($jemsettings->imagehight ?? 0),
                    JemImageProfilePolicy::displayMaxDimension($jemsettings)
                )) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_PROCESSING_FAILED'), 'warning');

                    return false;
                }
            }

            if (!File::move($working, $target)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'warning');

                return false;
            }
            $working = '';

            if ($thumbnailWorking !== '' && !File::move($thumbnailWorking, $thumbnail)) {
                File::delete($target);
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'warning');

                return false;
            }
            $thumbnailWorking = '';

            return true;
        } finally {
            if ($working !== '' && File::exists($working)) {
                File::delete($working);
            }
            if ($thumbnailWorking !== '' && File::exists($thumbnailWorking)) {
                File::delete($thumbnailWorking);
            }
        }
    }

    /**
     * Copy an existing image into another profile without modifying the source.
     */
    static public function copyForProfile($source, $target, $thumbnail, $jemsettings, $profile)
    {
        $source = Path::clean((string) $source);
        $target = Path::clean((string) $target);

        if (File::exists($target)) {
            return true;
        }

        if (!File::exists($source) || !JemImageProfilePolicy::isProfile((string) $profile)) {
            return false;
        }

        $working = self::temporaryImagePath($target, 'copy');

        try {
            if ((!Folder::exists(dirname($target)) && !Folder::create(dirname($target)))
                || !File::copy($source, $working)) {
                return false;
            }

            $analysis = self::analyseStoredImage($working, $jemsettings, (string) $profile, true);
            if (!$analysis['accepted'] || $analysis['minimum_not_met']
                || !self::prepareWorkingImage($working, $jemsettings, (string) $profile, $analysis)
                || !self::validatePreparedImage($working, $jemsettings, (string) $profile)) {
                return false;
            }

            return self::publishNormalisedImage($working, $target, (string) $thumbnail, $jemsettings);
        } finally {
            if (File::exists($working)) {
                File::delete($working);
            }
        }
    }

    /**
     * Analyse a stored image against one profile without modifying it.
     *
     * @return array{accepted: bool, reason: string, width: int, height: int, frames: int, minimum_not_met: bool, max_exceeded: bool, ratio_mismatch: bool, orientation: int, needs_normalisation: bool}
     */
    static public function analyseStoredImage($path, $jemsettings, $profile, $allowDimensionReduction = true)
    {
        $path = Path::clean((string) $path);
        $extension = strtolower(File::getExt($path));
        $inspectionLimit = $allowDimensionReduction
            ? JemImageResourcePolicy::MAX_CONFIGURED_DIMENSION
            : JemImageProfilePolicy::maxDimension($jemsettings);
        $resource = JemImageResourcePolicy::inspect(
            $path,
            $extension,
            $inspectionLimit,
            (int) ($jemsettings->imagewidth ?? 0),
            (int) ($jemsettings->imagehight ?? 0)
        );

        if (!$resource['accepted']) {
            return array(
                'accepted' => false,
                'reason' => $resource['reason'],
                'width' => (int) $resource['width'],
                'height' => (int) $resource['height'],
                'frames' => (int) $resource['frames'],
                'minimum_not_met' => false,
                'max_exceeded' => false,
                'ratio_mismatch' => false,
                'orientation' => 1,
                'needs_normalisation' => false,
            );
        }

        $orientation = self::imageOrientation($path, (int) $resource['type']);
        $width = (int) $resource['width'];
        $height = (int) $resource['height'];
        if (in_array($orientation, array(5, 6, 7, 8), true)) {
            $swap = $width;
            $width = $height;
            $height = $swap;
        }

        $config = JemImageProfilePolicy::resolve($jemsettings, (string) $profile);
        $minDimension = JemImageProfilePolicy::minDimension($jemsettings);
        $maxDimension = JemImageProfilePolicy::maxDimension($jemsettings);
        $minimumNotMet = $width < $minDimension || $height < $minDimension;
        $maxExceeded = $width > $maxDimension || $height > $maxDimension;
        $ratioMismatch = $config['mode'] !== JemImageProfilePolicy::MODE_NONE
            && !JemImageProfilePolicy::isExactRatio($width, $height, $config['ratio_width'], $config['ratio_height']);

        return array(
            'accepted' => true,
            'reason' => JemImageResourcePolicy::ACCEPTED,
            'width' => $width,
            'height' => $height,
            'frames' => (int) $resource['frames'],
            'minimum_not_met' => $minimumNotMet,
            'max_exceeded' => $maxExceeded,
            'ratio_mismatch' => $ratioMismatch,
            'orientation' => $orientation,
            'needs_normalisation' => $maxExceeded || $ratioMismatch || $orientation !== 1,
        );
    }

    /**
     * Normalise one stored original in place and rebuild its thumbnail atomically.
     */
    static public function normaliseStoredImage($source, $thumbnail, $jemsettings, $profile)
    {
        $source = Path::clean((string) $source);
        $analysis = self::analyseStoredImage($source, $jemsettings, (string) $profile, true);

        if (!$analysis['accepted'] || $analysis['minimum_not_met']) {
            return false;
        }

        if (!$analysis['needs_normalisation']) {
            return true;
        }

        $working = self::temporaryImagePath($source, 'normalise');

        try {
            if (!File::copy($source, $working)
                || !self::prepareWorkingImage($working, $jemsettings, (string) $profile, $analysis)
                || !self::validatePreparedImage($working, $jemsettings, (string) $profile)) {
                return false;
            }

            return self::replaceNormalisedImage($working, $source, (string) $thumbnail, $jemsettings);
        } finally {
            if (File::exists($working)) {
                File::delete($working);
            }
        }
    }

    static public function profileSummary($jemsettings, $profile)
    {
        $config = JemImageProfilePolicy::resolve($jemsettings, (string) $profile);
        $summary = Text::sprintf(
            'COM_JEM_IMAGE_UPLOAD_DIMENSION_INFO',
            JemImageProfilePolicy::minDimension($jemsettings),
            JemImageProfilePolicy::maxDimension($jemsettings)
        );

        if ($config['mode'] !== JemImageProfilePolicy::MODE_NONE) {
            $summary .= ' ' . Text::sprintf(
                'COM_JEM_IMAGE_UPLOAD_RATIO_INFO',
                $config['ratio_width'],
                $config['ratio_height'],
                Text::_('COM_JEM_IMAGE_ADJUSTMENT_' . strtoupper($config['mode']))
            );
        }

        return $summary;
    }

    private static function prepareWorkingImage($working, $jemsettings, $profile, array $analysis)
    {
        if (!$analysis['needs_normalisation']) {
            return true;
        }

        if ((int) $analysis['frames'] > 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_ANIMATED_ADJUSTMENT_UNSUPPORTED'), 'warning');

            return false;
        }

        $config = JemImageProfilePolicy::resolve($jemsettings, (string) $profile);
        $geometry = JemImageProfilePolicy::geometry(
            (int) $analysis['width'],
            (int) $analysis['height'],
            JemImageProfilePolicy::maxDimension($jemsettings),
            $config['mode'],
            $config['ratio_width'],
            $config['ratio_height']
        );
        $processed = self::temporaryImagePath((string) $working, 'processed');

        try {
            if (!self::transformImage((string) $working, $processed, $geometry)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_PROCESSING_FAILED'), 'warning');

                return false;
            }

            if (!File::delete((string) $working) || !File::move($processed, (string) $working)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_PROCESSING_FAILED'), 'warning');

                return false;
            }

            return true;
        } finally {
            if (File::exists($processed)) {
                File::delete($processed);
            }
        }
    }

    private static function transformImage($source, $target, array $geometry)
    {
        $image = new \stefangabos\Zebra_Image\Zebra_Image();
        $image->source_path = (string) $source;
        $image->target_path = (string) $target;
        $image->jpeg_quality = 95;
        $image->preserve_aspect_ratio = true;
        $image->enlarge_smaller_images = false;
        $image->preserve_time = true;
        $image->auto_handle_exif_orientation = function_exists('exif_read_data');

        $method = ZEBRA_IMAGE_NOT_BOXED;
        $background = -1;
        if ($geometry['method'] === 'pad') {
            $method = ZEBRA_IMAGE_BOXED;
            $background = '#000000';
        } elseif ($geometry['method'] === 'crop') {
            $method = ZEBRA_IMAGE_CROP_CENTER;
        }

        return $image->resize((int) $geometry['width'], (int) $geometry['height'], $method, $background);
    }

    private static function validatePreparedImage($path, $jemsettings, $profile)
    {
        $resource = JemImageResourcePolicy::inspect(
            (string) $path,
            strtolower(File::getExt((string) $path)),
            JemImageProfilePolicy::maxDimension($jemsettings),
            (int) ($jemsettings->imagewidth ?? 0),
            (int) ($jemsettings->imagehight ?? 0)
        );
        if (!$resource['accepted']) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_PROCESSING_FAILED'), 'warning');

            return false;
        }

        $minimum = JemImageProfilePolicy::minDimension($jemsettings);
        if ((int) $resource['width'] < $minimum || (int) $resource['height'] < $minimum) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_JEM_IMAGE_MIN_DIMENSION_NOT_MET', $minimum),
                'warning'
            );

            return false;
        }

        $config = JemImageProfilePolicy::resolve($jemsettings, (string) $profile);
        if ($config['mode'] !== JemImageProfilePolicy::MODE_NONE
            && !JemImageProfilePolicy::isExactRatio(
                (int) $resource['width'],
                (int) $resource['height'],
                $config['ratio_width'],
                $config['ratio_height']
            )) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_PROCESSING_FAILED'), 'warning');

            return false;
        }

        return true;
    }

    private static function publishNormalisedImage($working, $target, $thumbnail, $jemsettings)
    {
        $thumbnailWorking = '';

        try {
            if ($thumbnail !== '' && (int) ($jemsettings->gddisabled ?? 0) === 1) {
                if (!Folder::exists(dirname($thumbnail)) && !Folder::create(dirname($thumbnail))) {
                    return false;
                }
                $thumbnailWorking = self::temporaryImagePath($thumbnail, 'thumb');
                if (!self::thumb(
                    $working,
                    $thumbnailWorking,
                    (int) ($jemsettings->imagewidth ?? 0),
                    (int) ($jemsettings->imagehight ?? 0),
                    JemImageProfilePolicy::displayMaxDimension($jemsettings)
                )) {
                    return false;
                }
            }

            if (!File::move($working, $target)) {
                return false;
            }

            if ($thumbnailWorking !== '' && !File::move($thumbnailWorking, $thumbnail)) {
                File::delete($target);

                return false;
            }

            return true;
        } finally {
            if ($thumbnailWorking !== '' && File::exists($thumbnailWorking)) {
                File::delete($thumbnailWorking);
            }
        }
    }

    private static function replaceNormalisedImage($working, $source, $thumbnail, $jemsettings)
    {
        $sourceBackup = self::temporaryImagePath($source, 'backup');
        $thumbnailWorking = '';
        $thumbnailBackup = '';

        try {
            if ($thumbnail !== '' && (int) ($jemsettings->gddisabled ?? 0) === 1) {
                if (!Folder::exists(dirname($thumbnail)) && !Folder::create(dirname($thumbnail))) {
                    return false;
                }
                $thumbnailWorking = self::temporaryImagePath($thumbnail, 'thumb');
                if (!self::thumb(
                    $working,
                    $thumbnailWorking,
                    (int) ($jemsettings->imagewidth ?? 0),
                    (int) ($jemsettings->imagehight ?? 0),
                    JemImageProfilePolicy::displayMaxDimension($jemsettings)
                )) {
                    return false;
                }
            }

            if (!File::move($source, $sourceBackup)) {
                return false;
            }
            if ($thumbnail !== '' && File::exists($thumbnail)) {
                $thumbnailBackup = self::temporaryImagePath($thumbnail, 'backup');
                if (!File::move($thumbnail, $thumbnailBackup)) {
                    File::move($sourceBackup, $source);

                    return false;
                }
            }

            if (!File::move($working, $source)
                || ($thumbnailWorking !== '' && !File::move($thumbnailWorking, $thumbnail))) {
                if (File::exists($source)) {
                    File::delete($source);
                }
                File::move($sourceBackup, $source);
                if ($thumbnailBackup !== '') {
                    if (File::exists($thumbnail)) {
                        File::delete($thumbnail);
                    }
                    File::move($thumbnailBackup, $thumbnail);
                }

                return false;
            }

            File::delete($sourceBackup);
            if ($thumbnailBackup !== '') {
                File::delete($thumbnailBackup);
            }

            return true;
        } finally {
            foreach (array($sourceBackup, $thumbnailWorking, $thumbnailBackup) as $temporary) {
                if ($temporary !== '' && File::exists($temporary)) {
                    File::delete($temporary);
                }
            }
        }
    }

    private static function imageOrientation($path, $imageType)
    {
        if ($imageType !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data((string) $path, 'IFD0', true, false);
        $orientation = is_array($exif)
            ? (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1)
            : 1;

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private static function temporaryImagePath($target, $purpose)
    {
        $extension = strtolower(File::getExt((string) $target));
        $suffix = $extension !== '' ? '.' . $extension : '';

        return Path::clean(dirname((string) $target) . '/.jem-' . $purpose . '-' . bin2hex(random_bytes(8)) . $suffix);
    }

    /**
     * Determine the GD version
     * Code from php.net
     *
     * @param  int
     *
     * @return int
     */
    static public function gdVersion($user_ver = 0)
    {
        if (! extension_loaded('gd')) {
            return;
        }
        static $gd_ver = 0;

        // Just accept the specified setting if it's 1.
        if ($user_ver == 1) {
            $gd_ver = 1;
            return 1;
        }
        // Use the static variable if function was called previously.
        if ($user_ver != 2 && $gd_ver > 0) {
            return $gd_ver;
        }
        // Use the gd_info() function if possible.
        if (function_exists('gd_info')) {
            $ver_info = gd_info();
            preg_match('/\d/', $ver_info['GD Version'], $match);
            $gd_ver = $match[0];
            return $match[0];
        }
        $gd_ver = ($user_ver == 2) ? 2 : 1;

        return $gd_ver;
    }

    /**
     * Creates image information of an image
     *
     * @param  string $image The image name
     * @param  array $settings
     * @param  string $type event or venue
     *
     * @return imagedata if available
     */
    static public function flyercreator($image, $type, $folderPath = '')
    {
        $settings = JemHelper::config();

        if (($settings->imagewidth < 1) || ($settings->imagehight < 1)) {
            return false;
        }

        //define the environment based on the type
        if ($type == 'event') {
            $folder = 'events';
        } else if ($type == 'category') {
            $folder = 'categories';
        } else if ($type == 'venue') {
            $folder = 'venues';
        } else {
            return false;
        }

        if ($image) {
            $isSiteImagePath = strpos($image, '/') !== false || strpos($image, '\\') !== false;

            if (!$isSiteImagePath && $type === 'event') {
                $img_orig  = JemEventImagePath::imagePath($folderPath, $image);
                $img_thumb = JemEventImagePath::thumbPath($folderPath, $image);
            } else if (!$isSiteImagePath && $type === 'venue') {
                $img_orig  = JemVenueImagePath::imagePath($folderPath, $image);
                $img_thumb = JemVenueImagePath::thumbPath($folderPath, $image);
            } else {
                $img_orig  = $isSiteImagePath ? ltrim(str_replace('\\', '/', $image), '/') : 'images/jem/'.$folder.'/'.$image;
                $img_thumb = $isSiteImagePath ? $img_orig : 'images/jem/'.$folder.'/small/'.$image;
            }

            $filepath  = JPATH_SITE.'/'.$img_orig;
            $save      = JPATH_SITE.'/'.$img_thumb;

            // At least original image must exist
            if (!file_exists($filepath)) {
                return false;
            }

            $resource = JemImageResourcePolicy::inspect(
                $filepath,
                strtolower(File::getExt((string) $image)),
                JemImageProfilePolicy::displayMaxDimension($settings),
                (int) $settings->imagewidth,
                (int) $settings->imagehight
            );

            if (!$resource['accepted']) {
                return false;
            }

            //Create thumbnail if enabled and it does not exist already
            if (!$isSiteImagePath && $settings->gddisabled == 1 && !file_exists($save)) {
                $saveFolder = dirname($save);
                if (!Folder::exists($saveFolder)) {
                    Folder::create($saveFolder);
                }
                JemImage::thumb(
                    $filepath,
                    $save,
                    $settings->imagewidth,
                    $settings->imagehight,
                    JemImageProfilePolicy::displayMaxDimension($settings)
                );
            }

            //set paths
            $dimage['original'] = $img_orig;
            $dimage['thumb']    = $img_thumb;

            $iminfo = array($resource['width'], $resource['height']);

            //if the width or height is too large this formula will resize them accordingly
            if (($iminfo[0] > $settings->imagewidth) || ($iminfo[1] > $settings->imagehight)) {
                $iRatioW = $settings->imagewidth / $iminfo[0];
                $iRatioH = $settings->imagehight / $iminfo[1];

                if ($iRatioW < $iRatioH) {
                    $dimage['width']  = round($iminfo[0] * $iRatioW);
                    $dimage['height'] = round($iminfo[1] * $iRatioW);
                } else {
                    $dimage['width']  = round($iminfo[0] * $iRatioH);
                    $dimage['height'] = round($iminfo[1] * $iRatioH);
                }
            } else {
                $dimage['width']  = $iminfo[0];
                $dimage['height'] = $iminfo[1];
            }

            if (is_file(JPATH_SITE.'/'.$img_thumb)) {
                //get imagesize of the thumbnail
                $thumbiminfo = @getimagesize(JPATH_SITE.'/'.$img_thumb);

                // Set dimensions if the image information is successfully retrieved
                if (is_array($thumbiminfo)) {
                    $dimage['thumbwidth']  = $thumbiminfo[0];
                    $dimage['thumbheight'] = $thumbiminfo[1];
                } else {
                    $dimage['thumbwidth']  = 0;
                    $dimage['thumbheight'] = 0;
                }
            }

            return $dimage;
        }

        return false;
    }

    static public function check($file, $jemsettings, $profile = '')
    {
        $sizelimit = max(0, (int) ($jemsettings->sizelimit ?? 0)) * 1024; // size limit in KB
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $imagesize = $tmpName !== '' ? @filesize($tmpName) : false;
        $filetypes = ($jemsettings->image_filetypes ?? '') ?: 'jpg,gif,png,webp';
        $displayName = htmlspecialchars((string) ($file['name'] ?? ''), ENT_COMPAT, 'UTF-8');

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || $imagesize === false || $imagesize < 1) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED_NOT_AN_IMAGE').': '.$displayName, 'warning');
            return false;
        }

        // Trust the temporary file on disk, not the client-supplied size.
        if ($imagesize > $sizelimit) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_FILE_SIZE').': '.$displayName, 'warning');
            return false;
        }

        //check if the imagefiletype is valid
        $fileext = strtolower(File::getExt((string) ($file['name'] ?? '')));

        $allowable = explode(',', strtolower($filetypes));
        array_walk($allowable, function(&$v){$v = trim($v);});
        if (!in_array($fileext, $allowable, true)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_WRONG_IMAGE_FILE_TYPE').': '.$displayName, 'warning');
            return false;
        }

        $maxDimension = JemImageProfilePolicy::isProfile((string) $profile)
            ? JemImageProfilePolicy::maxDimension($jemsettings)
            : JemImageResourcePolicy::DEFAULT_MAX_DIMENSION;
        $resource = JemImageResourcePolicy::inspect(
            $tmpName,
            $fileext,
            $maxDimension,
            (int) ($jemsettings->imagewidth ?? 0),
            (int) ($jemsettings->imagehight ?? 0)
        );

        if (!$resource['accepted']) {
            if ($resource['reason'] === JemImageResourcePolicy::FORMAT_MISMATCH) {
                $message = Text::_('COM_JEM_WRONG_IMAGE_FILE_TYPE');
            } elseif ($resource['reason'] === JemImageResourcePolicy::NOT_IMAGE) {
                $message = Text::_('COM_JEM_UPLOAD_FAILED_NOT_AN_IMAGE');
            } elseif ($resource['reason'] === JemImageResourcePolicy::DIMENSIONS_EXCEEDED) {
                $message = Text::sprintf('COM_JEM_IMAGE_MAX_DIMENSION_EXCEEDED', $maxDimension);
            } else {
                $message = Text::_('COM_JEM_IMAGE_RESOURCE_LIMIT');
            }

            Factory::getApplication()->enqueueMessage($message.': '.$displayName, 'warning');
            return false;
        }

        if (JemImageProfilePolicy::isProfile((string) $profile)) {
            $minDimension = JemImageProfilePolicy::minDimension($jemsettings);
            if ((int) $resource['width'] < $minDimension || (int) $resource['height'] < $minDimension) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('COM_JEM_IMAGE_MIN_DIMENSION_NOT_MET', $minDimension) . ': ' . $displayName,
                    'warning'
                );

                return false;
            }
        }

        //XSS check
        //$xss_check = File::read($file['tmp_name'], false, 256);
        $xss_check = file_get_contents($tmpName, false, NULL, 0, 256);
        if ($xss_check === false) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED_NOT_AN_IMAGE').': '.$displayName, 'warning');
            return false;
        }
        $html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont','bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption','center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt','em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head','hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend','li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes','noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp','script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table','tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
        foreach ($html_tags as $tag) {
            // A tag is '<tagname ', so we need to add < and a space or '<tagname>'
            if (stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>')) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_WARN_IE_XSS'), 'warning');
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize the image file name and return an unique string
     *
     *
     * @param  string $base_Dir the target directory
     * @param  string $filename the unsanitized imagefile name
     *
     * @return string $filename the sanitized and unique image file name
     */
    static public function sanitize($base_Dir, $filename)
    {
        //check for any leading/trailing dots and remove them (trailing shouldn't be possible cause of the getEXT check)
        $filename = preg_replace("/^[.]*/", '', $filename);
        $filename = preg_replace("/[.]*$/", '', $filename); //shouldn't be necessary, see above

        //we need to save the last dot position cause preg_replace will also replace dots
        $lastdotpos = strrpos($filename, '.');

        //replace invalid characters
        $filename = strtolower(preg_replace("/[^0-9a-zA-Z_-]/", '_', $filename));

        //get the parts before and after the dot (assuming we have an extension...check was done before)
        $beforedot    = substr($filename, 0, $lastdotpos);
        $afterdot     = substr($filename, $lastdotpos + 1);

        //make a unique filename for the image and check it is not already taken
        //if it is already taken keep trying till success
        //$now = time();

        do {
            $now = bin2hex(random_bytes(8));
        } while (is_file($base_Dir . $beforedot . '_' . $now . '.' . $afterdot));

        //create out of the seperated parts the new filename
        $filename = $beforedot . '_' . $now . '.' . $afterdot;

        return $filename;
    }
}
?>
