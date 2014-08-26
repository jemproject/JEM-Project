window.addEvent('domready', function() {
	/* categories filtering */
	$$('.eventCat').each(
		function(item, index) {
			item.addEvent( 'click', function() {
				$$('.jlcalendar .'+item.getProperty('id')).each(
					function(eventcat) {
						if ( eventcat.getStyle('display') == 'none' ) {
							eventcat.setStyle('display', 'block');
							item.removeClass('catoff');
						}
						else {
							eventcat.setStyle('display', 'none');
							item.addClass('catoff');
						}
					}
				);
			});
		}
	);

	/* Show all */
	btn = $('buttonshowall');
	if (btn) {
		btn.addEvent( 'click', function() {
			$$('.jlcalendar .eventcontent').each(
				function(eventcat) {
					el = eventcat.getElements('div[class^=cat]');
					el.setStyle('display', 'block');
				}
			);

			$$('#jlcalendarlegend .eventCat').each(
				function(eventcat) {
					eventcat.removeClass('catoff');
				}
			);
		});
	}

	/* Hide all */
	btn = $('buttonhideall');
	if (btn) {
		btn.addEvent( 'click', function() {
			$$('.jlcalendar .eventcontent').each(
				function(eventcat) {
					el = eventcat.getElements('div[class^=cat]');
					el.setStyle('display', 'none');
				}
			);

			$$('#jlcalendarlegend .eventCat').each(
				function(eventcat) {
					eventcat.addClass('catoff');
				}
			);
		});
	}
});