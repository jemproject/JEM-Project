/*
 *	Gchats color picker by Majid Khosravi
 *	Copyright (c) 2006 - 2008 Gchat Design Studio
 *	URL: https://www.gchats.com
 *	Last Updated: August 29 2009
 *  Gchats color picker is freely distributable under the terms of GPL license.
 *  Please visit: https://www.gchats.com for updates
 *  @Version 1.2
 *  
 *  The code in function testcolor is based upon:
 *  https://www.nbdtech.com/Blog/archive/2008/04/27/Calculating-the-Perceived-Brightness-of-a-Color.aspx
 *  
 *--------------------------------------------------------------------------*/

const ColorPickerState = {
    layerWidth: 218,
    layerHeight: 144,
    currentId: "",
    orgColor: "",
    onPick: null,
    onCancel: null
};

function isValidColor(color) {
    return /^#[0-9A-F]{6}$/i.test(color);
}

function openPicker(id, _onPick, _onCancel) {
    if (_onPick) {
        ColorPickerState.onPick = _onPick;
    }
    if (_onCancel) {
        ColorPickerState.onCancel = _onCancel;
    }
    ColorPickerState.currentId = id;
    removeLayer("picker");
    
    const targetObj = document.getElementById(id);
    if (!targetObj) {
        console.error('Target element not found:', id);
        return;
    }

    ColorPickerState.orgColor = targetObj.value;
    const pos = targetObj.getBoundingClientRect();
    createLayer("picker", pos.right + 20, pos.top);
}

function createLayer(id, left, top) {
    const existingLayer = document.getElementById(id);
    if (existingLayer) {
        return;
    }

    const layer = document.createElement('div');
    layer.id = id;
    layer.className = "picker_layer";
    
    Object.assign(layer.style, {
        position: 'absolute',
        left: `${left}px`,
        top: `${top}px`,
        width: `${ColorPickerState.layerWidth}px`,
        height: `${ColorPickerState.layerHeight}px`,
        zIndex: '1000',
        textAlign: 'left'
    });

    layer.innerHTML = getPickerContent();
    document.body.appendChild(layer);
    
    // Event Listener für die Farbzellen hinzufügen
    setupColorCellEvents();
}

function setupColorCellEvents() {
    const cells = document.querySelectorAll('.cell_color');
    cells.forEach(cell => {
        cell.addEventListener('mouseover', () => showClr(cell.dataset.color));
        cell.addEventListener('click', () => setClr(cell.dataset.color));
    });
}

function showClr(color) {
    if (!isValidColor(color)) {
        console.error('Invalid color format:', color);
        return;
    }

    const targetObj = document.getElementById(ColorPickerState.currentId);
    const colorSample = document.getElementById("gcpicker_colorSample");
    const colorCode = document.getElementById("gcpicker_colorCode");
    
    if (!targetObj || !colorSample || !colorCode) {
        console.error('Required elements not found');
        return;
    }
    
    targetObj.value = color;
    targetObj.style.backgroundColor = color;
    colorSample.style.backgroundColor = color;
    colorCode.textContent = color;
}

function setClr(color) {
    if (!isValidColor(color)) {
        console.error('Invalid color:', color);
        return;
    }

    const targetObj = document.getElementById(ColorPickerState.currentId);
    if (!targetObj) {
        console.error('Target element not found:', ColorPickerState.currentId);
        return;
    }

    targetObj.value = color;
    targetObj.style.backgroundColor = color;
    
    if (ColorPickerState.onPick) {
        const fontColor = testcolor(color);
        targetObj.style.color = fontColor;
    }
    
    ColorPickerState.currentId = "";
    removeLayer("picker");
}

function cancel() {
    const targetObj = document.getElementById(ColorPickerState.currentId);
    if (!targetObj) {
        console.error('Target element not found:', ColorPickerState.currentId);
        return;
    }

    targetObj.value = ColorPickerState.orgColor;
    targetObj.style.backgroundColor = ColorPickerState.orgColor;
    removeLayer("picker");
    
    if (ColorPickerState.onCancel) {
        ColorPickerState.onCancel();
    }
}

function removeLayer(id) {
    const element = document.getElementById(id);
    if (element) {
        element.innerHTML = '';
        element.remove();
    }
}

function getPickerContent() {
    return `
        <div class="picker-container">
            <table width="222" border="0" cellpadding="0" cellspacing="1">
                <tr>
                    <td>
                        <table width="100%" border="0" cellpadding="0" cellspacing="1" class="color_table">
                            <tr>
                                <td bgcolor="${ColorPickerState.orgColor}" id="gcpicker_colorSample" width="40px" class="choosed_color_cell">&nbsp;</td>
                                <td align="center"><div id="gcpicker_colorCode">${ColorPickerState.orgColor}</div></td>
                                <td width="60px" align="center">
                                    <button onclick="cancel()" class="default_color_btn">Reset</button>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td>${colorTable()}</td>
                </tr>
            </table>
        </div>
    `;
}

function colorTable() {
    const basicColors = [
        "#000000", "#333333", "#666666", "#999999", "#cccccc", "#ffffff",
        "#ff0000", "#00ff00", "#0000ff", "#ffff00", "#00ffff", "#ff00ff"
    ];

    let table = '<table border="0" cellpadding="0" cellspacing="0" bgcolor="#000000"><tr>';
    
    // Basis-Farben
    for (let j = 0; j < 3; j++) {
        table += '<td width="11"><table bgcolor="#000000" border="0" cellpadding="0" cellspacing="1" class="color_table">';
        for (let i = 0; i < 12; i++) {
            const color = j === 1 ? basicColors[i] : '#000000';
            table += `<tr><td bgcolor="${color}" class="cell_color" data-color="${color}"></td></tr>`;
        }
        table += '</table></td>';
    }
    
    // Farbspektrum
    table += '<td><table border="0" cellpadding="0" cellspacing="0">';
    for (let c = 0; c < 6; c++) {
        if (c === 0 || c === 3) table += "<tr>";
        table += "<td>";
        
        table += '<table border="0" cellpadding="0" cellspacing="1" class="color_table">';
        for (let j = 0; j < 6; j++) {
            table += "<tr>";
            for (let i = 0; i < 6; i++) {
                const color = rgb2hex(j * 255 / 5, i * 255 / 5, c * 255 / 5);
                table += `<td bgcolor="${color}" class="cell_color" data-color="${color}"></td>`;
            }
            table += "</tr>";
        }
        table += "</table></td>";
        if (c === 2 || c === 5) table += "</tr>";
    }
    table += '</table></td></tr></table>';
    return table;
}

function rgb2hex(red, green, blue) {
    const r = Math.max(0, Math.min(255, Math.round(red))).toString(16).padStart(2, '0');
    const g = Math.max(0, Math.min(255, Math.round(green))).toString(16).padStart(2, '0');
    const b = Math.max(0, Math.min(255, Math.round(blue))).toString(16).padStart(2, '0');
    return `#${r}${g}${b}`;
}

function testcolor(color) {
    if (!color || typeof color !== 'string') {
        return '#000000';
    }
    
    color = color.replace(/^#/, '');
    if (!/^[0-9A-F]{6}$/i.test(color)) {
        return '#000000';
    }
    
    const R = parseInt(color.substring(0, 2), 16);
    const G = parseInt(color.substring(2, 4), 16);
    const B = parseInt(color.substring(4, 6), 16);
    const brightness = Math.sqrt(
        R * R * 0.299 +
        G * G * 0.587 +
        B * B * 0.114
    );
    
    return brightness < 130 ? '#FFFFFF' : '#000000';
}

// CSS Styles
const styles = `
.picker_layer {
    background: #ffffff;
    border: 1px solid #ccc;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 4px;
    padding: 10px;
}

.cell_color {
    width: 15px;
    height: 15px;
    cursor: pointer;
}

.cell_color:hover {
    outline: 1px solid #fff;
}

.choosed_color_cell {
    border: 1px solid #ccc;
}

.default_color_btn {
    padding: 4px 8px;
    border: 1px solid #ccc;
    background: #f0f0f0;
    cursor: pointer;
    border-radius: 3px;
}

.default_color_btn:hover {
    background: #e0e0e0;
}
`;

// Styles einfügen
const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);