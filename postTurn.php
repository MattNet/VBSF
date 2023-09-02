#!/usr/bin/php -q
<?php
###
# Creates a new data file from an old one
###
# Advances the turn variable
# Sets the previous-file link
# Invests research
# advance research (if needed)
# Creates fleets from built items
# Empties the orders array of new file
# Empties the construction array of new file
###

if( ! isset($argv[1]) )
{
  echo "\nCreates a data file for a player position\nThis is used after the second turn segment.\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." OLD_TURN_DATA_FILE [NEW_FILE_NAME]\n\n";
  exit(1);
}

###
# Initialization
###

$fileRepoDir = "files/";
$inputData = array(); // PHP variable of the JSON data
$newFileName = ""; // save file filename
$checklist = array(); // list of things that need to be done
$MAKE_CHECKLIST = false; // if true, adds a turn checklist to the events
$ACCELLERATED_RESEARCH = false; // If true, do research every half year. *Is Buggy*
$BUILD_LOADED_COLONY_FLEETS = true; // If true, load colony fleets on the turn they are built

if( isset($argv[2]) )
  $newFileName = $argv[2];

include( "./postFunctions.php" );

###
# Generate Input
###
$inputData = extractJSON( $argv[1] );
if( $inputData === false ) // leave if there was an error loading the file
  exit(1);

// get the lookup tables
list( $byColonyName, $byColonyOwner, $byFleetName, $byFleetLocation, $byFleetUnits, $byMapLocation, $byMapOwner, $byDesignator ) = makeLookUps($inputData);

// pull out the next-file name for writing the new data
if( empty($newFileName) )
{
  if( ! empty($inputData["game"]["nextDoc"]) )
  {
  // Filename is defined in the data file and is not assigned when the script was called
    $newFileName = $inputData["game"]["nextDoc"].".js";
  }
  else
  {
  // Filename is not defined in the data file and also is not defined in script arguments
    echo "Filename for new data file not given in data and not given in script-arguments.\n\n";
    exit(0);
  } 
}
else if( strpos( $newFileName, ".", -3 ) === false )
{
  // Filename does not contain the proper filetype extension (e.g. ".js")
  echo "Filename does not contain the proper filetype extension (e.g. '.js').\n\n";
  exit(0);
}

###
# Perform the end-of-turn actions
###

###
# Modify and write the previous-turn data file
###
// Copy the input data to use as output data
// Make seperate because of last-minute edits to the input copy that we don't 
// want to propogate to the output copy
$outputData = $inputData;

// make all of the orders section into non-drop-down menus
foreach( array_keys( $inputData["orders"] ) as $key )
  $inputData["orders"][$key]["perm"] = 1;

// clear the drop-downs in the orders section
$inputData["game"]["blankOrders"] = 0;

// add the next-doc link
// Overwrite previous value, since it might have been re-defined by the CLI
// remove the last 3 chars. They should be ".js"
$inputData["game"]["nextDoc"] = substr( $newFileName, 0, -3 );

// remove any location info from nextdoc but allow the written file name to keep it
// This means the display will point only at files in the same location,
// but the script will write to where the user wants
if( strrpos( $inputData["game"]["nextDoc"], "/" ) !== false )
  $inputData["game"]["nextDoc"] = substr( $inputData["game"]["nextDoc"], strrpos( $inputData["game"]["nextDoc"], "/" )+1 );

$results = writeJSON( $inputData, $argv[1] );
if( $results === false )
{
  echo "Error writing '".$argv[1]."'.\n\n";
  exit(0);
}
else
{
  echo "Removed order drop-down-menus in '".$argv[1]."'.\n\n";
}

###
# Make the end-of-turn modifications
###

  // set the prev file
  $outputData["game"]["previousDoc"] = str_replace( $fileRepoDir, "", $argv[1] );
  $outputData["game"]["previousDoc"] = str_replace( ".js", "", $outputData["game"]["previousDoc"] );

  // remove the next file
  $outputData["game"]["nextDoc"] = "";

  // remove the previous-turn events
  $outputData["events"] = array();

  // Advance the turn
  $outputData["game"]["turn"] += 1;

  // create the drop-downs in the orders section
  $outputData["game"]["blankOrders"] = 3;

  // clear the expenses so they aren't carried over to the new turn
  $outputData["empire"]["maintExpense"] = 0;
  $outputData["empire"]["miscExpense"] = 0;

  // Add in the excess EPs
  $outputData["empire"]["previousEP"] = getLeftover( $inputData );

  // Empty the events list
  unset( $outputData["events"] );
  $outputData["events"] = array();

###
# Start Of Turn processing
###

###
# Destroy units
# Note: Do this before colonization, in case colony fleets are destroyed before they can colonize
###

###
# Reduce or increase colony stats
# Note: This is for random events, loading or unloading census
###

###
# Load / unload units (colony fleets, troop transports)
# Only perform loading / unoloading of Census here
# The UI shows that the Census will be deducted at the end of turn
# Load Order: {"type":"load","reciever":"Colony Fleet w\/ Colony-1","target":"Census","note":"1"}
###
  // Find any load orders
  $orderKeys = findOrder( $outputData, "load" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      $loadAmt = (int) $outputData["orders"][$key]["note"];
      $reciever = (string) $outputData["orders"][$key]["reciever"];
      $target = (string) $outputData["orders"][$key]["target"];

      // Keep the load amount a single digit
      if( $loadAmt > 9 )
      {
        $loadAmt = 9;
        $outputData["orders"][$key]["note"] = 9; // truncate the given order
        echo "Order given to load '$reciever' with ".$outputData["orders"][$key]["note"];
        echo " of '$target'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to load '$reciever' with $loadAmt of '$target'. ";

      $fleet = -1; // key to the fleet array
      $fleetLoc = -1; // key to colonies array
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit

      // determine if this is a ground unit being loaded
      if( isset( $byDesignator[ $target ] )
          && $outputData["unitList"][ $byDesignator[ $target ] ]["design"] == "ground unit"
        )
        $isGroundUnit = true;

      // find the fleet
      foreach( $byFleetName as $fleetName=>$fleetKey )
       if( str_ends_with( $reciever, $fleetName ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."Could not find fleet.\n";
        exit(1);
      }

      // skip if this fleet location cannot be found
      if( ! isset( $byColonyName[ $outputData["fleets"][$fleet]["location"] ] ) )
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."Location of fleet is not a colony.\n");
        continue;
      }
      else
      {
        $fleetLoc = $byColonyName[ $outputData["fleets"][$fleet]["location"] ]; // find fleet location
      }

      // determine if this colony is owned by the player
      if( $outputData["colonies"][$fleetLoc]["owner"] != $outputData["empire"]["empire"] )
      {
        echo $loadErrorString."'. This player does not own this colony.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."This player does not own this colony.\n");
        continue;
      }

      // find amt of the supply trait in this fleet
      $supplyAmt = getFleetSupplyValue( $outputData, $outputData["fleets"][$fleet]["units"] );
      if( $supplyAmt == 0 ) // skip if this fleet has no supply trait
      {
        echo $loadErrorString."'. Fleet has no supply trait.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."Fleet has no supply trait.\n");
        continue;
      }
/*
      // find supply amt already used in this fleet
      $supplyUsed = getFleetloadedValue( $outputData["fleets"][$fleet] );

      // skip if the fleet cannot hold the unit
      if( $supplyAmt - $supplyUsed < ( 10 * $loadAmt ) )
      {
        echo $loadErrorString."'. Loading $loadAmt would overload fleet.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."Loading $loadAmt would overload fleet.\n");
        continue;
      }
*/

      // Load Census
      if( strtolower($outputData["orders"][$key]["target"]) == "census" )
      {
        // skip if there is not enough Census to load
        if( $outputData["colonies"][$fleetLoc]["census"] <= $loadAmt+1 )
        {
          echo $loadErrorString."'. Loading $loadAmt of Census would empty the colony.\n";
          $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                         "text"=>$loadErrorString."Loading $loadAmt of Census would empty the colony.\n");
          continue;
        }
/*
        // Add Census to fleet
        $outputData["fleets"][$fleet]["notes"] .= "$loadAmt Census loaded.";
*/
        // remove Census from location
        $outputData["colonies"][$fleetLoc]["census"] -= $loadAmt;
        // deal with maybe having to much Morale
        if( $outputData["colonies"][$fleetLoc]["morale"] > $outputData["colonies"][$fleetLoc]["census"] )
          $outputData["colonies"][$fleetLoc]["morale"] = $outputData["colonies"][$fleetLoc]["census"];

        // finished with this load order
        continue;
      }

/*
      // Load ground units
      if( $isGroundUnit )
      {
        $unitCount = 0;

        // skip if there is not enough of this ground unit to load
        foreach( $outputData["colonies"][$fleetLoc]["fixed"] as $fixedKey=>$fixed )
          if( strtolower($outputData["orders"][$key]["target"]) == strtolower($fixed) )
            $unitCount++;
        if( $unitCount < $loadAmt )
        {
          echo $loadErrorString."'. Not enough $target are present at colony.\n";
          $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                         "text"=>$loadErrorString."'. Not enough $target are present at colony.\n");
          continue;
        }
        
        // Add unit to fleet
        $outputData["fleets"][$fleet]["notes"] .= "$loadAmt $target loaded.";
        // remove unit from location
        foreach( $outputData["colonies"][$fleetLoc]["fixed"] as $fixedKey=>$fixed )
        {
          if( strtolower($target) == strtolower($fixed) && $loadAmt > 0 )
          {
            unset( $outputData["colonies"][$fleetLoc]["fixed"][$fixedKey] );
            $loadAmt--;
          }
        }
        // re-index the fixed-unit array
        $outputData["colonies"][$fleetLoc]["fixed"] = array_values( $outputData["colonies"][$fleetLoc]["fixed"] );

        // finished with this load order
        continue;
      }
*/
      echo $loadErrorString."'. Unit not loaded.\n";
      $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],"text"=>$loadErrorString."Unit not loaded.\n");
    }
//    $outputData["orders"] = array_values( $outputData["orders"] ); // re-index the orders to close up gaps caused by invalid orders
  }
/***
Unloading uses the same process, but with reverse effects
***/
  // Find any unload orders
  $orderKeys = findOrder( $outputData, "unload" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      $loadAmt = (int) $outputData["orders"][$key]["note"];
      $reciever = (string) $outputData["orders"][$key]["reciever"];
      $target = (string) $outputData["orders"][$key]["target"];

      // Keep the unload amount a single digit
      if( $loadAmt > 9 )
      {
        $loadAmt = 9;
        $outputData["orders"][$key]["note"] = 9; // truncate the given order
        echo "Order given to unload '$reciever' with ".$outputData["orders"][$key]["note"];
        echo " of '$target'. Amount truncated to 9.\n";
      }

      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to unload '$reciever' with $loadAmt of '$target'. ";

      $fleet = -1; // key of the fleet array that is being loaded
      $isGroundUnit = false; // determines if a unit being loaded is a ground unit

      // determine if this is a ground unit being unloaded
      if( isset( $byDesignator[ $target ] )
          && $outputData["unitList"][ $byDesignator[ $target ] ]["design"] == "ground unit"
        )
        $isGroundUnit = true;
      
      // find the fleet
      foreach( $outputData["fleets"] as $fleetKey=>$value )
       if( str_ends_with( $reciever, $value["name"] ) )
         $fleet = $fleetKey;
      if( $fleet == -1 )
      {
        echo $loadErrorString."Could not find fleet.\n";
        // do not remove order, because exiting the script
        exit(1);
      }
      
      $success = preg_match( "/(\d) $reciever loaded/i", $loadAmt, $matches );
      $amtLoaded = (int) $matches[1];
      if( ! $success || $amtLoaded < 1 )
      {
        echo $loadErrorString."Fleet does not carry any $target.\n";
        // do not remove order, because exiting the script
        exit(1);
      }

      // skip if there is not enough to unload
      if( $amtLoaded >= $loadAmt )
      {
        echo $loadErrorString."'. The fleet does not carry enough. It only has $amtLoaded.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."The fleet does not carry enough. It only has $amtLoaded.\n");
        continue;
      }

      // skip if this fleet location cannot be found
      if( ! isset( $byColonyName[ $outputData["fleets"][$fleet]["location"] ] ) )
      {
        echo $loadErrorString."'. Location of fleet is not a colony.\n";
        $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                       "text"=>$loadErrorString."Location of fleet is not a colony.\n");
        continue;
      }
      else
      {
        $fleetLoc = $byColonyName[ $outputData["fleets"][$fleet]["location"] ]; // find fleet location
      }

      // Unload Census
      if( strtolower($outputData["orders"][$key]["target"]) == "census" )
      {
        // determine if this colony is owned by the player
        if( $outputData["colonies"][$fleetLoc]["owner"] != $outputData["empire"]["empire"] )
        {
          echo $loadErrorString."'. This player does not own this colony.\n";
          $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                         "text"=>$loadErrorString."This player does not own this colony.\n");
          continue;
        }

        // Remove Census from fleet
        $outputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadAmt Census loaded.",
          "",
          $outputData["fleets"][$fleet]["notes"]
        );
        // add Census to location
        $outputData["colonies"][$fleetLoc]["census"] += $loadAmt;

        // finished with this unload order
        continue;
      }
/*
      // Unload ground units
      if( $isGroundUnit )
      {
        // Remove unit to fleet
        $outputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadAmt $target loaded.",
          "",
          $outputData["fleets"][$fleet]["notes"]
        );
        // add unit to location
        for( $i=$loadAmt; $i=0; $i-- )
          $outputData["colonies"][$fleetLoc]["fixed"][] = $outputData["orders"][$key]["target"];
        // re-index the fixed-unit array
        $outputData["colonies"][$fleetLoc]["fixed"] = array_values( $outputData["colonies"][$fleetLoc]["fixed"] );

        // finished with this unload order
        continue;
      }
      echo $loadErrorString."Unit not unloaded.\n";
      $outputData["events"][] = array("event"=>"Load order failed","time"=>"Turn ".$outputData["game"]["turn"],
                                     "text"=>$loadErrorString."Unit not unloaded.\n");
*/
    }
//    $outputData["orders"] = array_values( $outputData["orders"] ); // re-index the orders to close up gaps caused by invalid orders
  }

###
# Colonization
###

  // Find any colonize orders
  $orderKeys = findOrder( $outputData, "colonize" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      // convenience variable
      $fleetLoc = $outputData["orders"][$key]["reciever"];
      // convenience variable. Error string that identifies order that is wrong
      $loadErrorString = "Order given to colonize at '$fleetLoc'";
      // key of the fleet array that is colonizing
      $fleet = -1;
      // key of the colony array that is being colonized
      $colony = -1;

      // find the colony
      if( isset($byColonyName[ $fleetLoc ]) )
      {
        $colony = $byColonyName[ $fleetLoc ];
      }
      else
      {
        echo $loadErrorString."'. Could not find colonization location in colony data.\n";
        continue;
      }

      // find the fleet
      if( isset($byFleetLocation[ $fleetLoc ]) )
      {
        foreach( $byFleetLocation[ $fleetLoc ] as $tempFleetID )
        {
          // if the fleet has a colony fleet
          if( ! in_array( $tempFleetID, $fleetUnits["Colony Fleet"] ) )
            continue;
          // if the fleet has Census loaded
          $success = preg_match( "/(\d) Census loaded/i", $outputData["fleets"][$tempFleetID]["notes"], $matches );
          if( ! $success || $matches[1] < 1 )
            continue;
          $fleet = $tempFleetID;
          $loadedAmt = $matches[1];
        }

        if( $fleet == -1 )
        {
          $outputData["events"][] = array("event"=>$loadErrorString."'. Could not find a fleet with all colonization elements.","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");
          echo $loadErrorString."'. Could not find fleet with all colonization elements.\n";
          continue;
        }
      }
      else
      {
        // hard failure if we could not find the fleet location
        echo $loadErrorString."'. Could not find the fleet location.\n";
        exit(1);
      }

      // Fail if this is an empty system. e.g. by capacity = 0
      if( $outputData["colonies"][$colony]["capacity"] < 1 )
      {
        $outputData["events"][] = array("event"=>$loadErrorString."'. There is no system here.","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");
        echo $loadErrorString."'. There is no system here.\n";
        continue;
      }

      // determine if this colony is owned by nobody. e.g. by "General"
      if( $outputData["colonies"][$colony]["owner"] != "General" )
      {
        $outputData["events"][] = array("event"=>$loadErrorString."'. This location is already a colony.","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");
        echo $loadErrorString."'. This location is already a colony.\n";
        continue;
      }

      // add Census to location
      $outputData["colonies"][$colony]["census"] = 1;
      // add Morale to location
      $outputData["colonies"][$colony]["morale"] = 1;

      // change ownership of the location
      $outputData["colonies"][$colony]["owner"] = $outputData["empire"]["empire"];

      // update the lookup tables
      $byColonyOwner[ "General" ][ $outputData["empire"]["empire"] ][] = $colony;
      foreach( $byColonyOwner[ "General" ] as $genKey => $gen )
        if( $gen == $colony )
          unset( $byColonyOwner[ "General" ][ $genKey ] );

      // add owner to the map info
      foreach( $outputData["mapPoints"] as $mapKey=>$value )
      {
        if( $value[3] != $fleetLoc )
          continue; // skip if this is not the location
        $outputData["mapPoints"][$mapKey][2] = $outputData["empire"]["empire"];

        // update the lookup tables
        $byMapOwner[ $outputData["empire"]["empire"] ][] = $mapKey;
        foreach( $byMapOwner[ "General" ] as $genKey => $gen )
          if( $gen == $mapKey )
            unset( $byMapOwner[ "General" ][ $genKey ] );
      }

      // Remove Census from fleet
      if( $loadedAmt == 1 )
        $outputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadedAmt Census loaded.",
          "",
         $outputData["fleets"][$fleet]["notes"]
        );
      else
        $outputData["fleets"][$fleet]["notes"] = str_replace(
          "$loadedAmt Census loaded.",
          ($loadedAmt-1)." Census loaded.",
         $outputData["fleets"][$fleet]["notes"]
        );

      // Remove Colony Fleet from fleet
      foreach( $outputData["fleets"][$fleet]["units"] as  $fleetKey=>$fleetValue )
      {
        if( $fleetValue == "Colony Fleet" )
        {
          unset( $outputData["fleets"][$fleet]["units"][$fleetKey] );
          break; // stop the loop here. Don't want to unset more than one Colony Fleet
        }
      }

      // If the fleet is empty, remove the fleet
      if( empty($outputData["fleets"][$fleet]["units"]) )
      {
        // update the lookup tables
        unset( $byFleetName[ $outputData["fleets"][$fleet]["name"] ] );
        foreach( $byFleetLocation[ $fleetLoc ] as $tempKey=>$tempFleet)
          if( $tempFleet == $fleetKey )
            unset( $byFleetLocation[ $fleetLoc ][$fleetKey] );

        // remove the fleet
        unset( $outputData["fleets"][$fleet] );
        $outputData["fleets"] = array_values( $outputData["fleets"] ); // re-index the fleets array
      }

      $outputData["events"][] = array("event"=>"Colonized '$fleetLoc'","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"Ready to be renamed.");

      // finished with this colonization order
      continue;
    }
  }

###
# Calculate economy
###

  // calculate the income for the turn-start
  $outputData["empire"]["planetaryIncome"] = getTDP( $outputData );

  // invest research
  $orderKeys = findOrder( $outputData, "research" );
  if( isset($orderKeys[0]) )
    $outputData["empire"]["researchInvested"] += intval($outputData["orders"][$orderKeys[0]]["note"]);

###
# Process research advancement
###

  // if the current turn was the 0th turn of the year or if the accellerated research and we are halfway through the year
  // then advance research (if needed)
  if( $outputData["game"]["turn"] % $outputData["game"]["monthsPerYear"] == 0 ||
      ( $ACCELLERATED_RESEARCH && 
      floor($outputData["game"]["turn"] % $outputData["game"]["monthsPerYear"]) == floor($outputData["game"]["monthsPerYear"] / 2)  )
    )
  {
    $amt = $outputData["empire"]["researchInvested"]; // convenience variable
    $goal = floor( getTDP($outputData) / 2 ); // convenience variable
    $rand = mt_rand(1,100);
    if( $amt > $goal )
      $amt = $goal; // first roll can be no better than the goal

    // if the roll was equal or larger than the chance, then the player made the roll
    if( floor( $amt / $goal * 100 ) <= $rand )
    {
      $outputData["empire"]["techYear"]++;
      $outputData["empire"]["researchInvested"] -= $amt;
      $outputData["events"][] = array("event"=>"Advanced technology to Y".$outputData["empire"]["techYear"],
                                      "time"=>"Turn ".$outputData["game"]["turn"],
                                      "text"=>"Advanced technology to Y".$outputData["empire"]["techYear"].
                                      ". The chance of success was ".floor( $amt / $goal * 100 )." and rolled a ".$rand
                                     );
      // Attempt second advancement
      $goal = getTDP(); // convenience variable. Note that this makes the chances half of the successful attempt
      $amt = $outputData["empire"]["researchInvested"]; // convenience variable
      $rand = mt_rand(1,100);

      if( floor( $amt / $goal * 100 ) <= $rand )
      {
        $outputData["empire"]["techYear"]++;
        $outputData["empire"]["researchInvested"] -= $amt;
        $outputData["events"][] = array("event"=>"Advanced technology to Y".$outputData["empire"]["techYear"],
                                        "time"=>"Turn ".$outputData["game"]["turn"],
                                        "text"=>"Advanced technology to Y".$outputData["empire"]["techYear"].
                                        ". The chance of success was ".floor( $amt / $goal * 100 )." and rolled a ".$rand
                                       );
      }
      else
      {
        $outputData["empire"]["researchInvested"] = 0;
        $outputData["events"][] = array("event"=>"Failed to advance technology from Y".$outputData["empire"]["techYear"],
                                        "time"=>"Turn ".$outputData["game"]["turn"],
                                        "text"=>"Failed to advance technology to Y".$outputData["empire"]["techYear"].
                                        ". The chance of success was ".floor( $amt / $goal * 100 )." and rolled a ".$rand
                                       );
      }
    }
    else // did not make the roll
    {
      $outputData["events"][] = array("event"=>"Failed to advance technology from Y".$outputData["empire"]["techYear"],
                                      "time"=>"Turn ".$outputData["game"]["turn"],
                                      "text"=>"Failed to advance technology to Y".$outputData["empire"]["techYear"].
                                      ". The chance of success was ".floor( $amt / $goal * 100 )." and rolled a ".$rand
                                     );
    }
  }

###
# Build units
###

  // find the build orders and create the fleets
  $orderKeys = findOrder( $outputData, "build_unit" );
  if( isset($orderKeys[0]) )
  {
    foreach( $orderKeys as $orderKey )
    {
      $fleetKey = -1; // index to the fleets array for the destination fleet

      // find fleets with this name
      if( isset($byFleetName[ $outputData["orders"][$orderKey]["note"] ]) )
        $fleetKey = $byFleetName[ $outputData["orders"][$orderKey]["note"] ];
      // reset $fleetKey if the named fleet is not where the build occured
      if( $fleetKey >= 0 && $outputData["fleets"][ $fleetKey ]["location"] != $outputData["orders"][$orderKey]["target"] )
        $fleetKey = -1;

      // this unit should be built into an existing fleet
      if( $fleetKey >= 0 )
        $outputData["fleets"][$fleetKey]["units"][] = $outputData["orders"][$orderKey]["reciever"];
      else
      // if no fleets with this name, then create a new one
      $outputData["fleets"][] = array( "name" => $outputData["orders"][$orderKey]["note"], 
                     "location" => $outputData["orders"][$orderKey]["target"], 
                     "units" => array( $outputData["orders"][$orderKey]["reciever"] ), 
                     "notes" => "",
                   );
    }
  }

###
# Handle Morale checks
###

###
# Rename things
# Note: This is last, because the data files and orders point to the old names
#  {"type":"name","reciever":"Fraxee dir B","note":"Fraxa","target":""}
###
// rename the colony
// rename the location of fleets at that place

  // find renaming orders
  $orderKeys = findOrder( $outputData, "name" );

  if( isset($orderKeys[0]) ) // is there at least one instance?
  {
    foreach( $orderKeys as $key )
    {
      // some convenience variables
      $oldName = $outputData["orders"][$key]["reciever"];
      $newName = $outputData["orders"][$key]["note"];
      $colonyKey = -1; // index to the colony array
      $flag = false; // used to detect a fraudulent order

      // Does this colony exist?
      if( isset($byColonyName[ $oldName ]) )
        $colonyKey = $byColonyName[ $oldName ];
      else
      {
        echo "Tried to rename colony '$oldName' that doesn't exist.\n";
        $outputData["events"][] = array("event"=>"Could not rename $oldName: Does not exist.","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");
        continue;
      }

      // Does this colony belong to this empire?
      if( in_array( $colonyKey, $byColonyOwner[ $outputData["empire"]["empire"] ] ) )
      {
        echo "Tried to rename colony '$oldName' that doesn't belong to this player.\n";
        $outputData["events"][] = array("event"=>"Could not rename $oldName: Does not belong to you.","time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");
        continue;
      }

      // Rename the colony
      $outputData["colonies"][$colonyKey]["name"] = $newName;

      // find and rename the map point
      if( isset($byMapLocation[ $oldName ]) )
        $outputData["mapPoints"][ $byMapLocation[$oldName] ][3] = $newName;
      else
      {
        echo "Tried to rename colony '$oldName' that isn't in.\n";
        exit(1);
      }

      // find and rename the fleet locations
      foreach( $outputData["fleets"] as $fleetKey=>$value )
      {
        if( $value["location"] == $oldName )
          $outputData["fleets"][$fleetKey]["location"] = $newName;
      }
    }
  }


###
# Ready the new player sheet
###

  // Empty the orders
  unset( $outputData["orders"] );
  $outputData["orders"] = array();

  // Empty the construction list
  unset( $outputData["underConstruction"] );
  $outputData["underConstruction"] = array();

  // Empty the purchases list
  unset( $outputData["purchases"] );
  $outputData["purchases"] = array();

  // Fill events with a checklist
  if( $MAKE_CHECKLIST )
  {
    $checklist[] = "Checklist: Building";
    $checklist[] = "Checklist: Colonize and build assets";
    $checklist[] = "Checklist: Morale check";
    $checklist[] = "Checklist: Research";
    $checklist[] = "Checklist: Orders";
  }

  // fill events with the list generated by this script
  foreach( $checklist as $entry )
    $outputData["events"][] = array("event"=>$entry,"time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");

###
# Write out the file
###

writeJSON( $outputData, $newFileName );
exit(0); // all done

