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
$EXIT_PAGE = "sfb.local/vbsf/sheet/index.html"; // the page to show when the script is finished
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

//print_r( $_REQUEST );
//print_r( $_SERVER );


// find the data-file name
$dataFileRoot = $_REQUEST["dataFile"];
$dataFileName = $dataFileDir.$dataFileRoot.".js";

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
    else
      continue; // empty order
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

// re-index the orders array
$fileContents["orders"] = array_values( $fileContents["orders"] );

// write the (edited) file
writeJSON( $fileContents, $dataFileName );

//var_dump("location: http://".$EXIT_PAGE."?data=".$_REQUEST["dataFile"]);
// go back to the player-page
header( "location: http://".$EXIT_PAGE."?data=".$_REQUEST["dataFile"] );

exit;
?>
