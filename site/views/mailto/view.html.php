<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
/**
 * mailto-View
 */
class JemViewMailto extends HtmlView
{

    public $form = null;
    public $canDo;

    /**
     * Display the Hello World view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $jemsettings = JemHelper::config();
        $settings    = JemHelper::globalattribs();
        $app         = Factory::getApplication();
        JemHelper::setNoStoreHeaders();
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

        if (!JemMailtoHelper::canCurrentUserSend($app)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $user        = JemFactory::getUser();
        $userId      = $user->get('id');
        $document    = $app->getDocument();
        $model       = $this->getModel();
        $menu        = $app->getMenu();
        $menuitem    = $menu->getActive();
        $pathway     = $app->getPathway();
        $uri         = Uri::getInstance();

        $this->state = $this->get('State');
        $this->params = $this->state->get('params');
        $link = trim($app->input->getString('link', ''));
        $resolvedLink = JemMailtoHelper::validateHash($link);

        if (!$resolvedLink || !Uri::isInternal($resolvedLink)) {
            throw new Exception(Text::_('COM_JEM_MAILTO_LINK_IS_MISSING'), 400);
        }

        $this->link = $link;

        $layout = $app->input->get('layout', 'edit');

        $params = $this->params;
        $this->pageclass_sfx = $params->get('pageclass_sfx');
        // Get the form to display
        $this->form = $this->get('Form');


        $title = Text::_('COM_JEM_MAILTO_EMAIL_TO_A_FRIEND');

        $params->def('page_title', $title);
        $params->def('page_heading', $title);

        // Check for errors.
        if (count($errors = $this->get('Errors')))
        {
            throw new Exception(implode("\n", $errors), 500);
        }

        $this->setLayout($layout);
        // Call the parent display to display the layout file
        parent::display($tpl);

        // Set properties of the html document
        $this->_prepareDocument();
    }

    /**
     * Method to set up the html document properties
     *
     * @return void
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();

        $title = $this->params->get('page_title');
        if ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        }
        elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }
        $this->document->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        $this->document->setMetadata('robots', 'noindex, nofollow');
    }
}
?>
