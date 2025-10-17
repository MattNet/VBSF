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
# Scan a directory for data files, attempt to extract the 'game' object and 'turn'
# number from each file, and collect files that match a given game name and turn.
# Data files are in $gameFiles[]
###
// Validate search dir
if (!is_dir($searchDir)) {
  $errors[] = "Error: search directory '{$searchDir}' does not exist or is not a directory.\n";
  showErrors($errors);
}
$dir = scandir($searchDir);
// Examine each file. Keep the ones that match the criteria
foreach ($dir as $file) {
  if (strpos($file, '.') === 0) continue; // skip hidden files
  if (strpos($file, '.js', -3) === false) continue; // file must end in '.js'

  $fileCheck = new GameData($searchDir.$file);
  if ($fileCheck->game["game"] != $targetGame) continue;
  if ($fileCheck->game["turn"] != $targetTurn) continue;

  $gameFiles[] = $fileCheck; // Keep file. It passed our tests
  unset($fileCheck); // unload the game object if un-needed
}

#####
#
# Process Results
#
#####

###
# Supply Phase
###

###
# Construction Phase
###

###
# Tech Phase
###

###
# End Of Turn Phase
###
foreach ($gameFiles as $empireId => $file) {
  $empireName = $file->empire['empire'] ?? $empireId;
  $turn = intval($file->game['turn'] ?? 0);
  $file->events = $file->events ?? [];

  // System Improvements
  // Orders: type == "imp_capacity", "imp_pop", "imp_intel", "imp_fort"
  // Costs & limits: see VBAM 4.10.1 (capacity/pop cost formula and caps).

  $improvementOrders = array_filter($file->orders ?? [], function($o){
     return in_array($o['type'] ?? '', ['imp_capacity','imp_pop','imp_intel','imp_fort']);
  });
  if (!empty($improvementOrders)) {
    foreach ($improvementOrders as $ord) {
      $colonyName = $ord['receiver'][0] ?? null;
      if (!$colonyName) continue;
      $targetColony = $file->getColonyByName($colonyName);
      if (!$targetColony) {
        $errors[] = "System improvement order: colony '{$colonyName}' not found.";
        continue;
      }

      // Calculate cost & check constraints
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      switch ($ord['type']) {
        case 'imp_capacity':
          // New capacity = current + 1; cost = 10 * new capacity
          $newCap = intval($targetColony['capacity']) + 1;
          $cost = 10 * $newCap;

          // Cost check
          if ($availableFunds - $cost < 0) {
            $errors[] = "{$colonyName} attempted to increase Capacity to {$newCap}. ".
                        "Could not afford. Cost is {$cost} and had {$availableFunds}";
            break;
          }

          // Document the change
          $file->purchases[] = ["cost"=>$cost,"name"=>"Improve Cap @ {$colonyName}"];
          $targetColony['capacity'] = $newCap;

          // roll 1d10 and on 8+ RAW increases by 1
          $roll = rand(1,10);
          if ($roll >= 8) {
            $targetColony['raw'] = max(1, intval($targetColony['raw']) + 1);
            $file->events[] = ["event"=>'System Improvement', "turn"=>$turn,
               "text"=>"{$colonyName} increases Capacity to {$newCap}. (+1 RAW on roll {$roll})"];
          } else {
            $file->events[] = ["event"=>'System Improvement', "turn"=>$turn,
               "text"=>"{$colonyName} increases Capacity to {$newCap}."];
          }
          break;
        case 'imp_pop':
          // New pop = current + 1; cost = 10 * new population
          $newPop = intval($targetColony['population']) + 1;
          $cost = 10 * $newPop;

          // Capacity check
          if ($newPop > intval($targetColony['capacity'])) {
            $errors[] = "{$colonyName} attempted to increase Population to {$newPop}. ".
                        "Not enough capacity ({$targetColony['capacity']}) for the new population.";
            break;
          }
          // Cost check
          if ($availableFunds - $cost < 0) {
            $errors[] = "{$colonyName} attempted to increase Population to {$newPop}. ".
                        "Could not afford. Cost is {$cost} and had {$availableFunds}";
            break;
          }

          // Document the change
          $file->purchases[] = ["cost"=>$cost,"name"=>"Improve Pop @ {$colonyName}"];
          $targetColony['population'] = $newPop;
          $targetColony['morale'] += 1;
          break;
        case 'imp_intel':
          // New intel = current + 1; cost = 5 * new intel
          $newIntel = intval($targetColony['intel']) + 1;
          $cost = 5 * $newIntel;

          // Capacity check
          if ($newIntel > intval($targetColony['intel'])) {
            $errors[] = "{$colonyName} attempted to increase Intel to {$newIntel}. ".
                        "Not enough capacity ({$targetColony['capacity']}) for the new intel.";
            break;
          }
          // Cost check
          if ($availableFunds - $cost < 0) {
            $errors[] = "{$colonyName} attempted to increase Intel to {$newIntel}. ".
                        "Could not afford. Cost is {$cost} and had {$availableFunds}";
            break;
          }

          // Document the change
          $file->purchases[] = ["cost"=>$cost,"name"=>"Improve Intel @ {$colonyName}"];
          $targetColony['intel'] = $newPop;
          break;
        case 'imp_fort':
          // New fort = current + 1; cost = 5 * new fort
          $newFort = intval($targetColony['fort']) + 1;
          $cost = 5 * $newFort;

          // Capacity check
          if ($newFort > intval($targetColony['fort'])) {
            $errors[] = "{$colonyName} attempted to increase Fort to {$newFort}. ".
                        "Not enough capacity ({$targetColony['capacity']}) for the new fort.";
            break;
          }
          // Cost check
          if ($availableFunds - $cost < 0) {
            $errors[] = "{$colonyName} attempted to increase Fort to {$newFort}. ".
                        "Could not afford. Cost is {$cost} and had {$availableFunds}";
            break;
          }

          // Document the change
          $file->purchases[] = ["cost"=>$cost,"name"=>"Improve Fort @ {$colonyName}"];
          $targetColony['fort'] = $newFort;
          break;
        default:
          // ignore unknown
          break;
        } // end switch
      } // end foreach improvement orders
    } // end if improvements


    // Jump Lane Upgrades
    // Orders of type "upgrade_lane" (receiver: from, target: to)
    $upgradeOrders = array_filter($file->orders ?? [], function($o){
            return ($o['type'] ?? '') === 'upgrade_lane';
    });
    if (!empty($upgradeOrders)) {
      foreach ($upgradeOrders as $ord) {
        $from = $ord['receiver'][0] ?? null;
        $to = $ord['target'][0] ?? null;
        if (!$from) continue;
        if (!$to) continue;
        // search file->mapConnections array for [from,to] unordered pair
        foreach ($file->mapConnections as $idx=>&$conn) {
            if ((($conn[0] === $from && $conn[1] === $to) || ($conn[0] === $to && $conn[1] === $from))) {
                // conn format per spec: [from, to, status]
                $lane = ['from'=>$conn[0],'to'=>$conn[1],'status'=>$conn[2],'index'=>$idx];
                unset($conn);
                break;
            }
        }
        if (!isset($lane)) {
          $errors[] = "Upgrade lane failed: connection {$from} <-> {$to} unknown.";
          continue;
        }

        // Allowed status: Unexplored, Restricted, Minor, Major
        // Unexplored is upgraded to restricted by exploration. Majors cannot be further updgraded
        $statusOrder = ['Unexplored', 'Restricted', 'Minor', 'Major'];
        $laneIdx = array_search($lane['status'], $statusOrder, true);
        // Fail if lane status is unknown or is Unexplored or is Major
        if ($laneIdx === false || $laneIdx === 0 || $laneIdx > 2) {
          $errors[] = "Cannot upgrade lane {$from}<->{$to} from {$lane['status']}.";
          continue;
        }

### TODO: Determine cost to upgrade
### TODO: Determine scout placement, etc
        $cost = 0;
###
        if ($availableFunds - $cost < 0) {
          $errors[] = "Insufficient EP to upgrade lane {$from}<->{$to}. Cost is {$cost} and had {$availableFunds}.";
          continue;
        }

        // Document the change
        $file->purchases[] = ["cost"=>$cost,"name"=>"Upgrade {$lane['status']} lane {$from} to {$to}."];
        $file->mapConnections[$lane['index']][2] = $statusOrder[$laneIdx+1];

      } // end foreach upgrade orders
    } // end if upgrades

    // Jump Lane Downgrades
    // Orders of type "downgrade_lane" (receiver/from, target/to)
    // Downgrades are similar but step lane down one level (Major -> Minor -> Restricted -> Unexplored)
    $downgradeOrders = array_filter($file->orders ?? [], function($o){
       return ($o['type'] ?? '') === 'downgrade_lane';
    });
    if (!empty($downgradeOrders)) {
      foreach ($downgradeOrders as $ord) {
        $from = $ord['receiver'][0] ?? null;
        $to = $ord['target'][0] ?? null;
        if (!$from) continue;
        if (!$to) continue;
        // search file->mapConnections array for [from,to] unordered pair
        foreach ($file->mapConnections as $idx=>&$conn) {
            if ((($conn[0] === $from && $conn[1] === $to) || ($conn[0] === $to && $conn[1] === $from))) {
                // conn format per spec: [from, to, status]
                $lane = ['from'=>$conn[0],'to'=>$conn[1],'status'=>$conn[2],'index'=>$idx];
                unset($conn);
                break;
            }
        }
        if (!isset($lane)) {
          $errors[] = "Downgrade lane failed: connection {$from} <-> {$to} unknown.";
          continue;
        }

        // Allowed status: Unexplored, Restricted, Minor, Major
        // Unexplored is upgraded to restricted by exploration. Majors cannot be further updgraded
        $statusOrder = ['Unexplored', 'Restricted', 'Minor', 'Major'];
        $laneIdx = array_search($lane['status'], $statusOrder, true);
        // Fail if lane status is unknown or is Unexplored or is Major
        if ($laneIdx === false || $laneIdx === 0 || $laneIdx < 2) {
          $errors[] = "Cannot upgrade lane {$from}<->{$to} from {$lane['status']}.";
          continue;
        }

### TODO: Determine limitations

        $cost = 30;
        if ($availableFunds - $cost < 0) {
          $errors[] = "Insufficient EP to downgrade lane {$from}<->{$to}. Cost is {$cost} and had {$availableFunds}.";
          continue;
        }

        // Document the change
        $file->purchases[] = ["cost"=>$cost,"name"=>"Downgrade {$lane['status']} lane {$from} to {$to}."];
        $file->mapConnections[$lane['index']][2] = $statusOrder[$laneIdx-1];

      } // end foreach downgrade orders
    } // end if downgrades

    // Morale & System Loyalty
    // - Determine Good Order vs Opposition
    // - Then perform Morale Checks for: systems in Opposition OR systems with any enemy units present (VBAM 4.10.4.4).
    // - Apply modifiers: Good Order, Frontier world, martial law, bombardment, blockaded, empire traits, full garrison, etc. See table.
    foreach ($file->colonies as &$colony) {
      // compute Good Order vs Opposition
      $pop = intval($colony['population']);
      $morale = intval($colony['morale']);
      $inGoodOrder = ($morale >= ceil($pop / 2));
      $previousNotes = $colony['notes'] ?? '';

      // set notes accordingly
      if ($inGoodOrder) {
        // remove 'Opposition' string if present
        $colony['notes'] = str_replace('Opposition','', $colony['notes'] ?? '');
      } else if (strpos($colonies['notes'] ?? '', 'Opposition') === false) {
        // ensure Opposition note present
        $colony['notes'] .= (strlen($colony['notes']) > 2 ? ', ': '') . 'Opposition';
      }

      // decide whether to roll a Morale Check:
      // Morale Checks are required if the system is in Opposition OR if any enemy units are currently present.
### TODO
//      $enemyPresent = checkEnemyUnitsInSystem($file, $colonies['name']); // implement per CM knowledge of fleets
//      $requiresMoraleCheck = (!$inGoodOrder) || $enemyPresent;
      $requiresMoraleCheck = (!$inGoodOrder);

      if ($requiresMoraleCheck) {
        // Compose modifiers
        $mod = 0;
        // +1 Frontier World (Population 3 or less)
        if ($pop <= 3) $mod += 1;
        // +1 Good Order (only applies if currently Good Order)
        if ($inGoodOrder) $mod += 1;
        // +1 Full Garrison (# friendly troops >= Population)
### TODO
//        if (hasFullGarrison($file, $col)) $mod += 1;
        // -1 Orbital Bombardment this turn? (need to check per-turn flags)
### TODO
//        if (isset($colony['flags']['orbital_bombarded']) && $colonies['flags']['orbital_bombarded']) $mod -= 1;
        // -1 Economic Disruptions? (if flagged)
### TODO
//        if (isset($colony['flags']['econ_disrupted']) && $colonies['flags']['econ_disrupted']) $mod -= 1;
        // -1 System Blockaded
        if (isset($colony['notes']) && strpos($colony['notes'],'Blockaded') !== false) $mod -= 1;
        // -1 Martial Law
        if (isset($colony['notes']) && strpos($colony['notes'],'Martial Law') !== false) $mod -= 1;
        // Empire trait modifiers (Steadfast, Quarrelsome)
        # Un-implemented

        // Roll d10 and look up Morale Check Table
        $roll = rand(1,10);
        $rollTotal = $roll + $mod;
        $oldMorale = $colony['morale'];
        // returns -2,-1,0,1,2 per VBAM table & modifiers
        if ($rollTotal <= 0) $delta = -2;
        elseif ($rollTotal <= 3) $delta = -1;
        elseif ($rollTotal <= 7) $delta = 0;
        elseif ($rollTotal <= 10) $delta = 1;
        else $delta = 2; // 11+

        // no higher than capacity, no lower than 0
        $colony['morale'] = min(intval($colony['capacity']), intval($colony['morale']) + $delta);
        $colony['morale'] = max(0, $colony['morale']);

        // Re-check opposition
        if ($morale >= ceil($pop / 2)) {
          // remove 'Opposition' string if present
          $colony['notes'] = str_replace('Opposition','', $colony['notes'] ?? '');
        } else if (strpos($colonies['notes'] ?? '', 'Opposition') === false) {
          // ensure Opposition note present
          $colony['notes'] .= (strlen($colony['notes']) > 2 ? ', ': '') . 'Opposition';
        }

        $file->events[] = eventEntry('Morale Check', $turn,
          "{$colonies['name']}: Rolled {$roll} + mod {$mod} = {$rollTotal} => Morale change {$delta} (from {$oldMorale} to {$colonies['morale']}).");
        } // end morale check

      // 4.10.5 Rebellion
      // - Any system with Morale == 0 is checked for Rebellion (VBAM 4.10.5).
      // - The rules provide a die roll mechanism (apply rebellion modifiers such as martial law penalty, etc).
      // - If rebellion occurs, place Rebel troops/units or mark Rebellion note and set system to contested.
        if (intval($colony['morale']) === 0) {
          // Do Rebellion check
          $mod = 0;
          // martial law makes rebellion more likely. VBAM notes: Martial Law gives -1 to morale checks and rebellion rolls.
          if (strpos($colony['notes'] ?? '', 'Martial Law') !== false) $mod -= 1;
          // roll d10: 1-3 => rebellion
          $roll = rand(1,10);
          $rollTotal = $roll + $mod;
          $rebellionOccurs = ($rollTotal <= 3);
          if ($rebellionOccurs) {
            // Place rebellion: add "Rebellion" to notes and spawn Rebel troops (example: 1d10 EP of raider units or fixed troops)
            $colony['notes'] = (strlen($colony['notes']) > 2 ? ', ': '') . ' Rebellion';
            // Example: spawn Raiders worth 3d10 EP (VBAM random events used similar values). Adapt per 4.10.5 rules.
            $reRoll = rand(1,10) + rand(1,10) + rand(1,10); // 3d10 EP -> convert to units via CM rules
            $file->events[] = ['event'=>"Rebellion in {$colony['name']}", 'turn'=>$turn,
                   'text'=>"{$colonies['name']}: Rebellion occurred (roll {$roll} + mod {$rebMod} = {$rollTotal}). Rebel force magnitude ~{$reRoll} EP (CM convert to units)."];
          } else {
            $file->events[] = ['event'=>'Rebellion Check', 'turn'=>$turn,
                   'text'=>"{$colony['name']}: Rebellion check failed (roll {$roll} + mod {$rebMod} = {$rollTotal})."];
          }
        } // end rebellion check
      unset($colony);
    } // end foreach colony

    // Colonizing a System
    // - Convoys that were ordered to colonize this turn are dismantled and new colony entries are created in uninhabited systems.
    // - Orders: type == "colonize"
    $colonizeOrders = array_filter($file->orders ?? [], function($o){
      return ($o['type'] ?? '') === 'colonize';
    });
    if (!empty($colonizeOrders)) {
      foreach ($colonizeOrders as $ord) {
        $convoyLocation = $ord['receiver'][0] ?? null;
        if (!$convoyLocation) continue;
        // Find the convoy fleet and validate it's currently in an uninhabited system
        $convoyFleet = $file->getFleetByLocation($convoyLocation);
        if (!isset($convoyFleet)) {
          $errors[] = "Colonize failed: convoy at '{$convoyLocation}' not found.";
           continue;
        }
        $targetColony = $file->findColonyByName($convoyLocation);
        // Only colonize if target is uninhabited (population == 0 and owner == "")
        if ($targetColony && intval($targetColony['population']) === 0 && empty($targetColony['owner'])) {
          // Dismantle convoy - Mark as destroyed
          $file->unitStates[] = ["Convoy w/ {$convoyFleet['name']}","Destroyed"];
          // Create new colony entry or set owner/population etc.
          $targetColony['owner'] = $empireName;
          $targetColony['population'] = 1;
          $targetColony['morale'] = 1;
### TODO: add to traits from system specials
          $file->events[] = eventEntry('Colonize', $turn,
                     "{$empireName} colonized {$targetColony['name']}. Convoy {$convoyFleet['name']} dismantled.");
        } else {
          $errors[] = "Colonize failed: target {$location} is not uninhabited or not found.";
        }
      } // end foreach order
    } // end if orders


}

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

  # The original file is completed.
  # All code after this point will modify the new turn file

  // update the turn number
  $file->game["turn"] += 1;
  // update the previous/next docs
  $file->game["previousDoc"] = pathinfo($file->fileName, PATHINFO_FILENAME); // this is the old filename
  $file->game["nextDoc"] = "";
  // Allow orders
  $file->game["blankOrders"] = 3;
  $file->game["turnSegment"] = "pre";
  $file->orders = [];
  // Update economics
  $file->empire['previousEP'] += $file->empire['systemIncome']
                              + $file->empire['tradeIncome']
                              + $file->empire['miscIncome']
                              - $file->empire['maintExpense']
                              - $file->empire['miscExpense']
                              - $file->calculatePurchaseExpense();
  $file->empire['systemIncome'] = 0;
  $file->empire['tradeIncome'] = 0;
  $file->empire['miscIncome'] = 0;
  $file->empire['maintExpense'] = 0;
  $file->empire['miscExpense'] = 0;
  // empty purchases
  $file->purchases = [];

### TODO: go through unitStates and remove any units marked "Destroyed".
### Go through fleets and system['units'] arrays, removing those units that were marked destroyed

  $file->writeToFile($searchDir.$newFile.".js"); // Also updates the $file->fileName
  unset($file);
}

###
# Economics Phase
###
foreach ($gameFiles as &$file) {
  $maintenance = 0;
  $miscExpenses = 0;
  $miscIncome = 0;
  $totalSystemIncome = 0;
  $totalTradeIncome = 0;

  $usedSystems = []; // This is a list of unique systems being visited for trade

  foreach ($file->colonies as &$colony) {
    // Maintenance costs
    $unitsAtLocation = $file->getUnitsAtLocation($colony['name']);
    foreach ($unitsAtLocation as &$unit) {
      $unitData = $file->getUnitByName($unit);
      if (!isset($unitData)) {
        $errors[] = "Calculating maintenance cost of unknown unit, {$unit} for {$file->empire["name"]}";
        continue;
      }
      $maintenance += $unitData["cost"]*0.1;
      unset($unit);
    }

    if ($colony['owner'] !== $file->empire['empire']) continue;

    $colonyIncome = $file->calculateSystemIncome($colony["name"]);
    // blockade check
    if (str_contains(strToLower($colony["name"]), 'blockade'))
      $colonyIncome = 0;

    $totalSystemIncome += $colonyIncome;
    unset($colony);
  }

  // Look for Trade fleets
  foreach ($file->fleets as $fleet) {

    // Check if this is a valid Trade fleet
    if (strtolower(trim($fleet['location'])) !== 'trade') continue;
    // Must have a Convoy unit
    if (!$game->fleetHasAbility($fleet['name'], 'convoy')) continue;

    // Parse the trade route systems from fleet notes
    $routeSystems = explode(",", $fleet['notes']);

    if (count($routeSystems) > 3) { // more than three route legs
      $errors[] = "Fleet {$fleet['name']} for {$file->empire["name"]} has invalid trade route format: “{$fleet['notes']}”.";
      continue;
    }
    if (count($routeSystems) < 1) // less than one route leg
      continue;

    // Verify route continuity
    $isContiguous = false;
    if (count($routeSystems) > 1) {
      [$path, $count] = $game->findPath($routeSystems[0], $routeSystems[1], true);
      if ($count == 1) $isContiguous = true;
    } else {
      $isContiguous = true; // set true if only one system on the route
    }    
    if (count($routeSystems) == 3) {
      [$path, $count] = $game->findPath($routeSystems[0], $routeSystems[2], true);
      if ($count == 1) $isContiguous = true;
      [$path, $count] = $game->findPath($routeSystems[1], $routeSystems[2], true);
      if ($count == 1) $isContiguous = true;
    }
    if (!$isContiguous) continue;

    foreach ($routeSystems as $sysName) {
      // record duplicate trade sources per empire to prevent them later
      $usedSystems[$sysName] = true;

      // Validate ownership and treaties
      $colony = $game->getColonyByName($sysName);
      // colony is not in list
      if (!$colony) {
        unset($usedSystems[$sysName]); // remove the system from the list of trading systems
        continue;
      }
      // colony is un-owned
      if ($colony["owner"] == '') {
        unset($usedSystems[$sysName]); // remove the system from the list of trading systems
        continue;
      }
      // Must be owned by self or a valid trade partner
      // Owned by self is implied
      if ($colony["owner"] !== $empireName) {
        $treatyOK = false;
        foreach ($game->treaties as $treaty) {
          if ($treaty['empire'] === $colony["owner"])
            if (!atLeastPoliticalState($treaty['type'], 'Trade'))
              unset($usedSystems[$sysName]); // remove the system from the list of trading systems
        }
      }

      // Check for blockades
      if (str_contains(strToLower($colony['notes']), 'blockaded') !== false)
        unset($usedSystems[$sysName]); // remove the system from the list of trading systems
    }
  }

  // calculate the trade income for this system
  foreach ($usedSystems as $sysName) {
    $colony = $file->getColonyByName($sysName);
    $totalTradeIncome += (int)$colony['population'];
  }


  # System Income phase
  $file->empire['systemIncome'] = $totalSystemIncome;
  # Trade income phase
  $file->empire['tradeIncome'] = $totalTradeIncome;
  # Maintenance Expense phase
  $file->empire['maintExpense'] = $maintenance;
  # Misc Income/Expense phase
  $file->empire['miscIncome'] = $miscIncome;
  $file->empire['miscExpense'] = $miscExpenses;

  $file->empire['totalIncome'] = $file->empire['systemIncome'] +
                                 $file->empire['previousEP'] +
                                 $file->empire['tradeIncome'] +
                                 $file->empire['miscIncome'] - 
                                 $file->empire['maintExpense'] -
                                 $file->empire['miscExpense'];

  unset($file);
}


###
# Write the game files
###
foreach ($gameFiles as $file) {
  $file->writeToFile();
}

$errors = array_merge( $errors, $file->getErrors());
showErrors($errors);


function showErrors(array $errorArray): void
{
  foreach($errorArray as $line) {
    echo $line."\n";
  }
  exit(1);
}
?>

