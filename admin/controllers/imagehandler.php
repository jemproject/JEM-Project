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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Session\Session;


/**
 * JEM Component Imagehandler Controller
 *
 * @package JEM
 *
 */
class JemControllerImagehandler extends BaseController
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('eventimgup', 'uploadimage');
		$this->registerTask('venueimgup', 'uploadimage');
		$this->registerTask('categoriesimgup', 'uploadimage');
	}

	/**
	 * logic for uploading an image
	 *
	 * @access public
	 * @return void
	 */
	public function uploadimage()
	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid token');

		$app = Factory::getApplication();
		$jemsettings = JemAdmin::config();

		$file = Factory::getApplication()->input->files->get('userfile', array(), 'array');
		$task = Factory::getApplication()->input->get('task', '');

		// Set FTP credentials, if given

		ClientHelper::setCredentialsFromRequest('ftp');

		//set the target directory
		if ($task == 'venueimgup') {
			$base_Dir = JPATH_SITE.'/images/jem/venues/';
		} else if ($task == 'eventimgup') {
			$base_Dir = JPATH_SITE.'/images/jem/events/';
		} else if ($task == 'categoriesimgup') {
			$base_Dir = JPATH_SITE.'/images/jem/categories/';
		}

		//do we have an upload?
		if (empty($file['name'])) {
			echo "<script> alert('".Text::_('COM_JEM_IMAGE_EMPTY')."'); window.history.go(-1); </script>\n";
			$app->close();
		}

		//check the image
		$check = JemImage::check($file, $jemsettings);

		if ($check === false) {
			$app->redirect($_SERVER['HTTP_REFERER']);
		}

		//sanitize the image filename
		$filename = JemImage::sanitize($base_Dir, $file['name']);
		$filepath = $base_Dir . $filename;

		//upload the image
		if (!File::upload($file['tmp_name'], $filepath)) {
			echo "<script> alert('".Text::_('COM_JEM_UPLOAD_FAILED')."'); </script>\n";
			$app->close();
		} else {
			echo "<script> alert('".Text::_('COM_JEM_UPLOAD_COMPLETE')."'); window.parent.SelectImage('$filename', '$filename'); </script>\n";
			$app->close();
		}
	}

	/**
	 * logic to mass delete images
	 *
	 * @access public
	 * @return void
	 */
	public function delete()
	{
		// Check for request forgeries
		Session::checkToken('get') or jexit('Invalid Token');

		$app = Factory::getApplication();

		// Set FTP credentials, if given
		ClientHelper::setCredentialsFromRequest('ftp');

		// Get some data from the request
		$images = Factory::getApplication()->input->get('rm', array(), 'array');
		$folder = Factory::getApplication()->input->get('folder', '');

		if (count($images)) {
			foreach ($images as $image) {
				if ($image !== InputFilter::getInstance()->clean($image, 'path')) {
					Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'), 'warning');
					continue;
				}

				$fullPath = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$image);
				$fullPaththumb = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image);
				if (is_file($fullPath)) {
					File::delete($fullPath);
					if (File::exists($fullPaththumb)) {
						File::delete($fullPaththumb);
					}
				}
			}
		}

		if ($folder == 'events') {
			$task = 'selecteventimg';
		} else if ($folder == 'venues') {
			$task = 'selectvenueimg';
		} else if ($folder == 'categories') {
			$task = 'selectcategoriesimg';
		}

		$app->redirect('index.php?option=com_jem&view=imagehandler&task='.$task.'&tmpl=component');
	}

}
?>
