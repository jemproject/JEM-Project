/*
 *	Gchats color picker by Majid Khosravi
 *	Copyright (c) 2006 - 2008 Gchat Design Studio
 *	URL: http://www.gchats.com
 *	Last Updated: August 29 2009
 *  Gchats color picker is freely distributable under the terms of GPL license.
 *  Please visit: http://www.gchats.com for updates
 *  @Version 1.2
 *  
 *  The code in function testcolor is based upon:
 *  http://www.nbdtech.com/Blog/archive/2008/04/27/Calculating-the-Perceived-Brightness-of-a-Color.aspx
 *  
 *--------------------------------------------------------------------------*/
// JavaScript Document
var layerWidth = 218;
var layerHeight = 144;
var currentId = "";
var orgColor ="";
var onPick = "";
var onCancel = "";
function openPicker(id, _onPick, _onCancel) {
	if (_onPick) {
		onPick = _onPick;
	}
	if (_onCancel) {
		onCancel = _onCancel;
	}
	currentId = id;
	removeLayer("picker");
	Obj = document.getElementById(id);
	
	orgColor = Obj.value;
	createLayer("picker",findPosX(Obj)+Obj.offsetWidth+20,findPosY(Obj));
}

function createLayer(id,left,top){
	var width = layerWidth;
	var height = layerHeight;
	var zindex = 1000;
	var bgcolor = "#d4d0c8";
	var txtcolor = "#000000";
	var msg = getPickerContent();
	if (document.layers) {
		if (document.layers[id]) {
		   return;
		}
		var layer=document.layers[id]=new Layer(width);
		layer.className = "picker_layer";
		layer.name = id;
		layer.left=left;
		layer.top=top;
		layer.clip.height=height;
		layer.visibility = 'show';
		layer.zIndex=zindex;
		layer.bgColor=bgcolor;
		layer.innerHTML = msg;
	}else if (document.all) {
		if (document.all[id]) {
			return
		}
  		var layer= '\n<DIV class="picker_layer" id='+id+' style="position:absolute'
		+'; left:'+left+"px"
		+'; top:'+top+"px"
		+'; width:'+width
		+'; height:'+height
		+'; visibility:visible'
		+'; z-index:'+zindex
		+';text-align:left">'
		+ msg
		+'</DIV>';
		document.body.insertAdjacentHTML("BeforeEnd",layer);
	}else if(document.getElementById){
		var layer = document.createElement ('div');
		layer.setAttribute ('id', id);
		document.body.appendChild (layer);
		var ly = document.getElementById(id);
		ly.className = "picker_layer";
		ly.style.position= "absolute";
		ly.style.left= left+"px";
		ly.style.top= top+ "px";
		ly.style.width= width+ "px";
		ly.style.height= height+ "px";
		ly.style.textAlign= "left";
		ly.innerHTML = msg;
	}
}
function showClr(color){
	Obj = document.getElementById(currentId);
	Obj.value = color;
	Obj.style.backgroundColor=color;
	Obj = document.getElementById("gcpicker_colorSample");
	Obj.style.backgroundColor=color;
	Obj = document.getElementById("gcpicker_colorCode");
	Obj.innerHTML = color;

}
function setClr(color){
	Obj = document.getElementById(currentId);
	Obj.value = color;
	Obj.style.backgroundColor=color;
	currentId = "";
	removeLayer("picker");
	if (onPick) {
		var fontcolor = testcolor(color);
		Obj.style.color=fontcolor;
	}
}
function cancel(){
	Obj = document.getElementById(currentId);
	Obj.value = orgColor;
	Obj.style.backgroundColor=orgColor;
	removeLayer("picker");
	if (onCancel) {
	}
}
function removeLayer(id){
	if(document.getElementById(id) ==null){
		return;
	}
	if (document.layers && document.layers[id]) {
  		document.layers[id].visibility='hide';
		delete document.layers[id];
	}
	if (document.all && document.all[id]) {
		document.all[id].innerHTML='';
		document.all[id].outerHTML='';
	}else if(document.getElementById){
		 var b = document.body;
 		 var layer = document.getElementById(id);
	 	 b.removeChild(layer);
	}
}
function getPickerContent(){
	var content = 	'<table width="222" border="0" cellpadding="0" cellspacing="1"><tr><td>';
	content += '<table width="100%" border="0" cellpadding="0" cellspacing="1" class="color_table"><tr><td bgcolor="'+orgColor+'" id="gcpicker_colorSample" width="40px" class="choosed_color_cell">&nbsp;</td><td align="center"><div id="gcpicker_colorCode">'+orgColor+'</div></td><td width="60px" align="center"><input type="submit" value="" onclick="cancel()" class="default_color_btn" /></td></tr></table>';
	content += '</td></tr><tr><td>';
	content += colorTable()+'</td></tr></table>';
	return content;
}
function colorTable(){
	var clrfix = Array("#000000","#333333","#666666","#999999","#cccccc","#ffffff","#ff0000","#00ff00","#0000ff","#ffff00","#00ffff","#ff00ff");
	var table ='<table border="0"  cellpadding="0" cellspacing="0" bgcolor="#000000"><tr>';
	table += '';
	for(var j=0;j<3;j++){
		table += '<td width="11"><table bgcolor="#000000"  border="0"  cellpadding="0" cellspacing="1"  class="color_table">';
		for(var i=0;i<12;i++){
			var clr ='#000000';
			if(j==1){
				clr = clrfix[i];
			}
			table += '<tr><td bgcolor="'+clr+'" class="cell_color" onmouseover="showClr('+"'"+clr+"'"+')" onclick="setClr('+"'"+clr+"'"+')"></td></tr>';
		}
		table += '</table></td>';
	}
	table +='<td><table border="0" cellpadding="0" cellspacing="0">';
	for (var c = 0; c<6; c++) {
		if(c==0 || c==3){
			table +="<tr>";
		}
		table += "<td>";

		table = table+'<table border="0" cellpadding="0" cellspacing="1" class="color_table"> ';
		for (var j = 0; j<6; j++) {
			table +="<tr>";
			for (var i = 0; i<6; i++) {
				var clrhex = rgb2hex(j*255/5,i*255/5,c*255/5);
				table += '<td bgcolor="'+clrhex+'" class="cell_color" onmouseover="showClr('+"'"+clrhex+"'"+')" onclick="setClr('+"'"+clrhex+"'"+')"></td>';
			}
			table +="</tr>";
		}
		table +="</table>";
		table += "</td>";
		if(c==2 || c==5){
			table +="</tr>";
		}
	}
	table +='</table></td></tr></table>';
	return table;
}

function findPosX(obj){
	var curleft = 0;
	if(obj.offsetParent)
		while(1){
			curleft += obj.offsetLeft;
			if(!obj.offsetParent)
			break;
			obj = obj.offsetParent;
		}
	else if(obj.x)
	curleft += obj.x;
	return curleft;
}
function findPosY(obj){
	var curtop = 0;
	if(obj.offsetParent){
		while(1){
			curtop += obj.offsetTop;
			if(!obj.offsetParent){
				break;
			}
			obj = obj.offsetParent;
		}
	}else if(obj.y){
		curtop += obj.y;
	}
	return curtop;
}

function rgb2hex(red, green, blue)
{
	var decColor = red + 256 * green + 65536 * blue;
	var clr = decColor.toString(16);
	for(var i =clr.length;i<6;i++){
		clr = "0"+clr;
	}
	return "#"+clr;
}

function testcolor(color) {
	if(color.length==7)
	{
		color=color.substring(1);
	}
	var R = parseInt(color.substring(0,2),16);
	var G = parseInt(color.substring(2,4),16);
	var B = parseInt(color.substring(4,6),16);
	var x = Math.sqrt(R * R * .299 + G * G * .587 + B * B * .114);	
	var sColorText = x < 130 ? '#FFFFFF' : '#000000'; 
	
	return sColorText;
}	