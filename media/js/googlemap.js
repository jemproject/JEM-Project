function initialize() {

	/* coordinates */
	var lat			= document.getElementById('latitude').value;
	var long		= document.getElementById('longitude').value;

	/* address */
	var title		= document.getElementById('venue').value;
	var city		= document.getElementById('city').value;
	var postalCode	= document.getElementById('postalCode').value;
	var street		= document.getElementById('street').value;

	var myLatlng = new google.maps.LatLng(lat,long);

	var myMapOptions = {
			 zoom: 16
			,center: myLatlng
			,mapTypeId: google.maps.MapTypeId.ROADMAP
			,disableDefaultUI: false
		};

	var map = new google.maps.Map(document.getElementById('map-canvas'), myMapOptions);

	// check for zero
	if (lat == 0.000000) {
		lat = null;
	}

	if (long == 0.000000) {
		long = null;
	}

	/* check to see if we've lat+long
	/* if we've it then we can use the coordinates to center the map
	 */
	if (lat && long) {
		var marker = new google.maps.Marker({
		      position: myLatlng,
		      map: map,
		      title: title,
		      visible: true,
		      icon: "https://chart.apis.google.com/chart?chst=d_map_pin_letter_withshadow&chld=•|3491FF|000000"
		  });

		var boxText = document.createElement("div");
	    boxText.style.cssText = "border: 1px solid black; margin-top: 8px; background: yellow; padding: 5px;";
	    boxText.innerHTML = "<b>"+title+"</b><br>"+street+"<br>"+postalCode+"<br>"+city;

		var myOptions = {
				content: boxText
				,disableAutoPan: false
				,maxWidth: 0
				,pixelOffset: new google.maps.Size(-140, -120)
				,zIndex: null
				,boxStyle: {
				  background: "url('tipbox.gif') no-repeat"
				  ,opacity: 0.75
				  ,width: "280px"
				 }
				,closeBoxMargin: "10px 2px 2px 2px"
				,closeBoxURL: "https://www.google.com/intl/en_us/mapfiles/close.gif"
				,infoBoxClearance: new google.maps.Size(1, 1)
				,isHidden: false
				,pane: "floatPane"
				,enableEventPropagation: false
		};


		google.maps.event.addListener(marker, "click", function (e) {
			ib.open(map, this);
		});

		var ib = new InfoBox(myOptions);
		// ib.open(map, marker);  /* disabled infobox to popup at default */

	} else {

		geocoder = new google.maps.Geocoder();
		var address = street+","+postalCode+","+city;

		geocoder.geocode( { 'address': address}, function(results, status) {
		      if (status == google.maps.GeocoderStatus.OK) {
		    	  // Geocoding-status is ok, but we want to retrieve an exact address

		    	  if (results[0].geometry.location_type){

		    		  	//In this case it creates a marker, but you can get the lat and lng from the location.LatLng
				        map.setCenter(results[0].geometry.location);

				        var marker = new google.maps.Marker({
				            map: map,
				            position: results[0].geometry.location,
				            icon: "https://chart.apis.google.com/chart?chst=d_map_pin_letter_withshadow&chld=•|3491FF|000000"
				        });

				        var boxText = document.createElement("div");
				        boxText.style.cssText = "border: 1px solid black; margin-top: 8px; background: yellow; padding: 5px;";
				        boxText.innerHTML = "<b>"+title+"</b><br>"+street+"<br>"+postalCode+"<br>"+city;

				    	var myOptions = {
				    			content: boxText
				    			,disableAutoPan: false
				    			,maxWidth: 0
				    			,pixelOffset: new google.maps.Size(-140, -120)
				    			,zIndex: null
				    			,boxStyle: {
				    			  background: "url('tipbox.gif') no-repeat"
				    			  ,opacity: 0.75
				    			  ,width: "280px"
				    			 }
				    			,closeBoxMargin: "10px 2px 2px 2px"
				    			,closeBoxURL: "https://www.google.com/intl/en_us/mapfiles/close.gif"
				    			,infoBoxClearance: new google.maps.Size(1, 1)
				    			,isHidden: false
				    			,pane: "floatPane"
				    			,enableEventPropagation: false
				    	};


				    	google.maps.event.addListener(marker, "click", function (e) {
				    		ib.open(map, this);
				    	});

				    	var ib = new InfoBox(myOptions);
				    	// ib.open(map, marker); /* disabled infobox to popup at default */
		    	  } else {
		    		  error();
		    	  }
		      } else {
		        /*alert("Geocode was not successful for the following reason: " + status);*/
		    	error();
		      }
		    });
	}

}


function error() {
	/*var text = "<div style='text-align: center;'><h1 >Attention!</h1><p>no valid data to load the map</p></div>";*/
	var text = "";
	document.getElementById('map-canvas').innerHTML=text;
}


google.maps.event.addDomListener(window, 'load', initialize);
