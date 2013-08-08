function lookupGeoData15() {            
            myGeoPositionGeoPicker({
                returnFieldMap            : {
                                          'jform_latitude'        :    '<LAT>',
                                          'jform_longitude'        :    '<LNG>',
                                          'jform_street'        :    '<STREET> <STREETNUMBER>',
                                          'jform_plz'        :    '<POSTALCODE>',
                                          'jform_city'        :    '<CITY>',
                                          'jform_state' : '<STATE_LONG>',
                                          'jform_country' : '<COUNTRY>'
                                          },
            });
        }