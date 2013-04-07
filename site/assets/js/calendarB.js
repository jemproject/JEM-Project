/*
Add mootools tooltip event, with fading.
*/
window.addEvent('domready', function(){
   //do your tips stuff in here...
   var eventTip = new Tips($$('.eventTip'), {
      className: 'custom', //this is the prefix for the CSS class
      initialize:function(){
         this.fx = new Fx.Style(this.toolTip, 'opacity', {duration: 200, wait: false}).set(0);
      },
      maxTitleChars: 100, 
      onShow: function(toolTip) {
         this.fx.start(1);
      },
      onHide: function(toolTip) {
         this.fx.start(0);
      }
   });

   /* categories filtering */
   $$('.eventCat').each( 
     function(item, index) {
	     item.addEvent( 'click', function() {
			    $$('.jlcalendar .cat'+item.getProperty('catid')).each( 
	          function(eventcat) {
	            if ( eventcat.getStyle('display') == 'none' ) {
	              eventcat.setStyle('display', 'block');
	              item.removeClass('catoff');
	            }
	            else {
	              eventcat.setStyle('display', 'none');
	              item.addClass('catoff');
	            }
	          });
       });
     }
   );
   
   $('buttonshowall').addEvent( 'click', function() {
    $$('.jlcalendar .eventcontent').each( 
      function(eventcat) {
        el = eventcat.getElement('div[class^=cat]');
        el.setStyle('display', 'block');
      });
    $$('#jlcalendarlegend .eventCat').each( 
      function(eventcat) {
        eventcat.removeClass('catoff');
      });
   });
   
   $('buttonhideall').addEvent( 'click', function() {
    $$('.jlcalendar .eventcontent').each( 
      function(eventcat) {
        el = eventcat.getElement('div[class^=cat]');
        el.setStyle('display', 'none');
      });
    $$('#jlcalendarlegend .eventCat').each( 
      function(eventcat) {
        eventcat.addClass('catoff');
      });
   });
});

