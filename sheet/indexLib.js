/*
This file provides all of the functions referenced by the client-side code.

newRound(number, decimal)
Rounds a number to a specified number of decimal places.
Returns: rounded number.

makeFancyMonth(monthNum, monthsYear)
Converts a game month index into a month or season name.
Returns: month/season name string.

JsonConcatArrays(...arrays)
Concatenates up to four JSON arrays into one.
Returns: merged array.

changeMenu(el, indexFirst, indexSecond, indexThird, indexNote)
Updates dropdown menus and text input based on orderTable.
Returns: nothing (updates DOM elements).

OrderOutput(orderNum, index)
Builds and inserts HTML inputs for an order line.
Returns: nothing (modifies DOM).

calcColonyOutput({ raw, census, prod, morale })
Computes economic output of a colony from attributes.
Returns: number or "??" if invalid.

popitupEvent(text)
Opens a new window showing provided text.
Returns: false.

UnitCounts(units, previousCounts=[])
Counts units in an array and compares with existing counts.
Returns: array of [designation, count, index].

buildThemeDropdown()
Creates a theme selection dropdown from themeNames.
Returns: nothing (updates DOM).

applyStyleSheet(sheetName)
Applies a stylesheet and saves the choice to LocalStorage.
Returns: nothing.
*/

/*
Performs a more natural rounding than Math.round()
In that this will preserve decimal places if needed

PARAMS: The number to be rounded, and how many places to round
RETURN: The rounded number
*/
function newRound( number, decimal )
{
  return Number(Math.round(number+'e'+decimal)+'e-'+decimal);
}

/*
Equates an in-game month to a month-name or a season-name

PARAMS: (int) The current month. 1-based
        (int) How many turns per year
RETURN: (string) The name of the current month
*/
function makeFancyMonth(monthNum, monthsYear)
{
  if (monthsYear < 2 || monthsYear > 12 || monthNum < 0) return "";

  if (monthsYear === 2) return monthNum === 1 ? "Spring" : "Fall";
  if (monthsYear === 4) return ["Spring","Summer","Fall","Winter"][(monthNum - 1) % 4];

  return ["January","February","March","April","May","June",
          "July","August","September","October","November","December"]
          [Math.floor(((monthNum - 1) / monthsYear) * 12 ) % 12 ];
}

/***
# Concatonates up to four JSON arrays together and returns the result. The 
#   original arrays are untouched
###
# Each argument is optional
***/
function JsonConcatArrays( ...arrays )
{
  return arrays.flat().filter(Boolean);
}

/***
# Changes the two following dropdown menus and a text input, based on the 
#   contents of a global array 'orderTable'
###
# Accepts the element object who's value is a key in 'orderTable'
# 'orderTable' must be a multi-dimensional array of the following sample format
#
# orderTable['reference key'] = [
#    ['1st drop down', '1st drop down',], 
#    ['2nd drop down', '2nd drop down'],
#    'Text Input'
#  ];
#
# Optionally accepts an index into the array holding objects that describes 
#   an order-chain to be selected. The array being indexed, named 'orders', 
#   is in the following format:
#
# orders = [
#   { 'type':'', 'reciever':'', 'target':'', 'note':'' }
# ];
***/
function changeMenu( el, indexFirst, indexSecond, indexThird, indexNote )
{
  const orderIndexArray = orders; // the array of objects holding the user's orders
  const firstSiblingMenu = el.nextElementSibling || nextElementSibling(el);
  const secondSiblingMenu = firstSiblingMenu.nextElementSibling || nextElementSibling(firstSiblingMenu);
  const textMenu = secondSiblingMenu.nextElementSibling || nextElementSibling(secondSiblingMenu);

  // empty the sibling menus
  while( firstSiblingMenu.firstChild )
      firstSiblingMenu.removeChild(firstSiblingMenu.firstChild);
  while( secondSiblingMenu.firstChild )
      secondSiblingMenu.removeChild(secondSiblingMenu.firstChild);
  textMenu.value = '';
  textMenu.placeholder = '';

  // quit here if no order given
  if( el.selectedIndex == 0 && isNaN(indexFirst) )
      return;

  // fill the first sibling menu
  for( let optionText of orderTable[el.value][0] )
  {
    const o = document.createElement("option");
    o.text = optionText;
    if( ! isNaN(indexSecond) && orderIndexArray[indexSecond].reciever === optionText )
      o.selected = true;
    firstSiblingMenu.appendChild(o);
  }
  // fill the second sibling menu
  for( let optionText of orderTable[el.value][1] )
  {
    const o = document.createElement("option");
    o.text = optionText;
    if( ! isNaN(indexThird) && orderIndexArray[indexThird].target === optionText )
      o.selected = true;
    secondSiblingMenu.appendChild(o);
  }

  // fill the placeholder text of the text menu
  textMenu.placeholder = orderTable[el.value][2];
  if( ! isNaN(indexNote) )
    textMenu.value = orderIndexArray[indexNote].note;
}

/***
# Assembles the HTML inputs for the orders
###
# The first argument is the line-number for this order
#
# Optionally accepts an index into the array holding objects that describes 
#   an order-chain to be selected. The array being indexed, named 'orders', 
#   is in the following format:
#
# orders = [
#   { 'type':'', 'reciever':'', 'target':'', 'note':'' }
# ];
#
# This function references 'orderTable'
# 'orderTable' must be a multi-dimensional array of the following sample format
#
# orderTable['reference key'] = [
#    ['1st drop down', '1st drop down'], 
#    ['2nd drop down', '2nd drop down'],
#    'Text Input', 'Description of order'
#  ];
***/
function OrderOutput( orderNum, index )
{
  const ordersArea = document.getElementById( "ordersArea" );
  const orderIndexArray = orders;
  const fillArray = Object.keys( orderTable );
  const namePrefix = "OrderEntry";

  let orderRecieversArray = [];
  let orderTargetsArray = [];

  // container fragment (can hold multiple nodes)
  const frag = document.createDocumentFragment();

  // --- Order type select (A) ---
  const selectA = document.createElement( "select" );
  selectA.name = namePrefix + orderNum + "A";

  // No order option
  const noOrderOpt = document.createElement( "option" );
  noOrderOpt.textContent = "<-- No Order -->";
  selectA.appendChild( noOrderOpt );

  // Fill options
  let currentOptgroup = null;
  for (let typeKey of fillArray) {
    if (typeKey.startsWith("header_")) {
      // start a new optgroup
      if (currentOptgroup) selectA.appendChild(currentOptgroup);
      currentOptgroup = document.createElement("optgroup");
      currentOptgroup.label = orderTable[typeKey][0];
      continue;
    }

    const opt = document.createElement("option");
    opt.value = typeKey;
    opt.textContent = orderTable[typeKey][3];

    // --- Disable if not in the correct turn segment ---
    const segment = orderTable[typeKey][4]; // The pre/post note
    if (segment.toLowerCase() !== game.turnSegment.toLowerCase()) {
      opt.disabled = true;
    }

    if (!isNaN(index) && orderIndexArray[index].type === typeKey) {
      opt.selected = true;
      orderRecieversArray = orderTable[typeKey][0];
      orderTargetsArray = orderTable[typeKey][1];
    }

    (currentOptgroup || selectA).appendChild(opt);
  }
  if (currentOptgroup) selectA.appendChild(currentOptgroup);

  selectA.addEventListener( "change", function (){ changeMenu( this, index ); });

  frag.appendChild( document.createElement("br") );
  frag.appendChild( selectA );

  // --- Order reciever select (B) ---
  const selectB = document.createElement( "select" );
  selectB.name = namePrefix + orderNum + "B";

  if( ! isNaN(index) )
  {
    for( let rec of orderRecieversArray )
    {
      const opt = document.createElement( "option" );
      opt.value = rec;
      opt.textContent = rec;
      if( orderIndexArray[index].reciever === rec )
        opt.selected = true;
      selectB.appendChild( opt );
    }
  }

  frag.appendChild( selectB );

  // --- Order target select (C) ---
  const selectC = document.createElement( "select" );
  selectC.name = namePrefix + orderNum + "C";

  if( ! isNaN(index) )
  {
    for( let tar of orderTargetsArray )
    {
      const opt = document.createElement( "option" );
      opt.value = tar;
      opt.textContent = tar;
      if( orderIndexArray[index].target === tar )
        opt.selected = true;
      selectC.appendChild( opt );
    }
  }

  frag.appendChild( selectC );

  // --- Order note input (D) ---
  const inputD = document.createElement( "input" );
  inputD.type = "text";
  inputD.name = namePrefix + orderNum + "D";
  if( ! isNaN(index) )
    inputD.value = orderIndexArray[index].note || "";

  frag.appendChild( inputD );

  ordersArea.appendChild( frag );
}

/***
# Figures the economic output of a colony
###
# Input argument is a JSON object with the following property names:
#   census
#   morale
#   raw
#   prod
***/
function calcColonyOutput({ raw, population, morale })
{
  if ([raw, population, morale].some(Number.isNaN)) return "??";
  if (morale === 0) return 0;

  let output = raw * population;
  if (morale < population / 2) output *= 0.5;
  return output;
}

/***
# Creates a window that shows the given text
###
# Argument is a text string
***/
function popitupEvent( text )
{
  let newwindow=window.open('','Event Description','resizable=yes,scrollbars=yes');
  if(window.focus)
    newwindow.focus();
  let tmp=newwindow.document;
  tmp.write(text);
  tmp.close();
  return false;
}

/***
# Counts how many of each unit exist in the given array
###
# Output format is [ ['designation','count', 'index into unitList'], ... ]
***/
function UnitCounts(units, previousCounts = []) {
  const counts = new Map(previousCounts.map(([name, count, index]) => [name, [count, index]]));

  for (const unit of units) {
    // Check if the entry is in "NxDesignation" format
    const match = /^(\d+)x(.+)$/.exec(unit);
    if (match) {
      const num = parseInt(match[1], 10);
      const designation = match[2].trim();

      if (!counts.has(designation)) {
        const idx = unitList.findIndex(u => u.ship === designation);
        counts.set(designation, [num, isNaN(idx) ? "not in unit list" : idx]);
      } else {
        counts.get(designation)[0] += num;
      }
    } else {
      // Normal entry
      if (!counts.has(unit)) {
        const idx = unitList.findIndex(u => u.ship === unit);
        counts.set(unit, [1, isNaN(idx) ? "not in unit list" : idx]);
      } else {
        counts.get(unit)[0]++;
      }
    }
  }
  return Array.from(counts, ([designation, [count, index]]) => [designation, count, index]);
}

/***
# Builds a dropdown for the CSS theme
###
# Pulls the names from 'themeNames'
# Stores the current style in LocalStorage, not in cookies
***/
function buildThemeDropdown()
{
  const themeSelect = document.getElementById( "themeSelect" );
  const savedSheet = localStorage.getItem( "sheet" ) || themeNames[0].file;

  themeSelect.innerHTML = ""; // clear any existing options

  // Build the dropdown from themeNames
  themeNames.forEach( sheet => {
    const opt = document.createElement( "option" );
    opt.value = sheet.file;
    opt.textContent = sheet.name;
    if( savedSheet === sheet.file )
      opt.selected = true;
    themeSelect.appendChild( opt );
  });

  // Apply saved theme on load
  applyStyleSheet( savedSheet );
}

/***
# Applies a style and saves to LocalStorage
###
# Argument is the style URL
***/
function applyStyleSheet( sheetName )
{
  const linkTag = document.getElementById( "pagestyle" );
  if( linkTag )
  {
    linkTag.setAttribute( "href", "./"+sheetName+".css" );
    localStorage.setItem( "sheet", sheetName );
  }
}

