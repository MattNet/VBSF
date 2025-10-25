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
  echo "Using gamefile '{$file}' for empire '{$fileCheck->empire["empire"]}'\n";
  unset($fileCheck); // unload the game object if un-needed
}
if (empty($gameFiles)) {
  echo "No game files found.\n";
  exit(0);
}

#####
#
# Process Results
#
#####
foreach ($gameFiles as $empireId => $file) {
  $empireName = $file->empire['empire'] ?? $empireId;
  $turn = intval($file->game['turn'] ?? 0);
  $file->events = $file->events ?? [];

###
# Supply Phase
###
  $outOfSupply = [];
  $exhaustedShips = [];

  // Apply Local Supply, Mark OOS
  foreach ($file->fleets as $fleet) {
    $fleetLoc = $fleet['location'];
    $colony = null;
    foreach ($file->colonies as $c) {
      if ($c['name'] == $fleetLoc) {
        $colony = $c;
        break;
      }
    }

    $locPop = $colony['population'] ?? 0;
    $locOwner = $colony['owner'] ?? '';
    $localLimit = $locPop;
    $locSupplied = 0;

    foreach ($fleet['units'] as $unitName) {
      $unit = $file->unitList[$unitName] ?? null;
      if (!$unit) continue;

      $inSupply = false;

      // Can trace to any known supply source?
      $supplyLine = $file->traceSupplyLines($fleetLoc);
      if (!empty($supplyLine)) {
          $inSupply = true;
          // Exhaustion check
          if($supplyLine[0]['source'] == "fleet") {
            $roll = rand(1, 6);
            if ($roll <= 5) { // failed the exhaustion roll
              // find a ship to exhaust
              $location = $supplyLine[0]['paths'][0];
              $fleetList = $file->getFleetByLocation($location);
              foreach($fleetList as $fleets) {
                $supplyShip = $file->fleetHasAbility($fleets, 'Supply');
                if ($supplyShip === false ) continue; // skip non-supply ships
                if (in_array(array("{$supplyShip} w/ {$ship['fleet']}",'Exhausted'), $$file->unitStates))
                  continue;
                $file->unitStates[] = ["{$supplyShip} w/ {$ship['fleet']}",'Exhausted'];
                $file->events[] = [
                  'event' => "{$supplyShip} exhausted",
                  'time' => $turn,
                  'text' => "{$supplyShip} w/ {$ship['fleet']} is exhausted and cannot supply other units until re-supplied."
                ];
              }
            }
          }
          break;
      }

      // If still not in supply
      if (!$inSupply) {
        $file->unitStates[] = ["{$unitName} w/ {$fleet['name']}","Out of supply"];
        $file->events[] = [
          'event' => "{$unitName} out of supply",
          'time' => $turn,
          'text' => "{$unitName} w/ {$fleet['name']} cannot trace supply and suffers reduced effectiveness."
        ];
      }
    }
  }

  // Count Out-of-Supply (OOS) units per system
  $systemCount = [];
  foreach ($file->fleets as $fleet) {
    $system = $fleet['location'];
    // Find all OOS units in this fleet
    $oosUnits = [];
    foreach ($file->unitStates as $entry) {
      // unitStates entry format: [unit + " w/ " + fleetName, "state"]
      [$unitRef, $state] = $entry;
      if ($state === "Out of Supply" && str_contains($unitRef, " w/ {$fleet['name']}"))
        $oosUnits[] = $unitRef;
    }
    if (!empty($oosUnits)) {
      $systemCount[$system] = $systemCount[$system] ?? [];
      $systemCount[$system] = array_merge($systemCount[$system], $oosUnits);
    }
  }

  // Resolve attrition per system
  foreach ($systemCount as $system => $oosUnits) {
    $count = count($oosUnits);
    $roll = rand(1, 6);

    if ($roll <= $count) {
      // One OOS unit suffers attrition — select randomly
      $victim = $oosUnits[array_rand($oosUnits)];

      // Determine if the victim is already crippled
      $isCrippled = in_array($victim, $file->unitsNeedingRepair);

      if ($isCrippled) {
        // Destroy unit if already crippled
        $file->unitsNeedingRepair = array_values(array_diff($file->unitsNeedingRepair, [$victim]));
        $file->unitStates[] = [$victim,'Destroyed'];

        $file->events[] = [
          'event' => 'Supply Attrition',
          'time' => $turn,
          'text' => "$victim was destroyed due to prolonged lack of supply in $system."
        ];
      } else {
        // Cripple unit (mark for repair)
        $file->unitsNeedingRepair[] = $victim;

        $file->events[] = [
          'event' => 'Supply Attrition',
          'time' => $turn,
          'text' => "$victim has been crippled due to lack of supply in $system."
        ];
      }
    }
  }


###
# Construction Phase
###
  $constructionOrders = array_filter($file->orders ?? [], function ($o) {
    return in_array($o['type'] ?? '', [
      'build_unit', 'purchase_civ', 'purchase_troop', 'remote_build',
      'repair', 'convert', 'scrap', 'mothball', 'unmothball'
    ]);
  });
  if (empty($constructionOrders)) continue;

  $constructionCapacity = array();

  foreach ($constructionOrders as $order) {
    $type = $order['type'];
    $receiver = $order['receiver'] ?? '';
    $target = $order['target'] ?? '';
    $note = $order['note'] ?? '';

    // baseline variables
    $colonyName = $target ?: $receiver;
    $unitName = $receiver;
    $unit = $file->getUnitByName($unitName);
    if (!$unit) {
      $errors[] = "Invalid purchase order for {$unit} (no such unit).";
      continue;
    }
    $system = $file->getColonyByName($colonyName);
    if (!$system) {
      $errors[] = "Invalid purchase order at {$colonyName} (no such system).";
      continue;
    }
    $cost = $unit['cost'];

    // system lookup for construction capacity
    if (!$constructionCapacity[$colonyName])
      $constructionCapacity[$colonyName] = calculateConstructionCapacity($file, $colonyName);

    // Build unit at system or shipyard
    if ($type === "build_unit" || $type === "purchase_civ") {

      if (!$system || !$unit) {
        $errors[] = "Invalid build order: '{$unitName}' at '{$colonyName}'";
        continue;
      }

      // Blockade or supply checks
      if ($file->checkBlockaded($colonyName)) {
        $errors[] = "{$colonyName} is blockaded and cannot build.";
        continue;
      } elseif (count($file->traceSupplyLines($colonyName)) > 0) {
        $errors[] = "{$colonyName} is out of supply and cannot build.";
        continue;
      }

      $cost = $unit['cost'];

      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "{$colonyName} attempted to build {$unitName}. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }
      // Capacity check
      if ($constructionCapacity[$colonyName] - $cost < 0) {
        $errors[] = "{$colonyName} attempted to build {$unitName}. "
                    . "Did not have the construction capacity: Cost {$cost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => $unitName];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    // Remote Base Construction
    if ($type === "remote_build") {
      $convoyName = $target;
      $cost = $unit['cost'];

      // Supply check
      $supplyLine = $file->traceSupplyLines($fleetLoc);
      if (empty($supplyLine)) {
        $errors[] = "Remote {$convoyName} attempted to build {$unitName}. Is out of supply.";
        continue;
      }
      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "Remote {$convoyName} attempted to build {$unitName}. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }
      // Capacity check
      if ($constructionCapacity[$colonyName] - $cost < 0) {
        $errors[] = "Remote {$convoyName} attempted to build {$unitName}. "
                    . "Did not have the construction capacity: Cost {$cost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => $unitName];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    // Partial Construction
# Not Implemented

    // Convoy Purchase
    if ($type === "purchase_civ") {
      // skip if not buying a convoy
      if (strtolower($unit['design']) !== "convoy") continue;

      $limit = 1;
      $cost = 20;

      // Check system control and condition
      if ($system['owner'] !== $file->empire["name"]) {
        $errors[] = "{$colonyName} is not controlled by {$file->empire["name"]}; cannot purchase Convoys there.";
        continue;
      }
      if (str_contains(strtolower($system['notes']), 'rebellion') !== false) {
        $errors[] = "{$colonyName} is in Rebellion; Convoys cannot be purchased.";
        continue;
      }
      if (str_contains(strtolower($system['notes']), 'opposition') !== false) {
        $errors[] = "{$colonyName} is in Opposition; Convoys cannot be purchased.";
        continue;
      }

      // Check for Supply Source: Pop≥5 and Good Order or has Supply Depot
      $isGoodOrder = ($system['morale'] >= ($system['population'] / 2));
      $hasDepot = $file->locationHasAbility($colonyName, "Supply Depot");
      $isSupplySource = ($system['population'] >= 5 && $isGoodOrder) || !empty($hasDepot);

      if (!$isSupplySource) {
        $errors[] = "{$colonyName} is not a valid supply source; cannot requisition Convoys here.";
        continue;
      }

      // Purchase limit check
      $existing = $file->countPurchases($colonyName, "convoy");
      if ($existing >= $limit) {
        $errors[] = "{$colonyName} exceeded Convoy purchase limit ({$limit} per turn).";
        continue;
      }
      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "Remote {$convoyName} attempted to build {$unitName}. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => $unitName];
      continue;
    }
    // Troop Purchase
    if ($type === "purchase_troop") {
      $cost = $unit['cost'];
      if (str_contains(strtolower($system['notes']), 'opposition') !== false) $cost *= 2;
      if (str_contains(strtolower($system['notes']), 'rebellion') !== false) {
        $errors[] = "{$colonyName} is in rebellion; cannot raise troops.";
        continue;
      }

      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "{$convoyName} attempted to raise {$unitName} troops. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }
      // Capacity check
      if ($constructionCapacity[$colonyName] - $cost < 0) {
        $errors[] = "{$colonyName} attempted to raise {$unitName} troops. "
                    . "Did not have the construction capacity: Cost {$cost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => $unitName];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    // Repair Orders
    if ($type === "repair") {
      // Determine the location of the unit
      // Find the fleet this unit belongs to
      foreach ($file->unitsNeedingRepair as $entry) {
        if (str_starts_with($entry, $unitName . " w/ ")) {
          $fleetName = trim(substr($entry, strlen($unitName) + 4));
          break;
        }
      }
      if (!$fleetName) {
        $errors[] = "{$colonyName} attempted to repair {$unitName}. "
                    . "Could not find the repair entry of {$unitName}.";
        continue;
      }

      $f = $file->getFleetByName($fleetName);
      if ($f) // unit was part of a fleet
        $location = $f['location'];
      else // if not part of a fleet, unit was part of a colony
        $location = $fleetName; // colony locations are called out directly

      $cost = ceil($unit['cost'] * 0.25);

      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "{$colonyName} attempted to repair {$unitName} at {$location}. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }
      // Capacity check
      if ($constructionCapacity[$colonyName] - $cost < 0) {
        $errors[] = "{$colonyName} attempted to repair {$unitName} at {$location}. "
                    . "Did not have the construction capacity: Cost {$cost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => "Repair $unitName w/ {$location}"];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    // Refits (Convert/Upgrade)
    if ($type === "convert") {
      $newUnit = $file->getUnitByName($target);
      $newUnitName = $newUnit['ship'];
      $cost = ceil($newUnit['cost'] * 0.25);

      // Cost check
      $availableFunds = $file->empire["totalIncome"] - $file->calculatePurchaseExpense();
      if ($availableFunds - $cost < 0) {
        $errors[] = "{$convoyName} attempted to convert {$unitName} into a {$newUnitName}. "
                    . "Could not afford: Cost {$cost}, had {$availableFunds}.";
        continue;
      }
      // Capacity check
      if ($constructionCapacity[$colonyName] - $cost < 0) {
        $errors[] = "{$colonyName} attempted to convert {$unitName} into a {$newUnitName}. "
                    . "Did not have the construction capacity: Cost {$cost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => "Convert $unitName to {$newUnitName}"];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    // Scrapping
    if ($type === "scrap") {
      $cost = floor($unit['cost'] * 0.5) * -1;
      $file->empire["miscIncome"] -= $cost;

      // Document the change
      $file->purchases[] = ["cost" => $cost, "name" => "Scrap $unitName"];
      $file->unitStates[] = [$unitName,"Destroyed"];
      continue;
    }

    // Mothballing and Unmothballing
    if ($type === "mothball") {
      $capacityCost = ceil($unit['cost'] * 0.25);

      // Capacity check
      if ($constructionCapacity[$colonyName] - $capacityCost < 0) {
        $errors[] = "{$colonyName} attempted to mothball {$unitName}. "
                    . "Did not have the construction capacity: Cost {$capacityCost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => 0, "name" => "Mothball $unitName"];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }

    if ($type === "unmothball") {
      $capacityCost = ceil($unit['cost'] * 0.25);

      // Capacity check
      if ($constructionCapacity[$colonyName] - $capacityCost < 0) {
        $errors[] = "{$colonyName} attempted to mothball {$unitName}. "
                    . "Did not have the construction capacity: Cost {$capacityCost}, had {$constructionCapacity[$colonyName]}.";
        continue;
      }

      // Document the change
      $file->purchases[] = ["cost" => 0, "name" => "Mothball $unitName"];
      $constructionCapacity[$colonyName] -= $cost;
      continue;
    }
  } // end foreach order

###
# Tech Phase
###
  // Determine advancement ruleset
  $useHistorical = !empty($file->game['techAdvancement']);
  // Establish Tech Advancement Cost (TAC)
  $techAdvCost = $file->empire['systemIncome'] * 2;


  $researchOrders = array_filter($file->orders ?? [], function($o){
     return in_array($o['type'] ?? '', ['research','research_new']);
  });

  // Handle "Invest into research" orders
  foreach ($researchOrders as $order) {
    if ($order['type'] === 'research')
      $file->empire['researchInvested'] += intval($order['note']);
  }

  // Handle "Research Target" orders
  $researchTarget = null;
  foreach ($researchOrders as $order) {
      if ($order['type'] === 'research_new')
        $researchTarget = [
          'action' => strtolower(trim($order['receiver'])),
          'unit' => trim($order['target'])
        ];
  }

  // Apply research advancement
  if ($file->empire['researchInvested'] >= $techAdvCost) {
    $file->empire['researchInvested'] -= $techAdvCost;

    // Optional Rule – Historical Tech Advancement
    if ($useHistorical) {
      $file->empire['techYear'] += 1;
      $newUnits = array();

      // Unlock any units that become available
      foreach ($file->unitList as &$unit) {
        if ($unit['yis'] <= $file->empire['techYear']) {
          $unit['researched'] = true;
          $newUnits[] = $unit['ship'];
        }
      }
      $eventText = "$empireName advanced to Tech Year {$file->empire['techYear']}.";
      if (!empty($newUnits))
        $eventText = "The new units added are " . implode(", ",$newUnits);
      $file->events[] = [
        'event' => 'Tech Advancement',
        'time' => $turn,
        'text' => $eventText
      ];
    } else {
    // Normal research advancement (4.9.3.3)
      if ($researchTarget && isset($file->unitList[$researchTarget['unit']])) {
        $file->unitList[$researchTarget['unit']]['researched'] = true;

        $file->events[] = [
          'event' => 'Tech Advancement',
          'time' => $turn,
          'text' => "$empireName has successfully completed research on {$researchTarget['unit']}."
        ];
      } else {
        $file->events[] = [
          'event' => 'Tech Progress',
          'time' => $turn,
          'text' => "$empireName made progress in research but has not designated a valid target."
        ];
      }
    }
  }

###
# End Of Turn Phase
###
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

        // Determine colony ownership
        $fromOwner = null;
        $toOwner   = null;
        foreach ($file->colonies as $colony) {
          if ($colony['name'] === $from) $fromOwner = $colony['owner'] ?? null;
          if ($colony['name'] === $to)   $toOwner   = $colony['owner'] ?? null;
        }

        // Determine if scouts are present at the ends
        $scoutFrom = false;
        $scoutTo   = false;
        foreach ($file->fleets as $fleet) {
          if($file->fleetHasAbility($fleet, 'Scout') !== false) {
            if ($fleet['location'] === $from)
              $scoutFrom = true;
            if ($fleet['location'] === $to)
              $scoutTo = true;
          }
        }

        // Validate diplomatic permissions
        // Default: both systems must be friendly or owned by self
        $canUpgrade = true; // is true, to capture self-owned
        if ($fromOwner && $fromOwner !== $file->empire['empire']) {
          foreach ($file->treaties as $treaty) {
            if ($treaty['Empire'] === $fromOwner) {
              if (!$file->atLeastPoliticalState($treaty['type'], 'Trade'))
                $canUpgrade = false;
              break;
            }
          }
        }
        if ($toOwner && $toOwner !== $file->empire['empire']) {
          foreach ($file->treaties as $treaty) {
            if ($treaty['Empire'] === $toOwner) {
              if (!$file->atLeastPoliticalState($treaty['type'], 'Trade'))
                $canUpgrade = false;
              break;
            }
          }
        }

        // Final permission and cost
        if (!$canUpgrade) {
          $errors[] = "Cannot upgrade lane {$from}<->{$to}: no diplomatic permission.";
          continue;
        }

        // Determine cost
        if ($scoutFrom && $scoutTo) {
          $cost = 20; // normal cost
        } else {
          $cost = 30; // increased cost without full scout coverage
        }

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

        $canDowngrade = true;
        $locationLookup = array();

        // Check for CL or larger ship in adjacent systems
        $hasLargeShip = false;
        foreach ($file->fleets as $fleet) {
          if ($fleet['location'] !== $from && $fleet['location'] !== $to) continue;
          $locationLookup[] = $fleet;
          foreach ($fleet['units'] as $unit) {
            if ($file->atLeastShipSize($unit['Design'], 'CL'))
              $hasLargeShip = true;
          }
        }
        if (!$hasLargeShip) {
          $errors[] = "Cannot downgrade lane {$from}<->{$to}: no CL or larger ships in adjacent systems.";
          $canDowngrade = false;
        }

        // Check for Scout if downgrading Restricted -> Unexplored
        if ($canDowngrade && $lane['status'] === 'Restricted') {
          $hasScout = false;
          foreach ($locationLookup as $fleet) {
            if ($file->fleetHasAbility($fleet['name'], 'Scout') !== false) {
              $hasScout = true;
              break;
            }
          }
          if (!$hasScout) {
            $errors[] = "Cannot downgrade Restricted lane {$from}<->{$to} to Unexplored: no Scout present.";
            $canDowngrade = false;
          }
        }

        // Validate diplomatic permissions
        // Default: both systems must be hostile or owned by self
        $canUpgrade = true; // is true, to capture self-owned
        if ($fromOwner && $fromOwner !== $file->empire['empire']) {
          foreach ($file->treaties as $treaty) {
            if ($treaty['Empire'] === $fromOwner) {
              if ($file->atLeastPoliticalState($treaty['type'], 'Neutral'))
                $canUpgrade = false;
              break;
            }
          }
        }
        if ($toOwner && $toOwner !== $file->empire['empire']) {
          foreach ($file->treaties as $treaty) {
            if ($treaty['Empire'] === $toOwner) {
              if ($file->atLeastPoliticalState($treaty['type'], 'Neutral'))
                $canUpgrade = false;
              break;
            }
          }
        }

        // Abort downgrade if any of the checks failed
        if (!$canDowngrade) continue;

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
        if (isset($colony['notes']) && $file->checkBlockaded($colony["name"])) $mod -= 1;
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

        $file->events[] = [
          'event'=>'Morale Check: Morale changed', 'turn'=>$turn,
          'text'=>"{$colonies['name']}: Rolled {$roll} + mod {$mod} = {$rollTotal} => Morale change {$delta} (from {$oldMorale} to {$colonies['morale']})."
        ];
      } // end morale check

      // Rebellion
      // - Any system with Morale == 0 is checked for Rebellion.
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
                   'text'=>"{$colonies['name']}: Rebellion occurred (roll {$roll} + mod {$rebMod} = {$rollTotal}). " .
                           "Rebel force magnitude ~{$reRoll} EP (CM convert to units)."];
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

          // Apply system traits to new colony
          $systemTraits = $targetColony['traits'] ?? []; // Assuming traits are stored here
          foreach ($systemTraits as $trait) {
            switch ($trait) {
              case 'Ancient Ruins':
                $targetColony['raw'] += 1;
                $targetColony['morale'] += 1;
                break;
              case 'Automated Defenses':
                $targetColony['fort'] += 1;
                break;
              case 'Fair Biosphere':
                $targetColony['capacity'] += 2;
                $targetColony['morale'] += 1;
                break;
              case 'Lost Colony':
                $targetColony['population'] += 2;
                $targetColony['morale'] += 1;
                break;
              case 'Mineral Rich':
                $targetColony['raw'] += 1;
                break;
              case 'Scattered Survivors':
                $targetColony['population'] += 1;
                break;
              case 'Spy Satellites':
                $targetColony['intel'] += 1;
                break;
              case 'Strategic Resources':
                $targetColony['raw'] += 2;
                break;
            }
          }
          // Ensure no attribute exceeds capacity (except raw)
          $attributes = ['population','morale','intel','fort'];
          foreach ($attributes as $attr) {
            if ($targetColony[$attr] > $targetColony['capacity'])
              $targetColony[$attr] = $targetColony['capacity'];
          }

          $file->events[] = [
            'event'=>"Colonized {$targetColony['name']}", 'turn'=>$turn,
            'text'=>"{$empireName} colonized {$targetColony['name']}. Convoy {$convoyFleet['name']} dismantled."
          ];
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

  // synch up unitsNeedingRepair and unitStates with the units in colonies, fleets, and mothballs.
  $file->syncUnitStates();

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

  // Collect destroyed units
  $destroyedUnits = [];
  foreach ($file->unitStates as $key => $state) {
    if (strcasecmp($state[1], 'Destroyed') === 0) {
      $destroyedUnits[] = $state[0];
      unset($file->unitStates[$key]);
    }
  }

  // Remove destroyed units from fleets
  foreach ($file->fleets as &$fleet) {
    if (!empty($fleet['units']))
      $fleet['units'] = array_values(array_filter($fleet['units'], function($unit) use ($destroyedUnits) {
        return !in_array($unit, $destroyedUnits, true);
      }));
  }

  // Remove destroyed units from systems/colonies
  if (!empty($file->colonies)) {
    foreach ($file->colonies as &$system) {
      if (!empty($system['fixed']))
        $system['fixed'] = array_values(array_filter($system['fixed'], function($unit) use ($destroyedUnits) {
          return !in_array($unit, $destroyedUnits, true);
        }));
    }
  }

### TODO: Perform ownership transfers (e.g. "gifting")
### TODO: Perform re-naming of fleets and colonies
### TODO: Perform conversion of units so noted in purchases
### TODO: Perform mothballing and unmothballing. add/remove to unitsInMothballs
### TODO: Remove crippled status if repaired

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
    if ($file->checkBlockaded($colony["name"]))
      $colonyIncome = 0;

    $totalSystemIncome += $colonyIncome;
    unset($colony);
  }

  // Look for Trade fleets
  foreach ($file->fleets as $fleet) {

    // Check if this is a valid Trade fleet
    if (strtolower(trim($fleet['location'])) !== 'trade') continue;
    // Must have a Convoy unit
    if ($file->fleetHasAbility($fleet['name'], 'convoy') === false) continue;

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
      if ($file->checkBlockaded($sysName))
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
  $errors = array_merge( $errors, $file->getErrors());
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
# Determines the construction capacity of the named colony.
#   Arguments: $name – colony to check, $file – the local GameData instance
#   Output: Integer - Total construction capacity in EP
###
function calculateConstructionCapacity($file, $colonyName) {
  static $cache = array();

  // Build a unique key; adjust if multiple empires share colony names
  $empire = $file->empire['empire'];
  $key = "{$empire}:{$colonyName}";

  // Return cached value if available
  if (isset($cache[$key]))
    return $cache[$key];

  $capacity = 0;
  $colony = $file->getColonyByName($colonyName);
  if (!$colony) {
    $cache[$key] = 0;
    return 0;
  }

  // Base capacity: Population × RAW
  $capacity += intval($colony['population']) * intval($colony['raw']);

  // Shipyards: each adds 24 EPs
  $unitsAtColony = $file->getUnitsAtLocation($colonyName);
  foreach ($unitsAtColony as $uName) {
    $unit = $file->getUnitByName($uName);
    if (!$unit) continue;
    if (strtolower($unit['design']) === 'shipyard')
        $capacity += 24;
  }

  // Convoys: each adds 10 EP
  foreach ($unitsAtColony as $uName) {
    $unit = $file->getUnitByName($uName);
    if (!$unit) continue;
    if (stripos($unit['notes'], 'Convoy') !== false)
      $capacity += 10;
  }

  // Store and return
  $cache[$key] = $capacity;
  return $capacity;
}

?>

