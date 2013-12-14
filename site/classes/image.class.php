<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Holds the logic for image manipulation
 *
 * @package JEM
 */
class JEMImage {

	static function thumb($name,$filename,$new_w,$new_h)
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
		// image's quality
		$image->jpeg_quality = 100;

		// some additional properties that can be set
		// read about them in the documentation
		$image->preserve_aspect_ratio = true;
		$image->enlarge_smaller_images = true;
		$image->preserve_time = true;

		// resize the image to exactly 100x100 pixels by using the "crop from center" method
		// (read more in the overview section or in the documentation)
		// and if there is an error, check what the error is about
		if (!$image->resize($new_w, $new_h, ZEBRA_IMAGE_CROP_CENTER, -1)) {

			//only admins will see these errors
			if (JFactory::getUser()->authorise('core.manage')) {
			
			
			// if there was an error, let's see what the error is about
			switch ($image->error) {
				case 1:
					echo 'Source file could not be found!';
					break;
				case 2:
					echo 'Source file is not readable!';
					break;
				case 3:
					echo 'Could not write target file!';
					break;
				case 4:
					echo 'Unsupported source file format!';
					break;
				case 5:
					echo 'Unsupported target file format!';
					break;
				case 6:
					echo 'GD library version does not support target file format!';
					break;
				case 7:
					echo 'GD library is not installed!';
					break;
			}
			
			}

			// if no errors
		} else {
			echo '';
		}
	}

	/**
	 * Determine the GD version
	 * Code from php.net
	 *
	 *
	 * @param int
	 *
	 * @return int
	 */
	static function gdVersion($user_ver = 0) {
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
	 * @param string $image The image name
	 * @param array $settings
	 * @param string $type event or venue
	 *
	 * @return imagedata if available
	 */
	static function flyercreator($image, $type) {
		$settings = JEMHelper::config();

		jimport('joomla.filesystem.file');

		//define the environment based on the type
		if ($type == 'event') {
			$folder = 'events';
		} else if ($type == 'category') {
			$folder = 'categories';
		} else if ($type == 'venue') {
			$folder = 'venues';
		}

		if ($image) {
			//Create thumbnail if enabled and it does not exist already
			if ($settings->gddisabled == 1 && !file_exists(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image)) {

				$filepath 	= JPATH_SITE.'/images/jem/'.$folder.'/'.$image;
				$save 		= JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image;

				JEMImage::thumb($filepath, $save, $settings->imagewidth, $settings->imagehight);
			}

			//set paths
			$dimage['original'] = 'images/jem/'.$folder.'/'.$image;
			$dimage['thumb'] 	= 'images/jem/'.$folder.'/small/'.$image;

			//TODO: What is "limage" and "cimage" for?
			//set paths
			$limage['original'] = 'images/jem/'.$folder.'/'.$image;
			$limage['thumb'] 	= 'images/jem/'.$folder.'/small/'.$image;

			//set paths
			$cimage['original'] = 'images/jem/'.$folder.'/'.$image;
			$cimage['thumb'] 	= 'images/jem/'.$folder.'/small/'.$image;

			//get imagesize of the original
			$iminfo = @getimagesize('images/jem/'.$folder.'/'.$image);

			//if the width or height is too large this formula will resize them accordingly
			if (($iminfo[0] > $settings->imagewidth) || ($iminfo[1] > $settings->imagehight)) {

				$iRatioW = $settings->imagewidth / $iminfo[0];
				$iRatioH = $settings->imagehight / $iminfo[1];

				if ($iRatioW < $iRatioH) {
					$dimage['width'] 	= round($iminfo[0] * $iRatioW);
					$dimage['height'] 	= round($iminfo[1] * $iRatioW);
					$limage['width'] 	= round($iminfo[0] * $iRatioW);
					$limage['height'] 	= round($iminfo[1] * $iRatioW);
					$cimage['width'] 	= round($iminfo[0] * $iRatioW);
					$cimage['height'] 	= round($iminfo[1] * $iRatioW);
				} else {
					$dimage['width'] 	= round($iminfo[0] * $iRatioH);
					$dimage['height'] 	= round($iminfo[1] * $iRatioH);
					$limage['width'] 	= round($iminfo[0] * $iRatioH);
					$limage['height'] 	= round($iminfo[1] * $iRatioH);
					$cimage['width'] 	= round($iminfo[0] * $iRatioH);
					$cimage['height'] 	= round($iminfo[1] * $iRatioH);
				}
			} else {
				$dimage['width'] 	= $iminfo[0];
				$dimage['height'] 	= $iminfo[1];
				$limage['width'] 	= $iminfo[0];
				$limage['height'] 	= $iminfo[1];
				$cimage['width'] 	= $iminfo[0];
				$cimage['height'] 	= $iminfo[1];
			}

			if (JFile::exists(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image)) {
				//get imagesize of the thumbnail
				$thumbiminfo = @getimagesize('images/jem/'.$folder.'/small/'.$image);
				$dimage['thumbwidth'] 	= $thumbiminfo[0];
				$dimage['thumbheight'] 	= $thumbiminfo[1];
				$limage['thumbwidth'] 	= $thumbiminfo[0];
				$limage['thumbheight'] 	= $thumbiminfo[1];
				$cimage['thumbwidth'] 	= $thumbiminfo[0];
				$cimage['thumbheight'] 	= $thumbiminfo[1];
			}
			return $dimage;
			return $limage;
			return $cimage;
		}
		return false;
	}

	static function check($file, $jemsettings) {
		jimport('joomla.filesystem.file');

		$sizelimit = $jemsettings->sizelimit*1024; //size limit in kb
		$imagesize = $file['size'];

		//check if the upload is an image...getimagesize will return false if not
		if (!getimagesize($file['tmp_name'])) {
			JError::raiseWarning(100, JText::_('COM_JEM_UPLOAD_FAILED_NOT_AN_IMAGE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'));
			return false;
		}

		//check if the imagefiletype is valid
		$fileext = strtolower(JFile::getExt($file['name']));

		$allowable = array ('gif', 'jpg', 'png');
		if (!in_array($fileext, $allowable)) {
			JError::raiseWarning(100, JText::_('COM_JEM_WRONG_IMAGE_FILE_TYPE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'));
			return false;
		}

		//Check filesize
		if ($imagesize > $sizelimit) {
			JError::raiseWarning(100, JText::_('COM_JEM_IMAGE_FILE_SIZE').': '.htmlspecialchars($file['name'], ENT_COMPAT, 'UTF-8'));
			return false;
		}

		//XSS check
		$xss_check = JFile::read($file['tmp_name'], false, 256);
		$html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont','bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption','center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt','em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head','hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend','li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes','noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp','script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table','tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
		foreach($html_tags as $tag) {
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if(stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>')) {
				JError::raiseWarning(100, JText::_('COM_JEM_WARN_IE_XSS'));
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize the image file name and return an unique string
	 *
	 *
	 * @param string $base_Dir the target directory
	 * @param string $filename the unsanitized imagefile name
	 *
	 * @return string $filename the sanitized and unique image file name
	 */
	static function sanitize($base_Dir, $filename) {
		jimport('joomla.filesystem.file');

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

		
		while(JFile::exists($base_Dir . $beforedot . '_' . $now . '.' . $afterdot)) {
			$now++;
		}

		//create out of the seperated parts the new filename
		$filename = $beforedot . '_' . $now . '.' . $afterdot;

		return $filename;
	}
}
?>
