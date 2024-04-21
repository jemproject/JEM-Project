<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;

require_once(JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');

/**
 * Holds the logic for image manipulation
 *
 * @package JEM
 */
class JemImage
{
	static public function thumb($name,$filename,$new_w,$new_h)
	{
		// load the image manipulation class
		//require 'path/to/Zebra_Image.php';

		// create a new instance of the class
		$image = new Zebra_Image();

		// indicate a source image (a GIF, PNG or JPEG file)
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
		}
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
		// If phpinfo() is disabled use a specified / fail-safe choice...
		if (preg_match('/phpinfo/', ini_get('disable_functions'))) {
			if ($user_ver == 2) {
				$gd_ver = 2;
				return 2;
			} else {
				$gd_ver = 1;
				return 1;
			}
		}
		// ...otherwise use phpinfo().
		ob_start();
		phpinfo(8);
		$info = ob_get_contents();
		ob_end_clean();
		$info = stristr($info, 'gd version');
		preg_match('/\d/', $info, $match);
		$gd_ver = $match[0];

		return $match[0];
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
	static public function flyercreator($image, $type)
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
			$img_orig  = 'images/jem/'.$folder.'/'.$image;
			$img_thumb = 'images/jem/'.$folder.'/small/'.$image;

			$filepath  = JPATH_SITE.'/'.$img_orig;
			$save      = JPATH_SITE.'/'.$img_thumb;

			// At least original image must exist
			if (!file_exists($filepath)) {
				return false;
			}

			//Create thumbnail if enabled and it does not exist already
			if ($settings->gddisabled == 1 && !file_exists($save)) {
				JemImage::thumb($filepath, $save, $settings->imagewidth, $settings->imagehight);
			}

			//set paths
			$dimage['original'] = $img_orig;
			$dimage['thumb']    = $img_thumb;

			//get imagesize of the original
			$iminfo = @getimagesize($img_orig);

			// and it should be an image
			if (!is_array($iminfo) || count($iminfo) < 2) {
				return false;
			}

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

			if (File::exists(JPATH_SITE.'/'.$img_thumb)) {
				//get imagesize of the thumbnail
				$thumbiminfo = @getimagesize($img_thumb);
				$dimage['thumbwidth']  = $thumbiminfo[0];
				$dimage['thumbheight'] = $thumbiminfo[1];
			}

			return $dimage;
		}

		return false;
	}

	static public function check($file, $jemsettings)
	{
		$sizelimit = $jemsettings->sizelimit*1024; //size limit in kb
		$imagesize = $file['size'];
		$filetypes = $jemsettings->image_filetypes ?: 'jpg,gif,png,webp';

		//check if the upload is an image...getimagesize will return false if not
		if (!getimagesize($file['tmp_name'])) {
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED_NOT_AN_IMAGE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'), 'warning');
			return false;
		}

		//check if the imagefiletype is valid
		$fileext = strtolower(File::getExt($file['name']));

		$allowable = explode(',', strtolower($filetypes));
		array_walk($allowable, function(&$v){$v = trim($v);});
		if (!in_array($fileext, $allowable)) {
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_WRONG_IMAGE_FILE_TYPE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'), 'warning');
			return false;
		}

		//Check filesize
		if ($imagesize > $sizelimit) {
			Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_FILE_SIZE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'), 'warning');
			return false;
		}

		//XSS check
		//$xss_check = File::read($file['tmp_name'], false, 256);
		$xss_check = file_get_contents($file['tmp_name'], false, NULL, 0, 256);
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
		$beforedot	= substr($filename, 0, $lastdotpos);
		$afterdot 	= substr($filename, $lastdotpos + 1);

		//make a unique filename for the image and check it is not already taken
		//if it is already taken keep trying till success
		//$now = time();

		$now = rand();

		while (File::exists($base_Dir . $beforedot . '_' . $now . '.' . $afterdot)) {
			$now++;
		}

		//create out of the seperated parts the new filename
		$filename = $beforedot . '_' . $now . '.' . $afterdot;

		return $filename;
	}
}
?>
