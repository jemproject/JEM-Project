<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$input = Factory::getApplication()->input;
$function = $input->getCmd('function', 'jSelectArticle');
$articleTitle = $input->getString('article_title', '');
$articleCatid = $input->getInt('article_catid', 0);
$jemcats = $input->getString('jemcats', '');
Factory::getDocument()->setTitle(Text::_('COM_JEM_SELECT_ARTICLE'));
$cssSettings = JemHelper::retrieveCss();
$filterBackground = $cssSettings->get('css_color_bg_filter');
$filterBorder = $cssSettings->get('css_color_border_filter');
$filterStyle = array();

if (!empty($filterBackground)) {
    $filterStyle[] = 'background-color: ' . htmlspecialchars($filterBackground, ENT_QUOTES, 'UTF-8') . ' !important';
}

if (!empty($filterBorder)) {
    $filterStyle[] = 'border-color: ' . htmlspecialchars($filterBorder, ENT_QUOTES, 'UTF-8') . ' !important';
}
?>

<script>
    if (window.parent && window.parent.document) {
        var modalTitle = window.parent.document.querySelector('.modal.show .modal-title, .joomla-modal.show .modal-title');
        if (modalTitle) {
            modalTitle.textContent = "<?php echo $this->escape(Text::_('COM_JEM_SELECT_ARTICLE')); ?>";
        }
    }

    function tableOrdering(order, dir, view)
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value = order;
        form.filter_order_Dir.value = dir;
        form.submit(view);
    }
</script>

<style>
    #jem.jem_select_article {
        padding: 1rem;
    }

    #jem.jem_select_article #jem_filter {
        display: grid !important;
        grid-template-columns: auto auto minmax(3rem, 1fr) auto auto auto auto auto;
        align-items: center;
        gap: .4rem;
        margin: 0 0 1rem;
        padding: .65rem;
        border-radius: .25rem;
        width: 100%;
        box-sizing: border-box;
        white-space: nowrap;
    }

    #jem.jem_select_article #jem_filter .jem_fleft,
    #jem.jem_select_article #jem_filter .jem_fright {
        display: contents !important;
        float: none;
        width: auto !important;
        margin: 0 !important;
    }

    #jem.jem_select_article #filter_search {
        width: 100%;
        min-width: 3rem;
        max-width: none;
    }

    #jem.jem_select_article #jem_filter select {
        width: auto;
        min-width: 4.75rem;
        max-width: 7rem;
        padding-left: .4rem;
        padding-right: 1.75rem !important;
        background-position: right .5rem center !important;
        background-size: 1rem auto !important;
    }

    #jem.jem_select_article #jem_filter select#limit {
        min-width: 4.25rem;
        max-width: 4.75rem;
    }

    #jem.jem_select_article #jem_filter .btn,
    #jem.jem_select_article #jem_filter button {
        padding-left: .55rem;
        padding-right: .55rem;
        white-space: nowrap;
        width: auto !important;
    }

    #jem.jem_select_article #jem_filter label {
        margin: 0;
        white-space: nowrap;
    }

    #jem.jem_select_article .jem-create-associated-article {
        display: grid;
        grid-template-columns: auto minmax(12rem, 1fr) auto;
        gap: .5rem;
        align-items: center;
        margin: 0 0 1rem;
        padding: .65rem;
        border: 1px solid #d8dee8;
        border-radius: .25rem;
        background: #f6f8fb;
    }

    #jem.jem_select_article .jem-create-associated-article label {
        margin: 0;
        font-weight: 700;
    }

    #jem.jem_select_article .jem-create-associated-article input[type="text"] {
        width: 100%;
        min-width: 10rem;
    }

    @media (max-width: 560px) {
        #jem.jem_select_article #jem_filter {
            display: flex;
            flex-wrap: wrap;
            white-space: normal;
        }

        #jem.jem_select_article #jem_filter .jem_fleft,
        #jem.jem_select_article #jem_filter .jem_fright {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
            flex: 1 1 100%;
        }

        #jem.jem_select_article #filter_search {
            flex: 1 1 100%;
        }

        #jem.jem_select_article .jem-create-associated-article {
            grid-template-columns: 1fr;
        }
    }
</style>

<div id="jem" class="jem_select_article">
    <div class="clr"></div>

    <form action="<?php echo Route::_('index.php?option=com_jem&task=event.createAssociatedArticle&tmpl=component'); ?>" method="post" class="jem-create-associated-article">
        <label for="jem_article_title"><?php echo Text::_('JGLOBAL_TITLE'); ?></label>
        <input type="text" name="article_title" id="jem_article_title" value="<?php echo htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'); ?>" required>
        <button type="submit" class="btn btn-success"><?php echo Text::_('JACTION_CREATE') . ' / ' . Text::_('COM_JEM_SELECT'); ?></button>
        <input type="hidden" name="article_catid" value="<?php echo (int) $articleCatid; ?>">
        <input type="hidden" name="jemcats" value="<?php echo htmlspecialchars($jemcats, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosearticle&tmpl=component&function=' . $this->escape($function) . '&' . Session::getFormToken() . '=1'); ?>" method="post" name="adminForm" id="adminForm">
        <div id="jem_filter" class="floattext"<?php echo $filterStyle ? ' style="' . implode('; ', $filterStyle) . '"' : ''; ?>>
            <div class="jem_fleft">
                <?php
                echo '<label for="filter_type">' . Text::_('COM_JEM_FILTER') . '</label>';
                echo $this->searchfilter;
                ?>
                <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" onchange="document.adminForm.submit();" />
                <button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                <button type="button" class="pointer btn btn-primary" onclick="if (window.parent && typeof window.parent[<?php echo htmlspecialchars(json_encode($function), ENT_QUOTES, 'UTF-8'); ?>] === 'function') window.parent[<?php echo htmlspecialchars(json_encode($function), ENT_QUOTES, 'UTF-8'); ?>](0, '<?php echo $this->escape(addslashes(Text::_('COM_JEM_SELECT_ARTICLE'))); ?>');"><?php echo Text::_('COM_JEM_NO_ARTICLE'); ?></button>
            </div>
            <div class="jem_fright">
                <?php
                echo '<label for="limit">' . Text::_('COM_JEM_DISPLAY_NUM') . '</label>';
                echo $this->pagination->getLimitBox();
                ?>
            </div>
        </div>

        <table class="eventtable table table-striped" style="width:100%" summary="jem">
            <thead>
            <tr>
                <th style="width: 7px; text-align: left;" class="sectiontableheader"><?php echo Text::_('COM_JEM_NUM'); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'], 'selectarticle'); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'JCATEGORY', 'cat.title', $this->lists['order_Dir'], $this->lists['order'], 'selectarticle'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($this->rows)) : ?>
                <tr style="text-align: center"><td colspan="3"><?php echo Text::_('COM_JEM_NO_ARTICLES'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($this->rows as $i => $row) : ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td><?php echo $this->pagination->getRowOffset($i); ?></td>
                        <td style="text-align: left;">
                            <a class="pointer" onclick="if (window.parent && typeof window.parent[<?php echo htmlspecialchars(json_encode($function), ENT_QUOTES, 'UTF-8'); ?>] === 'function') window.parent[<?php echo htmlspecialchars(json_encode($function), ENT_QUOTES, 'UTF-8'); ?>]('<?php echo (int) $row->id; ?>', '<?php echo $this->escape(addslashes($row->title)); ?>');"><?php echo $this->escape($row->title); ?></a>
                        </td>
                        <td style="text-align: left;"><?php echo $this->escape($row->category_title); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <p>
            <input type="hidden" name="task" value="selectarticle" />
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="tmpl" value="component" />
            <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
            <input type="hidden" name="article_title" value="<?php echo htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="article_catid" value="<?php echo (int) $articleCatid; ?>" />
            <input type="hidden" name="jemcats" value="<?php echo htmlspecialchars($jemcats, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
            <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
        </p>
    </form>

    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>
</div>
