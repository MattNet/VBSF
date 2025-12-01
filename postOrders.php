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
  echo "Processing Intel Phase for empire '{$empireName}'\n";

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
foreach ($gameFiles as $empireId => $file) {
  $empireName = $file->empire['empire'] ?? $empireId;
  $turn = intval($file->game['turn'] ?? 0);
  $file->events = $file->events ?? [];
  echo "Processing Move Phase for empire '{$empireName}'\n";

  $deferredWrites = [];      // holds ['empire'=>string,'changes'=>[...]]
  $movementErrors = [];     // collect pre-check errors
  $movementEvents = [];     // events produced by this phase
  $executionQueue = [];     // queue of validated actions to execute

  $movementOrders = array_values(array_filter($file->orders ?? [], function($o) {
    return in_array($o['type'] ?? '', ['move','explore_lane','load','unload','flight','start_trade','stop_trade','convoy_raid','long_range']);
  }));

  # VALIDATION PASS
  foreach ($movementOrders as $ordIdx => $ord) {
    $otype = $ord['type'] ?? '';
    $receiver = $ord['receiver'] ?? [];
    $target = $ord['target'] ?? [];
    $note = $ord['note'] ?? '';

    switch ($otype) {
    case 'move':
      $fleetName = $receiver;
      $fromLoc = $file->getFleetByName($fleetName)['location'];
      $toLoc = $target;

      if (!$fleetName || !$toLoc) {
        $movementErrors[] = "ERR_MOVE_BAD_PARAMS: Move order missing fleet or destination.";
        $file->events[] = [ 'event' => 'Movement Order Invalid','time' => $turn,
          'text'  => "Move order missing fleet or destination."];
        break;
      }
      if (!isset($file->getFleetByName($fleetName))) {
        $movementErrors[] = "ERR_MOVE_FLEET_UNKNOWN: Fleet '{$fleetName}' not found.";
        $file->events[] = ['event' => 'Movement Order Invalid', 'time' => $turn,
          'text'  => "Fleet '{$fleetName}' not found."];
        break;
      }

      $pathResult = $file->findPath($fromLoc, $toLoc);
      if (!$pathResult || empty($pathResult['path']) || !isset($pathResult['distance'])) {
        $movementErrors[] = "ERR_PATH_NOT_FOUND: Cannot reach {$toLoc} from {$fromLoc}.";
        $movementEvents[] = ['event' => 'Move Failed','time'  => $turn,
          'text'  => "Cannot reach {$toLoc} from {$fromLoc}."];
        break;
      }

      $isCivilianFleet = false;
      $hasEscort = false;
      foreach ($fleet['units'] ?? [] as $u) {
        [$q,$design] = $file->parseUnitQuantity($u);
        // fixed units cannot move
        if ($file->checkUnitNotes($unitdef, 'Fixed') !== false) {
          $file->events[] = ['event' => 'Movement Order Invalid', 'time' => $turn,
            'text'  => "Fleet '{$fleetName}' contains units that cannot move." ];
          break 2;
        }
        if ($file->checkUnitNotes($unitdef, 'Convoy') !== false || $file->checkUnitNotes($unitdef, 'Civilian') !== false)
          $isCivilianFleet = true;
        if ($file->atLeastShipSize($design,'CL')) $hasEscort = true;
      }

      // Extract path details
      $pathArray   = $pathResult['path'];
      $pathDistance = intval($pathResult['distance']);

      // Rule: VBAM multi-jump limit = 3 jumps max
      if ($pathDistance > 3) {
        $movementErrors[] = "ERR_TOO_FAR: Multi-jump path {$fromLoc} to {$toLoc} requires {$pathDistance} jumps (max 3).";
        $movementEvents[] = ['event' => 'Move Failed', 'time'  => $turn,
          'text'  => "{$pathDistance} jumps ({$toLoc} from {$fromLoc}) exceeds VBAM 3-jump movement limit."];
        break;
      }

      // All multi-move jumps must be via Major lanes
      // if a single hop, lane can be minor or restricted.
      // if a colony ship, cannot be restricted unless $file->atLeastShipSize({escort},'CL')
      $legalJump = true;
      for ($i = 0; $i < count($pathArray) - 1; $i++) {
        $a = $pathArray[$i];
        $b = $pathArray[$i+1];
        $laneStatus = $file->getLinkStatus($a, $b);
        // Multi-lane hop
        if (count($pathArray) > 1) {
          if ($laneStatus !== 'Major') {
            // multi-hop through Minor or Restricted is illegal
            $legalJump = false;
            $movementErrors[] = "Multi-jump path includes {$laneStatus} lane {$a} to {$b}.";
            break;
          }
          continue;
        }
        // Single hop through Minor or Major is always allowed.
        if ($laneStatus === 'Major' || $laneStatus === 'Minor')
          continue;
        // Single-hop through RESTRICTED: Military OK, Civilian (convoy) requires escort
        if ($laneStatus === 'Restricted') {
          // Military fleets may always traverse restricted lanes
          if (!$isCivilianFleet)
            continue;
          // If convoy lacks escort, movement is illegal
          if (!$hasEscort) {
            $legalJump = false;
            $movementErrors[] =
              "ERR_RESTRICTED_NO_ESCORT: Convoy cannot cross Restricted lane {$a} to {$b} without CL+ escort.";
            break;
          }
          // Escorted convoy is legal
          continue;
        }
      } // end foreach pathArray

      if (!$legalJump) {
        $movementErrors[] = "ERR_NON_MAJOR_LANE: Multi-jump path includes non-Major lanes ({$fromLoc} → {$toLoc}).";
        $file->events[] = ['event' => 'Move Failed','time'  => $turn,
          'text'  => "Path of {$toLoc} from {$fromLoc} uses at least one non-Major lane, multi-jump not allowed."];
        break;
      }

      // If we reach here, movement is allowed
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $fleetName,
        'from'  => $fromLoc,
        'to'    => $toLoc,
        'path'  => $pathArray,
        'order' => $ord
      ];
      break;
    case 'explore_lane':
      $fleetName = $receiver;
      $fromLoc = $file->getFleetByName($fleetName)['location'];
      if (!$fleetName) {
        $movementErrors[] = 'ERR_EXPLORE_BAD_PARAMS: Order receiver not given';
        break;
      }
      if (!$fromLoc) {
        $movementErrors[] = "ERR_EXPLORE_FLEET_UNKNOWN: {$fleetName}";
        break;
      }
###
// Need to check if there are any unexplored lanes in this system
      $linkStatus = $file->getLinkStatus($fromLoc,$toLoc);
      if ($linkStatus === false || $linkStatus !== 'Unexplored') {
        $movementEvents[] = ['event'=>'Explore Failed','time'=>$file->game['turn'] ?? '','text'=>"ERR_EXPLORE_NO_UNEXPLORED_LANE: {$fromLoc}"];
        break;
      }
###
      // Only scout fleets may explore
      $fleet = $file->getFleetByName($fleetName);
      $hasScout = false;
      foreach ($fleet['units'] ?? [] as $u) {
        [$q,$design] = $file->parseUnitQuantity($u);
        if ($file->checkUnitNotes($design, 'Scout') !== false) { $hasScout = true; break; }
      }
      if (!$hasScout) {
        $file->events[] = ['event'=>'Explore Failed','time'=>$turn,
                           'text'=>"{$fleetName} must have scout units to explore."];
        break;
      }
      // queue exploration (resolution later; may require die roll)
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $fleetName,
        'from'  => $fromLoc,
        'to'    => '',
        'path'  => '',
        'order' => $ord
      ];
      break;

    case 'load':
    case 'unload':
    case 'flight':
      // Manage basing / carriage orders.
      // For load/unload: receiver contains carrier fleet name; target contains unit name; note contains qty
      $carrierFleet = $receiver[0] ?? null;
      $unitName = $target ?? null;
      $unitQty = $note ?? 1;
      if (!$carrierFleet || !$unitName) {
        $movementEvents[] = ['event'=>'Load/Unload Failed','time'=>$turn,
                         'text'=>"Unknown carrier fleet or unit name in load/unload/flight order"];
        break;
      }
      if (!isset($file->getFleetByName($carrierFleet))) {
        $movementEvents[] = ['event'=>'Load/Unload Failed','time'=>$turn,
                        'text'=>"Carrier fleet '{$carrierFleet}' was not found in load/unload/flight order."];
        break;
      }
      // We will validate capacity at execution time using unitList entries.
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $carrierFleet,
        'from'  => $fromLoc,
        'to'    => $unitSpec,
        'path'  => $qty,
        'order' => $ord
      ];
      break;

    case 'start_trade':
    case 'stop_trade':
      // Convoys assigned to trade ledger - queue for execution
      $convoyFleet = $receiver[0] ?? null;
      if (!$convoyFleet || !isset($file->getFleetByName($convoyFleet))) {
        $movementEvents[] = ['event'=>'Trade Failed','time'=>$turn,
                        'text'=>"Convoy fleet '{$convoyFleet}' was not found in start/stop trade order."];
        break;
      }
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $convoyFleet,
        'from'  => '',
        'to'    => '',
        'path'  => '',
        'order' => $ord
      ];
      break;

    case 'convoy_raid':
      // receiver: raiding fleet name, target: system to raid
      $raider = $receiver[0] ?? null;
      $raidTarget = $target[0] ?? null;
      if (!$raider || !$raidTarget) {
        $movementEvents[] = ['event'=>'Raid Failed','time'=>$turn,
                         'text'=>"Unknown raiding fleet or raid target in convoy raid order"];
        break;
      }
      if (!isset($file->getFleetByName($raider))) {
        $movementEvents[] = ['event'=>'Raid Failed','time'=>$turn,
                         'text'=>"Raiding fleet '{$raider}' was not found in convoy raid order."];
        break;
      }
### TODO Ensure that the raiding fleet is adjacent to the target
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $raider,
        'from'  => '',
        'to'    => $raidTarget,
        'path'  => '',
        'order' => $ord
      ];
      break;

    case 'long_range':
      // Long range scan; queue and resolve (creates intel events)
      $scanner = $receiver[0] ?? null;
      $scanTarget = $target[0] ?? null;
      if (!$scanner || !$scanTarget) {
        $movementEvents[] = ['event'=>'Scan Failed','time'=>$turn,
                         'text'=>"Unknown scanning fleet or scan target in long-range order"];
        break;
      }
      $executionQueue[] = [
        'type'  => $otype,
        'fleet' => $scanner,
        'from'  => '',
        'to'    => $scanTarget,
        'path'  => '',
        'order' => $ord
      ];
      break;

    default:
      // ignore unknown movement orders
      break;
  } // end switch
} // end pre-validation pass

/*
  === SUB-PHASE: EXECUTION PASS ===
  Execute in conservative, traceable order:
    1) explore_lane
    2) move
    3) load/unload/flight
    4) convoy_raid
    5) start_trade/stop_trade
    6) long_range
*/

usort($executionQueue, function($a,$b){
  $priority = ['explore_lane'=>10,'move'=>20,'load'=>30,'unload'=>30,'flight'=>30,'convoy_raid'=>40,'start_trade'=>50,'stop_trade'=>50,'long_range'=>60];
  return ($priority[$a['type']] ?? 99) <=> ($priority[$b['type']] ?? 99);
});

foreach ($executionQueue as $action) {
  switch ($action['type']) {
    case 'explore_lane':
      $fleetName = $action['fleet'];
      $from = $action['from'];
      $to = $action['to'];
      $roll = $file->rollDie();
    // NOT IMPLEMENTED: Empire Trait modifiers to roll. (HOOK)
      $modified = $roll; // placeholder for any modifiers
      $successThreshold = 8; // conservative default; real table could differ (rulebook references)
      if ($modified >= $successThreshold) {
        // convert lane from Unexplored to Restricted
        foreach ($file->mapConnections as $mi => $conn) {
          [$a,$b,$status] = $conn;
          if ((($a === $from && $b === $to) || ($a === $to && $b === $from)) && $status === 'Unexplored') {
            $file->mapConnections[$mi][2] = 'Restricted';
            $movementEvents[] = [ 'event'=>'Explore Success','time'=>$turn,
              'text'=>"{$fleetName} explored {$from}<->{$to} (roll={$roll}). The lane is now 'Restricted'."
            ];
            break;
          }
        }
        // also move fleet across the lane (explore moves them)
        foreach ($file->fleets as $fi => $fleet) {
          if ($fleet['name'] !== $fleetName) continue;
          $file->fleets[$fi]['location'] = $to;
          break;
        }
      } else {
        $movementEvents[] = [ 'event'=>'Explore Fail', 'time'=>$turn,
          'text'=>"{$action['fleet']} failed exploration from {$from} to {$to} (roll={$roll})."
        ];
      }
      break;

    case 'move':
      $fleetName = $action['fleet'];
      $old = $fleet['location'] ?? '';
      $dest = $action['to'];
      // Move fleet: update location in current sheet
      foreach ($file->fleets as $fi => $fleet) {
        if ($fleet['name'] !== $fleetName) continue;
        $file->fleets[$fi]['location'] = $dest;
        break;
      }
      $movementEvents[] = [
        'event'=>'Fleet Moved', 'time'=>$turn,
        'text'=>"{$fleetName} moved from {$old} to {$dest}. Path: " . implode(' -> ', $action['path'])
      ];

      // If movement may affect other empires (e.g., entering enemy system) prepare deferred write: create encounter flag
      // Defer adding a Combat Phase encounter entry to other empires' sheets by recording intent here.
      // We record a deferred write event for any other empire with colonies/units at $dest to be processed after writes.
      foreach ($file->otherEmpires as $otherEmpireName) {
        // Add a deferred change to notify other empire of encounter (they'll create a Combat encounter entry in their sheet).
        $deferredWrites[] = [
          'empire'=>$otherEmpireName,
          'changes'=>[
            [
              'action'=>'flag_encounter',
              'system'=>$dest,
              'intruderFleet'=>$fleetName,
              'intruderEmpire'=>$file->empire['empire']
            ]
          ],
          'reason'=>"Intruder {$fleetName} moved into {$dest}"
        ];
      }
      break;

    case 'load':
      $carrierFleet = $action['fleet'];
      $unitSpec = $action['unit'];
      // Determine where units currently are: try colony at carrier location first, then other fleets
      $carrierIdx = $file->getFleetByName($carrierFleet);
      $carrierLoc = $carrierIdx['location'];
      $unitFound = false;
      // Attempt to remove from colony fixed units
      $col = $file->getColonyByName($carrierLoc);
      if ($col) {
        // Try removing from colony fixed list via GameData->removeUnitsFromColony if defined
        $removed = $file->removeUnitsFromColony($carrierLoc, $unitSpec);
        if ($removed) $unitFound = true;
      }
      // If unit not found at colony, attempt to remove from other fleets at same location
      if (!$unitFound) {
        foreach ($file->fleets as $fi => $f) {
          if ($f['location'] !== $carrierLoc) continue;
          if ($f['name'] === $carrierFleet) continue;
          $removed = $file->removeUnitsFromFleet($f['name'],$unitSpec);
          if ($removed) { $unitFound = true; break; }
        }
      }
      if (!$unitFound) { // if still not found after looking at colonies...
        $movementEvents[] = ['event'=>'Load Failed','time'=>$file->game['turn'] ?? '','text'=>"ERR_LOAD_NO_UNIT: Could not find {$unitSpec} near {$carrierFleet} to load."];
        break;
      }
      // Add to carrier fleet
      $file->addUnitsToFleet($carrierFleet, $unitSpec);
      break;

    case 'unload':
      $carrierFleet = $action['fleet'];
      $unitSpec = $action['unit'];
      $carrierIdx = $file->getFleetByName($carrierFleet);
      $loc = $carrierIdx['location'];
      // Remove from fleet
      $removed = $file->removeUnitsFromFleet($carrierFleet, $unitSpec);
      if (!$removed) {
        $errors[] = "ERR_UNLOAD_NOT_ON_FLEET: {$unitSpec} not on {$carrierFleet}.";
        break;
      }
      // Add to colony fixed at location
      $file->addUnitsToColony($loc, $unitSpec);
      break;

    case 'flight':
      // Assign fighters to basing ships/bases in fleet/colony. For simplicity we record an event,
      // a full bay-count enforcement is performed here in conservative fashion.
      $fleetName = $action['fleet'];
      $unitSpec = $action['unit'];
      // We expect unitSpec to be a fighter design or a list; simply ensure carrier has capacity.
      $carrierIdx = $file->getFleetByName($fleetName);
      $carrierUnits = $file->fleets[$carrierIdx]['units'] ?? [];
      // Count carrier bay capacity by summing Carrier(X) notes on fleet units
      $totalBays = 0;
      foreach ($carrierUnits as $u) {
        [$q,$design] = $file->parseUnitQuantity($u);
        $ud = $file->getUnitByName($design);
        if ($ud && preg_match('/Carrier\((\d+)\)/i', $ud['notes'] ?? '', $m)) {
          $totalBays += intval($m[1]) * $q;
        }
      }
      // Count currently based fighters in this fleet
      $fighterCount = 0;
      foreach ($carrierUnits as $u) {
        [$q,$design] = $file->parseUnitQuantity($u);
        $ud = $file->getUnitByName($design);
        if ($ud && in_array($ud['design'] ?? '', ['LF','HF','SHF'], true)) $fighterCount += $q;
      }
      // Desired load quantity
      [$wantQty,$wantDesign] = $file->parseUnitQuantity($unitSpec);
      if (($fighterCount + $wantQty) > $totalBays) {
        $movementEvents[] = ['event'=>'Flight Failed','time'=>$file->game['turn'] ?? '','text'=>"ERR_FLIGHT_CAPACITY_EXCEEDED: {$fleetName} capacity {$totalBays}, requested additional {$wantQty}."];
        break;
      }
      // Otherwise add fighters to fleet (assumes they were already removed from colony/fleet by load)
      if (method_exists($file,'addUnitsToFleet')) $file->addUnitsToFleet($fleetName, $unitSpec);
      else $file->fleets[$carrierIdx]['units'][] = $unitSpec;
      $movementEvents[] = ['event'=>'Flight OK','time'=>$file->game['turn'] ?? '','text'=>"FLIGHT_OK: Added {$unitSpec} to {$fleetName}."];
      break;

    case 'convoy_raid':
      $raider = $action['fleet'];
      $targetSystem = $action['target'];
      // Find convoys in target system across all empires (scan current file and schedule deferred check for other empires)
      $convoysFound = [];
      // This empire's convoys:
      foreach ($file->fleets as $f) {
        if ($f['location'] === $targetSystem) {
          foreach ($f['units'] ?? [] as $u) {
            [$q,$d] = $file->parseUnitQuantity($u);
            $unitDef = $file->getUnitByName($d) ?? null;
            if ($unitDef && stripos($unitDef['notes'] ?? '', 'Convoy') !== false)
              $convoysFound[] = ['empire'=>$file->empire['empire'],'fleet'=>$f['name'],'qty'=>$q];
          }
        }
      }
      // Add deferred writes to other empires so they can check and add combat entries if their convoys are targeted
      foreach ($file->otherEmpires as $other) {
        $deferredWrites[] = [
          'empire'=>$other,
          'changes'=>[
            ['action'=>'check_convoys_for_raid','system'=>$targetSystem,'raider'=>$raider,'raider_empire'=>$file->empire['empire']]
          ],
          'reason'=>"Convoy raid by {$raider} at {$targetSystem}"
        ];
      }
      // For our own sheet, record event of raid attempt
      $movementEvents[] = ['event'=>'Convoy Raid','time'=>$turn,
                       'text'=>"{$raider} attempted raid at {$targetSystem}. Deferred checks created for other empires."];
      break;

    case 'start_trade':
      $convoyFleet = $action['fleet'];
      $fi = $file->getFleetByName($convoyFleet);
      // Mark location to "Trade" and set notes as route if provided
      $fi['location'] = 'Trade';
      $fi['notes'] = $action['order']['note'] ?? ($file->fleets[$fi]['notes'] ?? '');
      $movementEvents[] = ['event'=>'Trade Start','time'=>$turn,
                       'text'=>"{$convoyFleet} entered Trade service."];
      break;

    case 'stop_trade':
      $convoyFleet = $action['fleet'];
      $fi = $file->getFleetByName($convoyFleet);
      // Remove from Trade; place at one of the route colonies if provided else leave at 'Trade' but mark event
      $routeNote = $fi['notes'] ?? '';
      $dest = 'Unknown';
      if (!empty($routeNote)) {
        $parts = array_map('trim', explode(',', $routeNote));
        $dest = $parts[0] ?? 'Unknown';
      }
      $fi['location'] = $dest;
      if ($dest == "Unknown")
        $errors[] = "Unknown destination to stop trade of fleet $convoyFleet, fleet notes {$parts[0]}";
      $movementEvents[] = ['event'=>'Trade Stop','time'=>$turn,
                       'text'=>"TRADE_STOP: {$convoyFleet} left Trade and placed at {$dest}."];
      break;

    case 'long_range':
      // Create intel event for player
      $scanner = $action['fleet'];
      $scanTarget = $action['target'];
      // For correctness, simulate a scan success roll or deterministic reveal depending on presence.
      $movementEvents[] = [
        'event'=>'LongRangeScan',
        'time'=>$file->game['turn'] ?? '',
        'text'=>"LONG_RANGE: {$scanner} performed long-range scan of {$scanTarget}."
      ];
      // Potentially add deferred writes to owners of scanTarget (inform them they were scanned) if needed:
      foreach ($file->otherEmpires as $other) {
        $deferredWrites[] = [
          'empire'=>$other,
          'changes'=>[['action'=>'notify_scanned','system'=>$scanTarget,'scanner'=>$scanner,'by'=>$file->empire['empire']]],
          'reason'=>"Long range scan by {$scanner} at {$scanTarget}"
        ];
      }
      break;

    default:
      // Unknown action — ignore but record event
      $movementEvents[] = ['event'=>'Movement Unknown','time'=>$file->game['turn'] ?? '','text'=>"IGNORED_ACTION: " . json_encode($action)];
      break;
  } // end switch action
} // end executionQueue loop

/*
  === SUB-PHASE: POST-MOVE VALIDATIONS ===
  - Ensure carried units remain legal (fighters vs carriers, troop/garrison limits).
  - Flag Out-of-Supply candidates (Supply Phase will handle final marking).
  - Record where Empire Traits would adjust results (NOT IMPLEMENTED).
*/
/*
foreach ($file->fleets as $fi => $fleet) {
  // Fighters must be based to carriers/tenders; if not, attempt to unbase to colony
  $fighterCount = 0;
  $carrierBays = 0;
  foreach ($fleet['units'] as $u) {
    [$q,$d] = $file->parseUnitQuantity($u);
    $ud = $file->getUnitByName($d);
    if ($ud && in_array($ud['design'] ?? '', ['LF','HF','SHF'], true)) $fighterCount += $q;
    if ($ud && preg_match('/Carrier\((\d+)\)/i', $ud['notes'] ?? '', $m)) $carrierBays += intval($m[1]) * $q;
  }
  if ($fighterCount > $carrierBays) {
    // Attempt to move excess fighters to colony at fleet's location
    $excess = $fighterCount - $carrierBays;
    $moved = 0;
    foreach ($file->fleets[$fi]['units'] as $uIdx => $uVal) {
      [$q,$d] = $file->parseUnitQuantity($uVal);
      $ud = $file->getUnitByName($d);
      if ($ud && in_array($ud['design'] ?? '', ['LF','HF','SHF'], true)) {
        $moveQty = min($excess - $moved, $q);
        $unitStr = ($moveQty > 1 ? "{$moveQty}x{$d}" : $d);
        // remove from fleet
        $file->removeUnitsFromFleet($fleet['name'],$unitStr);
        // add to colony
        $file->addUnitsToColony($fleet['location'],$unitStr);
        $moved += $moveQty;
        $movementEvents[] = [
          'event'=>'Unbased Fighters',
          'time'=>$file->game['turn'] ?? '',
          'text'=>"UNBASE: {$moveQty} {$d} moved from fleet {$fleet['name']} to colony {$fleet['location']} due to insufficient carrier bays."
        ];
        if ($moved >= $excess) break;
      }
    }
  }

  // Garrison checks: if troops are unloaded to colony beyond population or capacity, record event
  // NOTE: Empire Traits that change garrison limits would be applied here. NOT IMPLEMENTED.
  $col = $file->getColonyByName($fleet['location']);
  if ($col) {
    $garrisonUnits = array_filter($col['fixed'] ?? [], function($u) use ($file){ [$q,$d]=$file->parseUnitQuantity($u); $ud=$file->getUnitByName($d); return ($ud && ($ud['design'] ?? '') === 'Ground Unit');});
    $garrisonCount = 0;
    foreach ($garrisonUnits as $g) { [$q,$d]=$file->parseUnitQuantity($g); $garrisonCount += $q; }
    if ($garrisonCount > intval($col['population'])) {
      $movementEvents[] = [
        'event'=>'Garrison Overflow',
        'time'=>$file->game['turn'] ?? '',
        'text'=>"GARRISON_OVERFLOW: Colony {$col['name']} has {$garrisonCount} troops vs population {$col['population']}. Excess troops must be trimmed (not automated)."
      ];
    }
  }
}

// Append movement events and any errors to $file->events for traceability
if (!empty($movementEvents)) {
  foreach ($movementEvents as $ev) $file->events[] = $ev;
}
if (!empty($movementErrors)) {
  foreach ($movementErrors as $errTxt) $file->events[] = ['event'=>'Movement Error','time'=>$file->game['turn'] ?? '','text'=>$errTxt];
}

// Save current data sheet now that local modifications are complete
$file->writeToFile();
$errors = array_merge($errors, $file->getErrors());
*/
/*
  Apply deferred writes to other empires' data sheets AFTER current sheet saved.
  - We find the matching GameData object in $gameFiles and apply the recorded
    'changes' inline, then save that sheet.
  - Each deferred change is an associative array describing the action.
*/
/*
if (!empty($deferredWrites)) {
  foreach ($deferredWrites as $dw) {
    $targetEmpire = $dw['empire'] ?? null;
    $changes = $dw['changes'] ?? [];
    // locate target GameData in $gameFiles
    foreach ($gameFiles as $otherFile) {
      if (($otherFile->empire['empire'] ?? '') !== $targetEmpire) continue;
      // apply each change
      foreach ($changes as $chg) {
        switch ($chg['action'] ?? '') {
          case 'flag_encounter':
            $otherFile->events[] = [
              'event'=>'Encounter Flagged',
              'time'=>$otherFile->game['turn'] ?? '',
              'text'=>"ENCOUNTER_FLAG: Intruder {$chg['intruderFleet']} ({$chg['intruderEmpire']}) moved into {$chg['system']}. Combat will be resolved in Combat Phase."
            ];
            break;
          case 'check_convoys_for_raid':
            // If this otherFile has convoys at system, flag a convoy raid encounter
            $sys = $chg['system'];
            $foundConvoys = [];
            foreach ($otherFile->fleets as $ff) {
              if ($ff['location'] !== $sys) continue;
              foreach ($ff['units'] ?? [] as $u) {
                [$q,$d] = $otherFile->parseUnitQuantity($u);
                $ud = $otherFile->getUnitByName($d);
                if ($ud && stripos($ud['notes'] ?? '', 'Convoy') !== false) {
                  $foundConvoys[] = $ff['name'];
                }
              }
            }
            if (empty($foundConvoys)) {
              $otherFile->events[] = [
                'event'=>'Convoy Raid Failed',
                'time'=>$otherFile->game['turn'] ?? '',
                'text'=>"CONVOY_RAID_NONE: Raider {$chg['raider']} attempted raid at {$sys} but found no convoys."
              ];
            } else {
              // Flag for Combat Phase / convoy raid resolution
              $otherFile->events[] = [
                'event'=>'Convoy Raid Alert',
                'time'=>$otherFile->game['turn'] ?? '',
                'text'=>"CONVOY_RAID_ALERT: {$chg['raider']} ({$chg['raider_empire']}) is raiding {$sys}. Convoys present: " . implode(', ',$foundConvoys)
              ];
            }
            break;
          case 'notify_scanned':
            $otherFile->events[] = [
              'event'=>'Scanned',
              'time'=>$otherFile->game['turn'] ?? '',
              'text'=>"SCANNED: {$chg['scanner']} ({$file->empire['empire']}) scanned {$chg['system']}."
            ];
            break;
          default:
            // generic log
            $otherFile->events[] = [
              'event'=>'Deferred Write',
              'time'=>$otherFile->game['turn'] ?? '',
              'text'=>"DEFERRED: {$dw['reason']} (raw: " . json_encode($chg) . ")"
            ];
            break;
        }
      } // end foreach change
      // save the other empire's sheet after applying all changes for this deferred write entry
      $otherFile->writeToFile();
      $errors = array_merge($errors, $otherFile->getErrors());
      break; // matched target empire; proceed to next deferred write
    } // end search for otherFile
  } // end foreach deferredWrites
} // end if deferredWrites
*/

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
  global $errors;
  switch (strtolower($mission)) {
  case "civilian":
    break;
  case "counter-insurgency":
    break;
  case "counterintel":
    break;
  case "espionage":
    break;
  case "fortification":
    break;
  case "industrial":
    break;
  case "insurgency":
    break;
  case "piracy":
    break;
  case "population":
    break;
  case "sabotage":
    break;
  case "tech":
    break;
  default:
    $errors[] = "Covert operation '{$mission}' does not exist. Unable to perform.";
    break;
  }
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

