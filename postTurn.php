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
  echo "\nCreates a data file for a player position\n\n";
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
list( $byColonyName, $byColonyOwner, $byFleetName, $byFleetLocation, $byFleetUnits, $byMapLocation, $byMapOwner ) = makeLookUps($inputData);

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
// to move the reduction/addition of census and gnd units from postOrders.php to here

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

