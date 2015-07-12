/*
highlight v4

Highlights arbitrary terms.

<http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html>

MIT license.

Johann Burkard
<http://johannburkard.de>
<mailto:jb@eaio.com>

To highlight all occurrances of "bla" (case insensitive) in all li elements, use the following code:
	$('li').highlight('bla');

Remove highlighting
	The highlight can be removed from any element with the removeHighlight function.
	In this example, all highlights under the element with the ID highlight-plugin are removed.

	$('#highlight-plugin').removeHighlight();
*/

$.fn.highlight = function(pat) {
	function innerHighlight(node, pat) {
		var skip = 0;
		if (node.nodeType == 3) {
			var pos = node.data.toUpperCase().indexOf(pat);
			if (pos >= 0) {
				var spannode = document.createElement('span');
				spannode.className = 'highlight';
				var middlebit = node.splitText(pos);
				var endbit = middlebit.splitText(pat.length);
				var middleclone = middlebit.cloneNode(true);
				spannode.appendChild(middleclone);
				middlebit.parentNode.replaceChild(spannode, middlebit);
				skip = 1;
			}
		}
		else if (node.nodeType == 1 && node.childNodes && !/(script|style)/i.test(node.tagName)) {
			for (var i = 0; i < node.childNodes.length; ++i) {
				i += innerHighlight(node.childNodes[i], pat);
			}
		}
		return skip;
	}
	return this.length && pat && pat.length ? this.each(function() {
		innerHighlight(this, pat.toUpperCase());
	}) : this;
};

$.fn.removeHighlight = function() {
	return this.find("span.highlight").each(function() {
		this.parentNode.firstChild.nodeName;
		with (this.parentNode) {
			replaceChild(this.firstChild, this);
			normalize();
		}
	}).end();
};

function highlightevents() {
	var searcharea = $("#search_in_here");
	var filterOptionValue = $('select[name=filter_type]').val(); // value of selected option
	var filterOptionText  = $("#filter_type option:selected").text(); // text of selected option
	var lowerCase  = filterOptionText.toLowerCase();
	
	switch(parseInt(filterOptionValue))
	{
	case 1:
		var filter = 'eventtitle';
		break;
	case 2:
		var filter = 'venue';
		break;
	case 3:
		var filter = 'city';
		break;
	case 4:
		var filter = 'category';
		break;
	case 5:
		var filter = 'state';
		break;
	case 6:
		var filter = 'country';
		break;
	case 7:
		alleventfilters();
		break;
	default:
		alleventfilters();
		break;
	}
	
	var newtext = $("#filter_search").val();
	var newtext2 = "td."+ filter +":contains(" + newtext + ")";

	if (filter && newtext) {
		searcharea.find(newtext2).addClass('red');
		$(searcharea.find(newtext2)).highlight(newtext);
	}
}

function alleventfilters() {
	var searcharea 	= $("#search_in_here");
	var keyword		= $("#filter_search").val();
	var title		= 'eventtitle';
	var venue		= 'venue';
	var city		= 'city';
	var state		= 'state';
	var country		= 'country';
	var category	= 'category';

	if (keyword) {
		var titleparameter	= "td."+ title +":contains(" + keyword + ")";
		searcharea.find(titleparameter).addClass('red');
		$(searcharea.find(titleparameter)).highlight(keyword);

		var venueparameter = "td."+ venue +":contains(" + keyword + ")";
		searcharea.find(venueparameter).addClass('red');
		$(searcharea.find(venueparameter)).highlight(keyword);

		var cityparameter	= "td."+ city +":contains(" + keyword + ")";
		searcharea.find(cityparameter).addClass('red');
		$(searcharea.find(cityparameter)).highlight(keyword);

		var stateparameter	= "td."+ state +":contains(" + keyword + ")";
		searcharea.find(stateparameter).addClass('red');
		$(searcharea.find(stateparameter)).highlight(keyword);

		var countryparameter = "td."+ country +":contains(" + keyword + ")";
		searcharea.find(countryparameter).addClass('red');
		$(searcharea.find(countryparameter)).highlight(keyword);

		var categoryparameter = "td."+ category +":contains(" + keyword + ")";
		searcharea.find(categoryparameter).addClass('red');
		$(searcharea.find(categoryparameter)).highlight(keyword);
	}
}


function highlightvenues() {
	var searcharea = $("#search_in_here");
	var filterOptionValue = $('select[name=filter_type]').val(); // value of selected option
	var filterOptionText  = $("#filter_type option:selected").text(); // text of selected option
	var lowerCase  = filterOptionText.toLowerCase();
	
	switch(parseInt(filterOptionValue))
	{
	case 1:
		var filter = 'venue';
		break;
	case 2:
		var filter = 'city';
		break;
	case 3:
		var filter = 'state';
		break;
	case 4:
		var filter = 'country';
		break;
	case 5:
		allvenuefilters();
		break;
	default:
		allvenuefilters();
		break;
	}

	var newtext = $("#filter_search").val();
	var newtext2 = "td."+ filter +":contains(" + newtext + ")";

	if (filter && newtext) {
		searcharea.find(newtext2).addClass('red');
		$(searcharea.find(newtext2)).highlight(newtext);
	}
}

function allvenuefilters() {
	var searcharea	= $("#search_in_here");
	var keyword		= $("#filter_search").val();
	var title		= 'venue';
	var city		= 'city';
	var state		= 'state';
	var country		= 'country';

	if (keyword) {
		var titleparameter = "td."+ title +":contains(" + keyword + ")";
		searcharea.find(titleparameter).addClass('red');
		$(searcharea.find(titleparameter)).highlight(keyword);

		var cityparameter = "td."+ city +":contains(" + keyword + ")";
		searcharea.find(cityparameter).addClass('red');
		$(searcharea.find(cityparameter)).highlight(keyword);

		var stateparameter = "td."+ state +":contains(" + keyword + ")";
		searcharea.find(stateparameter).addClass('red');
		$(searcharea.find(stateparameter)).highlight(keyword);

		var countryparameter = "td."+ country +":contains(" + keyword + ")";
		searcharea.find(countryparameter).addClass('red');
		$(searcharea.find(countryparameter)).highlight(keyword);
	}
}