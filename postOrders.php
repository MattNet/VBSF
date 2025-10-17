#!/usr/bin/env php
<?php
#####
# collect_game_files.php

# Perform processing for a VBAM turn

# Usage:
#   php collect_game_files.php --game="Game Name" --turn=5
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
$gameFiles = array();

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
  fwrite(STDERR, "Error: search directory '{$searchDir}' does not exist or is not a directory.\n");
  exit(2);
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

exit(0);
?>

