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
use Joomla\CMS\Session\Session;
?>
<form action="index.php" method="post" name="adminForm" id="adminForm" class="jem-imagehandler is-gallery">
    <style>
        .jem-imagehandler-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border: 1px solid #d6dde8;
            background: #f8fafc;
        }

        .jem-imagehandler-search {
            display: flex;
            flex: 1 1 22rem;
            align-items: center;
            gap: 0.5rem;
            min-width: 16rem;
        }

        .jem-imagehandler-search input {
            max-width: 28rem;
        }

        .jem-imagehandler-view-switch {
            display: inline-flex;
            gap: 0.25rem;
            margin-left: auto;
        }

        .jem-imagehandler-view-switch .btn {
            white-space: nowrap;
        }

        .jem-imagehandler [data-jem-image-panel] {
            display: none !important;
        }

        .jem-imagehandler.is-gallery [data-jem-image-panel="grid"],
        .jem-imagehandler.is-details [data-jem-image-panel="details"] {
            display: block !important;
        }

        .jem-imagehandler.is-gallery .jem-imagehandler-grid {
            display: grid !important;
        }

        .jem-imagehandler-grid {
            grid-template-columns: repeat(auto-fill, minmax(15.5rem, 1fr));
            gap: 0.75rem;
        }

        .jem-imagehandler-grid .item-image {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            float: none;
            width: auto;
            min-height: 14.5rem;
            margin: 0;
            padding: 0.5rem;
            border: 1px solid #d6dde8;
            background: #fff;
            box-sizing: border-box;
        }

        .jem-imagehandler-grid .imgBorder {
            border: 0;
            height: clamp(9rem, 16vw, 11rem);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0.2rem 2.2rem 0.2rem 0.2rem;
            width: 100%;
            overflow: hidden;
        }

        .jem-imagehandler-grid .imgBorder a,
        .jem-imagehandler-grid .imgBorder a:hover {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: transparent;
        }

        .jem-imagehandler-grid .image {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .jem-imagehandler-grid .image img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            object-fit: contain;
        }

        .jem-imagehandler-grid .controls {
            display: grid;
            grid-template-columns: auto auto auto;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin: 0;
        }

        .jem-imagehandler-grid .imageinfo {
            display: block;
            width: 100%;
            overflow: hidden;
            color: #1f2937;
            font-size: 0.9rem;
            line-height: 1.25;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .jem-imagehandler-card-size {
            color: #374151;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .jem-imagehandler-card-date {
            color: #6b7280;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .jem-imagehandler-grid .jem-imagehandler-delete {
            position: absolute;
            top: 0.3rem;
            right: 0.3rem;
            width: 1.35rem;
            height: 1.35rem;
            padding: 0;
            gap: 0;
            font-size: 0.75rem;
            line-height: 1;
        }

        .jem-imagehandler-grid .jem-imagehandler-delete .jem-imagehandler-delete-label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .jem-imagehandler-table-wrap {
            overflow-x: auto;
            border: 1px solid #d6dde8;
        }

        .jem-imagehandler-table {
            margin-bottom: 0;
            min-width: 720px;
        }

        .jem-imagehandler-table thead th {
            background: #fff;
            border-bottom: 1px solid #9ca3af;
        }

        .jem-imagehandler-table th,
        .jem-imagehandler-table td {
            vertical-align: middle;
        }

        .jem-imagehandler-table tbody tr:nth-child(odd) {
            background: #f3f4f6;
        }

        .jem-imagehandler-table tbody tr:nth-child(even) {
            background: #fff;
        }

        .jem-imagehandler-thumb {
            width: 4.5rem;
            height: 3.5rem;
            object-fit: contain;
            border: 1px solid #d6dde8;
            background: #fff;
        }

        .jem-imagehandler-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .jem-imagehandler-toolbar {
                align-items: stretch;
            }

            .jem-imagehandler-search,
            .jem-imagehandler-view-switch {
                width: 100%;
                margin-left: 0;
            }

            .jem-imagehandler-search {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div class="jem-imagehandler-toolbar">
        <div class="jem-imagehandler-search">
            <label for="filter_search" class="mb-0"><?php echo Text::_('COM_JEM_SEARCH'); ?></label>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->search, ENT_QUOTES, 'UTF-8'); ?>" class="text_area form-control inputbox" onChange="document.adminForm.submit();" />
            <button class="buttonfilter btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button class="buttonfilter btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
        </div>

        <div class="jem-imagehandler-view-switch" role="group" aria-label="<?php echo Text::_('COM_JEM_IMAGEHANDLER_VIEW_MODE'); ?>">
            <button type="button" class="btn btn-primary active" data-jem-image-view="grid"><?php echo Text::_('COM_JEM_IMAGEHANDLER_GRID_VIEW'); ?></button>
            <button type="button" class="btn btn-outline-primary" data-jem-image-view="details"><?php echo Text::_('COM_JEM_IMAGEHANDLER_DETAILS_VIEW'); ?></button>
        </div>
    </div>

    <div class="imglist jem-imagehandler-grid" data-jem-image-panel="grid">
        <?php
        $n = is_array($this->images) ? count($this->images) : 0;
        for ($i = 0; $i < $n; $i++) :
            $this->setImage($i);
            echo $this->loadTemplate('image');
        endfor;
        ?>
    </div>

    <div class="jem-imagehandler-table-wrap" data-jem-image-panel="details">
        <table class="table table-striped table-hover table-sm jem-imagehandler-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_JEM_IMAGE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMAGEHANDLER_FILE_NAME'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_SIZE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMAGEHANDLER_DIMENSIONS'); ?></th>
                    <th><?php echo Text::_('JDATE'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_JEM_ACTIONS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $n; $i++) : ?>
                    <?php
                    $image = $this->images[$i];
                    $imageName = (string) $image->name;
                    $imageNameAttr = htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8');
                    $folderAttr = htmlspecialchars((string) $this->folder, ENT_QUOTES, 'UTF-8');
                    $imageUrl = '../images/jem/' . rawurlencode((string) $this->folder) . '/' . rawurlencode($imageName);
                    $deleteUrl = 'index.php?option=com_jem&task=imagehandler.delete&tmpl=component&folder=' . $folderAttr . '&rm[]=' . rawurlencode($imageName) . '&' . Session::getFormToken() . '=1';
                    $modified = !empty($image->modified) ? HTMLHelper::_('date', $image->modified, Text::_('DATE_FORMAT_LC4')) : '-';
                    ?>
                    <tr>
                        <td>
                            <button type="button" class="btn btn-link p-0" onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>, null, <?php echo json_encode(''); ?>);'>
                                <img class="jem-imagehandler-thumb" src="<?php echo $imageUrl; ?>" alt="<?php echo $imageNameAttr; ?>" />
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-link p-0 text-start" onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>, null, <?php echo json_encode(''); ?>);'>
                                <?php echo $imageNameAttr; ?>
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars((string) $image->size, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $image->width; ?> x <?php echo (int) $image->height; ?> px</td>
                        <td><?php echo htmlspecialchars($modified, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-danger jem-imagehandler-delete delete-item" href="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="icon-trash" aria-hidden="true"></span>
                                <?php echo Text::_('COM_JEM_DELETE_IMAGE'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="clear"></div>

    <div class="pnav">
        <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
    </div>

    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="option" value="com_jem" />
    <input type="hidden" name="view" value="imagehandler" />
    <input type="hidden" name="tmpl" value="component" />
    <input type="hidden" name="task" value="<?php echo $this->task; ?>" />
</form>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var buttons = document.querySelectorAll('[data-jem-image-view]');
        var panels = document.querySelectorAll('[data-jem-image-panel]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-jem-image-view');

                buttons.forEach(function (item) {
                    item.classList.toggle('active', item === button);
                    item.classList.toggle('btn-primary', item === button);
                    item.classList.toggle('btn-outline-primary', item !== button);
                });

                document.getElementById('adminForm').classList.toggle('is-gallery', target === 'grid');
                document.getElementById('adminForm').classList.toggle('is-details', target === 'details');
            });
        });

        document.querySelectorAll('.jem-imagehandler-delete').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm(<?php echo json_encode(Text::_('COM_JEM_IMAGEHANDLER_CONFIRM_DELETE')); ?>)) {
                    event.preventDefault();
                }
            });
        });
    });
</script>
