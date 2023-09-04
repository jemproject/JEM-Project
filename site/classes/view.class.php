<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * JemView class with JEM specific extensions
 *
 * @package JEM
 */
class JemView extends HtmlView
{
	/**
	 * Layout style suffix
	 *
	 * @var    string
	 * @since  2.3
	 */
	protected $_layoutStyleSuffix = null;

	public function __construct($config = array())
	{
		parent::__construct($config);

		// additional path for layout style + corresponding override path
		$suffix = JemHelper::getLayoutStyleSuffix();
		if (!empty($suffix)) {
			$this->_layoutStyleSuffix = $suffix;
			if (is_dir($this->_basePath . '/view')) {
				$this->addTempltePath($this->_basePath . '/view/' . $this->getName() . '/tmpl/' . $suffix);
			}
			else {
				$this->addTemplatePath($this->_basePath . '/views/' . $this->getName() . '/tmpl/' . $suffix);
			}
			$this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/' . $this->getName() . '/' . $suffix);
		}
	}

	/**
	 * Adds a row to data indicating even/odd row number
	 *
	 * @return object $rows
	 */
	public function getRows($rowname = "rows")
	{
		if (!isset($this->$rowname) || !is_array($this->$rowname) || !count($this->$rowname)) {
			return;
		}

		$k = 0;
		foreach($this->$rowname as $row) {
			$row->odd = $k;
			$k = 1 - $k;
		}

		return $this->$rowname;
	}

	/**
	 * Add path for common templates.
	 */
	protected function addCommonTemplatePath()
	{
		// additional path for list part + corresponding override path
		$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');
		$this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/common');

		if (!empty($this->_layoutStyleSuffix)) {
			$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl/'.$this->_layoutStyleSuffix);
			$this->addTemplatePath(JPATH_THEMES . '/' . Factory::getApplication()->getTemplate() . '/html/com_jem/common/'.$this->_layoutStyleSuffix);
		}
	}

	/**
	 * Prepares the document.
	 */
	protected function prepareDocument()
	{
		$app   = Factory::getApplication();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();
		$print = $app->input->getBool('print', false);

		if ($print) {
			JemHelper::loadCss('print');
			$this->document->setMetaData('robots', 'noindex, nofollow');
		}

		if ($menu) {
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		} else {
			// TODO
			$this->params->def('page_heading', Text::_('COM_JEM_DEFAULT_PAGE_TITLE_DAY'));
		}

		$title = $this->params->get('page_title', '');

		if (empty($title)) {
			$title = $app->get('sitename');
		} elseif ($app->get('sitename_pagetitles', 0) == 1) {
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		} elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}
		$this->document->setTitle($title);

		// TODO: Metadata
		$this->document->setMetadata('keywords', $this->params->get('page_title'));
	}
}
