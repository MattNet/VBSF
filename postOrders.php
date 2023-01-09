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
$EXPLAIN_RAIDS = true; // if true, explains the chances of a successful raid
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

// get the lookup tables
list( $byColonyName, $byColonyOwner, $byFleetName, $byFleetLocation, $byFleetUnits, $byMapLocation, $byMapOwner, $byDesignator ) = makeLookUps($inputData);

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
      // find the cost of the unit
      $unitCost = $inputData["unitList"][ $byDesignator[ $inputData["orders"][ $key ]["reciever"] ] ]["cost"];

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
      # Verifies that the target location in unknownMovementPlaces or colonies is valid
      # Removes it from unknownMoementPlaces, if the location is there
      # It would verify the initial fleet location and hte target location are immediate neighbors,
      #   but there is currently no way to know they are connected.
      # Updates the location of the fleet to match the destination

      // convenience variable for the fleet's location
      $fleetLocation = $inputData["orders"][ $orderKey ]["target"];
      // convenience variable for the fleet's name
      $fleetName = $inputData["orders"][ $orderKey ]["reciever"];

      // flag for if the location is known
      // if it remains false, the location may not be a location that the position knows about
      $flag = false;

      // if the location is in the unknownMovementPlaces, remove it
      $unknownKey = array_search( $fleetLocation, $inputData["unknownMovementPlaces"] );
      if( $unknownKey !== false )
      {
        $flag = true; // we found the location in unknownMovementPlaces

        // update the fleet location with the proper case
        $fleetLocation = $inputData["unknownMovementPlaces"][$unknownKey];

        // remove the entry
        unset( $inputData["unknownMovementPlaces"][$unknownKey] );
      }
      // if the location is in colonies, then it is a valid order
      else if( isset( $byColonyName[ $fleetLocation ] ) )
        $flag = true; // we found the location in colonies

      if( ! $flag )
      {
        echo "Fleet '".$fleetName;
        echo "' ordered to move to '$fleetLocation', but location is not \nfound in ";
        echo "unknown, movable locations. Perhaps the order is mispelled.\n";
        $inputData["events"][] = array("event"=>"move order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>"Fleet '$fleetName' ordered to move to '$fleetLocation', but "
                                              ."location is not found in unknown, movable locations. Perhaps "
                                              ."the order is mispelled.\n"
                                      );
        unset( $inputData["orders"][$key] ); // remove failed order
      }

      // update the location of the fleet to match the target location
      $inputData["fleets"][ $byFleetName[ $fleetName ] ]["location"] = $fleetLocation;

###
# Exploration
###
      $flag = false; // reset the flag

      // if the location is not in colonies, add it
      if( ! isset($byColonyName[ $fleetLocation ]) )
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
        // case insensitive
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
        $inputData["orders"][$key]["note"] = 9; // truncate the given order
        echo "Order given to load '".$inputData["orders"][$key]["reciever"];
        echo "' with ".$inputData["orders"][$key]["note"]." of '";
        echo $inputData["orders"][$key]["target"]."'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to load '".$inputData["orders"][$key]["reciever"];
      $loadErrorString .= "' with $loadAmt of '".$inputData["orders"][$key]["target"];

      $fleet = -1; // key of the fleet array that is being loaded
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit

      // determine if this is a ground unit being loaded
      if( isset( $byDesignator[ $inputData["orders"][$key]["target"] ] )
          && $inputData["unitList"][ $byDesignator[ $inputData["orders"][$key]["target"] ] ]["design"] == "ground unit"
        )
        $isGroundUnit = true;

      // find the fleet
      foreach( $byFleetName as $fleetName=>$fleetKey )
       if( str_ends_with( $inputData["orders"][$key]["reciever"], $fleetName ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."'. Could not find fleet.\n";
        // do not remove order, because exiting the script
        exit(1);
      }

      // skip if this fleet location cannot be found
      if( ! isset( $byColonyName[ $inputData["fleets"][$fleet]["location"] ] ) )
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. Location of fleet is not a colony.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }
      else
      {
        $fleetLoc = $byColonyName[ $inputData["fleets"][$fleet]["location"] ]; // find fleet location
      }

      // determine if this colony is owned by the player
      if( $inputData["colonies"][$fleetLoc]["owner"] != $inputData["empire"]["empire"] )
      {
        echo $loadErrorString."'. This player does not own this colony.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. This player does not own this colony.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }

      // find amt of the supply trait in this fleet
      $supplyAmt = getFleetSupplyValue( $inputData, $inputData["fleets"][$fleet]["units"] );
      if( $supplyAmt == 0 ) // skip if this fleet has no supply trait
      {
        echo $loadErrorString."'. Fleet has no supply trait.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. Fleet has no supply trait.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }

      // find supply amt already used in this fleet
      $supplyUsed = getFleetloadedValue( $inputData["fleets"][$fleet] );

      // skip if the fleet cannot hold the unit
      if( $supplyAmt - $supplyUsed < ( 10 * $loadAmt ) )
      {
        echo $loadErrorString."'. Loading $loadAmt would overload fleet.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. Loading $loadAmt would overload fleet.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }

      // Load Census
      if( strtolower($inputData["orders"][$key]["target"]) == "census" )
      {
        // skip if there is not enough Census to load
        if( $inputData["colonies"][$fleetLoc]["census"] <= $loadAmt+1 )
        {
          echo $loadErrorString."'. Loading $loadAmt of Census would empty the colony.\n";
          $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                         "text"=>$loadErrorString."'. Loading $loadAmt of Census would empty the colony.\n");
          unset( $inputData["orders"][$key] ); // remove failed order
          continue;
        }
        
        // Add Census to fleet
        $inputData["fleets"][$fleet]["notes"] .= "$loadAmt Census loaded.";
        // remove Census from location
        $inputData["colonies"][$fleetLoc]["census"] -= $loadAmt;
        // deal with maybe having to much Morale
        if( $inputData["colonies"][$fleetLoc]["morale"] > $inputData["colonies"][$fleetLoc]["census"] )
          $inputData["colonies"][$fleetLoc]["morale"] = $inputData["colonies"][$fleetLoc]["census"];

        // finished with this load order
        continue;
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
          $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                         "text"=>$loadErrorString."'. Not enough ".$inputData["orders"][$key]["target"]." are present at colony.\n");
          unset( $inputData["orders"][$key] ); // remove failed order
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
      $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$loadErrorString."'. Unit not loaded.\n");
      unset( $inputData["orders"][$key] ); // remove failed order
    }
    $inputData["orders"] = array_values( $inputData["orders"] ); // re-index the orders to close up gaps caused by invalid orders
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
        $inputData["orders"][$key]["note"] = 9; // truncate the given order
        echo "Order given to unload '".$inputData["orders"][$key]["reciever"];
        echo "' with ".$inputData["orders"][$key]["note"]." of '";
        echo $inputData["orders"][$key]["target"]."'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to unload '".$inputData["orders"][$key]["reciever"];
      $loadErrorString .= "' with $loadAmt of '".$inputData["orders"][$key]["target"];

      $fleet = -1; // key of the fleet array that is being loaded
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit

      // determine if this is a ground unit being unloaded
      if( isset( $byDesignator[ $inputData["orders"][$key]["target"] ] )
          && $inputData["unitList"][ $byDesignator[ $inputData["orders"][$key]["target"] ] ]["design"] == "ground unit"
        )
        $isGroundUnit = true;
      
      // find the fleet
      foreach( $inputData["fleets"] as $fleetKey=>$value )
       if( str_ends_with( $inputData["orders"][$key]["reciever"], $value["name"] ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."'. Could not find fleet.\n";
        // do not remove order, because exiting the script
        exit(1);
      }
      
      $success = preg_match( "/(\d) ".$inputData["orders"][$key]["reciever"]." loaded/i", $inputData["fleets"][$fleet]["notes"], $matches );
      $amtLoaded = (int) $matches[1];
      if( ! $success || $amtLoaded < 1 )
      {
        echo $loadErrorString."'. Fleet does not carry any ".$inputData["orders"][$key]["target"].".\n";
        // do not remove order, because exiting the script
        exit(1);
      }

      // skip if there is not enough to unload
      if( $amtLoaded >= $loadAmt )
      {
        echo $loadErrorString."'. The fleet does not carry enough. It only has $amtLoaded.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. The fleet does not carry enough. It only has $amtLoaded.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }

      // skip if this fleet location cannot be found
      if( ! isset( $byColonyName[ $inputData["fleets"][$fleet]["location"] ] ) )
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                       "text"=>$loadErrorString."'. Location of fleet is not a colony.\n");
        unset( $inputData["orders"][$key] ); // remove failed order
        continue;
      }
      else
      {
        $fleetLoc = $byColonyName[ $inputData["fleets"][$fleet]["location"] ]; // find fleet location
      }

      // Unload Census
      if( strtolower($inputData["orders"][$key]["target"]) == "census" )
      {
        // determine if this colony is owned by the player
        if( $inputData["colonies"][$fleetLoc]["owner"] != $inputData["empire"]["empire"] )
        {
          echo $loadErrorString."'. This player does not own this colony.\n";
          $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                         "text"=>$loadErrorString."'. This player does not own this colony.\n");
          unset( $inputData["orders"][$key] ); // remove failed order
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
      $inputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$inputData["game"]["turn"],
                                     "text"=>$loadErrorString."'. Unit not unloaded.\n");
      unset( $inputData["orders"][$key] ); // remove failed order
    }
    $inputData["orders"] = array_values( $inputData["orders"] ); // re-index the orders to close up gaps caused by invalid orders
  }
  
###
# Add raids
# Note: This is post-movement, during combat
###
$raidPlaces = array();

// Mark locations owned by this player
// count naval value at each location
foreach( $byColonyOwner[ $inputData["empire"]["empire"] ] as $item )
{
  $locationName = $inputData["colonies"][ $item ]["name"]; // convenience variable
  $navalCost = 0; // EP cost of ships at this location
  $civvieCount = 0; // number of civilian units here

  // count the units present only if there are fleets here
  if( isset( $byFleetLocation[ $locationName ] ) )
    // count the ships here (civvie count, naval cost)
    foreach( $byFleetLocation[ $locationName ] as $index )
      foreach( $inputData["fleets"][$index]["units"] as $hull )
      {
        // count if a civilian unit
        if( in_array( $hull, $CIVILIAN_FLEETS ) )
        {
          $civvieCount++;
          continue;
        }

        // get EP cost if a naval unit
        $navalCost += $inputData["unitList"][ $byDesignator[$hull] ]["cost"];
      }

  // add to raid places if no fleet EP is here or there are civvies present
  if( $navalCost == 0 || $civvieCount > 0 )
    $raidPlaces[] = array( "civCount" => $civvieCount,
                           "location"=> $locationName,
                           "naval"=> $navalCost
                         );
}

// Calculate the raids
foreach( $raidPlaces as $place )
{
  $chance = 20; // base chance to get a raid
  $civChance = 0; // chance from additional civilian ships
  $navalChance = 0; // chance reduction from naval ships
  $intelChance = 0; // chance reduction from intel ops
  $rand = mt_rand(1,100); // determination of raid occurance

  // If there is more than one civilian fleet, increase the chance of a raid by 20% per additional
  // The first civilian fleet allows the chance of a raid
  if( $place["civCount"] > 1 )
    $civChance = ($place["civCount"]-1) * 20;

  if( $place["naval"] > 0 )
    $navalChance += 5; // 5% off for more than 0 construction value
  if( $place["naval"] > 8 )
    $navalChance += 5; // total of 10% off for more than 8 construction value
  if( $place["naval"] > 12 )
    $navalChance = 20 * intval($place["naval"] / 12); // 20% off the chance for every 12 construction value

  // find out if intel was used to prevent this
  $orderKeys = findOrder( $inputData, "intel" );
  if( isset($orderKeys[0]) )
    foreach( $orderKeys as $key )
      if( $inputData["orders"][$key]["reciever"] == "Reduce Raiding" && $inputData["orders"][$key]["target"] == $place["location"] )
        // reduce chances by 10% per intel used
        $intelChance = 10 * intval($inputData["orders"][$key]["note"]);

  $chance += $civChance - $navalChance - $intelChance;

  if( $rand <= $chance )
  {
    $raidAmt = mt_rand(1,3) * mt_rand(1,6);
    $text = "A raid struck '".$place["location"]."'. There was a $chance% chance and a $rand was rolled. ";
    $text .= "If there is a civilian fleet present, that is the target of the raid. Otherwise, it is the ";
    $text .= "fixed installation that are raided. It is raided with $raidAmt construction value of raiders.";
    if( $EXPLAIN_RAIDS )
    {
      $text .= " There were ".$place["civCount"]." civilian craft, for a base chance of ".(20+$civChance);
      $text .= "% and ".$place["naval"]." EP in naval units, which reduced the chance by $navalChance%. ";
      $text .= "Intel (if used) dropped chances by $intelChance%.";
    }
    $inputData["events"][] = array("event"=>"A raid in '".$place["location"]."' happened.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
  else
  {
    $text = "There was no raid in '".$place["location"]."'. There was a $chance% chance and a $rand was rolled.";
    if( $EXPLAIN_RAIDS )
    {
      $text .= " There were ".$place["civCount"]." civilian craft, for a base chance of ".(20+$civChance);
      $text .= "% and ".$place["naval"]." EP in naval units, which reduced the chance by $navalChance%. ";
      $text .= "Intel (if used) dropped chances by $intelChance%.";
    }
    $inputData["events"][] = array("event"=>"A raid failed in '".$place["location"]."'.","time"=>"Turn ".$inputData["game"]["turn"],"text"=>$text);
  }
}

// make all of the orders section into non-drop-down menus
foreach( array_keys( $inputData["orders"] ) as $key )
  $inputData["orders"][$key]["perm"] = 1;
// Note: Leave $inputData["game"]["blankOrders"] alone. The next segment of the turn needs these drop-downs.

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

