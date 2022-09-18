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
$EXIT_PAGE = "sheet/index.html"; // the page to show when the script is finished
$dataFileDir = "files/"; // location of the data files

###
# Initialization
###

include( "./postFunctions.php" );

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
    [OrderEntry3E_x] => 15 <-- Delete button hit
    [OrderEntry3E_y] => 9
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
Delete buttons give an <name>_x and <name>_y

"Order \""+orders[i].reciever+"\" to do \""+orders[i].type+"\" to \""+orders[i].target+"\" with \""+orders[i].note+"\"";

var orders = [
   {"type":"move","reciever":"Exploration Alpha","target":"Fraxee Dir A","note":"","perm":1},
   {"type":"move","reciever":"Exploration Beta","target":"Fraxee Dir F","note":"","perm":1},
   {"type":"invest","reciever":"","target":"","note":"13","perm":1},
   {"type":"build","reciever":"Colony Fleet","target":"Fraxee","note":"Colony-1","perm":1}
];
*/

print_r( $_REQUEST );
//print_r( $_SERVER );

// find the data-file name
$dataFileRoot = $_REQUEST["dataFile"];
$dataFileName = $dataFileDir.$dataFileRoot.".js";

// get the data file
$fileContents = extractJSON( $dataFileName );

// get the size of the orders array
// used as an offset for the $orderNum from the drop-down menus
$ordersOffset = count( $fileContents["orders"] );

// find deleted items
// find drop-down items (which are orders that are addded)
foreach( array_keys($_REQUEST) as $key )
{
  // skip if not an order-entry item
  if( str_starts_with( $key, "OrderEntry" ) !== true )
    continue;

  // get the order to affect with this $key
  $orderNum = intval( substr( $key, 10, 1 ) );
  $orderPos = strtolower( substr( $key, 11, 1 ) );

  // skip if we failed to get the location inside the order from the $key
  if( $orderNum === false || $orderPos === false )
    continue;

  // remove the deleted item
  if( str_ends_with( $key, "_x" ) === true )
  {
    unset( $fileContents["orders"][$orderNum] );
    continue; // skip the below for this one request-entry
  }
  if( str_ends_with( $key, "_y" ) === true )
    continue; // skip the below for this one request-entry

/*
###
## the below assumed the drop-downs appended to the orders array
## $key iterates through every order, with no distinction between drop-down and not
###

  $out = array(); // track where this item goes in the orders arrays

### add the drop-down items
  
  // create a segment of an order with this $key entry
  switch($orderPos)
  {
  case "a":
    $out["type"] = $_REQUEST[$key];
    break;
  case "b":
    $out["reciever"] = $_REQUEST[$key];
    break;
  case "c":
    $out["target"] = $_REQUEST[$key];
    break;
  case "d":
    $out["note"] = $_REQUEST[$key];
    $out["perm"] = 1; // make it not a drop-down, if we are noting the last item of the order
    break;
  }

  // set up the entry to the orders array if not already
  if( ! isset( $fileContents["orders"][$ordersOffset+$orderNum]) )
    $fileContents["orders"][$ordersOffset+$orderNum] = array();
  // add in the above order segment to the whole order entry
  array_merge( $fileContents["orders"][$ordersOffset+$orderNum], $out );
*/

}

// re-index the orders array
$fileContents["orders"] = array_values( $fileContents["orders"] );

print_r($fileContents);
// write the (edited) file
//writeJSON( $fileContents, $dataFileName );

// go back to the player-page
//header( "location: http://".$EXIT_PAGE."?data=".$dataFileRoot );
exit;
?>
