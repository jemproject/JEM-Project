<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$targetDirectory = '/images/jem/events/';

if ($this->task == 'venueimg') {
    $targetDirectory = '/images/jem/venues/';
    $this->task = 'imagehandler.venueimgup';
} else if ($this->task == 'eventimg') {
    $targetDirectory = '/images/jem/events/';
    $this->task = 'imagehandler.eventimgup';
} else if ($this->task == 'categoriesimg') {
    $targetDirectory = '/images/jem/categories/';
    $this->task = 'imagehandler.categoriesimgup';
}

$imageTypes = [
    ['supported' => ($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_PNG)), 'label' => Text::_('COM_JEM_PNG_SUPPORT'), 'missing' => Text::_('COM_JEM_NO_PNG_SUPPORT')],
    ['supported' => ($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_JPEG)), 'label' => Text::_('COM_JEM_JPG_SUPPORT'), 'missing' => Text::_('COM_JEM_NO_JPG_SUPPORT')],
    ['supported' => ($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_GIF)), 'label' => Text::_('COM_JEM_GIF_SUPPORT'), 'missing' => Text::_('COM_JEM_NO_GIF_SUPPORT')],
    ['supported' => ($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_WEBP)), 'label' => Text::_('COM_JEM_WEBP_SUPPORT'), 'missing' => Text::_('COM_JEM_NO_WEBP_SUPPORT')],
];
?>

<style>
    #jem.jem-image-upload {
        padding: 1.5rem;
    }

    #jem.jem-image-upload .jem-upload-panel {
        display: grid;
        grid-template-columns: minmax(18rem, 1fr) minmax(18rem, 0.9fr);
        gap: 1rem;
        align-items: stretch;
        max-width: 58rem;
        margin: 0 auto;
    }

    #jem.jem-image-upload .jem-upload-card {
        border: 1px solid #ccd6e3;
        background: #fff;
        padding: 1rem;
    }

    #jem.jem-image-upload .jem-upload-card h2 {
        margin: 0 0 0.75rem;
        font-size: 1.1rem;
        line-height: 1.25;
    }

    #jem.jem-image-upload .jem-upload-file {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
    }

    #jem.jem-image-upload input[type="file"] {
        max-width: 100%;
    }

    #jem.jem-image-upload .jem-upload-actions {
        margin-top: 1rem;
    }

    #jem.jem-image-upload .jem-upload-meta {
        display: grid;
        grid-template-columns: max-content 1fr;
        gap: 0.45rem 0.75rem;
        margin: 0 0 1rem;
    }

    #jem.jem-image-upload .jem-upload-meta dt {
        font-weight: 700;
    }

    #jem.jem-image-upload .jem-upload-meta dd {
        margin: 0;
    }

    #jem.jem-image-upload .jem-upload-format-list {
        display: grid;
        gap: 0.35rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    #jem.jem-image-upload .jem-upload-format-list li {
        display: flex;
        gap: 0.45rem;
        align-items: center;
    }

    #jem.jem-image-upload .jem-upload-format-ok {
        color: #2f7d32;
    }

    #jem.jem-image-upload .jem-upload-format-error {
        color: #b42318;
    }

    #jem.jem-image-upload .jem-upload-note {
        max-width: 58rem;
        margin: 1rem auto 0;
        border-left: 4px solid #5579b7;
        background: #f4f7fb;
        padding: 0.8rem 1rem;
        color: #344054;
    }

    #jem.jem-image-upload .jem-upload-note strong {
        display: block;
        margin-bottom: 0.25rem;
    }

    @media (max-width: 720px) {
        #jem.jem-image-upload {
            padding: 1rem;
        }

        #jem.jem-image-upload .jem-upload-panel {
            grid-template-columns: 1fr;
        }

        #jem.jem-image-upload .jem-upload-meta {
            grid-template-columns: 1fr;
        }
    }
</style>

<form method="post" action="<?php echo htmlspecialchars($this->request_url); ?>" enctype="multipart/form-data" name="adminForm" id="adminForm">

<div id="jem" class="jem-image-upload">
    <?php if($this->ftp): ?>
        <fieldset class="adminform">
            <legend><?php echo Text::_('COM_JEM_FTP_TITLE'); ?></legend>

            <?php echo Text::_('COM_JEM_FTP_DESC'); ?>

            <?php if($this->ftp INSTANCEOF Exception): ?>
                <p><?php echo Text::_($this->ftp->message); ?></p>
            <?php endif; ?>

            <table class="adminform nospace">
                <tbody>
                    <tr>
                        <td style="width: 120px">
                            <label for="username"><?php echo Text::_('COM_JEM_USERNAME'); ?>:</label>
                        </td>
                        <td>
                            <input type="text" id="username" name="username" class="input_box" size="70" value="" />
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 120px">
                            <label for="password"><?php echo Text::_('COM_JEM_PASSWORD'); ?>:</label>
                        </td>
                        <td>
                            <input type="password" id="password" name="password" class="input_box" size="70" value="" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </fieldset>
    <?php endif; ?>

    <div class="jem-upload-panel">
        <section class="jem-upload-card">
            <h2><?php echo Text::_('COM_JEM_SELECT_IMAGE_UPLOAD'); ?></h2>
            <div class="jem-upload-file">
                <input class="inputbox" name="userfile" id="userfile" type="file" />
            </div>
            <div class="jem-upload-actions">
                <input class="btn btn-primary" type="submit" value="<?php echo Text::_('COM_JEM_UPLOAD') ?>" name="adminForm" />
            </div>
        </section>

        <section class="jem-upload-card">
            <h2><?php echo Text::_('COM_JEM_UPLOAD_IMAGE'); ?></h2>
            <dl class="jem-upload-meta">
                <dt><?php echo Text::_('COM_JEM_TARGET_DIRECTORY'); ?></dt>
                <dd><?php echo $targetDirectory; ?></dd>
                <dt><?php echo Text::_('COM_JEM_IMAGE_FILESIZE'); ?></dt>
                <dd><?php echo (int) $this->jemsettings->sizelimit; ?> kb</dd>
            </dl>
            <ul class="jem-upload-format-list">
                <?php foreach ($imageTypes as $type): ?>
                    <li class="<?php echo $type['supported'] ? 'jem-upload-format-ok' : 'jem-upload-format-error'; ?>">
                        <span aria-hidden="true"><?php echo $type['supported'] ? '&#10003;' : '&#10007;'; ?></span>
                        <span><?php echo $type['supported'] ? $type['label'] : $type['missing']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <?php if($this->jemsettings->gddisabled) : ?>
        <div class="jem-upload-note">
            <strong><?php echo Text::_('COM_JEM_ATTENTION'); ?></strong>
            <?php echo Text::_('COM_JEM_GD_WARNING'); ?>
        </div>
    <?php endif; ?>
</div>

<?php echo HTMLHelper::_('form.token'); ?>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="task" value="<?php echo $this->task;?>" />
</form>

<p class="copyright">
    <?php echo JEMAdmin::footer(); ?>
</p>
