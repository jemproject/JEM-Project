<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 *
 * Country list: https://erikastokes.com/mysql-help/mysql-country-table.php
 * Api country checker: https://api.worldbank.org/countries/ss
 *
 * Lat+long finder: https://www.findlatitudeandlongitude.com/?loc=
 * For example:  https://www.findlatitudeandlongitude.com/?loc=Sint+Maarten&id=316082
 *
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

class JemHelperCountries
{
	/**
	 * return countries array indexed by iso3 code
	 *
	 * @return array
	 */
	static public function getCountries()
	{
		$country["AFG"] = array("iso2" => "AF", "name" => "Afghanistan, Islamic Republic of");
		$country["ALA"] = array("iso2" => "AX", "name" => "Aland Islands");
		$country["ALB"] = array("iso2" => "AL", "name" => "Albania, Republic of");
		$country["DZA"] = array("iso2" => "DZ", "name" => "Algeria, People's Democratic Republic of");
		$country["ASM"] = array("iso2" => "AS", "name" => "American Samoa");
		$country["AND"] = array("iso2" => "AD", "name" => "Andorra, Principality of");
		$country["AGO"] = array("iso2" => "AO", "name" => "Angola, Republic of");
		$country["AIA"] = array("iso2" => "AI", "name" => "Anguilla");
		$country["ATA"] = array("iso2" => "AQ", "name" => "Antarctica");
		$country["ATG"] = array("iso2" => "AG", "name" => "Antigua and Barbuda");
		$country["ARG"] = array("iso2" => "AR", "name" => "Argentina, Argentine Republic");
		$country["ARM"] = array("iso2" => "AM", "name" => "Armenia, Republic of");
		$country["ABW"] = array("iso2" => "AW", "name" => "Aruba");
		$country["AUS"] = array("iso2" => "AU", "name" => "Australia, Commonwealth of");
		$country["AUT"] = array("iso2" => "AT", "name" => "Austria, Republic of");
		$country["AZE"] = array("iso2" => "AZ", "name" => "Azerbaijan, Republic of");
		$country["BHS"] = array("iso2" => "BS", "name" => "Bahamas, Commonwealth of The");
		$country["BHR"] = array("iso2" => "BH", "name" => "Bahrain, Kingdom of");
		$country["BGD"] = array("iso2" => "BD", "name" => "Bangladesh, People's Republic of");
		$country["BRB"] = array("iso2" => "BB", "name" => "Barbados");
		$country["BLR"] = array("iso2" => "BY", "name" => "Belarus, Republic of");
		$country["BEL"] = array("iso2" => "BE", "name" => "Belgium, Kingdom of");
		$country["BLZ"] = array("iso2" => "BZ", "name" => "Belize");
		$country["BEN"] = array("iso2" => "BJ", "name" => "Benin, Republic of");
		$country["BMU"] = array("iso2" => "BM", "name" => "Bermuda, Bermuda Islands");
		$country["BTN"] = array("iso2" => "BT", "name" => "Bhutan, Kingdom of");
		$country["BOL"] = array("iso2" => "BO", "name" => "Bolivia, Plurinational State of Bolivia");
		$country["BIH"] = array("iso2" => "BA", "name" => "Bosnia and Herzegovina");
		$country["BWA"] = array("iso2" => "BW", "name" => "Botswana, Republic of");
		$country["BVT"] = array("iso2" => "BV", "name" => "Bouvet Island");
		$country["BRA"] = array("iso2" => "BR", "name" => "Brazil, Federative Republic of");
		$country["IOT"] = array("iso2" => "IO", "name" => "British Indian Ocean Territory");
		$country["VGB"] = array("iso2" => "VG", "name" => "Virgin Islands (British), British Virgin Islands");
		$country["BRN"] = array("iso2" => "BN", "name" => "Brunei Darussalam");
		$country["BGR"] = array("iso2" => "BG", "name" => "Bulgaria, Republic of");
		$country["BFA"] = array("iso2" => "BF", "name" => "Burkina Faso");
		$country["BDI"] = array("iso2" => "BI", "name" => "Burundi, Republic of");
		$country["KHM"] = array("iso2" => "KH", "name" => "Cambodia, Kingdom of");
		$country["CMR"] = array("iso2" => "CM", "name" => "Cameroon, Republic of");
		$country["CAN"] = array("iso2" => "CA", "name" => "Canada");
		$country["CPV"] = array("iso2" => "CV", "name" => "Cape Verde, Republic of");
		$country["CYM"] = array("iso2" => "KY", "name" => "Cayman Islands, The");
		$country["CAF"] = array("iso2" => "CF", "name" => "Central African Republic");
		$country["TCD"] = array("iso2" => "TD", "name" => "Chad, Republic of");
		$country["CHL"] = array("iso2" => "CL", "name" => "Chile, Republic of");
		$country["CHN"] = array("iso2" => "CN", "name" => "China, People's Republic of");
		$country["CXR"] = array("iso2" => "CX", "name" => "Christmas Island");
		$country["CCK"] = array("iso2" => "CC", "name" => "Cocos (Keeling) Islands");
		$country["COL"] = array("iso2" => "CO", "name" => "Colombia, Republic of");
		$country["COM"] = array("iso2" => "KM", "name" => "Comoros, Union of the");
		$country["COD"] = array("iso2" => "CD", "name" => "Congo, Democratic Republic of the");
		$country["COG"] = array("iso2" => "CG", "name" => "Congo, Republic of the");
		$country["COK"] = array("iso2" => "CK", "name" => "Cook Islands");
		$country["CRI"] = array("iso2" => "CR", "name" => "Costa Rica, Republic of");
		$country["CIV"] = array("iso2" => "CI", "name" => "Cote d'Ivoire (Ivory Coast), Republic of");
		$country["HRV"] = array("iso2" => "HR", "name" => "Croatia, Republic of");
		$country["CUB"] = array("iso2" => "CU", "name" => "Cuba, Republic of");
		$country["CYP"] = array("iso2" => "CY", "name" => "Cyprus, Republic of");
		$country["CZE"] = array("iso2" => "CZ", "name" => "Czech Republic");
		$country["DNK"] = array("iso2" => "DK", "name" => "Denmark, Kingdom of");
		$country["DJI"] = array("iso2" => "DJ", "name" => "Djibouti, Republic of");
		$country["DMA"] = array("iso2" => "DM", "name" => "Dominica, Commonwealth of");
		$country["DOM"] = array("iso2" => "DO", "name" => "Dominican Republic");
		$country["ECU"] = array("iso2" => "EC", "name" => "Ecuador, Republic of");
		$country["EGY"] = array("iso2" => "EG", "name" => "Egypt, Arab Republic of");
		$country["SLV"] = array("iso2" => "SV", "name" => "El Salvador, Republic of");
		$country["GNQ"] = array("iso2" => "GQ", "name" => "Equatorial Guinea, Republic of");
		$country["ERI"] = array("iso2" => "ER", "name" => "Eritrea, State of");
		$country["EST"] = array("iso2" => "EE", "name" => "Estonia, Republic of");
		$country["ETH"] = array("iso2" => "ET", "name" => "Ethiopia, Federal Democratic Republic of");
		$country["FRO"] = array("iso2" => "FO", "name" => "Faroe Islands, The");
		$country["FLK"] = array("iso2" => "FK", "name" => "Falkland Islands (Malvinas), The");
		$country["FJI"] = array("iso2" => "FJ", "name" => "Fiji, Republic of the Fiji");
		$country["FIN"] = array("iso2" => "FI", "name" => "Finland, Republic of");
		$country["FRA"] = array("iso2" => "FR", "name" => "France, French Republic");
		$country["GUF"] = array("iso2" => "GF", "name" => "French Guiana");
		$country["PYF"] = array("iso2" => "PF", "name" => "French Polynesia");
		$country["ATF"] = array("iso2" => "TF", "name" => "French Southern Territories");
		$country["GAB"] = array("iso2" => "GA", "name" => "Gabon, Gabonese Republic");
		$country["GMB"] = array("iso2" => "GM", "name" => "Gambia, Republic of The");
		$country["GEO"] = array("iso2" => "GE", "name" => "Georgia");
		$country["DEU"] = array("iso2" => "DE", "name" => "Germany, Federal Republic of");
		$country["GHA"] = array("iso2" => "GH", "name" => "Ghana, Republic of");
		$country["GIB"] = array("iso2" => "GI", "name" => "Gibraltar");
		$country["GRC"] = array("iso2" => "GR", "name" => "Greece, Hellenic Republic");
		$country["GRL"] = array("iso2" => "GL", "name" => "Greenland");
		$country["GRD"] = array("iso2" => "GD", "name" => "Grenada");
		$country["GLP"] = array("iso2" => "GP", "name" => "Guadeloupe");
		$country["GUM"] = array("iso2" => "GU", "name" => "Guam");
		$country["GTM"] = array("iso2" => "GT", "name" => "Guatemala, Republic of");
		$country["GGY"] = array("iso2" => "GG", "name" => "Guernsey");
		$country["GIN"] = array("iso2" => "GN", "name" => "Guinea, Republic of");
		$country["GNB"] = array("iso2" => "GW", "name" => "Guinea-Bissau, Republic of");
		$country["GUY"] = array("iso2" => "GY", "name" => "Guyana, Co-operative Republic of");
		$country["HTI"] = array("iso2" => "HT", "name" => "Haiti, Republic of");
		$country["HMD"] = array("iso2" => "HM", "name" => "Heard Island and McDonald Isla");
		$country["VAT"] = array("iso2" => "VA", "name" => "Vatican City, State of the Vatican City");
		$country["HND"] = array("iso2" => "HN", "name" => "Honduras, Republic of");
		$country["HKG"] = array("iso2" => "HK", "name" => "Hong Kong");
		$country["HUN"] = array("iso2" => "HU", "name" => "Hungary");
		$country["ISL"] = array("iso2" => "IS", "name" => "Iceland, Republic of");
		$country["IND"] = array("iso2" => "IN", "name" => "India, Republic of");
		$country["IDN"] = array("iso2" => "ID", "name" => "Indonesia, Republic of");
		$country["IRN"] = array("iso2" => "IR", "name" => "Iran, Islamic Republic of");
		$country["IRQ"] = array("iso2" => "IQ", "name" => "Iraq, Republic of");
		$country["IRL"] = array("iso2" => "IE", "name" => "Ireland");
		$country["IMN"] = array("iso2" => "IM", "name" => "Isle of Man");
		$country["ISR"] = array("iso2" => "IL", "name" => "Israel, State of");
		$country["ITA"] = array("iso2" => "IT", "name" => "Italy, Italian Republic");
		$country["JAM"] = array("iso2" => "JM", "name" => "Jamaica");
		$country["JPN"] = array("iso2" => "JP", "name" => "Japan");
		$country["JEY"] = array("iso2" => "JE", "name" => "Jersey, Bailiwick of");
		$country["JOR"] = array("iso2" => "JO", "name" => "Jordan, Hashemite Kingdom of");
		$country["KAZ"] = array("iso2" => "KZ", "name" => "Kazakhstan, Republic of");
		$country["KEN"] = array("iso2" => "KE", "name" => "Kenya, Republic of");
		$country["KIR"] = array("iso2" => "KI", "name" => "Kiribati, Republic of");
		$country["PRK"] = array("iso2" => "KP", "name" => "North Korea, Democratic People's Republic of Korea");
		$country["KOR"] = array("iso2" => "KR", "name" => "South Korea, Republic of Korea");
		$country["KWT"] = array("iso2" => "KW", "name" => "Kuwait, State of");
		$country["KGZ"] = array("iso2" => "KG", "name" => "Kyrgyzstan, Kyrgyz Republic");
		$country["LAO"] = array("iso2" => "LA", "name" => "Laos, Lao People's Democratic Republic");
		$country["LVA"] = array("iso2" => "LV", "name" => "Latvia, Republic of");
		$country["LBN"] = array("iso2" => "LB", "name" => "Lebanon, Republic of");
		$country["LSO"] = array("iso2" => "LS", "name" => "Lesotho, Kingdom of");
		$country["LBR"] = array("iso2" => "LR", "name" => "Liberia, Republic of");
		$country["LBY"] = array("iso2" => "LY", "name" => "Libya");
		$country["LIE"] = array("iso2" => "LI", "name" => "Liechtenstein, Principality of");
		$country["LTU"] = array("iso2" => "LT", "name" => "Lithuania, Republic of");
		$country["LUX"] = array("iso2" => "LU", "name" => "Luxembourg, Grand Duchy of");
		$country["MAC"] = array("iso2" => "MO", "name" => "Macao, The Macao Special Administrative Region");
		$country["MKD"] = array("iso2" => "MK", "name" => "Macedonia, The Former Yugoslav Republic of");
		$country["MDG"] = array("iso2" => "MG", "name" => "Madagascar, Republic of");
		$country["MWI"] = array("iso2" => "MW", "name" => "Malawi, Republic of");
		$country["MYS"] = array("iso2" => "MY", "name" => "Malaysia");
		$country["MDV"] = array("iso2" => "MV", "name" => "Maldives, Republic of");
		$country["MLI"] = array("iso2" => "ML", "name" => "Mali, Republic of");
		$country["MLT"] = array("iso2" => "MT", "name" => "Malta, Republic of");
		$country["MHL"] = array("iso2" => "MH", "name" => "Marshall Islands, Republic of the");
		$country["MTQ"] = array("iso2" => "MQ", "name" => "Martinique");
		$country["MRT"] = array("iso2" => "MR", "name" => "Mauritania, Islamic Republic of");
		$country["MUS"] = array("iso2" => "MU", "name" => "Mauritius, Republic of");
		$country["MYT"] = array("iso2" => "YT", "name" => "Mayotte");
		$country["MEX"] = array("iso2" => "MX", "name" => "Mexico, United Mexican States");
		$country["FSM"] = array("iso2" => "FM", "name" => "Micronesia, Federated States of");
		$country["MDA"] = array("iso2" => "MD", "name" => "Moldova, Republic of");
		$country["MCO"] = array("iso2" => "MC", "name" => "Monaco, Principality of");
		$country["MNG"] = array("iso2" => "MN", "name" => "Mongolia");
		$country["MNE"] = array("iso2" => "ME", "name" => "Montenegro");
		$country["MSR"] = array("iso2" => "MS", "name" => "Montserrat");
		$country["MAR"] = array("iso2" => "MA", "name" => "Morocco, Kingdom of");
		$country["MOZ"] = array("iso2" => "MZ", "name" => "Mozambique, Republic of");
		$country["MMR"] = array("iso2" => "MM", "name" => "Myanmar (Burma), Republic of the Union of Myanmar");
		$country["NAM"] = array("iso2" => "NA", "name" => "Namibia, Republic of");
		$country["NRU"] = array("iso2" => "NR", "name" => "Nauru, Republic of");
		$country["NPL"] = array("iso2" => "NP", "name" => "Nepal, Federal Democratic Republic of");
		$country["NLD"] = array("iso2" => "NL", "name" => "Netherlands, Kingdom of the");
		$country["NCL"] = array("iso2" => "NC", "name" => "New Caledonia");
		$country["NZL"] = array("iso2" => "NZ", "name" => "New Zealand");
		$country["NIC"] = array("iso2" => "NI", "name" => "Nicaragua, Republic of");
		$country["NER"] = array("iso2" => "NE", "name" => "Niger, Republic of");
		$country["NGA"] = array("iso2" => "NG", "name" => "Nigeria, Federal Republic of");
		$country["NIU"] = array("iso2" => "NU", "name" => "Niue");
		$country["NFK"] = array("iso2" => "NF", "name" => "Norfolk Island");
		$country["MNP"] = array("iso2" => "MP", "name" => "Northern Mariana Islands");
		$country["NOR"] = array("iso2" => "NO", "name" => "Norway, Kingdom of");
		$country["OMN"] = array("iso2" => "OM", "name" => "Oman, Sultanate of");
		$country["PAK"] = array("iso2" => "PK", "name" => "Pakistan, Islamic Republic of");
		$country["PLW"] = array("iso2" => "PW", "name" => "Palau, Republic of");
		$country["PSE"] = array("iso2" => "PS", "name" => "Palestine, State of Palestine (or Occupied Palestinian Territory)");
		$country["PAN"] = array("iso2" => "PA", "name" => "Panama, Republic of");
		$country["PNG"] = array("iso2" => "PG", "name" => "Papua New Guinea, Independent State of");
		$country["PRY"] = array("iso2" => "PY", "name" => "Paraguay, Republic of");
		$country["PER"] = array("iso2" => "PE", "name" => "Peru, Republic of");
		$country["PHL"] = array("iso2" => "PH", "name" => "Philippines, Republic of the");
		$country["PCN"] = array("iso2" => "PN", "name" => "Pitcairn");
		$country["POL"] = array("iso2" => "PL", "name" => "Poland, Republic of");
		$country["PRT"] = array("iso2" => "PT", "name" => "Portugal, Portuguese Republic");
		$country["PRI"] = array("iso2" => "PR", "name" => "Puerto Rico, Commonwealth of");
		$country["QAT"] = array("iso2" => "QA", "name" => "Qatar, State of");
		$country["REU"] = array("iso2" => "RE", "name" => "Reunion");
		$country["ROU"] = array("iso2" => "RO", "name" => "Romania");
		$country["RUS"] = array("iso2" => "RU", "name" => "Russia, Russian Federation");
		$country["RWA"] = array("iso2" => "RW", "name" => "Rwanda, Republic of");
		$country["BLM"] = array("iso2" => "BL", "name" => "Saint Barthelemy");
		$country["SHN"] = array("iso2" => "SH", "name" => "Saint Helena, Ascension and Tristan da Cunha");
		$country["KNA"] = array("iso2" => "KN", "name" => "Saint Kitts and Nevis, Federation of Saint Christopher and Nevis");
		$country["LCA"] = array("iso2" => "LC", "name" => "Saint Lucia");
		$country["MAF"] = array("iso2" => "MF", "name" => "Saint Martin");
		$country["SPM"] = array("iso2" => "PM", "name" => "Saint Pierre and Miquelon");
		$country["VCT"] = array("iso2" => "VC", "name" => "Saint Vincent and the Grenadines");
		$country["WSM"] = array("iso2" => "WS", "name" => "Samoa, Independent State of");
		$country["SMR"] = array("iso2" => "SM", "name" => "San Marino, Republic of");
		$country["STP"] = array("iso2" => "ST", "name" => "Sao Tome and Principe, Democratic Republic of");
		$country["SAU"] = array("iso2" => "SA", "name" => "Saudi Arabia, Kingdom of");
		$country["SEN"] = array("iso2" => "SN", "name" => "Senegal, Republic of");
		$country["SRB"] = array("iso2" => "RS", "name" => "Serbia, Republic of");
		$country["SYC"] = array("iso2" => "SC", "name" => "Seychelles, Republic of");
		$country["SLE"] = array("iso2" => "SL", "name" => "Sierra Leone, Republic of");
		$country["SGP"] = array("iso2" => "SG", "name" => "Singapore, Republic of");
		$country["SVK"] = array("iso2" => "SK", "name" => "Slovakia, Slovak Republic");
		$country["SVN"] = array("iso2" => "SI", "name" => "Slovenia, Republic of");
		$country["SLB"] = array("iso2" => "SB", "name" => "Solomon Islands");
		$country["SOM"] = array("iso2" => "SO", "name" => "Somalia, Somali Republic");
		$country["ZAF"] = array("iso2" => "ZA", "name" => "South Africa, Republic of");
		$country["SGS"] = array("iso2" => "GS", "name" => "South Georgia and the South Sandwich Islands");
		$country["ESP"] = array("iso2" => "ES", "name" => "Spain, Kingdom of");
		$country["LKA"] = array("iso2" => "LK", "name" => "Sri Lanka, Democratic Socialist Republic of");
		$country["SDN"] = array("iso2" => "SD", "name" => "Sudan, Republic of the");
		$country["SUR"] = array("iso2" => "SR", "name" => "Suriname, Republic of");
		$country["SJM"] = array("iso2" => "SJ", "name" => "Svalbard and Jan Mayen");
		$country["SWZ"] = array("iso2" => "SZ", "name" => "Swaziland, Kingdom of");
		$country["SWE"] = array("iso2" => "SE", "name" => "Sweden, Kingdom of");
		$country["CHE"] = array("iso2" => "CH", "name" => "Switzerland, Swiss Confederation");
		$country["SYR"] = array("iso2" => "SY", "name" => "Syria, Syrian Arab Republic");
		$country["TWN"] = array("iso2" => "TW", "name" => "Taiwan, Republic of China (Taiwan)");
		$country["TJK"] = array("iso2" => "TJ", "name" => "Tajikistan, Republic of");
		$country["TZA"] = array("iso2" => "TZ", "name" => "Tanzania, United Republic of");
		$country["THA"] = array("iso2" => "TH", "name" => "Thailand, Kingdom of");
		$country["TLS"] = array("iso2" => "TL", "name" => "Timor-Leste (East Timor), Democratic Republic of");
		$country["TGO"] = array("iso2" => "TG", "name" => "Togo, Togolese Republic");
		$country["TKL"] = array("iso2" => "TK", "name" => "Tokelau");
		$country["TON"] = array("iso2" => "TO", "name" => "Tonga, Kingdom of");
		$country["TTO"] = array("iso2" => "TT", "name" => "Trinidad and Tobago, Republic ");
		$country["TUN"] = array("iso2" => "TN", "name" => "Tunisia, Republic of");
		$country["TUR"] = array("iso2" => "TR", "name" => "Turkey, Republic of");
		$country["TKM"] = array("iso2" => "TM", "name" => "Turkmenistan");
		$country["TCA"] = array("iso2" => "TC", "name" => "Turks and Caicos Islands");
		$country["TUV"] = array("iso2" => "TV", "name" => "Tuvalu");
		$country["UGA"] = array("iso2" => "UG", "name" => "Uganda, Republic of");
		$country["UKR"] = array("iso2" => "UA", "name" => "Ukraine");
		$country["ARE"] = array("iso2" => "AE", "name" => "United Arab Emirates");
		$country["GBR"] = array("iso2" => "GB", "name" => "United Kingdom, United Kingdom of Great Britain and Nothern Ireland");
		$country["USA"] = array("iso2" => "US", "name" => "United States, United States of America");
		$country["UMI"] = array("iso2" => "UM", "name" => "United States Minor Outlying Islands");
		$country["VIR"] = array("iso2" => "VI", "name" => "Virgin Islands (US), Virgin Islands of the United States");
		$country["URY"] = array("iso2" => "UY", "name" => "Uruguay, Eastern Republic of");
		$country["UZB"] = array("iso2" => "UZ", "name" => "Uzbekistan, Republic of");
		$country["VUT"] = array("iso2" => "VU", "name" => "Vanuatu, Republic of");
		$country["VEN"] = array("iso2" => "VE", "name" => "Venezuela, Bolivarian Republic");
		$country["VNM"] = array("iso2" => "VN", "name" => "Vietnam, Socialist Republic of");
		$country["WLF"] = array("iso2" => "WF", "name" => "Wallis and Futuna");
		$country["ESH"] = array("iso2" => "EH", "name" => "Western Sahara");
		$country["YEM"] = array("iso2" => "YE", "name" => "Yemen, Republic of");
		$country["ZMB"] = array("iso2" => "ZM", "name" => "Zambia, Republic of");
		$country["ZWE"] = array("iso2" => "ZW", "name" => "Zimbabwe, Republic of");
		$country["BES"] = array("iso2" => "BQ", "name" => "Bonaire, Sint Eustatius and Saba");
		$country["CUW"] = array("iso2" => "CW", "name" => "Curacao");
		$country["SXM"] = array("iso2" => "SX", "name" => "Sint Maarten");
		$country["SSD"] = array("iso2" => "SS", "name" => "South Sudan, Republic of");
		$country["XKX"] = array("iso2" => "XK", "name" => "Kosov, Republic of");
		$country["0"] = array("iso2" => "0", "name" => "NO VALID COUNTRY");
		return $country;
	}

	public function getCountrycoordarray()
	{
		$countrycoord = array();
		$countrycoord['AD'] = array(42.5, 1.5);
		$countrycoord['AE'] = array(24, 54);
		$countrycoord['AF'] = array(33, 65);
		$countrycoord['AG'] = array(17.05, -61.8);
		$countrycoord['AI'] = array(18.25, -63.17);
		$countrycoord['AL'] = array(41, 20);
		$countrycoord['AM'] = array(40, 45);
		$countrycoord['AO'] = array(-12.5, 18.5);
		$countrycoord['AP'] = array(35, 105);
		$countrycoord['AQ'] = array(-90, 0);
		$countrycoord['AR'] = array(-34, -64);
		$countrycoord['AS'] = array(-14.33, -170);
		$countrycoord['AT'] = array(47.33, 13.33);
		$countrycoord['AU'] = array(-27, 133);
		$countrycoord['AW'] = array(12.5, -69.97);
		$countrycoord['AZ'] = array(40.5, 47.5);
		$countrycoord['BA'] = array(44, 18);
		$countrycoord['BB'] = array(13.17, -59.53);
		$countrycoord['BD'] = array(24, 90);
		$countrycoord['BE'] = array(50.83, 4);
		$countrycoord['BF'] = array(13, -2);
		$countrycoord['BG'] = array(43, 25);
		$countrycoord['BH'] = array(26, 50.55);
		$countrycoord['BI'] = array(-3.5, 30);
		$countrycoord['BJ'] = array(9.5, 2.25);
		$countrycoord['BM'] = array(32.33, -64.75);
		$countrycoord['BN'] = array(4.5, 114.67);
		$countrycoord['BO'] = array(-17, -65);
		$countrycoord['BR'] = array(-10, -55);
		$countrycoord['BS'] = array(24.25, -76);
		$countrycoord['BT'] = array(27.5, 90.5);
		$countrycoord['BV'] = array(-54.43, 3.4);
		$countrycoord['BW'] = array(-22, 24);
		$countrycoord['BY'] = array(53, 28);
		$countrycoord['BZ'] = array(17.25, -88.75);
		$countrycoord['CA'] = array(60, -95);
		$countrycoord['CC'] = array(-12.5, 96.83);
		$countrycoord['CD'] = array(0, 25);
		$countrycoord['CF'] = array(7, 21);
		$countrycoord['CG'] = array(-1, 15);
		$countrycoord['CH'] = array(47, 8);
		$countrycoord['CI'] = array(8, -5);
		$countrycoord['CK'] = array(-21.23, -159.77);
		$countrycoord['CL'] = array(-30, -71);
		$countrycoord['CM'] = array(6, 12);
		$countrycoord['CN'] = array(35, 105);
		$countrycoord['CO'] = array(4, -72);
		$countrycoord['CR'] = array(10, -84);
		$countrycoord['CU'] = array(21.5, -80);
		$countrycoord['CV'] = array(16, -24);
		$countrycoord['CX'] = array(-10.5, 105.67);
		$countrycoord['CY'] = array(35, 33);
		$countrycoord['CZ'] = array(49.75, 15.5);
		$countrycoord['DE'] = array(51, 9);
		$countrycoord['DJ'] = array(11.5, 43);
		$countrycoord['DK'] = array(56, 10);
		$countrycoord['DM'] = array(15.42, -61.33);
		$countrycoord['DO'] = array(19, -70.67);
		$countrycoord['DZ'] = array(28, 3);
		$countrycoord['EC'] = array(-2, -77.5);
		$countrycoord['EE'] = array(59, 26);
		$countrycoord['EG'] = array(27, 30);
		$countrycoord['EH'] = array(24.5, -13);
		$countrycoord['ER'] = array(15, 39);
		$countrycoord['ES'] = array(40, -4);
		$countrycoord['ET'] = array(8, 38);
		$countrycoord['EU'] = array(47, 8);
		$countrycoord['FI'] = array(64, 26);
		$countrycoord['FJ'] = array(-18, 175);
		$countrycoord['FK'] = array(-51.75, -59);
		$countrycoord['FM'] = array(6.92, 158.25);
		$countrycoord['FO'] = array(62, -7);
		$countrycoord['FR'] = array(46, 2);
		$countrycoord['GA'] = array(-1, 11.75);
		$countrycoord['GB'] = array(54, -2);
		$countrycoord['GD'] = array(12.12, -61.67);
		$countrycoord['GE'] = array(42, 43.5);
		$countrycoord['GF'] = array(4, -53);
		$countrycoord['GH'] = array(8, -2);
		$countrycoord['GI'] = array(36.18, -5.37);
		$countrycoord['GL'] = array(72, -40);
		$countrycoord['GM'] = array(13.47, -16.57);
		$countrycoord['GN'] = array(11, -10);
		$countrycoord['GP'] = array(16.25, -61.58);
		$countrycoord['GQ'] = array(2, 10);
		$countrycoord['GR'] = array(39, 22);
		$countrycoord['GS'] = array(-54.5, -37);
		$countrycoord['GT'] = array(15.5, -90.25);
		$countrycoord['GU'] = array(13.47, 144.78);
		$countrycoord['GW'] = array(12, -15);
		$countrycoord['GY'] = array(5, -59);
		$countrycoord['HK'] = array(22.25, 114.17);
		$countrycoord['HM'] = array(-53.1, 72.52);
		$countrycoord['HN'] = array(15, -86.5);
		$countrycoord['HR'] = array(45.17, 15.5);
		$countrycoord['HT'] = array(19, -72.42);
		$countrycoord['HU'] = array(47, 20);
		$countrycoord['ID'] = array(-5, 120);
		$countrycoord['IE'] = array(53, -8);
		$countrycoord['IL'] = array(31.5, 34.75);
		$countrycoord['IN'] = array(20, 77);
		$countrycoord['IO'] = array(-6, 71.5);
		$countrycoord['IQ'] = array(33, 44);
		$countrycoord['IR'] = array(32, 53);
		$countrycoord['IS'] = array(65, -18);
		$countrycoord['IT'] = array(42.83, 12.83);
		$countrycoord['JM'] = array(18.25, -77.5);
		$countrycoord['JO'] = array(31, 36);
		$countrycoord['JP'] = array(36, 138);
		$countrycoord['KE'] = array(1, 38);
		$countrycoord['KG'] = array(41, 75);
		$countrycoord['KH'] = array(13, 105);
		$countrycoord['KI'] = array(1.42, 173);
		$countrycoord['KM'] = array(-12.17, 44.25);
		$countrycoord['KN'] = array(17.33, -62.75);
		$countrycoord['KP'] = array(40, 127);
		$countrycoord['KR'] = array(37, 127.5);
		$countrycoord['KW'] = array(29.34, 47.66);
		$countrycoord['KY'] = array(19.5, -80.5);
		$countrycoord['KZ'] = array(48, 68);
		$countrycoord['LA'] = array(18, 105);
		$countrycoord['LB'] = array(33.83, 35.83);
		$countrycoord['LC'] = array(13.88, -61.13);
		$countrycoord['LI'] = array(47.17, 9.53);
		$countrycoord['LK'] = array(7, 81);
		$countrycoord['LR'] = array(6.5, -9.5);
		$countrycoord['LS'] = array(-29.5, 28.5);
		$countrycoord['LT'] = array(56, 24);
		$countrycoord['LU'] = array(49.75, 6.17);
		$countrycoord['LV'] = array(57, 25);
		$countrycoord['LY'] = array(25, 17);
		$countrycoord['MA'] = array(32, -5);
		$countrycoord['MC'] = array(43.73, 7.4);
		$countrycoord['MD'] = array(47, 29);
		$countrycoord['ME'] = array(42, 19);
		$countrycoord['MG'] = array(-20, 47);
		$countrycoord['MH'] = array(9, 168);
		$countrycoord['MK'] = array(41.83, 22);
		$countrycoord['ML'] = array(17, -4);
		$countrycoord['MM'] = array(22, 98);
		$countrycoord['MN'] = array(46, 105);
		$countrycoord['MO'] = array(22.17, 113.55);
		$countrycoord['MP'] = array(15.2, 145.75);
		$countrycoord['MQ'] = array(14.67, -61);
		$countrycoord['MR'] = array(20, -12);
		$countrycoord['MS'] = array(16.75, -62.2);
		$countrycoord['MT'] = array(35.83, 14.58);
		$countrycoord['MU'] = array(-20.28, 57.55);
		$countrycoord['MV'] = array(3.25, 73);
		$countrycoord['MW'] = array(-13.5, 34);
		$countrycoord['MX'] = array(23, -102);
		$countrycoord['MY'] = array(2.5, 112.5);
		$countrycoord['MZ'] = array(-18.25, 35);
		$countrycoord['NA'] = array(-22, 17);
		$countrycoord['NC'] = array(-21.5, 165.5);
		$countrycoord['NE'] = array(16, 8);
		$countrycoord['NF'] = array(-29.03, 167.95);
		$countrycoord['NG'] = array(10, 8);
		$countrycoord['NI'] = array(13, -85);
		$countrycoord['NL'] = array(52.5, 5.75);
		$countrycoord['NO'] = array(62, 10);
		$countrycoord['NP'] = array(28, 84);
		$countrycoord['NR'] = array(-0.53, 166.92);
		$countrycoord['NU'] = array(-19.03, -169.87);
		$countrycoord['NZ'] = array(-41, 174);
		$countrycoord['OM'] = array(21, 57);
		$countrycoord['PA'] = array(9, -80);
		$countrycoord['PE'] = array(-10, -76);
		$countrycoord['PF'] = array(-15, -140);
		$countrycoord['PG'] = array(-6, 147);
		$countrycoord['PH'] = array(13, 122);
		$countrycoord['PK'] = array(30, 70);
		$countrycoord['PL'] = array(52, 20);
		$countrycoord['PM'] = array(46.83, -56.33);
		$countrycoord['PR'] = array(18.25, -66.5);
		$countrycoord['PS'] = array(32, 35.25);
		$countrycoord['PT'] = array(39.5, -8);
		$countrycoord['PW'] = array(7.5, 134.5);
		$countrycoord['PY'] = array(-23, -58);
		$countrycoord['QA'] = array(25.5, 51.25);
		$countrycoord['RE'] = array(-21.1, 55.6);
		$countrycoord['RO'] = array(46, 25);
		$countrycoord['RS'] = array(44, 21);
		$countrycoord['RU'] = array(60, 100);
		$countrycoord['RW'] = array(-2, 30);
		$countrycoord['SA'] = array(25, 45);
		$countrycoord['SB'] = array(-8, 159);
		$countrycoord['SC'] = array(-4.58, 55.67);
		$countrycoord['SD'] = array(15, 30);
		$countrycoord['SE'] = array(62, 15);
		$countrycoord['SG'] = array(1.37, 103.8);
		$countrycoord['SH'] = array(-15.93, -5.7);
		$countrycoord['SI'] = array(46, 15);
		$countrycoord['SJ'] = array(78, 20);
		$countrycoord['SK'] = array(48.67, 19.5);
		$countrycoord['SL'] = array(8.5, -11.5);
		$countrycoord['SM'] = array(43.77, 12.42);
		$countrycoord['SN'] = array(14, -14);
		$countrycoord['SO'] = array(10, 49);
		$countrycoord['SR'] = array(4, -56);
		$countrycoord['ST'] = array(1, 7);
		$countrycoord['SV'] = array(13.83, -88.92);
		$countrycoord['SY'] = array(35, 38);
		$countrycoord['SZ'] = array(-26.5, 31.5);
		$countrycoord['TC'] = array(21.75, -71.58);
		$countrycoord['TD'] = array(15, 19);
		$countrycoord['TF'] = array(-43, 67);
		$countrycoord['TG'] = array(8, 1.17);
		$countrycoord['TH'] = array(15, 100);
		$countrycoord['TJ'] = array(39, 71);
		$countrycoord['TK'] = array(-9, -172);
		$countrycoord['TM'] = array(40, 60);
		$countrycoord['TN'] = array(34, 9);
		$countrycoord['TO'] = array(-20, -175);
		$countrycoord['TR'] = array(39, 35);
		$countrycoord['TT'] = array(11, -61);
		$countrycoord['TV'] = array(-8, 178);
		$countrycoord['TW'] = array(23.5, 121);
		$countrycoord['TZ'] = array(-6, 35);
		$countrycoord['UA'] = array(49, 32);
		$countrycoord['UG'] = array(1, 32);
		$countrycoord['UM'] = array(19.28, 166.6);
		$countrycoord['US'] = array(38, -97);
		$countrycoord['UY'] = array(-33, -56);
		$countrycoord['UZ'] = array(41, 64);
		$countrycoord['VA'] = array(41.9, 12.45);
		$countrycoord['VC'] = array(13.25, -61.2);
		$countrycoord['VE'] = array(8, -66);
		$countrycoord['VG'] = array(18.5, -64.5);
		$countrycoord['VI'] = array(18.33, -64.83);
		$countrycoord['VN'] = array(16, 106);
		$countrycoord['VU'] = array(-16, 167);
		$countrycoord['WF'] = array(-13.3, -176.2);
		$countrycoord['WS'] = array(-13.58, -172.33);
		$countrycoord['YE'] = array(15, 48);
		$countrycoord['YT'] = array(-12.83, 45.17);
		$countrycoord['ZA'] = array(-29, 24);
		$countrycoord['ZM'] = array(-15, 30);
		$countrycoord['ZW'] = array(-20, 30);
		$countrycoord['BQ'] = array(17.48, -62.98);
		$countrycoord['CW'] = array(10.0, -84.0);
		$countrycoord['SX'] = array(18.03, -63.07);
		$countrycoord['SS'] = array(12.86, 30.21);
		$countrycoord['XK'] = array(41.91, 24.7);
		return $countrycoord;
	}

	static public function getCountryOptions($value_tag = 'value', $text_tag = 'text')
	{
		$countries = self::getCountries();
		$options = array();
		foreach ($countries as $country) {
			$name = explode(',', $country['name']);
			$options[] = JHtml::_('select.option', $country['iso2'], Text::_($name[0]), $value_tag, $text_tag);
		}
		return $options;
	}

	static public function convertIso2to3($iso_code_2)
	{
		$convert2to3["AF"] = "AFG";
		$convert2to3["AX"] = "ALA";
		$convert2to3["AL"] = "ALB";
		$convert2to3["DZ"] = "DZA";
		$convert2to3["AS"] = "ASM";
		$convert2to3["AD"] = "AND";
		$convert2to3["AO"] = "AGO";
		$convert2to3["AI"] = "AIA";
		$convert2to3["AQ"] = "ATA";
		$convert2to3["AG"] = "ATG";
		$convert2to3["AR"] = "ARG";
		$convert2to3["AM"] = "ARM";
		$convert2to3["AW"] = "ABW";
		$convert2to3["AU"] = "AUS";
		$convert2to3["AT"] = "AUT";
		$convert2to3["AZ"] = "AZE";
		$convert2to3["BS"] = "BHS";
		$convert2to3["BH"] = "BHR";
		$convert2to3["BD"] = "BGD";
		$convert2to3["BB"] = "BRB";
		$convert2to3["BY"] = "BLR";
		$convert2to3["BE"] = "BEL";
		$convert2to3["BZ"] = "BLZ";
		$convert2to3["BJ"] = "BEN";
		$convert2to3["BM"] = "BMU";
		$convert2to3["BT"] = "BTN";
		$convert2to3["BO"] = "BOL";
		$convert2to3["BA"] = "BIH";
		$convert2to3["BW"] = "BWA";
		$convert2to3["BV"] = "BVT";
		$convert2to3["BR"] = "BRA";
		$convert2to3["IO"] = "IOT";
		$convert2to3["BN"] = "BRN";
		$convert2to3["BG"] = "BGR";
		$convert2to3["BF"] = "BFA";
		$convert2to3["BI"] = "BDI";
		$convert2to3["KH"] = "KHM";
		$convert2to3["CM"] = "CMR";
		$convert2to3["CA"] = "CAN";
		$convert2to3["CV"] = "CPV";
		$convert2to3["KY"] = "CYM";
		$convert2to3["CF"] = "CAF";
		$convert2to3["TD"] = "TCD";
		$convert2to3["CL"] = "CHL";
		$convert2to3["CN"] = "CHN";
		$convert2to3["CX"] = "CXR";
		$convert2to3["CC"] = "CCK";
		$convert2to3["CO"] = "COL";
		$convert2to3["KM"] = "COM";
		$convert2to3["CG"] = "COG";
		$convert2to3["CD"] = "COD";
		$convert2to3["CK"] = "COK";
		$convert2to3["CR"] = "CRI";
		$convert2to3["CI"] = "CIV";
		$convert2to3["HR"] = "HRV";
		$convert2to3["CU"] = "CUB";
		$convert2to3["CY"] = "CYP";
		$convert2to3["CZ"] = "CZE";
		$convert2to3["DK"] = "DNK";
		$convert2to3["DJ"] = "DJI";
		$convert2to3["DM"] = "DMA";
		$convert2to3["DO"] = "DOM";
		$convert2to3["EC"] = "ECU";
		$convert2to3["EG"] = "EGY";
		$convert2to3["SV"] = "SLV";
		$convert2to3["GQ"] = "GNQ";
		$convert2to3["ER"] = "ERI";
		$convert2to3["EE"] = "EST";
		$convert2to3["ET"] = "ETH";
		$convert2to3["FK"] = "FLK";
		$convert2to3["FO"] = "FRO";
		$convert2to3["FJ"] = "FJI";
		$convert2to3["FI"] = "FIN";
		$convert2to3["FR"] = "FRA";
		$convert2to3["GF"] = "GUF";
		$convert2to3["PF"] = "PYF";
		$convert2to3["TF"] = "ATF";
		$convert2to3["GA"] = "GAB";
		$convert2to3["GM"] = "GMB";
		$convert2to3["GE"] = "GEO";
		$convert2to3["DE"] = "DEU";
		$convert2to3["GH"] = "GHA";
		$convert2to3["GI"] = "GIB";
		$convert2to3["GR"] = "GRC";
		$convert2to3["GL"] = "GRL";
		$convert2to3["GD"] = "GRD";
		$convert2to3["GP"] = "GLP";
		$convert2to3["GU"] = "GUM";
		$convert2to3["GT"] = "GTM";
		$convert2to3["GG"] = "GGY";
		$convert2to3["GN"] = "GIN";
		$convert2to3["GW"] = "GNB";
		$convert2to3["GY"] = "GUY";
		$convert2to3["HT"] = "HTI";
		$convert2to3["HM"] = "HMD";
		$convert2to3["VA"] = "VAT";
		$convert2to3["HN"] = "HND";
		$convert2to3["HK"] = "HKG";
		$convert2to3["HU"] = "HUN";
		$convert2to3["IS"] = "ISL";
		$convert2to3["IN"] = "IND";
		$convert2to3["ID"] = "IDN";
		$convert2to3["IR"] = "IRN";
		$convert2to3["IQ"] = "IRQ";
		$convert2to3["IE"] = "IRL";
		$convert2to3["IM"] = "IMM";
		$convert2to3["IL"] = "ISR";
		$convert2to3["IT"] = "ITA";
		$convert2to3["JM"] = "JAM";
		$convert2to3["JP"] = "JPN";
		$convert2to3["JE"] = "JEY";
		$convert2to3["JO"] = "JOR";
		$convert2to3["KZ"] = "KAZ";
		$convert2to3["KE"] = "KEN";
		$convert2to3["KI"] = "KIR";
		$convert2to3["KP"] = "PRK";
		$convert2to3["KR"] = "KOR";
		$convert2to3["KW"] = "KWT";
		$convert2to3["KG"] = "KGZ";
		$convert2to3["LA"] = "LAO";
		$convert2to3["LV"] = "LVA";
		$convert2to3["LB"] = "LBN";
		$convert2to3["LS"] = "LSO";
		$convert2to3["LR"] = "LBR";
		$convert2to3["LY"] = "LBY";
		$convert2to3["LI"] = "LIE";
		$convert2to3["LT"] = "LTU";
		$convert2to3["LU"] = "LUX";
		$convert2to3["MO"] = "MAC";
		$convert2to3["MK"] = "MKD";
		$convert2to3["MG"] = "MDG";
		$convert2to3["MW"] = "MWI";
		$convert2to3["MY"] = "MYS";
		$convert2to3["MV"] = "MDV";
		$convert2to3["ML"] = "MLI";
		$convert2to3["MT"] = "MLT";
		$convert2to3["MH"] = "MHL";
		$convert2to3["MQ"] = "MTQ";
		$convert2to3["MR"] = "MRT";
		$convert2to3["MU"] = "MUS";
		$convert2to3["YT"] = "MYT";
		$convert2to3["MX"] = "MEX";
		$convert2to3["FM"] = "FSM";
		$convert2to3["MD"] = "MDA";
		$convert2to3["MC"] = "MCO";
		$convert2to3["MN"] = "MNG";
		$convert2to3["ME"] = "MNE";
		$convert2to3["MS"] = "MSR";
		$convert2to3["MA"] = "MAR";
		$convert2to3["MZ"] = "MOZ";
		$convert2to3["MM"] = "MMR";
		$convert2to3["NA"] = "NAM";
		$convert2to3["NR"] = "NRU";
		$convert2to3["NP"] = "NPL";
		$convert2to3["NL"] = "NLD";
		$convert2to3["NC"] = "NCL";
		$convert2to3["NZ"] = "NZL";
		$convert2to3["NI"] = "NIC";
		$convert2to3["NE"] = "NER";
		$convert2to3["NG"] = "NGA";
		$convert2to3["NU"] = "NIU";
		$convert2to3["NF"] = "NFK";
		$convert2to3["MP"] = "MNP";
		$convert2to3["NO"] = "NOR";
		$convert2to3["OM"] = "OMN";
		$convert2to3["PK"] = "PAK";
		$convert2to3["PW"] = "PLW";
		$convert2to3["PS"] = "PSE";
		$convert2to3["PA"] = "PAN";
		$convert2to3["PG"] = "PNG";
		$convert2to3["PY"] = "PRY";
		$convert2to3["PE"] = "PER";
		$convert2to3["PH"] = "PHL";
		$convert2to3["PN"] = "PCN";
		$convert2to3["PL"] = "POL";
		$convert2to3["PT"] = "PRT";
		$convert2to3["PR"] = "PRI";
		$convert2to3["QA"] = "QAT";
		$convert2to3["RE"] = "REU";
		$convert2to3["RO"] = "ROU";
		$convert2to3["RU"] = "RUS";
		$convert2to3["RW"] = "RWA";
		$convert2to3["BL"] = "BLM";
		$convert2to3["SH"] = "SHN";
		$convert2to3["KN"] = "KNA";
		$convert2to3["LC"] = "LCA";
		$convert2to3["MT"] = "MAF";
		$convert2to3["PM"] = "SPM";
		$convert2to3["VC"] = "VCT";
		$convert2to3["WS"] = "WSM";
		$convert2to3["SM"] = "SMR";
		$convert2to3["ST"] = "STP";
		$convert2to3["SA"] = "SAU";
		$convert2to3["SN"] = "SEN";
		$convert2to3["RS"] = "SRB";
		$convert2to3["SC"] = "SYC";
		$convert2to3["SL"] = "SLE";
		$convert2to3["SG"] = "SGP";
		$convert2to3["SK"] = "SVK";
		$convert2to3["SI"] = "SVN";
		$convert2to3["SB"] = "SLB";
		$convert2to3["SO"] = "SOM";
		$convert2to3["ZA"] = "ZAF";
		$convert2to3["GS"] = "SGS";
		$convert2to3["ES"] = "ESP";
		$convert2to3["LK"] = "LKA";
		$convert2to3["SD"] = "SDN";
		$convert2to3["SR"] = "SUR";
		$convert2to3["SJ"] = "SJM";
		$convert2to3["SZ"] = "SWZ";
		$convert2to3["SE"] = "SWE";
		$convert2to3["CH"] = "CHE";
		$convert2to3["SY"] = "SYR";
		$convert2to3["TW"] = "TWN";
		$convert2to3["TJ"] = "TJK";
		$convert2to3["TZ"] = "TZA";
		$convert2to3["TH"] = "THA";
		$convert2to3["TL"] = "TLS";
		$convert2to3["TG"] = "TGO";
		$convert2to3["TK"] = "TKL";
		$convert2to3["TO"] = "TON";
		$convert2to3["TT"] = "TTO";
		$convert2to3["TN"] = "TUN";
		$convert2to3["TR"] = "TUR";
		$convert2to3["TM"] = "TKM";
		$convert2to3["TC"] = "TCA";
		$convert2to3["TV"] = "TUV";
		$convert2to3["UG"] = "UGA";
		$convert2to3["UA"] = "UKR";
		$convert2to3["AE"] = "ARE";
		$convert2to3["GB"] = "GBR";
		$convert2to3["US"] = "USA";
		$convert2to3["UM"] = "UMI";
		$convert2to3["UY"] = "URY";
		$convert2to3["UZ"] = "UZB";
		$convert2to3["VU"] = "VUT";
		$convert2to3["VA"] = "VAT";
		$convert2to3["VE"] = "VEN";
		$convert2to3["VN"] = "VNM";
		$convert2to3["VG"] = "VGB";
		$convert2to3["VI"] = "VIR";
		$convert2to3["WF"] = "WLF";
		$convert2to3["EH"] = "ESH";
		$convert2to3["YE"] = "YEM";
		$convert2to3["YU"] = "YUG";
		$convert2to3["ZM"] = "ZMB";
		$convert2to3["ZW"] = "ZWE";
		$convert2to3['BQ'] = "BES";
		$convert2to3['CW'] = "CUW";
		$convert2to3['SX'] = "SXM";
		$convert2to3['SS'] = "SSD";
		$convert2to3['XK'] = "XKX";

		if (isset($convert2to3[$iso_code_2])) {
			return $convert2to3[$iso_code_2];
		} else {
			return null;
		}
	}

	static public function convertIso3to2($iso_code_3)
	{
		$convert3to2["AFG"] = "AF";
		$convert3to2["ALA"] = "AX";
		$convert3to2["ALB"] = "AL";
		$convert3to2["DZA"] = "DZ";
		$convert3to2["ASM"] = "AS";
		$convert3to2["AND"] = "AD";
		$convert3to2["AGO"] = "AO";
		$convert3to2["AIA"] = "AI";
		$convert3to2["ATA"] = "AQ";
		$convert3to2["ATG"] = "AG";
		$convert3to2["ARG"] = "AR";
		$convert3to2["ARM"] = "AM";
		$convert3to2["ABW"] = "AW";
		$convert3to2["AUS"] = "AU";
		$convert3to2["AUT"] = "AT";
		$convert3to2["AZE"] = "AZ";
		$convert3to2["BHS"] = "BS";
		$convert3to2["BHR"] = "BH";
		$convert3to2["BGD"] = "BD";
		$convert3to2["BRB"] = "BB";
		$convert3to2["BLR"] = "BY";
		$convert3to2["BEL"] = "BE";
		$convert3to2["BLZ"] = "BZ";
		$convert3to2["BEN"] = "BJ";
		$convert3to2["BMU"] = "BM";
		$convert3to2["BTN"] = "BT";
		$convert3to2["BOL"] = "BO";
		$convert3to2["BIH"] = "BA";
		$convert3to2["BWA"] = "BW";
		$convert3to2["BVT"] = "BV";
		$convert3to2["BRA"] = "BR";
		$convert3to2["IOT"] = "IO";
		$convert3to2["BRN"] = "BN";
		$convert3to2["BGR"] = "BG";
		$convert3to2["BFA"] = "BF";
		$convert3to2["BDI"] = "BI";
		$convert3to2["KHM"] = "KH";
		$convert3to2["CMR"] = "CM";
		$convert3to2["CAN"] = "CA";
		$convert3to2["CPV"] = "CV";
		$convert3to2["CYM"] = "KY";
		$convert3to2["CAF"] = "CF";
		$convert3to2["TCD"] = "TD";
		$convert3to2["CHL"] = "CL";
		$convert3to2["CHN"] = "CN";
		$convert3to2["CXR"] = "CX";
		$convert3to2["CCK"] = "CC";
		$convert3to2["COL"] = "CO";
		$convert3to2["COM"] = "KM";
		$convert3to2["COG"] = "CG";
		$convert3to2["COD"] = "CD";
		$convert3to2["COK"] = "CK";
		$convert3to2["CRI"] = "CR";
		$convert3to2["CIV"] = "CI";
		$convert3to2["HRV"] = "HR";
		$convert3to2["CUB"] = "CU";
		$convert3to2["CYP"] = "CY";
		$convert3to2["CZE"] = "CZ";
		$convert3to2["DNK"] = "DK";
		$convert3to2["DJI"] = "DJ";
		$convert3to2["DMA"] = "DM";
		$convert3to2["DOM"] = "DO";
		$convert3to2["ECU"] = "EC";
		$convert3to2["EGY"] = "EG";
		$convert3to2["SLV"] = "SV";
		$convert3to2["GNQ"] = "GQ";
		$convert3to2["ERI"] = "ER";
		$convert3to2["EST"] = "EE";
		$convert3to2["ETH"] = "ET";
		$convert3to2["FLK"] = "FK";
		$convert3to2["FRO"] = "FO";
		$convert3to2["FJI"] = "FJ";
		$convert3to2["FIN"] = "FI";
		$convert3to2["FRA"] = "FR";
		$convert3to2["GUF"] = "GF";
		$convert3to2["PYF"] = "PF";
		$convert3to2["ATF"] = "TF";
		$convert3to2["GAB"] = "GA";
		$convert3to2["GMB"] = "GM";
		$convert3to2["GEO"] = "GE";
		$convert3to2["DEU"] = "DE";
		$convert3to2["GHA"] = "GH";
		$convert3to2["GIB"] = "GI";
		$convert3to2["GRC"] = "GR";
		$convert3to2["GRL"] = "GL";
		$convert3to2["GRD"] = "GD";
		$convert3to2["GLP"] = "GP";
		$convert3to2["GUM"] = "GU";
		$convert3to2["GTM"] = "GT";
		$convert3to2["GGY"] = "GG";
		$convert3to2["GIN"] = "GN";
		$convert3to2["GNB"] = "GW";
		$convert3to2["GUY"] = "GY";
		$convert3to2["HTI"] = "HT";
		$convert3to2["HMD"] = "HM";
		$convert3to2["VAT"] = "VA";
		$convert3to2["HND"] = "HN";
		$convert3to2["HKG"] = "HK";
		$convert3to2["HUN"] = "HU";
		$convert3to2["ISL"] = "IS";
		$convert3to2["IND"] = "IN";
		$convert3to2["IDN"] = "ID";
		$convert3to2["IRN"] = "IR";
		$convert3to2["IRQ"] = "IQ";
		$convert3to2["IRL"] = "IE";
		$convert3to2["IMM"] = "IM";
		$convert3to2["ISR"] = "IL";
		$convert3to2["ITA"] = "IT";
		$convert3to2["JAM"] = "JM";
		$convert3to2["JPN"] = "JP";
		$convert3to2["JEY"] = "JE";
		$convert3to2["JOR"] = "JO";
		$convert3to2["KAZ"] = "KZ";
		$convert3to2["KEN"] = "KE";
		$convert3to2["KIR"] = "KI";
		$convert3to2["PRK"] = "KP";
		$convert3to2["KOR"] = "KR";
		$convert3to2["KWT"] = "KW";
		$convert3to2["KGZ"] = "KG";
		$convert3to2["LAO"] = "LA";
		$convert3to2["LVA"] = "LV";
		$convert3to2["LBN"] = "LB";
		$convert3to2["LSO"] = "LS";
		$convert3to2["LBR"] = "LR";
		$convert3to2["LBY"] = "LY";
		$convert3to2["LIE"] = "LI";
		$convert3to2["LTU"] = "LT";
		$convert3to2["LUX"] = "LU";
		$convert3to2["MAC"] = "MO";
		$convert3to2["MKD"] = "MK";
		$convert3to2["MDG"] = "MG";
		$convert3to2["MWI"] = "MW";
		$convert3to2["MYS"] = "MY";
		$convert3to2["MDV"] = "MV";
		$convert3to2["MLI"] = "ML";
		$convert3to2["MLT"] = "MT";
		$convert3to2["MHL"] = "MH";
		$convert3to2["MTQ"] = "MQ";
		$convert3to2["MRT"] = "MR";
		$convert3to2["MUS"] = "MU";
		$convert3to2["MYT"] = "YT";
		$convert3to2["MEX"] = "MX";
		$convert3to2["FSM"] = "FM";
		$convert3to2["MDA"] = "MD";
		$convert3to2["MCO"] = "MC";
		$convert3to2["MNG"] = "MN";
		$convert3to2["MNE"] = "ME";
		$convert3to2["MSR"] = "MS";
		$convert3to2["MAR"] = "MA";
		$convert3to2["MOZ"] = "MZ";
		$convert3to2["MMR"] = "MM";
		$convert3to2["NAM"] = "NA";
		$convert3to2["NRU"] = "NR";
		$convert3to2["NPL"] = "NP";
		$convert3to2["NLD"] = "NL";
		$convert3to2["ANT"] = "AN";
		$convert3to2["NCL"] = "NC";
		$convert3to2["NZL"] = "NZ";
		$convert3to2["NIC"] = "NI";
		$convert3to2["NER"] = "NE";
		$convert3to2["NGA"] = "NG";
		$convert3to2["NIU"] = "NU";
		$convert3to2["NFK"] = "NF";
		$convert3to2["MNP"] = "MP";
		$convert3to2["NOR"] = "NO";
		$convert3to2["OMN"] = "OM";
		$convert3to2["PAK"] = "PK";
		$convert3to2["PLW"] = "PW";
		$convert3to2["PSE"] = "PS";
		$convert3to2["PAN"] = "PA";
		$convert3to2["PNG"] = "PG";
		$convert3to2["PRY"] = "PY";
		$convert3to2["PER"] = "PE";
		$convert3to2["PHL"] = "PH";
		$convert3to2["PCN"] = "PN";
		$convert3to2["POL"] = "PL";
		$convert3to2["PRT"] = "PT";
		$convert3to2["PRI"] = "PR";
		$convert3to2["QAT"] = "QA";
		$convert3to2["REU"] = "RE";
		$convert3to2["ROU"] = "RO";
		$convert3to2["RUS"] = "RU";
		$convert3to2["RWA"] = "RW";
		$convert3to2["BLM"] = "BL";
		$convert3to2["SHN"] = "SH";
		$convert3to2["KNA"] = "KN";
		$convert3to2["LCA"] = "LC";
		$convert3to2["MAF"] = "MT";
		$convert3to2["SPM"] = "PM";
		$convert3to2["VCT"] = "VC";
		$convert3to2["WSM"] = "WS";
		$convert3to2["SMR"] = "SM";
		$convert3to2["STP"] = "ST";
		$convert3to2["SAU"] = "SA";
		$convert3to2["SEN"] = "SN";
		$convert3to2["SRB"] = "RS";
		$convert3to2["SYC"] = "SC";
		$convert3to2["SLE"] = "SL";
		$convert3to2["SGP"] = "SG";
		$convert3to2["SVK"] = "SK";
		$convert3to2["SVN"] = "SI";
		$convert3to2["SLB"] = "SB";
		$convert3to2["SOM"] = "SO";
		$convert3to2["ZAF"] = "ZA";
		$convert3to2["SGS"] = "GS";
		$convert3to2["ESP"] = "ES";
		$convert3to2["LKA"] = "LK";
		$convert3to2["SDN"] = "SD";
		$convert3to2["SUR"] = "SR";
		$convert3to2["SJM"] = "SJ";
		$convert3to2["SWZ"] = "SZ";
		$convert3to2["SWE"] = "SE";
		$convert3to2["CHE"] = "CH";
		$convert3to2["SYR"] = "SY";
		$convert3to2["TWN"] = "TW";
		$convert3to2["TJK"] = "TJ";
		$convert3to2["TZA"] = "TZ";
		$convert3to2["THA"] = "TH";
		$convert3to2["TLS"] = "TL";
		$convert3to2["TGO"] = "TG";
		$convert3to2["TKL"] = "TK";
		$convert3to2["TON"] = "TO";
		$convert3to2["TTO"] = "TT";
		$convert3to2["TUN"] = "TN";
		$convert3to2["TUR"] = "TR";
		$convert3to2["TKM"] = "TM";
		$convert3to2["TCA"] = "TC";
		$convert3to2["TUV"] = "TV";
		$convert3to2["UGA"] = "UG";
		$convert3to2["UKR"] = "UA";
		$convert3to2["ARE"] = "AE";
		$convert3to2["GBR"] = "GB";
		$convert3to2["USA"] = "US";
		$convert3to2["UMI"] = "UM";
		$convert3to2["URY"] = "UY";
		$convert3to2["UZB"] = "UZ";
		$convert3to2["VUT"] = "VU";
		$convert3to2["VAT"] = "VA";
		$convert3to2["VEN"] = "VE";
		$convert3to2["VNM"] = "VN";
		$convert3to2["VGB"] = "VG";
		$convert3to2["VIR"] = "VI";
		$convert3to2["WLF"] = "WF";
		$convert3to2["ESH"] = "EH";
		$convert3to2["YEM"] = "YE";
		$convert3to2["YUG"] = "YU";
		$convert3to2["ZMB"] = "ZM";
		$convert3to2["ZWE"] = "ZW";
		$convert3to2['BES'] = "BQ";
		$convert3to2['CUW'] = "CW";
		$convert3to2['SXM'] = "SX";
		$convert3to2['SSD'] = "SS";
		$convert3to2['XKX'] = "XK";

		if (isset($convert3to2[$iso_code_3]))
		{
			return $convert3to2[$iso_code_3];
		}
		else
		{
			return null;
		}
	}

	/**
	 * return flag url from iso code
	 *
	 * @param  $iso_code
	 */
	static public function getIsoFlag($iso_code)
	{
		$uri = Uri::getInstance();
		$settings = JemHelper::config();
		if (strlen($iso_code) == 3) {
			$iso_code = self::convertIso3to2($iso_code);
		}
		if ($iso_code) {
			$flagpath = $settings->flagicons_path . (str_ends_with($settings->flagicons_path, '/')?'':'/');
			$flagext = substr($flagpath, strrpos($flagpath,"-")+1,-1) ;
			$path = Uri::getInstance()->base() . $flagpath . strtolower($iso_code) . '.' . $flagext;
			return $path;
		}
		else
		{
			return null;
		}
	}

	/**
	 * example: echo self::getCountryFlag($country);
	 *
	 * @param  string an iso3 country code, e.g AUT
	 * @param  string additional html attributes for the img tag
	 * @return string html code for the flag image
	 */
	static public function getCountryFlag($countrycode, $attributes = '')
	{
		$src = self::getIsoFlag($countrycode);
		if (!$src) {
			return '';
		}

		$html = '<img src="' . $src . '" alt="' . self::getCountryName($countrycode) . '" ';
		$html .= 'title="' . self::getCountryName($countrycode) . '" ' . $attributes . ' />';
		return $html;
	}

	/**
	 * @param  string an iso country code, e.g AUT
	 * @return string a country name
	 */
	static public function getCountryName($iso)
	{
		if (strlen($iso) == 2) {
			$iso = self::convertIso2to3($iso);
		}
		$countries = self::getCountries();
		if (isset($countries[$iso]['name'])) {
			$c = explode(',', $countries[$iso]['name']);
			return Text::_($c[0]);
		}
		return false;
	}

	/**
	 * @param  string an iso3 country code, e.g AUT
	 * @return string a country full name
	 */
	static public function getCountryFullName($iso)
	{
		if (strlen($iso) == 2) {
			$iso = self::convertIso2to3($iso);
		}
		$countries = self::getCountries();
		if(isset($countries[$iso]['name']))
		return Text::_($countries[$iso]['name']);
	}

	/**
	 * @param  string an iso3 country code, e.g AUT
	 * @return string a country name, short form
	 */
	static public function getShortCountryName($iso)
	{
		if (strlen($iso) == 2) {
			$iso = self::convertIso2to3($iso);
		}
		$full = self::getCountryName($iso);
		if (empty($full)) {
			return false;
		}
		$parts = explode(',', $full);
		return Text::_($parts[0]);
	}
}
?>
