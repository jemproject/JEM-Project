<?php
/**
 * @package    JEM
 * @subpackage JEM Mailer Plugin
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Visual profile and matrix helper for the JEM mailer plugin parameters.
 *
 * This field does not introduce new mailer behaviour. It only writes the
 * existing yes/no plugin parameters that are still rendered in the detailed
 * fieldsets.
 */
class JFormFieldMailerprofiles extends FormField
{
    protected $type = 'Mailerprofiles';

    protected function getLabel()
    {
        return '';
    }

    protected function getInput()
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('plg_jem_mailer', JPATH_ADMINISTRATOR)
            || $language->load('plg_jem_mailer', JPATH_PLUGINS . '/jem/mailer');

        $sections = array(
            'registration' => array(
                'label' => Text::_('PLG_JEM_MAILER_SECTION_REGISTRATION'),
                'columns' => array(
                    'affected_attendee' => Text::_('PLG_JEM_MAILER_MATRIX_AFFECTED_ATTENDEE'),
                    'event_creator' => Text::_('PLG_JEM_MAILER_MATRIX_EVENT_CREATOR'),
                    'admin' => Text::_('PLG_JEM_MAILER_GLOBAL_FIELD_ADMIN_LABEL'),
                    'category' => Text::_('PLG_JEM_MAILER_CATEGORY_LABEL'),
                    'group' => Text::_('PLG_JEM_MAILER_GROUP_LABEL'),
                ),
                'rows' => array(
                    'registration' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_REGISTRATION'),
                        'fields' => array(
                            'affected_attendee' => 'reg_mail_user',
                            'event_creator' => 'reg_mail_creator',
                            'admin' => 'reg_mail_admin',
                            'category' => 'reg_mail_category',
                            'group' => 'reg_mail_group',
                        ),
                    ),
                    'waitinglist' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_WAITINGLIST'),
                        'fields' => array(
                            'affected_attendee' => 'reg_mail_user_onoff',
                            'event_creator' => 'reg_mail_creator_onoff',
                            'admin' => 'reg_mail_admin_onoff',
                            'category' => 'reg_mail_category_onoff',
                            'group' => 'reg_mail_group_onoff',
                        ),
                    ),
                    'unregistration' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_UNREGISTRATION'),
                        'fields' => array(
                            'affected_attendee' => 'unreg_mail_user',
                            'event_creator' => 'unreg_mail_creator',
                            'admin' => 'unreg_mail_admin',
                            'category' => 'unreg_mail_category',
                            'group' => 'unreg_mail_group',
                        ),
                    ),
                ),
            ),
            'events' => array(
                'label' => Text::_('PLG_JEM_MAILER_SECTION_EVENTS'),
                'columns' => array(
                    'submitter_editor' => Text::_('PLG_JEM_MAILER_MATRIX_SUBMITTER_EDITOR'),
                    'event_creator' => Text::_('PLG_JEM_MAILER_MATRIX_EVENT_CREATOR'),
                    'admin' => Text::_('PLG_JEM_MAILER_GLOBAL_FIELD_ADMIN_LABEL'),
                    'registered_attendees' => Text::_('PLG_JEM_MAILER_MATRIX_REGISTERED_ATTENDEES'),
                    'category' => Text::_('PLG_JEM_MAILER_CATEGORY_LABEL'),
                    'category_acl' => Text::_('PLG_JEM_MAILER_CATEGORY_ACL_LABEL'),
                    'group' => Text::_('PLG_JEM_MAILER_GROUP_LABEL'),
                ),
                'rows' => array(
                    'event_new' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_EVENT_NEW'),
                        'fields' => array(
                            'submitter_editor' => 'newevent_mail_user',
                            'admin' => 'newevent_mail_admin',
                            'category' => 'newevent_mail_category',
                            'category_acl' => 'newevent_mail_category_acl',
                            'group' => 'newevent_mail_group',
                        ),
                    ),
                    'event_edit' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_EVENT_EDIT'),
                        'fields' => array(
                            'submitter_editor' => 'editevent_mail_user',
                            'event_creator' => 'editevent_mail_creator',
                            'admin' => 'editevent_mail_admin',
                            'registered_attendees' => 'editevent_mail_registered',
                            'category' => 'editevent_mail_category',
                            'category_acl' => 'editevent_mail_category_acl',
                            'group' => 'editevent_mail_group',
                        ),
                    ),
                ),
            ),
            'venues' => array(
                'label' => Text::_('PLG_JEM_MAILER_SECTION_VENUES'),
                'columns' => array(
                    'submitter_editor' => Text::_('PLG_JEM_MAILER_MATRIX_SUBMITTER_EDITOR'),
                    'venue_creator' => Text::_('PLG_JEM_MAILER_MATRIX_VENUE_CREATOR'),
                    'event_creators_using_venue' => Text::_('PLG_JEM_MAILER_MATRIX_EVENT_CREATORS_USING_VENUE'),
                    'admin' => Text::_('PLG_JEM_MAILER_GLOBAL_FIELD_ADMIN_LABEL'),
                    'registered_attendees' => Text::_('PLG_JEM_MAILER_MATRIX_REGISTERED_ATTENDEES'),
                    'category' => Text::_('PLG_JEM_MAILER_CATEGORY_LABEL'),
                    'group' => Text::_('PLG_JEM_MAILER_GROUP_LABEL'),
                ),
                'rows' => array(
                    'venue_new' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_VENUE_NEW'),
                        'fields' => array(
                            'submitter_editor' => 'newvenue_mail_user',
                            'admin' => 'newvenue_mail_admin',
                        ),
                    ),
                    'venue_edit' => array(
                        'label' => Text::_('PLG_JEM_MAILER_MATRIX_VENUE_EDIT'),
                        'fields' => array(
                            'submitter_editor' => 'editvenue_mail_user',
                            'venue_creator' => 'editvenue_mail_creator',
                            'event_creators_using_venue' => 'editvenue_mail_ev-creator',
                            'admin' => 'editvenue_mail_admin',
                            'registered_attendees' => 'editvenue_mail_registered',
                            'category' => 'editvenue_mail_category',
                            'group' => 'editvenue_mail_group',
                        ),
                    ),
                ),
            ),
        );

        $profiles = array(
            'quiet' => array(
                'label' => Text::_('PLG_JEM_MAILER_PROFILE_QUIET'),
                'description' => Text::_('PLG_JEM_MAILER_PROFILE_QUIET_DESC'),
                'values' => array(
                    'reg_mail_user' => 1,
                    'reg_mail_creator' => 0,
                    'reg_mail_admin' => 0,
                    'reg_mail_category' => 0,
                    'reg_mail_group' => 0,
                    'reg_mail_user_onoff' => 1,
                    'reg_mail_creator_onoff' => 0,
                    'reg_mail_admin_onoff' => 0,
                    'reg_mail_category_onoff' => 0,
                    'reg_mail_group_onoff' => 0,
                    'unreg_mail_user' => 1,
                    'unreg_mail_creator' => 0,
                    'unreg_mail_admin' => 0,
                    'unreg_mail_category' => 0,
                    'unreg_mail_group' => 0,
                    'newevent_mail_user' => 0,
                    'newevent_mail_admin' => 0,
                    'newevent_mail_category' => 0,
                    'newevent_mail_category_acl' => 0,
                    'newevent_mail_group' => 0,
                    'editevent_mail_user' => 0,
                    'editevent_mail_creator' => 0,
                    'editevent_mail_admin' => 0,
                    'editevent_mail_registered' => 0,
                    'editevent_mail_category' => 0,
                    'editevent_mail_category_acl' => 0,
                    'editevent_mail_group' => 0,
                    'newvenue_mail_user' => 0,
                    'newvenue_mail_admin' => 0,
                    'editvenue_mail_user' => 0,
                    'editvenue_mail_creator' => 0,
                    'editvenue_mail_ev-creator' => 0,
                    'editvenue_mail_admin' => 0,
                    'editvenue_mail_registered' => 0,
                    'editvenue_mail_category' => 0,
                    'editvenue_mail_group' => 0,
                ),
            ),
            'standard' => array(
                'label' => Text::_('PLG_JEM_MAILER_PROFILE_STANDARD'),
                'description' => Text::_('PLG_JEM_MAILER_PROFILE_STANDARD_DESC'),
                'values' => array(
                    'reg_mail_user' => 1,
                    'reg_mail_admin' => 1,
                    'reg_mail_user_onoff' => 1,
                    'reg_mail_admin_onoff' => 1,
                    'unreg_mail_user' => 1,
                    'unreg_mail_admin' => 1,
                    'newevent_mail_user' => 1,
                    'newevent_mail_admin' => 1,
                    'editevent_mail_user' => 1,
                    'editevent_mail_admin' => 1,
                    'newvenue_mail_user' => 1,
                    'newvenue_mail_admin' => 1,
                    'editvenue_mail_user' => 1,
                    'editvenue_mail_admin' => 1,
                ),
            ),
            'editorial' => array(
                'label' => Text::_('PLG_JEM_MAILER_PROFILE_EDITORIAL'),
                'description' => Text::_('PLG_JEM_MAILER_PROFILE_EDITORIAL_DESC'),
                'values' => array(
                    'newevent_mail_user' => 1,
                    'newevent_mail_admin' => 1,
                    'newevent_mail_category_acl' => 1,
                    'editevent_mail_user' => 1,
                    'editevent_mail_creator' => 1,
                    'editevent_mail_admin' => 1,
                    'editevent_mail_category_acl' => 1,
                    'newvenue_mail_user' => 1,
                    'newvenue_mail_admin' => 1,
                    'editvenue_mail_user' => 1,
                    'editvenue_mail_creator' => 1,
                    'editvenue_mail_admin' => 1,
                    'editvenue_mail_ev-creator' => 1,
                ),
            ),
            'community' => array(
                'label' => Text::_('PLG_JEM_MAILER_PROFILE_COMMUNITY'),
                'description' => Text::_('PLG_JEM_MAILER_PROFILE_COMMUNITY_DESC'),
                'values' => array(
                    'reg_mail_user' => 1,
                    'reg_mail_creator' => 1,
                    'reg_mail_admin' => 1,
                    'reg_mail_category' => 1,
                    'reg_mail_group' => 1,
                    'reg_mail_user_onoff' => 1,
                    'reg_mail_creator_onoff' => 1,
                    'reg_mail_admin_onoff' => 1,
                    'reg_mail_category_onoff' => 1,
                    'reg_mail_group_onoff' => 1,
                    'unreg_mail_user' => 1,
                    'unreg_mail_creator' => 1,
                    'unreg_mail_admin' => 1,
                    'unreg_mail_category' => 1,
                    'unreg_mail_group' => 1,
                    'newevent_mail_user' => 1,
                    'newevent_mail_admin' => 1,
                    'newevent_mail_category' => 1,
                    'newevent_mail_category_acl' => 1,
                    'newevent_mail_group' => 1,
                    'editevent_mail_user' => 1,
                    'editevent_mail_creator' => 1,
                    'editevent_mail_admin' => 1,
                    'editevent_mail_registered' => 1,
                    'editevent_mail_category' => 1,
                    'editevent_mail_category_acl' => 1,
                    'editevent_mail_group' => 1,
                    'newvenue_mail_user' => 1,
                    'newvenue_mail_admin' => 1,
                    'editvenue_mail_user' => 1,
                    'editvenue_mail_creator' => 1,
                    'editvenue_mail_ev-creator' => 1,
                    'editvenue_mail_admin' => 1,
                    'editvenue_mail_registered' => 1,
                    'editvenue_mail_category' => 1,
                    'editvenue_mail_group' => 1,
                ),
            ),
        );

        $allFields = array();
        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row['fields'] as $fieldName) {
                    $allFields[$fieldName] = 0;
                }
            }
        }

        foreach ($profiles as &$profile) {
            $profile['values'] = array_replace($allFields, $profile['values']);
        }
        unset($profile);

        $activeProfile = $this->value ?: $this->getParamValue('mailer_profiles', 'standard');

        $html = array();
        $html[] = '<div class="jem-mailer-matrix" data-profiles="' . htmlspecialchars(json_encode($profiles), ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . htmlspecialchars((string) $activeProfile, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<div class="jem-mailer-intro-block">';
        $html[] = '<p class="jem-mailer-intro">' . Text::_('PLG_JEM_MAILER_PROFILES_MATRIX_HELP') . '</p>';
        $html[] = '<div class="jem-mailer-legend">';
        $html[] = '<span><strong>' . Text::_('PLG_JEM_MAILER_LEGEND_PROFILE') . '</strong> ' . Text::_('PLG_JEM_MAILER_LEGEND_PROFILE_DESC') . '</span>';
        $html[] = '<span><strong>' . Text::_('PLG_JEM_MAILER_LEGEND_CHECKBOX') . '</strong> ' . Text::_('PLG_JEM_MAILER_LEGEND_CHECKBOX_DESC') . '</span>';
        $html[] = '<span><strong>-</strong> ' . Text::_('PLG_JEM_MAILER_MATRIX_NOT_APPLICABLE') . '</span>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '<fieldset class="jem-mailer-section jem-mailer-profiles-section">';
        $html[] = '<legend>' . Text::_('PLG_JEM_MAILER_SECTION_PROFILES') . '</legend>';
        $html[] = '<div class="jem-mailer-profiles">';

        foreach ($profiles as $key => $profile) {
            $html[] = '<button type="button" class="btn btn-secondary jem-mailer-profile" data-profile="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
            $html[] = '<strong>' . htmlspecialchars($profile['label'], ENT_QUOTES, 'UTF-8') . '</strong>';
            $html[] = '<span>' . htmlspecialchars($profile['description'], ENT_QUOTES, 'UTF-8') . '</span>';
            $html[] = '</button>';
        }

        $html[] = '</div>';
        $html[] = '</fieldset>';
        $commonColumnKeys = array('admin', 'category', 'group');
        $specificColumnSlotKeys = array(
            array('affected_attendee', 'registered_attendees'),
            array('submitter_editor'),
            array('event_creator', 'venue_creator'),
            array('category_acl', 'event_creators_using_venue'),
        );
        $specificColumnSlots = count($specificColumnSlotKeys);

        foreach ($sections as $section) {
            $sectionColumns = $this->normaliseSectionColumns($section['columns'], $commonColumnKeys, $specificColumnSlotKeys);

            $html[] = '<fieldset class="jem-mailer-section">';
            $html[] = '<legend>' . htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8') . '</legend>';
            $html[] = '<div class="table-responsive">';
            $html[] = '<table class="table table-striped table-sm jem-mailer-matrix-table">';
            $html[] = '<colgroup><col class="jem-mailer-col-action">';
            for ($i = 0; $i < $specificColumnSlots; $i++) {
                $html[] = '<col class="jem-mailer-col-specific">';
            }
            foreach ($commonColumnKeys as $commonColumnKey) {
                $html[] = '<col class="jem-mailer-col-common">';
            }
            $html[] = '</colgroup>';
            $html[] = '<thead><tr><th>' . Text::_('PLG_JEM_MAILER_MATRIX_ACTION') . '</th>';

            foreach ($sectionColumns as $columnKey => $columnLabel) {
                if ($this->isSpacerColumn($columnKey)) {
                    $html[] = '<th class="jem-mailer-spacer" aria-hidden="true"></th>';
                    continue;
                }

                $html[] = '<th>' . htmlspecialchars($columnLabel, ENT_QUOTES, 'UTF-8') . '</th>';
            }

            $html[] = '</tr></thead><tbody>';

            foreach ($section['rows'] as $row) {
                $html[] = '<tr><th scope="row">' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</th>';
                foreach ($sectionColumns as $columnKey => $columnLabel) {
                    if ($this->isSpacerColumn($columnKey)) {
                        $html[] = '<td class="jem-mailer-spacer" aria-hidden="true"></td>';
                        continue;
                    }

                    if (isset($row['fields'][$columnKey])) {
                        $fieldName = $row['fields'][$columnKey];
                        $html[] = '<td><input type="checkbox" class="jem-mailer-toggle" data-param="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($row['label'] . ' - ' . $columnLabel, ENT_QUOTES, 'UTF-8') . '"></td>';
                    } else {
                        $html[] = '<td class="jem-mailer-na" title="' . htmlspecialchars(Text::_('PLG_JEM_MAILER_MATRIX_NOT_APPLICABLE'), ENT_QUOTES, 'UTF-8') . '">-</td>';
                    }
                }
                $html[] = '</tr>';
            }

            $html[] = '</tbody></table>';
            $html[] = '</div>';
            $html[] = '</fieldset>';
        }
        $html[] = '<fieldset class="jem-mailer-section jem-mailer-admin-options">';
        $html[] = '<legend>' . Text::_('PLG_JEM_MAILER_ADMIN_OPTIONS') . '</legend>';
        $html[] = '<div class="jem-mailer-admin-grid">';
        $html[] = '<label class="jem-mailer-switch"><input type="checkbox" class="jem-mailer-setting-toggle" data-param="fetch_admin_mails"> <span>' . Text::_('PLG_JEM_MAILER_ALL_ADMINS') . '</span></label>';
        $html[] = '<label class="jem-mailer-switch"><input type="checkbox" class="jem-mailer-setting-toggle" data-param="send_html"> <span>' . Text::_('PLG_JEM_MAILER_SENDHTML') . '</span></label>';
        $html[] = '<label class="jem-mailer-admin-receivers"><span>' . Text::_('PLG_JEM_MAILER_ADMIN_MAIL_RECEIVERS') . '</span><textarea class="form-control jem-mailer-setting-text" data-param="admin_receivers" rows="4"></textarea></label>';
        $html[] = '</div>';
        $html[] = '</fieldset>';
        $html[] = '</div>';
        $html[] = $this->getScriptAndStyle();

        return implode('', $html);
    }

    private function getParamValue($name, $default = '')
    {
        if (!$this->form) {
            return $default;
        }

        $value = $this->form->getValue($name, 'params', null);
        if ($value !== null && $value !== '') {
            return $value;
        }

        $params = $this->form->getValue('params');
        if (is_array($params) && array_key_exists($name, $params) && $params[$name] !== '') {
            return $params[$name];
        }

        if (is_object($params) && isset($params->$name) && $params->$name !== '') {
            return $params->$name;
        }

        return $default;
    }

    private function normaliseSectionColumns(array $columns, array $commonColumnKeys, array $specificColumnSlotKeys)
    {
        $commonColumns = array();

        foreach ($columns as $columnKey => $columnLabel) {
            if (in_array($columnKey, $commonColumnKeys, true)) {
                $commonColumns[$columnKey] = $columnLabel;
            }
        }

        $normalisedColumns = array();
        $slot = 0;

        foreach ($specificColumnSlotKeys as $slotColumnKeys) {
            $matchedColumnKey = null;

            foreach ($slotColumnKeys as $columnKey) {
                if (isset($columns[$columnKey])) {
                    $matchedColumnKey = $columnKey;
                    break;
                }
            }

            if ($matchedColumnKey !== null) {
                $normalisedColumns[$matchedColumnKey] = $columns[$matchedColumnKey];
            } else {
                $normalisedColumns['__spacer_' . $slot] = '';
            }

            $slot++;
        }

        foreach ($commonColumnKeys as $columnKey) {
            if (isset($columns[$columnKey])) {
                $normalisedColumns[$columnKey] = $columns[$columnKey];
                continue;
            }

            $normalisedColumns['__common_spacer_' . $columnKey] = '';
        }

        return $normalisedColumns;
    }

    private function isSpacerColumn($columnKey)
    {
        return strpos($columnKey, '__spacer_') === 0 || strpos($columnKey, '__common_spacer_') === 0;
    }

    private function getScriptAndStyle()
    {
        return '<style>
.jem-mailer-matrix{width:100%;max-width:none;color:var(--body-color,#102033)}
.jem-mailer-intro-block{margin:0 0 1rem}
.jem-mailer-intro{margin:0 0 .75rem;color:var(--body-color,#334)}
.jem-mailer-profiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:.75rem}
.jem-mailer-profile{display:flex;flex-direction:column;align-items:flex-start;text-align:left;white-space:normal;min-height:5rem;border:1px solid #8fa2b8}
.jem-mailer-profile.is-active{background:#253f61;border-color:#253f61;box-shadow:0 0 0 .18rem rgba(37,63,97,.18)}
.jem-mailer-profile span{font-size:.875rem;font-weight:400;line-height:1.3;margin-top:.25rem}
.jem-mailer-legend{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:.4rem;margin:.4rem 0;color:#44556b;font-size:.9rem}
.jem-mailer-legend span{display:flex;align-items:center;gap:.25rem;background:#f4f7fa;border:1px solid #d8dde3;border-radius:4px;padding:.35rem .55rem}
.jem-mailer-section{margin:1.15rem 0 0;padding:.75rem 1rem 1rem;border:1px solid #d8dde3;border-radius:6px;background:var(--body-bg,#fff);min-inline-size:0}
.jem-mailer-section legend{float:none;width:auto;max-width:100%;margin:0 0 .6rem;padding:.35rem .75rem;background:#253f61;color:#fff;border-radius:4px;font-size:1.05rem;font-weight:700;line-height:1.25}
.jem-mailer-section .table-responsive{overflow-x:auto}
.jem-mailer-matrix-table{margin-bottom:0;border:1px solid #ccd4dd;min-width:940px;table-layout:fixed;width:100%}
.jem-mailer-col-action{width:22%}
.jem-mailer-col-specific{width:11%}
.jem-mailer-col-common{width:11.33%}
.jem-mailer-matrix-table thead th{background:#e7edf5;color:#17324f;font-weight:700;border-bottom:2px solid #8fa2b8;text-align:center;vertical-align:middle}
.jem-mailer-matrix-table th,.jem-mailer-matrix-table td{text-align:center;vertical-align:middle}
.jem-mailer-matrix-table th{overflow-wrap:anywhere}
.jem-mailer-matrix-table tbody th{font-weight:600}
.jem-mailer-matrix-table th:first-child{text-align:left;min-width:13rem}
.jem-mailer-matrix-table input[type=checkbox]{width:1.05rem;height:1.05rem}
.jem-mailer-na{color:#778390;background:#f3f5f7;font-weight:600}
.jem-mailer-spacer{color:transparent}
.jem-mailer-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,330px),max-content));gap:1rem 1.5rem;align-items:start}
.jem-mailer-switch{display:flex;gap:.5rem;align-items:center;margin:.35rem 0;white-space:nowrap;min-width:max-content}
.jem-mailer-switch input{flex:0 0 auto}
.jem-mailer-switch span{white-space:nowrap}
.jem-mailer-admin-receivers{display:flex;flex-direction:column;gap:.35rem;grid-column:1/-1;max-width:760px}
@media (max-width: 900px){
    .jem-mailer-section{padding:.65rem .75rem .8rem}
    .jem-mailer-section legend{font-size:1rem;padding:.3rem .65rem}
    .jem-mailer-matrix-table{min-width:860px}
    .jem-mailer-matrix-table th:first-child{min-width:11rem}
}
@media (max-width: 640px){
    .jem-mailer-legend{display:block}
    .jem-mailer-legend span{display:flex;margin-bottom:.4rem}
    .jem-mailer-profile{min-height:auto}
    .jem-mailer-matrix-table{font-size:.9rem;min-width:820px}
}
</style><script>
(function(){
    function setRadio(name, value) {
        var selector = "input[type=radio][name=\'jform[params][" + name + "]\'][value=\'" + value + "\']";
        var radio = document.querySelector(selector);
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event("change", {bubbles:true}));
        }

        document.querySelectorAll("input[type=hidden][name=\'jform[params][" + name + "]\']").forEach(function(hidden){
            hidden.value = value;
            hidden.dispatchEvent(new Event("change", {bubbles:true}));
        });
    }

    function getRadio(name) {
        var radio = document.querySelector("input[type=radio][name=\'jform[params][" + name + "]\']:checked");
        if (radio) {
            return radio.value;
        }

        var hidden = document.querySelector("input[type=hidden][name=\'jform[params][" + name + "]\']");
        return hidden ? hidden.value : "0";
    }

    function setText(name, value) {
        document.querySelectorAll("textarea[name=\'jform[params][" + name + "]\'],input[name=\'jform[params][" + name + "]\']").forEach(function(field){
            field.value = value;
            field.dispatchEvent(new Event("change", {bubbles:true}));
        });
    }

    function getText(name) {
        var field = document.querySelector("textarea[name=\'jform[params][" + name + "]\'],input[name=\'jform[params][" + name + "]\']");
        return field ? field.value : "";
    }

    function hideLegacyTabs() {
        ["basic", "event_notifications", "venue_notifications", "admin_options"].forEach(function(name){
            ["attrib-" + name, "params-" + name, name].forEach(function(id){
                document.querySelectorAll("[href=\'#" + id + "\'],[data-bs-target=\'#" + id + "\'],[aria-controls=\'" + id + "\']").forEach(function(tab){
                    var wrapper = tab.closest("li") || tab.closest(".nav-item") || tab;
                    wrapper.style.display = "none";
                });

                var panel = document.getElementById(id);
                if (panel) {
                    panel.style.display = "none";
                    panel.classList.remove("active", "show");
                }
            });
        });

    }

    function expandFieldWidth(root) {
        var group = root.closest(".control-group,.form-group");
        if (!group) {
            return;
        }

        var label = group.querySelector(".control-label,.form-label");
        if (label) {
            label.style.display = "none";
        }

        var controls = group.querySelector(".controls,.field-input");
        if (controls) {
            controls.style.marginLeft = "0";
            controls.style.width = "100%";
            controls.style.maxWidth = "none";
        }
    }

    function hideParentFieldsetLegend(root) {
        var fieldset = root.closest("fieldset");
        if (!fieldset) {
            return;
        }

        var legend = fieldset.querySelector(":scope > legend");
        if (legend && legend.textContent.trim() === "' . Text::_('PLG_JEM_MAILER_NOTIFICATIONS') . '") {
            legend.style.display = "none";
            fieldset.style.border = "0";
            fieldset.style.padding = "0";
            fieldset.style.margin = "0";
            fieldset.style.background = "transparent";
            fieldset.style.minInlineSize = "0";
        }
    }

    function syncMatrix(root) {
        root.querySelectorAll(".jem-mailer-toggle").forEach(function(toggle){
            toggle.checked = getRadio(toggle.dataset.param) === "1";
        });
        root.querySelectorAll(".jem-mailer-setting-toggle").forEach(function(toggle){
            toggle.checked = getRadio(toggle.dataset.param) === "1";
        });
        root.querySelectorAll(".jem-mailer-setting-text").forEach(function(field){
            field.value = getText(field.dataset.param);
        });
    }

    function setActiveProfile(root, key) {
        root.querySelectorAll(".jem-mailer-profile").forEach(function(button){
            button.classList.toggle("is-active", button.dataset.profile === key);
        });
    }

    function init(root) {
        expandFieldWidth(root);
        hideParentFieldsetLegend(root);
        var profiles = {};
        try {
            profiles = JSON.parse(root.getAttribute("data-profiles") || "{}");
        } catch (e) {
            profiles = {};
        }

        syncMatrix(root);

        root.querySelectorAll(".jem-mailer-toggle").forEach(function(toggle){
            toggle.addEventListener("change", function(){
                setRadio(toggle.dataset.param, toggle.checked ? "1" : "0");
                var hidden = root.querySelector("input[type=hidden]");
                if (hidden) {
                    hidden.value = "custom";
                }
                setActiveProfile(root, "");
            });
        });

        root.querySelectorAll(".jem-mailer-setting-toggle").forEach(function(toggle){
            toggle.addEventListener("change", function(){
                setRadio(toggle.dataset.param, toggle.checked ? "1" : "0");
            });
        });

        root.querySelectorAll(".jem-mailer-setting-text").forEach(function(field){
            field.addEventListener("input", function(){
                setText(field.dataset.param, field.value);
            });
        });

        root.querySelectorAll(".jem-mailer-profile").forEach(function(button){
            button.addEventListener("click", function(){
                var profile = profiles[button.dataset.profile];
                if (!profile || !profile.values) {
                    return;
                }
                Object.keys(profile.values).forEach(function(name){
                    setRadio(name, profile.values[name] ? "1" : "0");
                });
                syncMatrix(root);
                var hidden = root.querySelector("input[type=hidden]");
                if (hidden) {
                    hidden.value = button.dataset.profile;
                }
                setActiveProfile(root, button.dataset.profile);
            });
        });

        var matchedProfile = "";
        Object.keys(profiles).forEach(function(key){
            var values = profiles[key].values || {};
            var matches = Object.keys(values).every(function(name){
                return getRadio(name) === String(values[name] ? 1 : 0);
            });
            if (matches) {
                var hidden = root.querySelector("input[type=hidden]");
                if (hidden) {
                    hidden.value = key;
                }
                matchedProfile = key;
            }
        });
        setActiveProfile(root, matchedProfile);
    }

    document.addEventListener("DOMContentLoaded", function(){
        hideLegacyTabs();
        document.querySelectorAll(".jem-mailer-matrix").forEach(init);
    });
})();
</script>';
    }
}
