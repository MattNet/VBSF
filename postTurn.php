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
  echo "  ".$argv[0]." OLD_TURN_DATA_FILE [NEW_FILE_NAME]\n";
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

###
# Modify and write the previous-turn data file
###
// clear the drop-downs in the orders section
$inputData["game"]["blankOrders"] = 0;

$results = writeJSON( $inputData, $argv[1] );
if( $results === false )
{
  echo "Error writing '".$argv[1]."'.";
  exit(0);
}
else
{
  echo "Removed order drop-down-menus in '".$argv[1]."'.\n\n";
}

###
# Make the end-of-turn modifications
###
  $outputData = $inputData; // preserve the input PHP variable

  // pull out the next file, if defined and if not overridden when the script was called
  if( ! empty($outputData["game"]["nextDoc"]) && empty($newFileName) )
    $newFileName = $outputData["game"]["nextDoc"].".js";

  // set the prev file
  $outputData["game"]["previousDoc"] = str_replace( $fileRepoDir, "", $argv[1] );
  $outputData["game"]["previousDoc"] = str_replace( ".js", "", $outputData["game"]["previousDoc"] );

  // Advance the turn
  $outputData["game"]["turn"] += 1;

  // create the drop-downs in the orders section
  $outputData["game"]["blankOrders"] = 3;

  // Add in the excess EPs
  $outputData["empire"]["previousEP"] = getLeftover( $inputData );

  // invest research
  $orderKeys = findOrder( $inputData, "invest" );
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
  $orderKeys = findOrder( $inputData, "build" );
  if( isset($orderKeys[0]) )
  {
    foreach( $orderKeys as $key )
    {
      $item = array( "name" => $outputData["orders"][$key]["note"], 
                     "location" => $outputData["orders"][$key]["target"], 
                     "units" => array( $outputData["orders"][$key]["reciever"] ), 
                   );

      $outputData["fleets"][] = $item;
    }
  }

  // Empty the orders
  unset( $outputData["orders"] );
  $outputData["orders"] = array();

  // Empty the construction list
  unset( $outputData["underConstruction"] );
  $outputData["underConstruction"] = array();

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

