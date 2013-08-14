<?php
// No direct access
 defined('_JEXEC') or die('Restricted access');
 
 jimport('joomla.form.formfield');
 
 /**
  * Book form field class
  */
 class JFormFieldModal_Contact extends JFormField
 {
        /**
         * field type
         * @var string
         */
        protected $type = 'Modal_Contact';


        
        /*
        $linkcsel = 'index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component';
        
        // build venue select js and load the view
        $js = "
		function elSelectContact(id, contactid) {
			document.getElementById('a_id2').value = id;
			document.getElementById('a_name2').value = contactid;
			window.parent.SqueezeBox.close();
		}";
        
        $document->addScriptDeclaration($js);
        
        $contactselect = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name2\" value=\"$row->contactname\" disabled=\"disabled\" /></div>";
        $contactselect .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECT')."\" href=\"$linkcsel\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECT')."</a></div></div>\n";
        $contactselect .= "\n<input type=\"hidden\" id=\"a_id2\" name=\"contactid\" value=\"$row->contactid\" />";
        $contactselect .= "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"elSelectContact(0, '".JText::_('COM_JEM_NO_CONTACT')."' );\" value=\"".JText::_('COM_JEM_NO_CONTACT')."\" onblur=\"seo_switch()\" />";
        */
        
        
        
        
        
 	/**
   * Method to get the field input markup
   */
  protected function getInput()
  {
          // Load modal behavior
          JHtml::_('behavior.modal', 'a.modal');
 
          // Build the script
          $script = array();
          $script[] = '    function jSelectContact_'.$this->id.'(id, name, object) {';
          $script[] = '        document.id("'.$this->id.'_id").value = id;';
          $script[] = '        document.id("'.$this->id.'_name").value = name;';
          $script[] = '        SqueezeBox.close();';
          $script[] = '    }';
 
          // Add to document head
          JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));
 
          // Setup variables for display
          $html = array();
          
          $link = 'index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component&amp;function=jSelectContact_'.$this->id;
          
          
     
 
          $db = JFactory::getDbo();
          $query = $db->getQuery(true);
          $query->select('name');
          $query->from('#__contact_details');
          $query->where('id='.(int)$this->value);
          $db->setQuery($query);
          
          
          $contactid = $db->loadResult();
          
          if ($error = $db->getErrorMsg()) {
          	JError::raiseWarning(500, $error);
          }
          
  
          
          if (empty($contactid)) {
                  $contactid = JText::_('COM_JEM_SELECTCONTACT');
          }
          $contactid = htmlspecialchars($contactid, ENT_QUOTES, 'UTF-8');
 
          // The current book input field
          $html[] = '<div class="fltlft">';
          $html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$contactid.'" disabled="disabled" size="35" />';
          $html[] = '</div>';
 
          // The book select button
          $html[] = '<div class="button2-left">';
          $html[] = '  <div class="blank">';
          $html[] = '    <a class="modal" title="'.JText::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
                         JText::_('COM_JEM_SELECT').'</a>';
          $html[] = '  </div>';
          $html[] = '</div>';
 
         // The active book id field
          if (0 == (int)$this->value) {
                  $value = '';
          } else {
                  $value = (int)$this->value;
          }

          // class='required' for client side validation
          $class = '';
          if ($this->required) {
                  $class = ' class="required modal-value"';
          }
 
          $html[] = '<input type="hidden" id="'.$this->id.'_id"'.$class.' name="'.$this->name.'" value="'.$value.'" />';
 
         return implode("\n", $html);
  }



















 
 }
 ?>