function lookupGeoData() {
	myGeoPositionGeoPicker({
		returnFieldMap: {
			'jform_latitude'		: '<LAT>',
			'jform_longitude'		: '<LNG>',
			'jform_street'			: '<STREET> <STREETNUMBER>',
			'jform_postalCode'		: '<POSTALCODE>',
			'jform_city'			: '<CITY>',
			'jform_state' 			: '<STATE_LONG>',
			'jform_country' 		: '<COUNTRY>'
		},
	});
}

function lookupGeoData2() {
	myGeoPositionGeoPicker({
		returnFieldMap: {
			'latitude'				: '<LAT>',
			'longitude'				: '<LNG>',
			'street'				: '<STREET> <STREETNUMBER>',
			'postalCode'			: '<POSTALCODE>',
			'city'					: '<CITY>',
			'state' 				: '<STATE_LONG>',
			'country' 				: '<COUNTRY>'
		},
	});
}