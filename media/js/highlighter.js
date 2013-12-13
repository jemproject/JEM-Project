/*
highlight v4

Highlights arbitrary terms.

<http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html>

MIT license.

Johann Burkard
<http://johannburkard.de>
<mailto:jb@eaio.com>

*/

jQuery.fn.highlight = function(pat) {
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

jQuery.fn.removeHighlight = function() {
	return this.find("span.highlight").each(function() {
		this.parentNode.firstChild.nodeName;
		with (this.parentNode) {
			replaceChild(this.firstChild, this);
			normalize();
		}
	}).end();
};

function highlightevents() {
	var elements = $('seach_in_here');
	var g = $('filter');
	var filtertext = g.options[g.selectedIndex].text.toLowerCase();
	var filtervalue = g.options[g.selectedIndex].value;

	switch(filtervalue)
	{
	case '1':
		var filter = 'eventtitle';
		break;
	case '2':
		var filter = 'city';
		break;
	case '3':
		var filter = 'state';
		break;
	case '4':
		var filter = 'country';
		break;
	case '5':
		var filter = 'category';
		break;
	case '6':
		alleventfilters();
		break;
	}

	var newtext = $('filter_search').value;
	var newtext2 = "td."+ filter +":contains(" + newtext + ")";

	elements.getElements(newtext2).addClass('red');
	jQuery(elements.getElements(newtext2)).highlight(newtext);
}

function alleventfilters() {
	var elements = $('seach_in_here');

	var title = 'eventtitle';
	var city = 'city';
	var state = 'state';
	var country = 'country';
	var category = 'category';

	var titlekeyword = $('filter_search').value;
	var titleparameter = "td."+ title +":contains(" + titlekeyword + ")";
	elements.getElements(titleparameter).addClass('red');
	jQuery(elements.getElements(titleparameter)).highlight(titlekeyword);

	var citykeyword = $('filter_search').value;
	var cityparameter = "td."+ city +":contains(" + citykeyword + ")";
	elements.getElements(cityparameter).addClass('red');
	jQuery(elements.getElements(cityparameter)).highlight(citykeyword);

	var statekeyword = $('filter_search').value;
	var stateparameter = "td."+ state +":contains(" + statekeyword + ")";
	elements.getElements(stateparameter).addClass('red');
	jQuery(elements.getElements(stateparameter)).highlight(statekeyword);

	var countrykeyword = $('filter_search').value;
	var countryparameter = "td."+ country +":contains(" + countrykeyword + ")";
	elements.getElements(countryparameter).addClass('red');
	jQuery(elements.getElements(countryparameter)).highlight(countrykeyword);
	
	var categorykeyword = $('filter_search').value;
	var categoryparameter = "td."+ category +":contains(" + categorykeyword + ")";
	elements.getElements(categoryparameter).addClass('red');
	jQuery(elements.getElements(categoryparameter)).highlight(categorykeyword);
}


function highlightvenues() {
	var elements = $('seach_in_here');
	var g = $('filter');
	var filtertext = g.options[g.selectedIndex].text.toLowerCase();
	var filtervalue = g.options[g.selectedIndex].value;

	switch(filtervalue)
	{
	case '1':
		var filter = 'venue';
		break;
	case '2':
		var filter = 'city';
		break;
	case '3':
		var filter = 'state';
		break;
	case '4':
		var filter = 'country';
		break;
	case '5':
		allvenuefilters();
		break;
	}

	var newtext = $('filter_search').value;
	var newtext2 = "td."+ filter +":contains(" + newtext + ")";

	elements.getElements(newtext2).addClass('red');
	jQuery(elements.getElements(newtext2)).highlight(newtext);
}

function allvenuefilters() {
	var elements = $('seach_in_here');

	var title = 'venue';
	var city = 'city';
	var state = 'state';
	var country = 'country';

	var titlekeyword = $('filter_search').value;
	var titleparameter = "td."+ title +":contains(" + titlekeyword + ")";
	elements.getElements(titleparameter).addClass('red');
	jQuery(elements.getElements(titleparameter)).highlight(titlekeyword);

	var citykeyword = $('filter_search').value;
	var cityparameter = "td."+ city +":contains(" + citykeyword + ")";
	elements.getElements(cityparameter).addClass('red');
	jQuery(elements.getElements(cityparameter)).highlight(citykeyword);

	var statekeyword = $('filter_search').value;
	var stateparameter = "td."+ state +":contains(" + statekeyword + ")";
	elements.getElements(stateparameter).addClass('red');
	jQuery(elements.getElements(stateparameter)).highlight(statekeyword);

	var countrykeyword = $('filter_search').value;
	var countryparameter = "td."+ country +":contains(" + countrykeyword + ")";
	elements.getElements(countryparameter).addClass('red');
	jQuery(elements.getElements(countryparameter)).highlight(countrykeyword);
}