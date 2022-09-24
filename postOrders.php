#!/usr/bin/php -q
<?php
###
# Creates a new data file from an old one
###
# Stuffs the purchase & construction display
# Performs basic movement
# - Does not check for collisions with others
# - Does not check for movement to a neighbor sector
# Adds unknown location to colonies
# Adds new unknown locations for new sector
# Adds raiding scenarios
###

if( ! isset($argv[1]) )
{
  echo "\nHandles post-orders items for a player position\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." DATA_FILE [NEW FILE NAME]\n";
  exit(1);
}

###
# Configuration
###
$MAKE_CHECKLIST = true; // if true, adds a turn checklist to the events
$SHOW_ALL_RAIDS = true; // if true, shows failed raids as events
$AUTO_ROLL_EMPTY_SYSTEMS = true; // if true, calls "system_data.php" for any new explorations
$mapX = 30; // mapPoints increment for the x-coord
$mapY = 35; // mapPoints increment for the y-coord


###
# Initialization
###
$inputData = array(); // PHP variable of the JSON data
$newFileName = $argv[1]; // save file filename
$checklist = array(); // list of things that need to be done

if( isset($argv[2]) )
  $newFileName = $argv[2];
else if( strrpos( $argv[1], "/" ) !== false )
  $newFileName = substr( $argv[1], strrpos( $argv[1], "/" )+1 );

include( "./postFunctions.php" );

###
# Generate Input
###
$inputData = extractJSON( $argv[1] );
if( $inputData === false ) // leave if there was an error loading the file
  exit(1);

###
# Make the middle-turn modifications
###

// stuff the purchases and underConstruction array
$inputData["purchases"] = array(); // empty the purchases array
$inputData["underConstruction"] = array(); // empty the construction array

// get the orders for things that are purchased
$orderKeys = array_merge( findOrder( $inputData, "build" ), findOrder( $inputData, "invest" ), findOrder( $inputData, "convert" ) );
if( isset($orderKeys[0]) ) // if there are none of these orders, then skip
{
  foreach( $orderKeys as $key )
  {
    // research investment
    if( strtolower($inputData["orders"][ $key ]["type"]) == "invest" )
    {
      $inputData["purchases"][] = array( "name"=>"Research","cost"=>intval($inputData["orders"][ $key ]["note"]) );
    }
    elseif( strtolower($inputData["orders"][ $key ]["type"]) == "convert" ) // unit conversions
    {
// How to know which unit to convert?
// need to define the order
/*
      $unitCost = 0;
      $unitRebate = 0;
      // find the cost of the old unit
      foreach( $inputData["unitList"] as $hull )
        if( strtolower($hull["ship"]) == strtolower($inputData["orders"][ $orderKeys ]["reciever"]) )
        {
          $unitRebate = $hull["cost"];
          break; // quit the loop. We found our unit.
        }
      // find the cost of the new unit
      foreach( $inputData["unitList"] as $hull )
        if( strtolower($hull["ship"]) == strtolower($inputData["orders"][ $orderKeys ]["target"]) )
        {
          $unitCost = $hull["cost"];
          break; // quit the loop. We found our unit.
        }
      if( $unitRebate > $unitCost )
        $unitCost = 0; // the rebate cannot give back money if larger than the cost
      else
        $unitCost -= $unitRebate

      // find the location of the unit
      foreach( $inputData["fleet"] as $item )
        foreach( $item["units"] as $hull )
        {
        }

      $inputData["purchases"][] = array( "name"=>"Convert '".$inputData["orders"][ $orderKey ]["reciever"]."' to '".$inputData["orders"][ $orderKey ]["target"]."'","cost"=>$unitCost );
      $inputData["underConstruction"][] = array( "location"=>$inputData["orders"][ $orderKey ]["target"],"unit"=>$inputData["orders"][ $orderKey ]["reciever"] );
*/
    }
    else // unit purchase
    {
      $unitCost = 0;
      // find the cost of the unit
      foreach( $inputData["unitList"] as $hull )
        if( strtolower($hull["ship"]) == strtolower($inputData["orders"][ $key ]["reciever"]) )
        {
          $unitCost = $hull["cost"];
          break; // quit the loop. We found our unit.
        }

      $inputData["purchases"][] = array( "name"=>$inputData["orders"][ $key ]["reciever"],"cost"=>$unitCost );
      $inputData["underConstruction"][] = array( "location"=>$inputData["orders"][ $key ]["target"],"unit"=>$inputData["orders"][ $key ]["reciever"] );
    }
  }
}


###
# Process movement orders
###
  // find the movement orders and re-locate the fleets
  $orderKeys = findOrder( $inputData, "move" );
  // make sure there are move orders
  // also ensure there are fleets to move
  if( isset($orderKeys[0]) && isset($inputData["fleets"]) && ! empty($inputData["fleets"]) )
  {
    foreach( $orderKeys as $orderKey ) // go through the movement orders
    {
      // convenience variable for the fleet's location
      $fleetLocation = strtolower( $inputData["orders"][ $orderKey ]["target"] );

      // flag for if the location is not in unknownMovementPlaces
      // if it remains false, the location may not be a location that the position knows about
      $flag = false;

      // if the location is in the unknownMovementPlaces, remove it
      foreach( $inputData["unknownMovementPlaces"] as $key=>$value )
      {
        if( strtolower($value) != strtolower($fleetLocation) )
          continue;

        $flag = true; // we found the location in unknownMovementPlaces

        // update the fleet location with the proper case
        $fleetLocation = $value;

        // remove the entry
        unset( $inputData["unknownMovementPlaces"][$key] );

        break; // skip to the end, since we found the location
      }
      // if the location is in colonies, then it is a valid order
      foreach( $inputData["colonies"] as $key=>$value )
      {
        if( strtolower($value["name"]) != strtolower($fleetLocation) )
          continue;

        $flag = true; // we found the location in colonies

        // update the fleet location with the proper case
        $fleetLocation = $value["name"];

        break; // skip to the end, since we found the location
      }

      if( ! $flag )
      {
        echo "Fleet '".$inputData["orders"][ $orderKey ]["reciever"];
        echo "' ordered to move to '$fleetLocation', but location is not \nfound in ";
        echo "unknown, movable locations. Perhaps the order is mispelled.\n";
      }

      $flag = false; // reset the flag

      // find a movement order that matches that fleet and update it
      foreach( $inputData["fleets"] as $key=>$value )
      {
        // case insensative;
        if( strtolower($value["name"]) != strtolower($inputData["orders"][ $orderKey ]["reciever"]) )
          continue;

        // set the new location of the fleet
        $inputData["fleets"][ $key ]["location"] = $fleetLocation;

        break; // skip to the end, since we found the fleet for this order
      }

      // find the location in colonies
      foreach( $inputData["colonies"] as $key=>$value )
      {
        if( strtolower($value["name"]) != strtolower($fleetLocation) )
          continue;

        $flag = true; // we found the location in colonies

        break; // skip to the end, since we found the location
      }
      // if the location is not in colonies, add it
      if( ! $flag )
      {
        echo "Need to create the stats for the system at '".$fleetLocation."'\n";
        $inputData["events"][] = array(
                    "event"=>"Need to create the stats for the system at '".$fleetLocation,
                    "time"=>"Turn ".$inputData["game"]["turn"],
                    "text"=>"");
        $inputData["colonies"][] = array("name"=>$fleetLocation, "census"=>0, 
                    "owner"=>"", "morale"=>0, "raw"=>0,"prod"=>0, "capacity"=>0, 
                    "intel"=>0, "fixed"=>array(), "notes"=>""
                                         );
        // roll for newly-explored systems, if set to do so
        if( $AUTO_ROLL_EMPTY_SYSTEMS )
        {
          echo "\nRolling for system at '".$fleetLocation."'.\n";
          shell_exec("../system_data.php");
        }
      }

      $flag = false; // reset the flag

      // update MapPoints
      // format is [ y , x, color, name ]

      // Make a copy of the map so we can add to the array without iterating over the new entries
      $mapCopy = $inputData["MapPoints"];
      foreach( $mapCopy as $key=>$value )
      {
        // case insensative;
        if( $value[3] != $fleetLocation )
          continue;

        $neighbors = array(); // list of neighbor coords
        $neighbors["A"] = array( ($value[0]-$mapY), $value[1] ); // previous Y, same X
        $neighbors["D"] = array( ($value[0]+$mapY), $value[1] ); // next Y, same X

        // find if the location is even/odd
        if( $value[1] % ($mapX*2) > 0 ) // Dir B/F on same Y as location
        {
          $neighbors["B"] = array( $value[0], ($value[1]+$mapX) );             // same Y, next X
          $neighbors["C"] = array( ($value[0]+$mapY), ($value[1]+$mapX) ); // next Y, next X
          $neighbors["E"] = array( ($value[0]+$mapY), ($value[1]-$mapX) ); // next Y, previous X
          $neighbors["F"] = array( $value[0], ($value[1]-$mapX) );             // same Y, previous X
        }
        else // Dir C/E on same Y as location
        {
          $neighbors["B"] = array( ($value[0]-$mapY), ($value[1]+$mapX) ); // previous Y, next X
          $neighbors["C"] = array( $value[0], ($value[1]+$mapX) );             // same Y, next X
          $neighbors["E"] = array( $value[0], ($value[1]-$mapX) );             // same Y, previous X
          $neighbors["F"] = array( ($value[0]-$mapY), ($value[1]-$mapX) ); // previous Y, previous X
        }
        // find and remove neighbors in MapPoints
        // what remains is not in MapPoints
        foreach( $inputData["MapPoints"] as $neighborFind )
        {
          if( isset($neighbors["A"]) && $neighborFind[0] == $neighbors["A"][0] && $neighborFind[1] == $neighbors["A"][1] )
            unset( $neighbors["A"] );
          if( isset($neighbors["B"]) && $neighborFind[0] == $neighbors["B"][0] && $neighborFind[1] == $neighbors["B"][1] )
            unset( $neighbors["B"] );
          if( isset($neighbors["C"]) && $neighborFind[0] == $neighbors["C"][0] && $neighborFind[1] == $neighbors["C"][1] )
            unset( $neighbors["C"] );
          if( isset($neighbors["D"]) && $neighborFind[0] == $neighbors["D"][0] && $neighborFind[1] == $neighbors["D"][1] )
            unset( $neighbors["D"] );
          if( isset($neighbors["E"]) && $neighborFind[0] == $neighbors["E"][0] && $neighborFind[1] == $neighbors["E"][1] )
            unset( $neighbors["E"] );
          if( isset($neighbors["F"]) && $neighborFind[0] == $neighbors["F"][0] && $neighborFind[1] == $neighbors["F"][1] )
            unset( $neighbors["F"] );
        }

        // add the new entry
        foreach( $neighbors as $newKey=>$newValue )
        {
          $inputData["MapPoints"][] = array( $newValue[0], $newValue[1], "", $value[3].$newKey );

          // Add this entry to unknownMovementPlaces
          $inputData["unknownMovementPlaces"][] = $value[3].$newKey;
        }

        break; // skip to the end, since we found the location
      }
    }
    $inputData["unknownMovementPlaces"] = array_values( $inputData["unknownMovementPlaces"] );
  } // end of movement-order handling

###
# Add raids
# Note: This is post-movement
###
$ownedPlaces = array();
$raidPlaces = array();

// Mark locations owned by this player
foreach( $inputData["colonies"] as $item )
{
  if( $item["owner"] = $inputData["empire"]["empire"] )
    $ownedPlaces[] = array( "name"=>$item["name"], "navalCost"=>0 );
}

// look through the fleets for colony and trade fleets
// Note naval units to locations
if( isset($inputData["fleets"]) ) // make sure the input exists
{
  foreach( $inputData["fleets"] as $item )
  {
    // Note naval value at each owned location
    foreach( $ownedPlaces as &$place )
    {
      if( $item["location"] = $place["name"] )
        $place["navalCost"] += getFleetValue( $inputData, $item["units"], true );
    }

    // Note locations of trade, transport, and colony fleets
    if( in_array("Colony Fleet",$item["units"]) ||
        in_array("Trade Fleet", $item["units"]) ||
        in_array("Transport Fleet", $item["units"])
      )
    {
      $raidPlaces[] = array( "location"=>$item["location"], "naval"=>getFleetValue( $inputData, $item["units"], true ) );
    }
  }
}

// note places empty of naval units
foreach( $ownedPlaces as $place )
{
  if( $place["navalCost"] == 0 )
    $raidPlaces[] = array( "location"=>$place["name"], "naval"=>0 );
}

// Calculate the raids
foreach( $raidPlaces as $place )
{
  $chance = 20; // base chance to get a raid
  $rand = mt_rand(1,100);
  
  if( $place["naval"] < 0 )
    $chance -= 5; // 5% off for more than 0 construction value
  if( $place["naval"] < 8 )
    $chance -= 5; // total of 10% off for more than 8 construction value
  if( $place["naval"] < 12 )
    $chance -= 10; // total of 20% off for more than 12 construction value

  // find out if intel was used to prevent this
  $orderKeys = findOrder( $inputData, "intel" );
  if( isset($orderKeys[0]) )
  {
    foreach( $orderKeys as $key )
    {
      if( $inputData["orders"][$key]["reciever"] == "Reduce Raiding" && $inputData["orders"][$key]["target"] == $place["location"] )
      {
        // reduce chances by 10% per intel used
        $chance -= 10 * intval($inputData["orders"][$key]["note"]);
      }
    }
  }

  if( $rand <= $chance )
  {
    $raidAmt = mt_rand(1,3) * mt_rand(1,6);
    $text = "A raid struck '".$place["location"]."'. There was a $chance% chance and a $rand was rolled. This chance may be increased by 20% if there are more than 1 civilian fleet present. If there is a civilian fleet present, that is the target of the raid. Otherwise, it is the fixed installation that are raided. It is raided with $raidAmt construction value of raiders.";
    $inputData["events"][] = array("event"=>"A raid in '".$place["location"]."' happened.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
  else if( $rand <= ($chance+20) )
  {
    $raidAmt = mt_rand(1,3) * mt_rand(1,6);
    $text = "A raid failed in '".$place["location"]."' but may still happen. There was a $chance% chance and a $rand was rolled. This raid may be still ocur if there are more than 1 civilian fleet present. The civilian fleet(s) are the target of the raid. It is raided with $raidAmt construction value of raiders.";
    $inputData["events"][] = array("event"=>"A raid may happen in '".$place["location"]."'.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
  else
  {
    $text = "There was no raid in '".$place["location"]."'. There was a $chance% chance and a $rand was rolled. This chance may be increased by 20% if there are more than 1 civilian fleet present.";
    $inputData["events"][] = array("event"=>"A raid failed in '".$place["location"]."'.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
}

if( $MAKE_CHECKLIST )
{
  // Fill events with a checklist
  $checklist[] = "Checklist: Intel";
  $checklist[] = "Checklist: Diplomatic shifts for NPEs";
  $checklist[] = "Checklist: Hostility checks for NPEs";
  $checklist[] = "Checklist: NPEs offer treaties";
  $checklist[] = "Checklist: Movement";
  $checklist[] = "Checklist: Check supply";
  $checklist[] = "Checklist: Raiding";
  $checklist[] = "Checklist: Combat";
}

foreach( $checklist as $entry )
  $inputData["events"][] = array("event"=>$entry,"time"=>"Turn ".$inputData["game"]["turn"],"text"=>"");

###
# Write out the file
###

writeJSON( $inputData, $newFileName );
exit(0); // all done


