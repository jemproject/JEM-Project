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

/**
 * Mailto model class.
 *
 * @since  3.8.9
 */
class JemModelMailto extends JModelForm
{
	/**
	 * Method to get the mailto form.
	 *
	 * The base form is loaded from XML and then an event is fired
	 * for users plugins to extend the form with extra fields.
	 *
	 * @param   array    $data      An optional array of data for the form to interrogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  JForm	A JForm object on success, false on failure
	 *
	 * @since   3.8.9
	 */

    protected function populateState()
    {
        $app = Factory::getApplication();
        $params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('layout', $app->input->getCmd('layout', ''));
    }
	public function getForm($data = array(), $loadData = true)
	{
        
		// Get the form.
		$form = $this->loadForm('com_jem.mailto', 'mailto', array('load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  array  The default data is an empty array.
	 *
	 * @since   3.8.9
	 */
   
	protected function loadFormData()
	{
        $app  = Factory::getApplication();
		$user = $app->getIdentity();
      
		$data = $app->getUserState('jem.mailto.form.data', array());
		
		$data['link'] = urldecode($app->input->get('link', '', 'BASE64'));

		if ($data['link'] == '')
		{
			// JError::raiseError(403, Text::_('COM_JEM_MAILTO_LINK_IS_MISSING'));
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_MAILTO_LINK_IS_MISSING'), 'error');

			return false;
		}

		// Load with previous data, if it exists
		$data['sender']    = $app->input->post->getString('sender', '');
		$data['subject']   = $app->input->post->getString('subject', '');
		$data['emailfrom'] = JStringPunycode::emailToPunycode($app->input->post->getString('emailfrom', ''));
		$data['emailto']   = JStringPunycode::emailToPunycode($app->input->post->getString('emailto', ''));

		if (!$user->guest)
		{
			$data['sender']    = $user->name;
			$data['emailfrom'] = $user->email;
		}
        
		$app->setUserState('jem.mailto.form.data', $data);

		$this->preprocessData('com_jem.mailto', $data);

		return $data;
	}

	/**
	 * Get the request data
	 *
	 * @return  array  The requested data
	 *
	 * @since   3.8.9
	 */
	public function getData()
	{
		$input = Factory::getApplication()->input;

		$data['emailto']    = $input->get('emailto', '', 'string');
		$data['sender']     = $input->get('sender', '', 'string');
		$data['emailfrom']  = $input->get('emailfrom', '', 'string');
		$data['subject']    = $input->get('subject', '', 'string');
		$data['consentbox'] = $input->get('consentbox', '', 'string');

		return $data;
	}

 
}
