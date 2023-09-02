/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

function changeoldMode() {
    if (document.getElementById) {
        mode = window.document.adminForm.oldevent.selectedIndex;
        switch (mode) {
            case 0:
                document.getElementById('old').style.display = 'none';
                break;
            default:
                document.getElementById('old').style.display = '';
        }
    }
}

function changeintegrateMode() {
    if (document.getElementById) {
        mode = window.document.adminForm.event_comunsolution.selectedIndex;
        switch (mode) {
            case 0:
                document.getElementById('integrate').style.display = 'none';
                break;
            default:
                document.getElementById('integrate').style.display = '';
        }
    }
}

function changegdMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('gd1').style.display = 'none';
                break;
            default:
                document.getElementById('gd1').style.display = '';
        }
    }
}

function changemapMode() {
    if (document.getElementById) {
        mode = window.document.adminForm.showmapserv.selectedIndex;
        switch (mode) {
            case 0:
                document.getElementById('tld').style.display = 'none';
                document.getElementById('lg').style.display = 'none';
                break;
            case 1:
                document.getElementById('tld').style.display = '';
                document.getElementById('lg').style.display = '';
                break;
            case 2:
                document.getElementById('tld').style.display = '';
                document.getElementById('lg').style.display = '';
                break;
            default:
                document.getElementById('tld').style.display = '';
                document.getElementById('lg').style.display = '';
        }
    }
}

function changetitleMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('title1').style.display = 'none';
                document.adminForm.titlewidth.value = '';
                break;
            default:
                document.getElementById('title1').style.display = '';
        }
    }
}

function changelocateMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('locate1').style.display = 'none';
                document.adminForm.locationwidth.value = '';
                document.getElementById('locate2').style.display = 'none';
                break;
            default:
                document.getElementById('locate1').style.display = '';
                document.getElementById('locate2').style.display = '';
        }
    }
}

function changecityMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('city1').style.display = 'none';
                document.adminForm.citywidth.value = '';
                break;
            default:
                document.getElementById('city1').style.display = '';
        }
    }
}

function changestateMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('state1').style.display = 'none';
                document.adminForm.statewidth.value = '';
                break;
            default:
                document.getElementById('state1').style.display = '';
        }
    }
}

function changecatMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('cat1').style.display = 'none';
                document.adminForm.catfrowidth.value = '';
                document.getElementById('cat2').style.display = 'none';
                break;
            default:
                document.getElementById('cat1').style.display = '';
                document.getElementById('cat2').style.display = '';
        }
    }
}

function changeatteMode(mode) {
    if (document.getElementById) {
        switch (mode) {
            case 0:
                document.getElementById('atte1').style.display = 'none';
                document.adminForm.attewidth.value = '';
                document.getElementById('atte2').style.display = 'none';
                break;
            default:
                document.getElementById('atte1').style.display = '';
                document.getElementById('atte2').style.display = '';
        }
    }
}

function changeregMode() {
    if (document.getElementById) {
        mode = window.document.adminForm.showfroregistra.selectedIndex;
        switch (mode) {
            case 0:
                document.getElementById('froreg').style.display = 'none';
                break;
            default:
                document.getElementById('froreg').style.display = '';
        }
    }
}

document.switcher = null;

if (MooTools.version == '1.11') {
    window.onDomReady(function () {
        toggler = $('submenu');
        element = $('elconfig-document');
        if (element) {
            document.switcher = new JSwitcher(toggler, element, {cookieName: toggler.getAttribute('class')});
        }
    });
} else {
    // window.addEvent('domready', function() {
    jQuery(document).ready(function ($) {

        toggler = $('submenu');
        element = $('elconfig-document');
        if (element) {
            document.switcher = new JSwitcher(toggler, element, {cookieName: toggler.getAttribute('class')});
        }
    });
}
