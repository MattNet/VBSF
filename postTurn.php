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
$MAKE_CHECKLIST = true; // if true, adds a turn checklist to the events
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

$outputData = $inputData; // Copy the input data to use as output data

// pull out the next-file name for writing the new data
if( empty($newFileName) )
{
  if( ! empty($outputData["game"]["nextDoc"]) )
  {
  // Filename is defined in the data file and is not assigned when the script was called
    $newFileName = $outputData["game"]["nextDoc"].".js";
  }
  else
  {
  // Filename is not defined in the data file and also is not defined in script arguments
    echo "Filename for new data file not given in data and not given in script-arguments.\n\n";
    exit(0);
  } 
}

###
# Modify and write the previous-turn data file
###
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
// but the scriopt will write to where the user wants
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

  // Add in the excess EPs
  $outputData["empire"]["previousEP"] = getLeftover( $inputData );

  // calculate the income for the turn-start
  $outputData["empire"]["pointPool"] = getTDP( $outputData ) + $outputData["empire"]["previousEP"];
  $outputData["empire"]["pointPool"] += $outputData["empire"]["tradeIncome"] + $outputData["empire"]["miscIncome"];

  // invest research
  $orderKeys = findOrder( $inputData, "research" );
  if( isset($orderKeys[0]) )
    $outputData["empire"]["researchInvested"] += intval($outputData["orders"][$orderKeys[0]]["note"]);

  // if the current turn was the 0th turn of the year or if the accellerated research and we are halfway through the year
  // then advance research (if needed)
  if( $outputData["game"]["turn"] % $outputData["game"]["monthsPerYear"] == 0 ||
      ( $ACCELLERATED_RESEARCH && 
      floor($outputData["game"]["turn"] % $outputData["game"]["monthsPerYear"]) == floor($outputData["game"]["monthsPerYear"] / 2)  )
    )
  {
    $amt = $outputData["empire"]["researchInvested"]; // convenience variable
    $rand = mt_rand(1,100);
    $goal = floor( getTDP() / 2 ); // convenience variable
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

  // find the build orders and create the fleets
  $orderKeys = findOrder( $inputData, "build_unit" );
  if( isset($orderKeys[0]) )
  {
    foreach( $orderKeys as $orderKey )
    {
      $fleetFlag = false; // flag to mark if the built goes into an existing fleet
      // find fleets with this name
      foreach( $outputData["fleets"] as $fleetKey=>$fleetData )
      {
        // skip if the name of the fleet does not match the name on the orders
        // and if the named fleet is not where the build occured
        if( $fleetData["name"] != $outputData["orders"][$orderKey]["note"] || 
            $fleetData["location"] != $outputData["orders"][$orderKey]["target"]
          )
          continue;
        $fleetFlag = true;
        // this unit should be built into this fleet
        $outputData["fleets"][$fleetKey]["units"][] = $outputData["orders"][$orderKey]["reciever"];
      }
      if( ! $fleetFlag )
      {
        // if no fleets with this name, then create a new one
        $outputData["fleets"][] = array( "name" => $outputData["orders"][$orderKey]["note"], 
                       "location" => $outputData["orders"][$orderKey]["target"], 
                       "units" => array( $outputData["orders"][$orderKey]["reciever"] ), 
                       "notes" => "",
                     );
      }
    }
  }

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

  unset( $outputData["events"] );
  $outputData["events"] = array();

  foreach( $checklist as $entry )
    $outputData["events"][] = array("event"=>$entry,"time"=>"Turn ".$outputData["game"]["turn"],"text"=>"");

###
# Write out the file
###

writeJSON( $outputData, $newFileName );
exit(0); // all done

