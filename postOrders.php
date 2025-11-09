#!/usr/bin/env php
<?php
#####
# open_game_files.php

# Perform processing for a VBAM turn

# Usage:
#   open_game_files.php  GAME_NAME  TURN
#####

#####
#
# Configuration
#
#####
$searchDir = 'files/'; // note the trailing slash

#####
#
# Initialization
#
#####
require_once("./GameData.php"); // for the reading and writing functions
$errors = [];
$gameFiles = array();
$targetGame = "";
$targetTurn = 0;

// lookup table of the various fleets. Used for blockade checks and Morale
// Format is $EnemyFleetLookup[location][empire name][array of unit designations]
// - Where 'empire name' is at GameData->empire['empire']
// - Where 'unit designation' is at GameData->unitList[]['ship']
$EnemyFleetLookup = [];
###
# CLI argument parsing
###
if (!isset($argv[2])) {
  echo "Perform processing for a VBAM turn\n";
  echo "Usage: ${argv[0]}  GAME_NAME  TURN\n\n";
  exit(1);
}

$targetGame = trim($argv[1]);
$targetTurn = (int)$argv[2];

###
# File retrieval:
# Data files are in $gameFiles[]
###
$gameFiles = fileRetrieval ($searchDir,$targetGame,$targetTurn);

#####
#
# Process Results
#
#####
foreach ($gameFiles as $empireId => $file) {
  $empireName = $file->empire['empire'] ?? $empireId;
  $turn = intval($file->game['turn'] ?? 0);
  $file->events = $file->events ?? [];
  echo "Building lists from '{$empireName}' file\n";

# Build up list of empire names to file names, for cross-sheet applications
  if (!isset($global_empire_names)) $global_empire_names = [];
  $global_empire_names[$empireName] = $file->fileName;
}
foreach ($gameFiles as $empireId => $file) {
  $empireName = $file->empire['empire'] ?? $empireId;
  $turn = intval($file->game['turn'] ?? 0);
  $file->events = $file->events ?? [];
  echo "Processing file for empire '{$empireName}'\n";

###
# Intel Phase
###
  if (!isset($globalModQueue)) $globalModQueue = [];

  // Create a lookup for the intel trait and intel modifiers for each colony
  $file->colonyIntelModifiers = [];
  foreach ($file->colonies as $colony) {
    $colonyName = $colony['name'] ?? '';
    $modifier = 0;

    // Listening post (military base of Outpost size or larger) grants +2 Intel Range per rules.
    if ($file->locationHasAbility($colonyName, 'Listening Post')) $modifier += 2;

    // Store effective intel value and modifiers
    $file->colonyIntelModifiers[$colonyName] = [
        'baseIntel' => $col['intel'] ?? 0,
        'modifier'  => $modifier
    ];
    // TODO: Empire trait hooks would alter $modifier or baseIntel here (not implemented).
  }

  // 4.3.4 Covert operations
  // Resolve all orders type == "covert" originating from colonies on this sheet.
  $intelOrders = array_filter($file->orders ?? [], function ($o) {
    return in_array($o['type'] ?? '', ['covert','special_force']);
  });
  if (!empty($intelOrders)) {
    foreach ($intelOrders as $ord) {
      $source = $order['receiver'] ?? '';
      $target = $order['target'] ?? '';
      $mission = $order['note'] ?? 'Unknown Mission';
      $mod = $file->colonyIntelModifiers[$source]['modifier'] ?? 0; // listening post bonus already included

      $sourceColony = $file->getColonyByName($source);
      if (!$srcCol) {
        $file->events[] = ['text'=>'Covert operation order invalid','turn'=>$turn,
              'event'=>"Covert order from {$source} ignored. Source colony not found."];
        continue;
      }
      $targetColony = $file->getColonyByName($target);
/*
# Look for the targetcolony in unknown places
# however, need to know who the real owner is and a translation of 
# the unknown name to the actual name
      if (!$targetColony) {
        foreach ($file->unknownMovementPlaces as $unknown) {
          if ($target == $unknown) {
            $targetColony = [
              "name":"","capacity":0,"fort":0,"intel":0,"morale":0,"owner":"",
              "population":0,"raw":0,"type":"","notes":"","fixed":[]
            ];
            break;
          }
        }
      }
*/
      if (!$targetColony) {
        $file->events[] = ['text'=>'Covert operation order invalid','turn'=>$turn,
              'event'=>"Covert order from {$target} ignored. Source colony not found."];
        continue;
      }

      switch ($ord['type'])
      {
      case 'covert':
        // Check range from source to target. Covert ops must be within {intel} systems
        $maxIntelRange = $file->colonyIntelModifiers[$source]['baseIntel'];
        break;
      case 'special_force':
        $hasSF = $file->locationHasAbility($targetColony, 'Special Forces');
        if (!$hasSF) {
          $file->events[] = ['text'=>'Special Forces absent','turn'=>$turn,
              'event'=>"No special forces at {$source} for mission {$mission}."];
          continue 2;
        }
        // Check range from source to target. Special forces must be from adjacent systems
        $maxIntelRange = 1;
        // Spcial forces has an aditional -1 modifier to covert ops
        $mod -= 1;
      } // end switch

      // Blockade prevents covert missions from that system
      if ($file->checkBlockaded($source)) {
        $file->events[] = ['text'=>'Covert operation blocked','turn'=>$turn,
              'event'=>"{$source} is blockaded and cannot perform covert missions."];
        continue;
      }

      // Must have intel > 0 to perform covert missions
      if ($file->colonyIntelModifiers[$source]['baseIntel'] <= 0) {
        $file->events[] = ['text'=>'Covert operation denied','turn'=>$turn,
              'event'=>"{$source} has insufficient Intel to attempt {$mission} on {$target}."];
        continue;
      }

      // Check range from source to target.
      $pathInfo = $file->findPath($source, $target, true);
      $distance = $pathInfo['distance'];
      if ($distance > $maxIntelRange) {
        $file->events[] = ['text'=>'Covert operation out of range', "{$source} cannot reach {$target}. Distance {$distance} exceeds range."];
        continue;
      }

      // Prepare resolution: roll d10 then apply modifiers
      $roll = $file->rollDie();

### TODO check for trade route that touches the target system

      // If the target's intel is less than the source intel, then mission gets a +1
      $targetIntel = $targetColony['intel'] ?? 0;
      if ($targetIntel < $file->colonyIntelModifiers[$source]['baseIntel']) $mod += 1;

      // Mission-specific simple modifier examples
      switch ($mission)
      {
      case 'Civilian':
        $mod -= 2;
        break;
      case 'Counter-Insurgency':
        $mod -= 1;
        break;
      case 'Counterintel':
        $mod -= 1;
        break;
      case 'Espionage':
        $mod -= 0;
        break;
      case 'Fortification':
        $mod -= 1;
        break;
      case 'Industrial':
        $mod -= 0;
        break;
      case 'Insurgency':
        $mod -= 1;
        break;
      case 'Piracy':
        $mod -= 1;
        break;
      case 'Population':
        $mod -= 3;
        break;
      case 'Sabotage':
        $mod -= 1;
        break;
      case 'Tech':
        $mod -= 2;
        break;
      }

      // Empire traits would modify $mod here. TODO: apply $file->empire['traits'] if present.

      $final = $roll + $mod;

      $intelSuccess = ($final >= 6); // Success value
      $intelDetected = ($final >= 3 || $final == 6 || $final == 7); // Detected value
      $intelCaptured = ($final >= 1); // Captured value
      $intelImplicated = ($final >= 11); // Implication value

      // Build event text
      $eventText = "Covert '{$mission}' mission from {$source} to {$target}. Roll {$roll} + modifiers ({$mod}) = {$final}. ";
      $eventText .= $intelSuccess ? "Mission succeeded" : "Mission failed";
      $eventTextOpponent = "A covert '{$mission}' mission occurred on {$target}";
      if ($intelDetected) {
        $eventText .= " and was detected.";
      }
      if ($intelCaptured)
        $eventText .= " Your spy was captured in the act.";

      // determine who the source is (source may be implicated)
      $implication = $file->empire['empire'];
      if ($intelImplicated && !empty($file->otherEmpires)) {
        $implication = array_rand($file->otherEmpires); // Random implication ATM. Saves on creating an order for player to choose.
        $eventText .= " The {$implication} nation was implicated in the act.";
      }
      if ($intelDetected || $intelImplicated)
        $eventTextOpponent .= " by the {$implication} nation.";


      $intelOpDetails = [
            'action'     => "",
            'originFile' => $file->fileName ?? $global_empire_names[$empireName],
            'origin'     => $source,
            'target'     => $target,
            'targetFile' => $global_empire_names[$targetCol['owner']] ?? '',
            'mission'    => $mission,
            'result'     => $intelSuccess,
            'detected'   => $intelDetected,
            'source'     => $implication, // who is the implicated power?
            'roll'       => $roll,
            'modifiers'  => $mod,
            'final'      => $final,
            'turn'       => $turn,
            'notes'      => $eventTextOpponent
      ];


      // If target is local (same sheet) apply local effects immediately
      if ($targetCol['owner'] !== $empireName) {
        if ($intelSuccess) intelOpResults($mission, $target);
        $file->events[] = ['text'=>"Covert operation on own system, {$target}",'turn'=>$turn,'event'=>$eventText];
      } else {
        // External target: queue cross-sheet modification
        $intelOpDetails['action'] = 'intel_covert_resolution';
        $globalModQueue[] = $intelOpDetails;
        $file->events[] = ['text'=>"Covert operation on {$target}",'turn'=>$turn,'event'=>$eventText];
      }
      // If detected, may trigger diplomatic incident. We create a queued diplomatic incident entry.
      if ($intelDetected) {
        $intelOpDetails['action'] = 'diplomatic_incident';
      }
    } // end foreach $intelOrders
  } // end if $intelOrders
} // End foreach $GameFiles
###
# End of principal intel phase
###

$file->writeToFile($file->fileName); // commit current sheet now

if (empty($globalModQueue))
  echo "No cross-sheet modifications queued during Intel Phase.\n";
else
  echo "Applying " . count($globalModQueue) . " cross-sheet Intel modifications...\n";

###
# Start of recording of Intel results at target empires
###
foreach ($globalModQueue as $idx => $mod) {
  // Validate structure
  if (empty($mod['target'])) continue;  // must have target colony
  if (empty($mod['targetFile'])) continue;  // must have file to open

  if (!$targetFile || !file_exists($targetFile)) {
    echo "Data file not found for queued item {$idx} (target {$targetName}). Skipped.\n";
    continue;
  }

  // Load target sheet through GameData
  $file = new GameData($targetFile);

  // Prepare to log result as event on target sheet
  switch ($action) {
    case 'intel_covert_resolution':
      if ($targetCol && $targetCol['owner'] !== $empireName) {
        if ($intelSuccess) intelOpResults($mission, $target);
        $file->events[] = ['text'=>"Covert operation on {$target}",'turn'=>$turn,'event'=>$eventText];
      }
      break;
    case 'diplomatic_incident':
      # handle diplomatic incidents during the diplomacy phase. 
      break;
    default:
      $eventText = "[Intel Phase] Unrecognized queued action '{$action}' from {$origin} targeting {$targetName}.";
      break;
  }
  // Write back target file
  $file->writeToFile();
} // end foreach modQueue

###
# Movement Phase
###

###
# Diplomacy Phase
###

###
# Combat Phase
###


###
# End Of Turn Phase
###

###
# Write the old file. Create the new file
###
foreach ($gameFiles as &$file) {
  // make a new filename
  $newFile = substr(
               rtrim(
                 strtr(
                   base64_encode(
                     random_bytes(9)
                   ), '+/', '-_'
                 ),'='
               ), 0, 12
             );

  // Prevent new orders
  $file->game["blankOrders"] = 0;
  foreach ($file->orders as $order) {
    $order["perm"] = 1;
  }

  // set the next turn's name
  $file->game["nextDoc"] = $newFile;

  // Write the old file
  $file->writeToFile();

  // update the turn number
  $file->game["turn"] += 1;
  // update the previous/next docs
  $file->game["previousDoc"] = pathinfo($file->fileName, PATHINFO_FILENAME); // this is the old filename
  $file->game["nextDoc"] = "";
  // Allow orders
  $file->game["blankOrders"] = 3;
  $file->game["turnSegment"] = "pre";
  $file->orders = [];

  $file->writeToFile($searchDir.$newFile.".js"); // Also updates the $file->fileName
  unset($file);
}

###
# Economics Phase
###




###
# Write the game files
###
foreach ($gameFiles as $file) {
  $file->writeToFile();
}

showErrors($errors);

function showErrors(array $errorArray): void
{
  foreach($errorArray as $line) {
    echo $line."\n";
  }
  exit(1);
}

###
# Intel Operation Results:
# From the mission, affects the indicated changes to target. Assumes that the mission was a success
###
### TODO Handle the results of the intel operation
function intelOpResults (string $mission, string $target) {


}

###
# File retrieval:
# Scan a directory for data files, attempt to extract the 'game' object and 'turn'
# number from each file, and collect files that match a given game name and turn.
###
function fileRetrieval (string $directory, string $game, int $turn) {
  global $errors;

  // Validate search dir
  if (!is_dir($directory)) {
    $errors[] = "Error: search directory '{$directory}' does not exist or is not a directory.\n";
    showErrors($errors);
  }
  $dir = scandir($directory);
  // Examine each file. Keep the ones that match the criteria
  foreach ($dir as $file) {
    if (strpos($file, '.') === 0) continue; // skip hidden files
    if (pathinfo($file, PATHINFO_EXTENSION) !== "js") continue; // file must end in '.js'

    $fileCheck = new GameData($directory.$file);
    if ($fileCheck->game["game"] != $game) continue;
    if ($fileCheck->game["turn"] != $turn) continue;
    if ($fileCheck->game["turnSegment"] != 'pre') continue;
    $fileList[] = $fileCheck; // Keep file. It passed our tests
    echo "Using gamefile '{$file}' for empire '{$fileCheck->empire["empire"]}'\n";
    unset($fileCheck); // unload the game object if un-needed
  }
  if (empty($fileList)) {
    $errors[] = "No game files found.\n";
    showErrors($errors);
  }
  return $fileList;
}
?>

