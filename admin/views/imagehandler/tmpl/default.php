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
<form action="index.php" method="post" name="adminForm" id="adminForm">
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

        .jem-imagehandler-view {
            display: none;
        }

        .jem-imagehandler-view.is-active {
            display: block;
        }

        .jem-imagehandler-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
            gap: 0.75rem;
        }

        .jem-imagehandler-grid .item-image {
            float: none;
            width: auto;
            min-height: 0;
            margin: 0;
            padding: 0.5rem;
            border: 1px solid #d6dde8;
            background: #fff;
        }

        .jem-imagehandler-grid .imgBorder {
            border: 0;
            min-height: 4.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .jem-imagehandler-grid .image {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .jem-imagehandler-grid .controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }

        .jem-imagehandler-grid .imageinfo {
            overflow: hidden;
            color: #4b5563;
            font-size: 0.8rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .jem-imagehandler-card-size {
            color: #374151;
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

        .jem-imagehandler-table th,
        .jem-imagehandler-table td {
            vertical-align: middle;
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

    <div class="imglist jem-imagehandler-view jem-imagehandler-grid is-active" data-jem-image-panel="grid">
        <?php
        $n = is_array($this->images) ? count($this->images) : 0;
        for ($i = 0; $i < $n; $i++) :
            $this->setImage($i);
            echo $this->loadTemplate('image');
        endfor;
        ?>
    </div>

    <div class="jem-imagehandler-view jem-imagehandler-table-wrap" data-jem-image-panel="details">
        <table class="table table-striped table-hover table-sm jem-imagehandler-table">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_JEM_IMAGE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMAGEHANDLER_FILE_NAME'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ATTACHMENT_FILE_SIZE'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMAGEHANDLER_DIMENSIONS'); ?></th>
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
                    ?>
                    <tr>
                        <td>
                            <button type="button" class="btn btn-link p-0" onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>);'>
                                <img class="jem-imagehandler-thumb" src="<?php echo $imageUrl; ?>" alt="<?php echo $imageNameAttr; ?>" />
                            </button>
                        </td>
                        <td>
                            <button type="button" class="btn btn-link p-0 text-start" onclick='window.parent.SelectImage(<?php echo json_encode($imageName); ?>, <?php echo json_encode($imageName); ?>);'>
                                <?php echo $imageNameAttr; ?>
                            </button>
                        </td>
                        <td><?php echo htmlspecialchars((string) $image->size, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int) $image->width; ?> x <?php echo (int) $image->height; ?> px</td>
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

                panels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-jem-image-panel') === target);
                });
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
