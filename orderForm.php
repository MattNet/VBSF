<?php
###
# Accepts orders from the player sheet in the form of POST data
# and puts them into the data file
###

###
# Order names
###
# ORDER TYPE	-	ORDER NAME
# Break a treaty -	break
# Build unit -		build_unit
# Build intel points -	build_intel
# Colonize system -	colonize
# Convert Unit -	convert
# Cripple unit -	cripple
# Destroy unit -	destroy
# Assign flights -	flight
# Perform an intel action - intel
# Load units -		load
# Mothball a unit -	mothball
# Move fleet -		move
# Move unit -		move_unit
# (Re) name a place -	name
# Offer a treaty -	offer
# Increase productivity - productivity
# Repair unit -		repair
# Invest into research - research
# Sign a treaty -	sign
# Set a trade route -	trade_route
# Unload units -	unload
# Unmothball a unit -	unmothball
###

###
# Configuration
###
$EXIT_PAGE = $_SERVER["HTTP_HOST"]."/sheet/index.html"; // the page to show when the script is finished
$dataFileDir = "files/"; // location of the data files

###
# Initialization
###
include( "./postFunctions.php" );

$errorStrings = ""; // ongoing litany of errors to send to the UI

// Format is $orderTable['internal "type" keyword'] = [ require "reciever", require "target", require "note", 'external "type" phrase' ]
$orderTable = array();
$orderTable['break'] 		= [ false, false, false, 'Break a treaty' ];
$orderTable['build_unit'] 	= [ true, true, true, 'Build unit' ];
$orderTable['build_intel'] 	= [ true, false, true, 'Build intel points' ];
$orderTable['colonize'] 	= [ true, false, false, 'Colonize system' ];
$orderTable['convert'] 		= [ true, true, false, 'Convert Unit' ];
$orderTable['cripple'] 		= [ true, false, false, 'Cripple unit' ];
$orderTable['destroy'] 		= [ true, false, false, 'Destroy unit' ];
$orderTable['flight'] 		= [ true, true, false, 'Assign flights' ];
$orderTable['intel'] 		= [ true, true, true, 'Perform an intel action' ];
$orderTable['load'] 		= [ true, true, true, 'Load units' ];
$orderTable['mothball'] 	= [ true, false, false, 'Mothball a unit' ];
$orderTable['move'] 		= [ true, true, false, 'Move fleet' ];
$orderTable['move_unit'] 	= [ true, true, true, 'Move unit' ];
$orderTable['name'] 		= [ true, false, false, '(Re) name a place' ];
$orderTable['offer'] 		= [ true, true, false, 'Offer a treaty' ];
$orderTable['productivity'] 	= [ true, false, false, 'Increase productivity' ];
$orderTable['repair'] 		= [ true, false, false, 'Repair unit' ];
$orderTable['research'] 	= [ false, false, true, 'Invest into research' ];
$orderTable['sign'] 		= [ true, true, false, 'Sign a treaty' ];
$orderTable['trade_route'] 	= [ true, true, false, 'Set a trade route' ];
$orderTable['unload'] 		= [ true, false, true, 'Unload units' ];
$orderTable['unmothball'] 	= [ true, false, false, 'Unmothball a unit' ];

/*
Array
(
    [dataFile] = sample01
// "perm" orders below
    [OrderEntry0A] => move
    [OrderEntry0B] => Exploration Alpha
    [OrderEntry0C] => Fraxee Dir A
    [OrderEntry0D] => 
    [OrderEntry1A] => move
    [OrderEntry1B] => Exploration Beta
    [OrderEntry1C] => Fraxee Dir F
    [OrderEntry1D] => 
    [OrderEntry2A] => invest
    [OrderEntry2B] => 
    [OrderEntry2C] => 
    [OrderEntry2D] => 13
    [OrderEntry3A] => build
    [OrderEntry3B] => Colony Fleet
    [OrderEntry3C] => Fraxee
    [OrderEntry3D] => Colony-1
// drop-down entries below
    [OrderEntry4A] => productivity
    [OrderEntry4B] => Fraxee
    [OrderEntry4D] => 
    [OrderEntry5A] => colonize
    [OrderEntry5B] => Fraxee dir A
    [OrderEntry5D] => 
    [OrderEntry6A] => move
    [OrderEntry6B] => Exploration Beta
    [OrderEntry6C] => Fraxee
    [OrderEntry6D] => 
)

Always gives OrderEntryxD (the text-entry)
"finished" entries gives all four entries
unassigned orders are given "<-- No Order -->" for the A entry

"Order \""+orders[i].reciever+"\" to do \""+orders[i].type+"\" to \""+orders[i].target+"\" with \""+orders[i].note+"\"";

var orders = [
   {"type":"move","reciever":"Exploration Alpha","target":"Fraxee Dir A","note":"","perm":0},
   {"type":"move","reciever":"Exploration Beta","target":"Fraxee Dir F","note":"","perm":0},
   {"type":"invest","reciever":"","target":"","note":"13","perm":1},
   {"type":"build","reciever":"Colony Fleet","target":"Fraxee","note":"Colony-1","perm":0}
];
*/

// find the data-file name
$dataFileRoot = $_REQUEST["dataFile"];
$dataFileName = $dataFileDir.$dataFileRoot.".js";

// error-catching for inability to read or write to $dataFileName
if( ! is_readable($dataFileName) )
{
  echo "Cannot write to '$dataFileName'\n\n";
  exit(0);
}
if( ! is_writeable($dataFileName) )
{
  echo "Cannot write to '$dataFileName'\n\n";
  exit(0);
}


// get the data file
$fileContents = extractJSON( $dataFileName );

$flagDelete = -1; // set to an order number to be deleted

// Iterate through the order array
// If an order if "<-- No Order -->" then try to delete the entry from the data file
// otherwise add to the data file
foreach( array_keys($_REQUEST) as $key )
{
  // get the order to affect with this $key
  $orderNum = intval( substr( $key, 10, 1 ) );
  $orderPos = strtolower( substr( $key, 11, 1 ) );

  // skip if we failed to get the location inside the order from the $key
  if( $orderNum === false || $orderPos === false )
    continue;

  if( $_REQUEST[ $key ] == "<-- No Order -->" || $flagDelete == $orderNum )
  {
    $flagDelete = $orderNum;
    if( isset($fileContents["orders"][$orderNum]) )
      unset( $fileContents["orders"][$orderNum] ); // remove the deleted item
    continue; // order is empty
  }

  // set everything else. They might have changed

  // set up the entry to the orders array if not already
  if( ! isset( $fileContents["orders"][$orderNum]) )
    $fileContents["orders"][$orderNum] = array();

  // create a segment of an order with this $key entry
  switch($orderPos)
  {
  case "a":
    $fileContents["orders"][$orderNum]["type"] = $_REQUEST[$key];
    break;
  case "b":
    $fileContents["orders"][$orderNum]["reciever"] = $_REQUEST[$key];
    break;
  case "c":
    $fileContents["orders"][$orderNum]["target"] = $_REQUEST[$key];
    break;
  case "d":
    $fileContents["orders"][$orderNum]["note"] = $_REQUEST[$key];
    break;
  }
}

foreach( array_keys($fileContents["orders"]) as $orderNum )
{
// create any otherwise-empty order fields
  if( ! isset($fileContents["orders"][$orderNum]["type"]) )
    $fileContents["orders"][$orderNum]["type"] = "";
  if( ! isset($fileContents["orders"][$orderNum]["reciever"]) )
    $fileContents["orders"][$orderNum]["reciever"] = "";
  if( ! isset($fileContents["orders"][$orderNum]["target"]) )
    $fileContents["orders"][$orderNum]["target"] = "";
  if( ! isset($fileContents["orders"][$orderNum]["note"]) )
    $fileContents["orders"][$orderNum]["note"] = "";

// Check that all required fields are not blank
  if( ! isset( $orderTable[ $fileContents["orders"][$orderNum]["type"] ] ) )
  {
    $errorStrings .= "Order processing file does not know of order type '".$fileContents["orders"][$orderNum]["type"]."'.\n";
    unset( $fileContents["orders"][$orderNum] ); // delete the offending entry
  }
  $orderTableEntry = $orderTable[ $fileContents["orders"][$orderNum]["type"] ];
  if( empty($fileContents["orders"][$orderNum]["reciever"]) && $orderTableEntry[0] == true )
  {
    $errorStrings .= "Order type '".$orderTableEntry[3]."' requires a reciever. None given.\n";
    unset( $fileContents["orders"][$orderNum] ); // delete the offending entry
  }
  if( empty($fileContents["orders"][$orderNum]["target"]) && $orderTableEntry[1] == true )
  {
    $errorStrings .= "Order type '".$orderTableEntry[3]."' requires a target. None given.\n";
    unset( $fileContents["orders"][$orderNum] ); // delete the offending entry
  }
  if( empty($fileContents["orders"][$orderNum]["note"]) && $orderTableEntry[2] == true )
  {
    $errorStrings .= "Order type '".$orderTableEntry[3]."' requires a note. None given.\n";
    unset( $fileContents["orders"][$orderNum] ); // delete the offending entry
  }
}

// re-index the orders array
$fileContents["orders"] = array_values( $fileContents["orders"] );

// write the (edited) file
writeJSON( $fileContents, $dataFileName );

// go back to the player-page
header( "location: http://".$EXIT_PAGE."?data=".$_REQUEST["dataFile"]."&e=".$errorStrings."&t=".time(), true, 302 );
exit;
?>
