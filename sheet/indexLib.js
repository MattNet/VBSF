/*
This file provides all of the functions referenced by the client-side code.
*/

/*
Starts up whatever function on the complete load of the page (after images are 
finished).  Cross-browser

PARAMS: Function callback to be executed on window load
RETURN: None
*/
function onLoadStartUp( startupFunc )
{
  if( window.addEventListener ){
    window.addEventListener("load",startupFunc,false);
  } else if( window.attachEvent ){
    window.attachEvent("onload",startupFunc);
  } else {
    window.onload = startupFunc;
  }
}

/***
# Fakes a nextElementSibling method call
# A functional equivilent to the firefox method.
###
# Takes in an object (e.g. 'this') and returns the next (non-text, non-comment)
# element in the DOM
***/
function nextElementSibling( el )
{
    do { el = el.nextSibling } while ( el && el.nodeType !== 1 );
    return el;
}

/***
# Finds within the document a named element or an element with the given ID
###
# Accepts the name string or ID string as the first element.
# Optionally, if the second argument is a javascript object (e.g. a document 
#   object inside a frame), will search within that object hierarchy
***/
function ElementFind( search_item, obj )
{
  if( !obj )
    obj = top;
  if( obj.document.getElementById && obj.document.getElementById(search_item) )
    return obj.document.getElementById(search_item);
  else if( obj.document.getElementsByName && obj.document.getElementsByName(search_item) )
    return obj.document.getElementsByName(search_item);
  else if (obj.document.all && obj.document.all[search_item])
    return obj.document.all[search_item];
  else if (obj.document[search_item])
    return obj.document[search_item];
  else if (obj.frames && obj.frames[search_item])
    return obj.frames[search_item];
  return false;
}

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

/***
# Concatonates up to four JSON arrays together and returns the result. The 
#   original arrays are untouched
###
# Each argument is optional
***/
function JsonConcatArrays( arA, arB, arC, arD )
{
  var output = Array();
  if( typeof arA !== 'undefined' && arA.length>0 )
    for( var i=0;i<arA.length;i++ )
      output.push(arA[i]);
  if( typeof arB !== 'undefined' && arB.length>0 )
    for( var i=0;i<arB.length;i++ )
      output.push(arB[i]);
  if( typeof arC !== 'undefined' && arC.length>0 )
    for( var i=0;i<arC.length;i++ )
      output.push(arC[i]);
  if( typeof arD !== 'undefined' && arD.length>0 )
    for( var i=0;i<arD.length;i++ )
      output.push(arD[i]);
  return output;
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
  var orderIndexArray = orders; // the array of objects holding the user's orders

  var firstSiblingMenu = el.nextElementSibling || nextElementSibling(el);
  var secondSiblingMenu = firstSiblingMenu.nextElementSibling || nextElementSibling(firstSiblingMenu);
  var textMenu = secondSiblingMenu.nextElementSibling || nextElementSibling(secondSiblingMenu);

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
  for( var i = 0; i < orderTable[el.value][0].length; i++ )
  {
    var o = document.createElement("option");
    o.text = orderTable[el.value][0][i];
    if( ! isNaN(indexSecond) && orderIndexArray[indexSecond].reciever == orderTable[el.value][0][i] )
      o.selected = true;

    firstSiblingMenu.appendChild(o);
  }
  // fill the second sibling menu
  for( var i = 0; i < orderTable[el.value][1].length; i++ )
  {
    var o = document.createElement("option");
    o.text = orderTable[el.value][1][i];
    if( ! isNaN(indexThird) && orderIndexArray[indexThird].target == orderTable[el.value][1][i] )
      o.selected = true;

    secondSiblingMenu.appendChild(o);
  }
  // fill the placeholder text of the text menu
  textMenu.placeholder = orderTable[el.value][2];
  if( ! isNaN(indexNote) && orderIndexArray[indexNote].target == orderTable[el.value][1][i] )
    textMenu.textContent = orderIndexArray[indexNote].note;
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
#    ['1st drop down', '1st drop down',], 
#    ['2nd drop down', '2nd drop down'],
#    'Text Input', 'Description of order'
#  ];
***/
function OrderOutput( orderNum, index )
{
  var orderIndexArray = orders; // the array of objects holding the user's orders
  var fillArray = Object.keys( orderTable ); // The array with the initial order types
  var orderRecieversArray = []; // The array with the order recievers
  var orderTargetsArray = []; // The array with the order targets
  var namePrefix = 'OrderEntry';
  var output = '';


  output += "<br><select name='"+namePrefix+orderNum+"A' onchange='changeMenu(this";
  // this argument allows the drop-downs to "lock onto" the given order when
  // they select the given order type
  if( ! isNaN(index) )
    output += ", "+index;
  output += ")'><option><-- No Order --></option>"
  for( i=0; i<fillArray.length; i++ )
  {
    output += "<option ";

    if( ! isNaN(index) && orderIndexArray[index].type == fillArray[i] )
    {
      output += "SELECTED ";

      // populate the key lookups for the dropdowns following the initial order
      orderRecieversArray = orderTable[ fillArray[i] ][0]; // The array with the order recievers
      orderTargetsArray = orderTable[ fillArray[i] ][1]; // The array with the order targets
    }

    output += "value='"+fillArray[i]+"'>"+orderTable[ fillArray[i] ][3]+"</option>";
  }
  output += "</select><select name='"+namePrefix+orderNum+"B'>";
  if( ! isNaN(index) )
  {
    for( var i = 0; i < orderRecieversArray.length; i++ )
    {
      output += "<option ";
      if( orderIndexArray[index].reciever == orderRecieversArray[i] )
        output += "SELECTED ";
      output += "value='"+orderRecieversArray[i]+"'>"+orderRecieversArray[i]+"</option>";
    }
  }
  output += "</select><select name='"+namePrefix+orderNum+"C'>";
  if( ! isNaN(index) )
  {
    for( var i = 0; i < orderTargetsArray.length; i++ )
    {
      output += "<option ";
      if( orderIndexArray[index].target == orderTargetsArray[i] )
        output += "SELECTED ";
      output += "value='"+orderTargetsArray[i]+"'>"+orderTargetsArray[i]+"</option>";
    }
  }
  output += "</select><input type='text' name='";
  output += namePrefix+orderNum+"D'";
  if( ! isNaN(index) )
    output += "value='"+orderIndexArray[index].note+"' ";
  output += ">";
  return output;
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
function calcColonyOutput( obj )
{
  var output = 0;

  // Skip if some values are not numbers
  if( isNaN(obj.raw) || isNaN(obj.census) || isNaN(obj.prod) || isNaN(obj.morale) )
    return "??";

  output = obj.raw;
  if( obj.census < obj.prod )
    output *= obj.census;
  else
    output *= obj.prod;
  if( obj.morale < (obj.census / 2) )
    output *= 0.5;
  else if( obj.morale == 0 )
    output = 0;

  return output;
}

/***
# Creates a window that shows the given text
###
# Argument is a text string
***/
function popitupEvent( text )
{
  var newwindow=window.open('','Event Description','resizable=yes,scrollbars=yes');
  if(window.focus)
    newwindow.focus();
  var tmp=newwindow.document;
  tmp.write(text);
  tmp.close();
  return false;
}

/***
# Counts how many of each unit exist in the given array
###
# Output format is [ ['designation','count', 'index into unitList'], ... ]
***/
function UnitCounts( unitArray, previousCounts = [] )
{
  var output = [];
  // if 'previousCounts' was actually filled
  if( previousCounts.length > 0 )
    output = previousCounts;

  for( var b=0; b<unitArray.length; b++ )
  {
    var flag = 0;
    // increment an existing instance of an entry
    for( var c=0; c<output.length; c++ )
      if( flag == 0 && unitArray[b] == output[c][0] )
      {
        output[c][1]++;
        flag = 1;
      }
    // This is for the first instance of an entry
    if( flag == 0 )
    {
      var notes = '';
      var index = "not in unit list";
      for( var c=0; c<unitList.length; c++ )
        if( unitList[c].ship == unitArray[b] )
          index = c;
      output.push( [ unitArray[b], 1, index ] );
    }
  }
  return output;
}

