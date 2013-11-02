<?php
require_once('calendar.class.php');
/*
* @subclass: activeCalendarWeek
* @class: activeCalendar
* @project: Active Calendar
* @license: GNU LESSER GENERAL PUBLIC LICENSE;
* Special thanks to Jeremy D. Frens, Professor, Computer Science <jdfrens@calvin.edu> for his help developing this code
* This subclass generates weekly calendars as a html table (XHTML Valid) using the activeCalendar class
* Support, feature requests and bug reports please at : http://www.micronetwork.de/activecalendar/

* -------- You may remove all comments below to reduce file size -------- *

*/
class ActiveCalendarWeek extends JEMCalendar {
/*
********************************************************************************
Names of the generated html classes. You may change them to avoid any conflicts with your existing CSS
********************************************************************************
*/
var $cssWeeksTable="week";
var $cssMonthWeek="monthweek";
/*
----------------------
@START PUBLIC METHODS
----------------------
*/
/*
********************************************************************************
PUBLIC class constructor. Calls the main class constructor
********************************************************************************
*/
function __construct($year=false,$month=false,$day=false,$GMTDiff="none") {
parent::__construct($year, $month, $day, $GMTDiff);
$this->GMT=$GMTDiff;
}
/*
********************************************************************************
PUBLIC showWeeks() -> returns the week view as html table string
The week calendar starts on the date set in the constructor
It generates as many rows as set in $numberOfWeeks
********************************************************************************
*/
function showWeeks($numberOfWeeks=1){
$out=$this->mkWeeksHead();
$out.=$this->mkWeekDayz();
$out.=$this->mkWeeksBody($numberOfWeeks);
$out.=$this->mkWeeksFoot();

return $out;
}
/*
********************************************************************************
PUBLIC showWeeksByID() -> returns the week view as html table string
The week calendar starts with the week row, that has the same week number of the year as set in the weekID
It generates as many rows as set in $numberOfWeeks
********************************************************************************
*/
function showWeeksByID($weekID=1,$numberOfWeeks=1){
$xday=1;
	for ($day=1; $day<358; $day++) {
		$tmp=parent::getWeekNum($day);
		if ($tmp==$weekID){
			$xday=$day;
			break;
		}
	}
	if ($this->startOnSun) $xday=$xday-1;
$this->__construct($this->actyear,$this->actmonth,$xday,$this->GMT);
return $this->showWeeks($numberOfWeeks);
}
/*
********************************************************************************
PUBLIC enableDayLinks -> calls same method of the parent class
********************************************************************************
*/
function enableDayLinks($link=false,$javaScript=false){
parent::enableDayLinks($link,$javaScript);
}
/*
********************************************************************************
PUBLIC enableWeekNum -> calls same method of the parent class
********************************************************************************
*/
function enableWeekNum($title="",$link=false,$javaScript=false){
parent::enableWeekNum($title,$link,$javaScript);
}
/*
********************************************************************************
PUBLIC setEvent -> calls same method of the parent class
********************************************************************************
*/
function setEvent($year,$month,$day,$id=false,$url=false){
parent::setEvent($year,$month,$day,$id,$url);
}
/*
********************************************************************************
PUBLIC setEventContent -> calls same method of the parent class
********************************************************************************
*/
function setEventContent($year,$month,$day,$content,$url=false,$id=false){
parent::setEventContent($year,$month,$day,$content,$url,$id);
}
/*
********************************************************************************
PUBLIC setMonthNames -> calls same method of the parent class
********************************************************************************
*/
function setMonthNames($namesArray){
parent::setMonthNames($namesArray);
}
/*
********************************************************************************
PUBLIC setDayNames -> calls same method of the parent class
********************************************************************************
*/
function setDayNames($namesArray){
parent::setDayNames($namesArray);
}
/*
********************************************************************************
PUBLIC enableDayLinks -> calls same method of the parent class
If weekID>1 the following method setFirstWeekDay() will set Sunday or Monday as first week day
$daynum=0 -> Sunday
The setFirstWeekDay() does not affect the method showWeeks()
********************************************************************************
*/
function setFirstWeekDay($daynum){
parent::setFirstWeekDay($daynum);
}
/*
----------------------
@START PRIVATE METHODS
----------------------
*/
/*
********************************************************************************
PRIVATE mkWeeksHead() -> creates the week table tag
********************************************************************************
*/
function mkWeeksHead(){
return "<table class=\"".$this->cssWeeksTable."\">\n";
}
/*
********************************************************************************
PRIVATE mkWeekDayz() -> creates the tr tag of the week table for the weekdays
********************************************************************************
*/
function mkWeekDayz(){
$out="<tr>";
	if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNumTitle."\">".$this->weekNumTitle."</td>";
	for ($x=0; $x<=6; $x++){
		$out.=$this->mkSingleWeekDay($this->actday+$x);
	}
$out.="</tr>\n";
return $out;
}
/*
********************************************************************************
PRIVATE mkWeeksBody() -> creates the tr tags of the week table
********************************************************************************
*/
function mkWeeksBody($numberOfWeeks) {
$this->resetSelectedToToday();
$out = $this->mkMonthRow();
	for ($week = 0; $week < $numberOfWeeks; $week++) {
	$out .= "<tr>";
	$weeknumber=parent::mkWeekNum($this->actday);
	$weekday=parent::getWeekday($this->actday);
		if ($this->startOnSun && ($weekday>4 || $weekday==0)) $weeknumber=parent::mkWeekNum($this->actday+1); // week starts on Monday in date("w")
		if ($this->weekNum) $out.="<td class=\"".$this->cssWeekNum."\">".$weeknumber."</td>";
		for ($i = 0; $i <= 6; $i++){
		$out.=$this->mkDay($this->actday);
		$this->__construct($this->actyear, $this->actmonth,$this->actday+1,$this->GMT);
		$this->resetSelectedToToday();
		}
		$out.="</tr>\n";
		if ($this->actday+6>$this->getDaysThisMonth() && $week<$numberOfWeeks-1) $out.= $this->mkMonthRow(false);
		elseif ($this->actday==1 && $week<$numberOfWeeks-1) $out.=$this->mkMonthRow();
		}
return $out;
}
/*
********************************************************************************
PRIVATE mkWeeksFoot() -> closes the week table tag
********************************************************************************
*/
function mkWeeksFoot(){
return "</table>\n";
}
/*
********************************************************************************
PRIVATE mkMonthRow() -> creates the tr tag of the week table to tisplay month names when needed
The parameter indicates if the name of the first month is needed (at the beginning of the weekly calendar).
********************************************************************************
*/
function mkMonthRow($bothMonths=true) {
$colspanLeft =  min($this->getDaysThisMonth() - $this->actday + 1, 7);
$colspanRight = 7 - $colspanLeft;
$out = '<tr>';
	if ($this->weekNum) $out.="<td></td>";
$out .= '<td class="' . $this->cssMonthWeek . '" colspan=\"' . $colspanLeft . '\">';
	if ($bothMonths) $out .= parent::getMonthName($this->actmonth).$this->monthYearDivider.$this->actyear;
$out .= '</td>';
	if ($colspanRight>0){
		if ($this->actmonth+1>12){
			$calmonth=1;
			$calyear=$this->actyear+1;
		}
		else {
			$calmonth=$this->actmonth+1;
			$calyear=$this->actyear;
		}
		$out .= '<td class="' . $this->cssMonthWeek . '" colspan=\"' . $colspanRight . '\">'.parent::getMonthName($calmonth).$this->monthYearDivider.$calyear.'</td>';
	}
return $out;
}
/* Helper methods */
function mkSingleWeekDay($var){
$weekday=parent::getWeekday($var);
$out ="<td class=\"".$this->cssWeekDay."\">".parent::getDayName($weekday)."</td>";
return $out;
}
function getDaysThisMonth() {
return $this->getMonthDays($this->actmonth, $this->actyear);
}
function resetSelectedToToday() {
$this->selectedyear = $this->yeartoday;
$this->selectedmonth = $this->monthtoday;
$this->selectedday = $this->daytoday;
}
}
?>