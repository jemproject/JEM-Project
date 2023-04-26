// window.addEvent('domready', function() {
jQuery(document).ready(function($){
	
	/* categories filtering */
	$('.eventCat').each(
		function(index,item) {
			
			$(item).on( 'click', function() {
				$('.jlcalendar .'+$(item).attr('id')).each(
					function(index,eventcat) {
						eventcat = $(eventcat);
						if ( eventcat.css('display') == 'none' ) {
							eventcat.css('display', 'block');
							$(item).removeClass('catoff');
						}
						else {
							eventcat.css('display', 'none');
							$(item).addClass('catoff');
						}
					}
				);
			});
		}
	);

	/* Show all */
	btn = $('#buttonshowall');
	if (btn) {
		btn.on( 'click', function() {
			$('.jlcalendar .eventcontent').each(
				function(index,eventcat) {				
					el = $(eventcat).find('div[class^=cat]');
					el.css('display', 'block');
				}
			);

			$('#jlcalendarlegend .eventCat').each(
				function(index,eventcat) {
					$(eventcat).removeClass('catoff');
				}
			);
		});
	}

	/* Hide all */
	btn = $('#buttonhideall');
	if (btn) {
		btn.on( 'click', function() {
			$('.jlcalendar .eventcontent').each(
				function(index,eventcat) {
					el = $(eventcat).find('div[class^=cat]');
					el.css('display', 'none');
				}
			);

			$('#jlcalendarlegend .eventCat').each(
				function(index,eventcat) {
					$(eventcat).addClass('catoff');
				}
			);
		});
	}
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl,{html:true})
})
});