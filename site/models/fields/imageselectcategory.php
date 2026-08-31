<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Folder;

require_once __DIR__ . '/imageselectevent.php';

/**
 * Searchable selector for images stored in the JEM category image folder.
 */
class JFormFieldImageselectcategory extends JFormFieldImageselectevent
{
    protected $type = 'Imageselectcategory';

    protected function getOptions()
    {
        $options = array(
            HTMLHelper::_('select.option', '', Text::_('COM_JEM_NO_IMAGE')),
        );
        $path = JPATH_SITE . '/images/jem/categories';
        $this->imageBaseUrl = rtrim(Uri::root(), '/') . '/images/jem/categories/';
        $this->imageRelativePath = 'images/jem/categories';

        if (!is_dir($path)) {
            return $options;
        }

        $images = Folder::files($path, '\.(jpg|jpeg|png|gif|webp)$', false, false, array('index.html'));
        natcasesort($images);

        foreach ($images as $image) {
            $options[] = HTMLHelper::_('select.option', $image, $image);
        }

        return $options;
    }
}
