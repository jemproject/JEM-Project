<?php

use AcyMailing\Libraries\acymPlugin;

// The class name must be "plgAcym" followed by the add-on's folder name with an uppercase at the beginning
// Any other uppercase character in the class name will prevent the good execution
class plgAcymExample extends acymPlugin
{
    /**
     * This method is mandatory
     */
    public function __construct()
    {
        parent::__construct();
        // Joomla, WordPress or all for an add-on that can work on both CMSs, don't forget the uppercase characters
        $this->cms = 'all';

        // Title displayed on the tab in the dynamic texts popup or the dynamic content insertion button
        $this->pluginDescription->name = 'Jem';
        // This is optional, if you set it, a tooltip text will be shown when hovering the button
        $this->pluginDescription->title = 'Insert event content';
        // Used on our add-ons listing to filter them, you can leave this value as it should not be used for you.
        // Possible values are "Content management", "User management", "Subscription system", "Events management", "E-commerce solutions" and "Files management"
        $this->pluginDescription->category = 'Event management';
        // What your custom add-on does
        $this->pluginDescription->description = '- Insert something in emails through the editor';
        // Path to the icon displayed on the button. It can be a svg, png, gif, jpg, etc... file
        $this->pluginDescription->icon = ACYM_DYNAMICS_URL.basename(__DIR__).'/jem.svg';
        /*
        If this is an integration with a WP plugin or a Joomla extension, you should make sure the integrated extension is installed on the site to avoid any issue.
        If it's not the case, set $this->installed = false; to hide your plugin's interface

        For WordPress:
        $this->installed = acym_isExtensionActive('the_wp_plugin/the_wp_plugin.php');

        For Joomla:

        $this->installed = acym_isExtensionActive('com_theextension');
     */

        //Add settings to your plugin
        $this->settings = [
            'key' => [
                'type' => 'checkbox',
                'label' => 'Hello',
                'value' => 1,
            ],
            'key1' => [
                'type' => 'switch',
                'label' => 'Switch',
                'value' => 1,
            ],
            'key2' => [
                'type' => 'select',
                'label' => 'Select',
                'value' => 1,
                'data' => ['select 1', 'select 2', 'select 3'],
            ],
            'key3' => [
                'type' => 'multiple_select',
                'label' => 'Select Multiple',
                'value' => [0, 2],
                'data' => ['select 1', 'select 2', 'select 3'],
            ],
            'key4' => [
                'type' => 'text',
                'label' => 'Text',
                'value' => '',
            ],
            'key5' => [
                'type' => 'radio',
                'label' => 'Radio',
                'value' => 1,
                'data' => ['radio 1', 'radio 2', 'radio 3'],
            ],
            'key6' => [
                'type' => 'custom',
                'content' => '<button class="cell button">Button Example</button>',
            ],
            'key7' => [
                'type' => 'date',
                'label' => 'Date field',
                'value' => '',
            ],
            'custom_view' => [
                'type' => 'custom_view',
                'tags' => [
                    'title' => ['ACYM_TITLE'],
                    'desc' => ['ACYM_DESCRIPTION'],
                    'readmore' => ['ACYM_READ_MORE'],
                ],
            ],
        ];
    }


    /* * * * * * * * *
     * Dynamic texts *
     * * * * * * * * */

    /**
     * This method is optional, it is used to declare a new dynamic text tab in the editor when editing a text
     */
    public function dynamicText($mailId)
    {
        return $this->pluginDescription;
    }

    /**
     * This method is mandatory if you declare a new dynamic text. It is used to display the options and interface of your dynamic text's tab, in the popup for dynamic text
     * insertion
     */
    public function textPopup()
    {
        $text = '<div class="acym__popup__listing text-center grid-x">
                    <h1 class="acym__title acym__title__secondary text-center cell">This is only en example, you can display any option here:</h1>';

        $others = [];
        $others['{'.$this->name.':45|anoption}'] = 'Some description, like "Name"';
        $others['{'.$this->name.':7}'] = 'Some other description, like "Phone number"';


        foreach ($others as $tagname => $tag) {
            $text .= '<div class="grid-x medium-12 cell acym__listing__row acym__listing__row__popup text-left" onclick="setTag(\''.$tagname.'\', jQuery(this));" >
                        <div class="cell small-12 acym__listing__title acym__listing__title__dynamics">'.$tag.'</div>
                     </div>';
        }

        $text .= '</div>';

        echo $text;
    }


    /* * * * * * * * * *
     * Dynamic content *
     * * * * * * * * * */

    /**
     * This method is optional and is used to declare a new dynamic content button that can be dragged in the editor
     */
    public function getPossibleIntegrations()
    {
        return $this->pluginDescription;
    }

    /**
     * This method is mandatory if you declared a dynamic content.
     * It is used to show the options needed to customize what you want to insert in the email.
     *
     * For a better example, take a look at the code for the post / article insertion plugins
     *
     * @param null $defaultValues
     */
    public function insertionOptions($defaultValues = null)
    {
        /*
         * If needed, you can declare some options here
         */
        $displayOptions = [
            [
                'title' => 'Data to display',
                'type' => 'checkbox', // checkbox, boolean, pictures, radio, select, multiselect, text, number, date
                'name' => 'display',
                'options' => [
                    'title' => ['Title', true], // The key is the value you'll get when replacing the inserted tag
                    'price' => ['Price', true], // The value is always an array with the displayed label and a boolean to choose whether or not it is checked by default
                    'desc' => ['Description', true],
                    'attribs' => ['Details', false],
                ],
                'class' => 'a_CSS_class_for_this_option',
            ],
            [
                'title' => 'Clickable title',
                'type' => 'boolean',
                'name' => 'clickable',
                'default' => true,
            ],
            [
                'title' => 'Some text option',
                'type' => 'text',
                'name' => 'wrap',
                'default' => 0,
            ],
            [
                'title' => 'Display pictures',
                'type' => 'pictures',
                'name' => 'pictures',
            ],
        ];

        echo $this->displaySelectionZone($this->prepareListing());
        echo $this->pluginHelper->displayOptions($displayOptions, $this->name, 'individual', $defaultValues);
    }

    /**
     * You defined some options, good!
     * Now display a listing of the elements you propose to insert
     */
    public function prepareListing()
    {
        /*
         * Build here the SQL query to get the elements you want to list
         */
        $this->querySelect = 'SELECT element.* ';
        $this->query = 'FROM `#__my_table` AS element ';
        $this->filters = [];
        $this->filters[] = 'element.published = 1';
        $this->searchFields = ['element.id', 'element.title']; // Columns on which you want to apply the search field
        $this->pageInfo->order = 'element.id'; // The elements will be listed by id if this column exists
        $this->elementIdTable = 'element';
        $this->elementIdColumn = 'id';

        parent::prepareListing();

        // This defines the listing of the elements that can be inserted
        // Each column has a size, the total must be 12
        $listingOptions = [
            'header' => [
                'title_column' => [ // this key is the column name
                                    'label' => 'Title', // The title of the column to display
                                    'size' => '8',
                ],
                'id' => [
                    'label' => 'ID',
                    'size' => '4',
                    'class' => 'text-center',
                ],
            ],
            'id' => 'ID', // The value is the column name for the ids of the elements you insert
            'rows' => $this->getElements(),
        ];

        return $this->getElementsListing($listingOptions);
    }


    /* * * * * * * * * * *
     * Insert the content *
     * * * * * * * * * * */

    /**
     * @param object $email The email object, if you need to modify something in this object, make sure to do it right
     * @param bool   $send  If this variable is true, the email will be sent, if it's false the email won't be sent (on the summary page for example)
     */
    public function replaceContent(&$email, $send = true)
    {
        // You can use this special function to get a formatted array with all the shortcodes included in your email
        $extractedTags = $this->pluginHelper->extractTags($email, $this->name);

        // If none of your shortcodes are found in the email, no need to go further
        if (empty($extractedTags)) return;

        $tags = [];
        foreach ($extractedTags as $shortcode => $oneTag) {
            if (isset($tags[$shortcode])) continue;

            $textDisplayed = '';

            /*
             For each shortcode you will have to return the content you want to display.
             If the shortcode {exampleidentifier:45|anoption|foo:bar} is found, $oneTag will be like this:

             object {
                 'id' => 45,
                 'anoption' => true,
                 'foo' => 'bar'
             }
             */

            if (!empty($oneTag->foo) && $oneTag->foo == 'bar') {
                $textDisplayed = 'Display this text';
            } else {
                $textDisplayed = 'Display this other text';
            }

            $tags[$shortcode] = $textDisplayed;
        }

        // This function will replace the shortcodes in the email
        $this->pluginHelper->replaceTags($email, $tags);
    }

    /**
     * This method is almost the same as the previous one, except that it is called FOR EACH user the email is sent to
     * used to replace the "shortcodes" in the sent emails. {exampleidentifier:7} in this example
     *
     * @param object $email The email object, if you need to modify something in this object, make sure to do it right
     * @param object $user  The user object, you can then access to its data if you want to customize what you display
     * @param bool   $send  If this variable is true, the email will be sent, if it's false the email won't be sent (on the summary page for example)
     */
    public function replaceUserInformation(&$email, &$user, $send = true)
    {
        $extractedTags = $this->pluginHelper->extractTags($email, $this->name);
        if (empty($extractedTags)) return;


        if (empty($user->cms_id)) {
            // The current user hasn't any account created on the site (only an AcyMailing user)
        }

        $tags = [];
        foreach ($extractedTags as $i => $oneTag) {
            if (isset($tags[$i])) continue;

            $tags[$i] = 'Some content';
        }

        $this->pluginHelper->replaceTags($email, $tags);
    }
}
