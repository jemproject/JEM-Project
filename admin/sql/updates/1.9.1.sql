ALTER TABLE `#__jem_venues`
	ADD publish_up datetime NOT NULL,
	ADD publish_down datetime NOT NULL,
	CHANGE plz postalCode varchar(20) default NULL;
	
ALTER TABLE `#__jem_groups`
	ADD addvenue int(11) NOT NULL,
	ADD addevent int(11) NOT NULL,
	ADD publishvenue int(11) NOT NULL,
	ADD editvenue int(11) NOT NULL;
	
ALTER TABLE `#__jem_countries`
	CHANGE name name varchar(100) NOT NULL;

UPDATE `#__jem_countries` SET name = 'Afghanistan, Islamic Republic of' WHERE id = 1;
UPDATE `#__jem_countries` SET name = 'Aland Islands' WHERE id = 2;
UPDATE `#__jem_countries` SET name = 'Algeria, People''s Democratic Republic of' WHERE id = 4;
UPDATE `#__jem_countries` SET name = 'Antarctica' WHERE id = 9;
UPDATE `#__jem_countries` SET name = 'Bahamas, Commonwealth of The' WHERE id = 17;
UPDATE `#__jem_countries` SET name = 'Bangladesh, People''s Republic of' WHERE id = 19;
UPDATE `#__jem_countries` SET name = 'Bermuda, Bermuda Islands' WHERE id = 25;
UPDATE `#__jem_countries` SET name = 'Bolivia, Plurinational State of Bolivia' WHERE id = 27;
UPDATE `#__jem_countries` SET name = 'Bouvet Island' WHERE id = 30;
UPDATE `#__jem_countries` SET name = 'Virgin Islands (British), British Virgin Islands' WHERE id = 33;
UPDATE `#__jem_countries` SET name = 'Cayman Islands, The' WHERE id = 42;
UPDATE `#__jem_countries` SET name = 'Congo, Democratic Republic of the' WHERE id = 51;
UPDATE `#__jem_countries` SET name = 'Cote d''Ivoire (Ivory Coast), Republic of' WHERE id = 55;
UPDATE `#__jem_countries` SET name = 'Ethiopia, Federal Democratic Republic of' WHERE id = 70;
UPDATE `#__jem_countries` SET name = 'Faroe Islands, The' WHERE id = 71;
UPDATE `#__jem_countries` SET name = 'Falkland Islands (Malvinas), The' WHERE id = 72;
UPDATE `#__jem_countries` SET name = 'Fiji, Republic of the Fiji' WHERE id = 73;
UPDATE `#__jem_countries` SET name = 'Gambia, Republic of The' WHERE id = 80;
UPDATE `#__jem_countries` SET name = 'Guernsey' WHERE id = 91;
UPDATE `#__jem_countries` SET name = 'Guyana, Co-operative Republic of' WHERE id = 94;
UPDATE `#__jem_countries` SET name = 'Vatican City, State of the Vatican City' WHERE id = 97;
UPDATE `#__jem_countries` SET name = 'Hong Kong' WHERE id = 99;
UPDATE `#__jem_countries` SET name = 'Hungary' WHERE id = 100;
UPDATE `#__jem_countries` SET name = 'North Korea, Democratic People''s Republic of Korea' WHERE id = 117;
UPDATE `#__jem_countries` SET name = 'South Korea, Republic of Korea' WHERE id = 118;
UPDATE `#__jem_countries` SET name = 'Kyrgyzstan, Kyrgyz Republic' WHERE id = 120;
UPDATE `#__jem_countries` SET name = 'Laos, Lao People''s Democratic Republic' WHERE id = 121;
UPDATE `#__jem_countries` SET name = 'Lebanon, Republic of' WHERE id = 123;
UPDATE `#__jem_countries` SET name = 'Libya' WHERE id = 126;
UPDATE `#__jem_countries` SET name = 'Macao, The Macao Special Administrative Region' WHERE id = 130;
UPDATE `#__jem_countries` SET name = 'Macedonia, The Former Yugoslav Republic of' WHERE id = 131;
UPDATE `#__jem_countries` SET name = 'Marshall Islands, Republic of the' WHERE id = 138;
UPDATE `#__jem_countries` SET name = 'Mauritania, Islamic Republic of' WHERE id = 140;
UPDATE `#__jem_countries` SET name = 'Micronesia, Federated States of' WHERE id = 144;
UPDATE `#__jem_countries` SET name = 'Montenegro' WHERE id = 148;
UPDATE `#__jem_countries` SET name = 'Myanmar (Burma), Republic of the Union of Myanmar' WHERE id = 152;
UPDATE `#__jem_countries` SET name = 'Nepal, Federal Democratic Republic of' WHERE id = 155;
UPDATE `#__jem_countries` SET name = 'Northern Mariana Islands' WHERE id = 165;
UPDATE `#__jem_countries` SET name = 'Palestine, State of Palestine (or Occupied Palestinian Territory)' WHERE id = 170;
UPDATE `#__jem_countries` SET name = 'Papua New Guinea, Independent State of' WHERE id = 172;
UPDATE `#__jem_countries` SET name = 'Pitcairn' WHERE id = 176;
UPDATE `#__jem_countries` SET name = 'Russia, Russian Federation' WHERE id = 183;
UPDATE `#__jem_countries` SET name = 'Saint Helena, Ascension and Tristan da Cunha' WHERE id = 186;
UPDATE `#__jem_countries` SET name = 'Saint Kitts and Nevis, Federation of Saint Christopher and Nevis' WHERE id = 187;
UPDATE `#__jem_countries` SET name = 'Saint Vincent and the Grenadines' WHERE id = 191;
UPDATE `#__jem_countries` SET name = 'Sao Tome and Principe, Democratic Republic of' WHERE id = 194;
UPDATE `#__jem_countries` SET name = 'Slovakia, Slovak Republic' WHERE id = 201;
UPDATE `#__jem_countries` SET name = 'South Georgia and the South Sandwich Islands' WHERE id = 206;
UPDATE `#__jem_countries` SET name = 'Sri Lanka, Democratic Socialist Republic of' WHERE id = 208;
UPDATE `#__jem_countries` SET name = 'Sudan, Republic of the' WHERE id = 209;
UPDATE `#__jem_countries` SET name = 'Svalbard and Jan Mayen' WHERE id = 211;
UPDATE `#__jem_countries` SET name = 'Switzerland, Swiss Confederation' WHERE id = 214;
UPDATE `#__jem_countries` SET name = 'Syria, Syrian Arab Republic' WHERE id = 215;
UPDATE `#__jem_countries` SET name = 'Taiwan, Republic of China (Taiwan)' WHERE id = 216;
UPDATE `#__jem_countries` SET name = 'Timor-Leste (East Timor), Democratic Republic of' WHERE id = 220;
UPDATE `#__jem_countries` SET name = 'Tunisia, Republic of' WHERE id = 225;
UPDATE `#__jem_countries` SET name = 'United Kingdom, United Kingdom of Great Britain and Northern Ireland' WHERE id = 233;
UPDATE `#__jem_countries` SET name = 'United States, United States of America' WHERE id = 234;
UPDATE `#__jem_countries` SET name = 'United States Minor Outlying Islands' WHERE id = 235;
UPDATE `#__jem_countries` SET name = 'Virgin Islands (US), Virgin Islands of the United States' WHERE id = 236;
UPDATE `#__jem_countries` SET name = 'Yemen, Republic of' WHERE id = 244;
UPDATE `#__jem_countries` SET name = 'Zimbabwe, Republic of' WHERE id = 246;

INSERT IGNORE INTO `#__jem_countries` (`id`, `continent`, `iso2`, `iso3`, `un`, `name`) VALUES
(247, 'NA', 'BQ', 'BES', 535, 'Bonaire, Sint Eustatius and Saba'),
(248, 'NA', 'CW', 'CUW', 531, 'Curacao'),
(249, 'NA', 'SX', 'SXM', 534, 'Sint Maarten'),
(250, 'AF', 'SS', 'SSD', 728, 'South Sudan, Republic of'),
(251, 'EU', 'XK', 'XKX', '',  'Kosovo, Republic of');

DELETE FROM `#__jem_countries` WHERE id = 156;