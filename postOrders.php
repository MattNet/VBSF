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
$AUTO_ROLL_EMPTY_SYSTEMS = true; // if true, calls $SYSTEM_ROLLER for any new explorations
$MAKE_CHECKLIST = false; // if true, adds a turn checklist to the events
$MAPPOINTS_NAME = 3; // Index in mapPoints array of datafile for the location name
$MAPPOINTS_OWNER = 2; // Index in mapPoints array of datafile for the owning position (empire type)
$mapX = 30; // mapPoints increment for the x-coord
$mapY = 35; // mapPoints increment for the y-coord
$SHOW_ALL_RAIDS = true; // if true, shows failed raids as events
$SYSTEM_ROLLER = "./system_data.php"; // script to generate each system
$SYSTEM_ROLLER_SEPERATOR = "&bull;"; // HTML entity that prepends the output of $SYSTEM_ROLLER

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
include( $SYSTEM_ROLLER );

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
$orderKeys = array_merge( findOrder( $inputData, "build_unit" ), findOrder( $inputData, "research" ), findOrder( $inputData, "convert" ) );

if( isset($orderKeys[0]) ) // if there are none of these orders, then skip
{
  foreach( $orderKeys as $key )
  {
    // research investment
    if( strtolower($inputData["orders"][ $key ]["type"]) == "research" )
    {
      // Determine if this would cause overspending
      if( getLeftover( $inputData ) < intval($inputData["orders"][ $key ]["note"]) )
      {
        $inputData["events"][] = array(
          "event"=>"Research order cancelled due to overspending",
          "time"=>"Turn ".$inputData["game"]["turn"],
          "text"=>""
        );
        echo "Research order cancelled due to overspending.\n";
        continue; // go on to the next order
      }

      // add to the purchases
      $inputData["purchases"][] = array( "name"=>"Research","cost"=>intval($inputData["orders"][ $key ]["note"]) );
    }
    elseif( strtolower($inputData["orders"][ $key ]["type"]) == "convert" ) // unit conversions
    {
// How to know which unit to convert?
// need to define the order to convert
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

      // Determine if this would cause overspending
      if( getLeftover( $inputData ) < $unitCost )
      {
        $inputData["events"][] = array(
          "event"=>"Build order of ".$inputData["orders"][ $key ]["reciever"]." cancelled due to overspending",
          "time"=>"Turn ".$inputData["game"]["turn"],
          "text"=>""
        );
        echo "Build order of ".$inputData["orders"][ $key ]["reciever"]." cancelled due to overspending.\n";
        continue; // go on to the next order
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

      // if the location is not in colonies, add it
      if( getColonyLocation( $fleetLocation, $inputData ) == false )
      {
        // roll for newly-explored systems, if set to do so
        if( $AUTO_ROLL_EMPTY_SYSTEMS )
        {
          echo "\nRolling for system at '".$fleetLocation."'.\n";
          $outputData = VBAMExploration(); // get the output from the $SYSTEM_ROLLER command
          $output = array_shift( $outputData ); // the text output was the first entry
          // print the result of $SYSTEM_ROLLER with $SYSTEM_ROLLER_SEPERATOR prepended to each line
          echo html_entity_decode( $SYSTEM_ROLLER_SEPERATOR );
          echo str_replace( "\n", "\n".html_entity_decode($SYSTEM_ROLLER_SEPERATOR)." ", rtrim($output) );
          echo "\n\n";

          // Ask if we modify the system per the above script
          echo "Use this entry in the data file? (Y/N) ";
          $answer = rtrim( fgets( STDIN ) );

          if( strtolower( $answer ) == "y" )
          {
            $outputData["name"] = $fleetLocation;
            $inputData["colonies"][] = $outputData;
          }
          else
          {
            echo "Need to create the stats for the system at '".$fleetLocation."'\n";
            $inputData["events"][] = array(
                        "event"=>"Need to create the stats for the system at '".$fleetLocation,
                        "time"=>"Turn ".$inputData["game"]["turn"],
                        "text"=>""
                        );
            $inputData["colonies"][] = array("name"=>$fleetLocation, "census"=>0, 
                      "owner"=>"", "morale"=>0, "raw"=>0,"prod"=>0, "capacity"=>0, 
                      "intel"=>0, "fixed"=>array(), "notes"=>""
                      );
          }
        }
      }
      if( ! $AUTO_ROLL_EMPTY_SYSTEMS )
      {
        echo "Need to create the stats for the system at '".$fleetLocation."'\n";
        $inputData["events"][] = array(
                    "event"=>"Need to create the stats for the system at '".$fleetLocation,
                    "time"=>"Turn ".$inputData["game"]["turn"],
                    "text"=>""
                    );
        $inputData["colonies"][] = array("name"=>$fleetLocation, "census"=>0, 
                    "owner"=>"", "morale"=>0, "raw"=>0,"prod"=>0, "capacity"=>0, 
                    "intel"=>0, "fixed"=>array(), "notes"=>""
                    );
      }

      $flag = false; // reset the flag

      // update MapPoints
      // format is [ y , x, color, name ]

      // Make a copy of the map so we can add to the array without iterating over the new entries
      $mapCopy = $inputData["mapPoints"];
      foreach( $mapCopy as $key=>$value )
      {
        // Skip if this value of mapPoints is not the location of interest
        // case insensative
        if( $value[3] != $fleetLocation )
          continue;

        // update the owner of this location, with the 'colonies' array being the standard
        foreach( $inputData["colonies"] as $colonyData )
        {
          // skip of this colony is not this location
          if( $colonyData["name"] != $inputData["mapPoints"][$key][$MAPPOINTS_NAME] )
            continue;
          if( $colonyData["owner"] != $inputData["mapPoints"][$key][$MAPPOINTS_OWNER] )
            $inputData["mapPoints"][$key][2] = $colonyData["owner"];
        }

        // find the neighbor X/Y values of this location
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
        foreach( $inputData["mapPoints"] as $neighborFind )
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
          $inputData["mapPoints"][] = array( $newValue[0], $newValue[1], "", $value[3].$newKey );

          // Add this entry to unknownMovementPlaces
          $inputData["unknownMovementPlaces"][] = $value[3].$newKey;
        }

        break; // skip to the end, since we found the location
      }
    }
    $inputData["unknownMovementPlaces"] = array_values( $inputData["unknownMovementPlaces"] );
  } // end of movement-order handling

// Re-order Colonies, based on name
usort( $inputData["colonies"], "colonyNameSort" );

// Re-order mapPoints, based on location
usort( $inputData["mapPoints"], "mapLocSort" );

###
# Add Checklist
# - Placed here so raid-output is placed correct
###
if( $MAKE_CHECKLIST )
{
  $checklist = array();
  // Fill events with a checklist
  $checklist[] = "Checklist: Intel";
  $checklist[] = "Checklist: Diplomatic shifts for NPEs";
  $checklist[] = "Checklist: Hostility checks for NPEs";
  $checklist[] = "Checklist: NPEs offer treaties";
  $checklist[] = "Checklist: Movement";
  $checklist[] = "Checklist: Check supply";
  $checklist[] = "Checklist: Raiding";
  foreach( $checklist as $entry )
    $inputData["events"][] = array("event"=>$entry,"time"=>"Turn ".$inputData["game"]["turn"],"text"=>"");
  unset( $checklist );
}

###
# Load / unload units (colony fleets, troop transports)
# Note: This is post-movement, before combat
# Load Order: {"type":"load","reciever":"Colony Fleet w\/ Colony-1","target":"Census","note":"1"}
###
  // Find any load orders
  $orderKeys = findOrder( $inputData, "load" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      $loadAmt = (int) $inputData["orders"][$key]["note"];
      // Keep the load amount a single digit
      if( $loadAmt > 9 )
      {
        $loadAmt = 9;
        echo "Order given to load '".$inputData["orders"][$key]["reciever"];
        echo "' with ".$inputData["orders"][$key]["note"]." of '";
        echo $inputData["orders"][$key]["target"]."'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to load '".$inputData["orders"][$key]["reciever"];
      $loadErrorString .= "' with $loadAmt of '".$inputData["orders"][$key]["target"];

      $fleet = -1; // key of the fleet array that is being loaded
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit

      // find the fleet
      foreach( $inputData["fleets"] as $fleetKey=>$value )
       if( str_ends_with( $inputData["orders"][$key]["reciever"], $value["name"] ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."'. Could not find fleet.\n";
        exit(1);
      }

      // find fleet location
      $fleetLoc = getColonyLocation( $inputData["fleets"][$fleet]["location"], $inputData );
      if( $fleetLoc === false ) // skip if this fleet location cannot be found
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        continue;
      }

      // determine if this colony is owned by the player
      if( $inputData["colonies"][$fleetLoc]["owner"] != $inputData["empire"]["empire"] )
      {
        echo $loadErrorString."'. This player does not own this colony.\n";
        continue;
      }

      // find amt of the supply trait in this fleet
      $supplyAmt = getFleetSupplyValue( $inputData, $inputData["fleets"][$fleet]["units"] );
      if( $supplyAmt == 0 ) // skip if this fleet has no supply trait
      {
        echo $loadErrorString."'. Fleet has no supply trait.\n";
        continue;
      }

      // find supply amt already used in this fleet
      $supplyUsed = getFleetloadedValue( $inputData["fleets"][$fleet] );

      // skip if the fleet cannot hold the unit
      if( $supplyAmt - $supplyUsed < ( 10 * $loadAmt ) )
      {
        echo $loadErrorString."'. Loading $loadAmt would overload fleet.\n";
        continue;
      }

      // Load Census
      if( strtolower($inputData["orders"][$key]["target"]) == "census" )
      {
        // skip if there is not enough census to load
        if( $inputData["colonies"][$fleetLoc]["census"] <= $loadAmt+1 )
        {
          echo $loadErrorString."'. Loading $loadAmt of Census would empty the colony.\n";
          continue;
        }
        
        // Add Census to fleet
        $inputData["fleets"][$fleet]["notes"] .= "$loadAmt Census loaded.";
        // remove Census from location
        $inputData["colonies"][$fleetLoc]["census"] -= $loadAmt;

        // finished with this load order
        continue;
      }

      // determine if this is a ground unit being loaded
      foreach( $inputData["unitList"] as $unit )
      {
        if( strtolower($unit["design"]) != "ground unit" )
          continue;
        if( strtolower($inputData["orders"][$key]["target"]) == strtolower($unit["design"]) )
        {
          $isGroundUnit = true;
          break;
        }
      }

      // Load ground units
      if( $isGroundUnit )
      {
        $unitCount = 0;

        // skip if there is not enough of this ground unit to load
        foreach( $inputData["colonies"][$fleetLoc]["fixed"] as $fixedKey=>$fixed )
        {
          if( strtolower($inputData["orders"][$key]["target"]) == strtolower($fixed) )
            $unitCount++;
        }
        if( $unitCount < $loadAmt )
        {
          echo $loadErrorString."'. Not enough ".$inputData["orders"][$key]["target"]." are present at colony.\n";
          continue;
        }
        
        // Add unit to fleet
        $inputData["fleets"][$fleet]["notes"] .= "$loadAmt ".$inputData["orders"][$key]["target"]." loaded.";
        // remove unit from location
        foreach( $inputData["colonies"][$fleetLoc]["fixed"] as $fixedKey=>$fixed )
        {
          if( strtolower($inputData["orders"][$key]["target"]) == strtolower($fixed) && $loadAmt > 0 )
          {
            unset( $inputData["colonies"][$fleetLoc]["fixed"][$fixedKey] );
            $loadAmt--;
          }
        }
        // re-index the fixed-unit array
        $inputData["colonies"][$fleetLoc]["fixed"] = array_values( $inputData["colonies"][$fleetLoc]["fixed"] );

        // finished with this load order
        continue;
      }
      echo $loadErrorString."'. Unit not loaded.\n";
    }
  }
/***
Unloading uses the same process, but with reverse effects
***/
  // Find any unload orders
  $orderKeys = findOrder( $inputData, "unload" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      $loadAmt = (int) $inputData["orders"][$key]["note"];
      // Keep the unload amount a single digit
      if( $loadAmt > 9 )
      {
        $loadAmt = 9;
        echo "Order given to unload '".$inputData["orders"][$key]["reciever"];
        echo "' with ".$inputData["orders"][$key]["note"]." of '";
        echo $inputData["orders"][$key]["target"]."'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to unload '".$inputData["orders"][$key]["reciever"];
      $loadErrorString .= "' with $loadAmt of '".$inputData["orders"][$key]["target"];

      $fleet = -1; // key of the fleet array that is being loaded
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit
      $success = preg_match( "/(\d) ".$inputData["orders"][$key]["reciever"]." loaded/i", $inputData["fleets"][$fleet]["notes"], $matches );
      $amtLoaded = (int) $matches[1];
      if( ! $success || $amtLoaded < 1 )
      {
        echo $loadErrorString."'. Fleet does not carry any ".$inputData["orders"][$key]["target"].".\n";
        exit(1);
      }

      // skip if there is not enough to unload
      if( $amtLoaded >= $loadAmt )
      {
        echo $loadErrorString."'. The fleet does not carry enough. It only has $amtLoaded.\n";
        continue;
      }
        
      // find the fleet
      foreach( $inputData["fleets"] as $fleetKey=>$value )
       if( str_ends_with( $inputData["orders"][$key]["reciever"], $value["name"] ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."'. Could not find fleet.\n";
        exit(1);
      }

      // find fleet location
      $fleetLoc = getColonyLocation( $inputData["fleets"][$fleet]["location"], $inputData );
      if( $fleetLoc === false ) // skip if this fleet location cannot be found
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        continue;
      }

      // Unload Census
      if( strtolower($inputData["orders"][$key]["target"]) == "census" )
      {
        // determine if this colony is owned by the player
        if( $inputData["colonies"][$fleetLoc]["owner"] != $inputData["empire"]["empire"] )
        {
          echo $loadErrorString."'. This player does not own this colony.\n";
          continue;
        }

        // Remove Census from fleet
        $inputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadAmt Census loaded.",
          "",
          $inputData["fleets"][$fleet]["notes"]
        );
        // add Census to location
        $inputData["colonies"][$fleetLoc]["census"] += $loadAmt;

        // finished with this unload order
        continue;
      }

      // determine if this is a ground unit being unloaded
      foreach( $inputData["unitList"] as $unit )
      {
        if( strtolower($unit["design"]) != "ground unit" )
          continue;
        if( strtolower($inputData["orders"][$key]["target"]) == strtolower($unit["design"]) )
        {
          $isGroundUnit = true;
          break;
        }
      }

      // Unload ground units
      if( $isGroundUnit )
      {
        // Remove unit to fleet
        $inputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadAmt ".$inputData["orders"][$key]["target"]." loaded.",
          "",
          $inputData["fleets"][$fleet]["notes"]
        );
        // add unit to location
        for( $i=$loadAmt; $i=0; $i-- )
          $inputData["colonies"][$fleetLoc]["fixed"][] = $inputData["orders"][$key]["target"];
        // re-index the fixed-unit array
        $inputData["colonies"][$fleetLoc]["fixed"] = array_values( $inputData["colonies"][$fleetLoc]["fixed"] );

        // finished with this unload order
        continue;
      }
      echo $loadErrorString."'. Unit not unloaded.\n";
    }
  }


###
# Add raids
# Note: This is post-movement, during combat
###
$ownedPlaces = array();
$raidPlaces = array();

// Mark locations owned by this player
foreach( $inputData["colonies"] as $item )
{
  if( $item["owner"] == $inputData["empire"]["empire"] )
  {
    $ownedPlaces[] = array( "name"=>$item["name"], "navalCost"=>0 );
  }
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
      if( $item["location"] == $place["name"] )
        $place["navalCost"] += getFleetValue( $inputData, $item["units"], true );
    }
    // Count the trade, transport, and colony fleets at the fleet location
    $civCount = 0;
    foreach( $item["units"] as $unit )
    {
      if( $unit == "Colony Fleet" )
        $civCount++;
      else if( $unit == "Trade Fleet" )
        $civCount++;
      else if( $unit == "Transport Fleet" )
        $civCount++;
    }
    // Note locations and count of trade, transport, and colony fleets
    if( $civCount > 0 )
      $raidPlaces[] = array( "civCount" => $civCount, "location"=>$item["location"], "naval"=>getFleetValue( $inputData, $item["units"], true ) );
  }
}

// note places empty of naval units
foreach( $ownedPlaces as $place )
{
  if( $place["navalCost"] == 0 )
    $raidPlaces[] = array( "civCount" => 0, "location"=>$place["name"], "naval"=>0 );
}

// Calculate the raids
foreach( $raidPlaces as $place )
{
  $chance = 20; // base chance to get a raid
  $rand = mt_rand(1,100); // determination of raid occurance

  // If there is more than one civilian fleet, increase the chance of a raid by 20% per additional
  // The first civilian fleet allows the chance of a raid
  if( $place["civCount"] > 1 )
    $chance += ($place["civCount"]-1) * 20;

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
    $text = "A raid struck '".$place["location"]."'. There was a $chance% chance and a $rand was rolled. If there is a civilian fleet present, that is the target of the raid. Otherwise, it is the fixed installation that are raided. It is raided with $raidAmt construction value of raiders.";
    $inputData["events"][] = array("event"=>"A raid in '".$place["location"]."' happened.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
  else
  {
    $text = "There was no raid in '".$place["location"]."'. There was a $chance% chance and a $rand was rolled.";
    $inputData["events"][] = array("event"=>"A raid failed in '".$place["location"]."'.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
}

// make all of the orders section into non-drop-down menus
foreach( array_keys( $inputData["orders"] ) as $key )
  $inputData["orders"][$key]["perm"] = 1;
// Note: Leaving $inputData["game"]["blankOrders"] alone. The next segment of the turn needs these drop-downs.

###
# Add Checklist
# - Placed here so raid-output is placed correct
###
if( $MAKE_CHECKLIST )
{
  // Fill events with a checklist
  $checklist[] = "Checklist: Combat";
  foreach( $checklist as $entry )
    $inputData["events"][] = array("event"=>$entry,"time"=>"Turn ".$inputData["game"]["turn"],"text"=>"");
  unset( $checklist );
}

###
# Write out the file
###

writeJSON( $inputData, $newFileName );
exit(0); // all done

###
# Sorting function to be used on the colonies data structure
###
function colonyNameSort( $a, $b )
{
  return strcmp($a["name"], $b["name"]);
}

###
# Sorting function to be used on the mapPoints data structure
###
function mapLocSort( $a, $b )
{
  if( intval($a[0]) == intval($b[0]) )
  {
    if( intval($a[1]) == intval($b[1]) )
      return 0;
    else
      return ( intval($a[1]) > intval($b[1])) ? +1 : -1;
  }
  else
    return ( intval($a[0]) > intval($b[0])) ? +1 : -1;
}

