/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @author Sascha Karnatz
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 * Comments in German
 * Function: dieses Javascript ermoeglicht es automatisch die Metatags zu befuellen
**/

// Variablen werden als global definiert, da sie ueber mehrere Funktion hinweg genutzt werden
var $keyword;			// Array - aufgeschlitete Variablen, welche einem HTML-Tag zugeordnet werden kann
var $keywords;			// String - Verbindungsvariable zur noch nicht umgeschriebenen Variante der Keywords
var $manual_keywords = "";	// String - Speichert alle manuell eingefuegen Keywords ab
var $description;		// String - die Beschreibung
var $description_vars;	// Array - HTML - Tags, die mit einem onchange versehen werden
var $inputbox = "";		// String - es wird mit Hilfe dieser Variable ermittel, ob der User Beschreibung bzw. Keywords angeklickt hat
var meta_error;			// String - Fehler, der in der jeweiligen Sprache ausgegeben wird

function starter($msg) {			// Funktion, welche beim Starten der Seite aufgerufen werden
	get_keywords();					// leider funktionier window.onload nicht, da sonst die Popupfenster Fehler verursachen
	get_description();
	switchstart();
	$meta_error = $msg;
}

function switchstart() {	// Diese Funktion uebergibt alle relevanten Feldern ein onchange

	try {
		if ($keyword.length > 0) {
			for (var i=0; i < $keyword.length; i++) {
				$($keyword[i]).onchange = seo_switch;
			}
		}
		if ($description_vars.length > 0) {
			for (var i=0; i < $description_vars.length; i++) {
				$($description_vars[i]).onchange = seo_switch;
			}
		}
	} catch(e) {
		//alert("Error occurred! Eventlist SEO - Javascript stopped!");
	}
}

function seo_switch() {	// Bei jeder Veraenderung werden beide Funktionen aufgerufen
	include_keyword();
	include_description();
}

function get_keywords() {
	$keywords = $("meta_keywords").value;	// Keywords auslesenen
	var $Nullvalue = "[NULL]";
	$manual_keywords = "";					// die Anzeige der nicht zuordbaren Variable wird geleert
	$keyword = $keywords.split(",");			// in eine Array aufspalten
	for (var i = 0; i < $keyword.length; i++) {	// und alle Leerzeichen entfernen
		try { 									// Die Abfrage wird Fehlersicher gemacht
			$keyword[i] = $keyword[i].replace(/ /g,"");
			$keyword[i] = $keyword[i].replace(/\[/g,"");
			$keyword[i] = $keyword[i].replace(/\]/g,"");
			$($keyword[i]).value;				// ein Fehler wird provoziert, falls dieses Element nicht vorhanden ist
      	} catch (e) {
			if ($manual_keywords != "") {	// das nicht verwendete Keyword wird neu abgespeichert
				$manual_keywords += ", ";
			}
			$manual_keywords += $keyword[i];
			$keyword[i] = $Nullvalue;	// Falls eine angegebene ID nicht vorhanden ist, wird diese aus dem Array entfernt
        	continue;
      	}
	}
	var $keyword_count = 0;
	var $keyword_length = $keyword.length;
	i = 0;
	while (i < $keyword_length) {
		if ($keyword[i] == $Nullvalue) {
			$keyword[i] = $keyword[$keyword.length - $keyword_count - 1];
			$keyword[$keyword.length - $keyword_count - 1] = $Nullvalue;
			$keyword_length--;
			$keyword_count++;
		} else {
			i++;
		}
	}
	for (i=0; i < $keyword_count; i++) {
		$keyword.pop();
	}
}

function get_description() {
	$description = $("meta_description").value;	// uebergebene Bechreibung wird aufgerufen und eingefuegt
	if ($description != "") {
		var Ergebnis = $description.split("[");		// alle relevanten Teile werden getrennt
		if (Ergebnis.length > 1) {
			$description_vars = new Array(Ergebnis.length - 1);	// Neues Array zum eintragen der geforderten Variablen wird angelegt
			for (var i=1; i < Ergebnis.length; i++) {
				var inputarray = Ergebnis[i].substring(0,(Ergebnis[i].indexOf("]")));	// die einzelnen Variablen werden aus dem Satz ausgelesen
				try { 									// Die Abfrage wird Fehlersicher gemacht
					$description_vars[i-1] = inputarray;
					$($description_vars[i-1]).value;
				} catch (e) {
					$description_vars.pop();
					continue;
				}
			}
		} else {
			$description_vars = new Array(0);
		}
	} else {
		$description_vars = new Array(0);
	}
}

function include_keyword() {
	var $keywords="";
	for (var i=0; i<$keyword.length; i++) { 		// Es werden alle keywords ausgelesen
		if ($keywords != "") {
					$keywords += ", ";
				}
		if($($keyword[i]).tagName == "SELECT") {	// es wird unterschieden zwischen normalen Inputfeld und Selectfeld
			if ($($keyword[i]).value != 0) {		// um auch korrekt abspeichern zu koennen wird das Komma richtig gesetzt
				$keywords += get_selected_option($keyword[i]); // Auslesen des Wertes aus dem Selectfeld
			} else {
				$keywords += "["+$keyword[i]+"]";
			}
		} else if ($($keyword[i]).value != "") {
			$keywords += $($keyword[i]).value;	//Auslesen des Wertes aus dem Inputfeld
		} else {
			$keywords += "["+$keyword[i]+"]";
		}
	}
	if ($manual_keywords != "") {
		if ($keywords != "") {
			$manual_keywords = ", "+ $manual_keywords;
		}
		$keywords = $keywords + $manual_keywords;
	}
	$("meta_keywords").value = $keywords;
}

function include_description() {
	var desc_split, desc_value, desc_length;
	var desc_output = $description;	// Es wird die urspruengliche Ausgabe abgespeichert, da diese im spaeteren Verlauf geaendert wird
	for (var i = 0; i < $description_vars.length; i++) {
		desc_value = "["+$description_vars[i]+"]";	// Der Wert wird auf Default gesetzt, damit er ausgegeben werden kann, falls ein deafulteinstellung gewaehlt wird
		if ($($description_vars[i]).tagName == "SELECT") {	// es wird wieder unterschieden zwischen Select und Inputfeld
			if ($($description_vars[i]).value != 0) {
				desc_value = get_selected_option($description_vars[i]);
			}
		} else {
			if ($($description_vars[i]).value != "") {
				desc_value = $($description_vars[i]).value;
			}
		}
		desc_split = desc_output.split("["+$description_vars[i]+"]");	// Der Satz wird in zwei Teile geteilt
		desc_output = "";			// der auszugebene Satz wird geloescht, damit er mit den beiden Haelften wieder befuellt werden kann
		desc_length = desc_split.length;
		for (var j = 0; j < desc_length; j++) {
			desc_output += desc_split[j];
			if (j < desc_length - 1) {
				desc_output += desc_value;	// der Wert wird zwischen beide Texthaelften geschrieben
			}
		}
	}
	$("meta_description").value = desc_output;
}


function insert_keyword($keyword) {
	try {
		var $input = $($inputbox).value;
		if ($inputbox == "meta_keywords") {
			if ($input != "") {
				$input += ",";
			}
		}
		$input += " "+$keyword;
		$($inputbox).value = $input;
		change_metatags();
	} catch(e) {
		alert($meta_error);
	}
}

function change_metatags() {

	if ($inputbox == "meta_keywords") {
		$keywords = $($inputbox).value;
		get_keywords();
	} else {
		$description = $($inputbox).value;
		get_description();
	}
	switchstart();
}

function get_inputbox($input) {
	if ($input == "meta_keywords") {
		$($input).value = $keywords;
	} else {
		$($input).value = $description;
	}
	$inputbox = $input;
}

function get_selected_option($selectfield) {
	var $buffer;
	for(i= 0; i < $($selectfield).length; i++) {
		if($($selectfield).options[i].value == $($selectfield).value) {
			$buffer = $($selectfield).options[i].text;
			break;
		}
	}
	return $buffer;
}