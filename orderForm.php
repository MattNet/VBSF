<?php
/**
 * Accepts orders from the player sheet in the form of POST data
 * and puts them into the data file
 */

###
# Configuration
###
$EXIT_PAGE   = "sheet/index.html"; // relative player page to redirect to
$dataFileDir = "files/";           // location of the data files

###
# Initialization
###
require_once("./postFunctions.php"); // for the extractJSON and encodeJSON functions

$errors = []; // structured error messages

// Order table: [ require "reciever", require "target", require "note", 'description' ]
$orderTable = [
# Fleet Deployment
    'add_fleet'    => [ true, false, true, 'Add to Fleet' ],
    'flight'       => [ true, true, false, true ],
    'name_fleet'   => [ true, false, true, 'Rename a fleet' ],
# Intelligence Orders
    'covert'       => [ true, true, true, 'Perform covert mission' ],
    'special_force'=> [ true, true, true, 'Perform special-forces mission' ],
# Movement Orders
    'convoy_raid'  => [ true, true, false, 'Convoy Raid' ],
    'explore_lane' => [ true, false, false, 'Explore Jump-Lane' ],
    'move'         => [ true, true, false, 'Move fleet' ],
    'load'         => [ true, true, true, 'Load units' ],
    'long_range'   => [ true, true, false, 'Long-Range Scan' ],
    'start_trade'  => [ true, true, true, 'Set a trade route' ],
    'stop_trade'   => [ true, true, false, true ],
    'unload'       => [ true, false, true, 'Unload units' ],
# Diplomatic Orders
    'hostile_check'=> [ true, false, false, 'Declare War' ],
    'diplo_check'  => [ true, false, false, 'Offer a treaty' ],
    'sign_treaty'  => [ true, true, false, 'Sign a treaty' ],
    'sneak_attack' => [ true, false, false, 'Sneak Attack' ],
# Construction orders
    'build_unit'   => [ true, true, 'New fleet name', 'Build unit at system' ],
    'convert'      => [ true, true, false, 'Convert/Refit Unit' ],
    'mothball'     => [ true, false, false, 'Mothball a unit' ],
    'purchase_civ' => [ true, true, true, 'Purchase civilian unit at system' ],
    'purchase_troop'=> [ true, true, true, 'Purchase troop at system' ],
    'remote_build' => [ true, true, false, 'Remote build unit' ],
    'repair'       => [ true, false, false, 'Repair unit' ],
    'scrap'        => [ true, false, false, 'Scrap a unit' ],
    'unmothball'   => [ true, false, false, 'Unmothball a unit' ],
# Investment Orders
    'colonize'     => [ true, false, false, 'Colonize system' ],
    'downgrade_lane'=> [ true, true, false, 'Downgrade Lane' ],
    'imp_capacity' => [ true, false, false, 'Improve capacity' ],
    'imp_pop'      => [ true, false, false, 'Improve Population' ],
    'imp_intel'    => [ true, false, false, 'Improve Intelligence' ],
    'imp_fort'     => [ true, false, false, 'Improve Fortifications' ],
    'research'     => [ false, false, true, 'Invest into research' ],
    'name_place'   => [ true, false, false, '(Re) name a colony' ],
    'research_new' => [ true, true, false, 'Research Target' ],
    'upgrade_lane' => [ true, true, false, 'Upgrade Lane' ],
# Combat Orders
    'cripple'      => [ true, false, false, 'Cripple unit' ],
    'destroy'      => [ true, false, false, 'Destroy unit' ],
    'gift'         => [ true, true, false, 'Transfer ownership of unit' ]
];

###
# Input validation
###

// Require POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method not allowed");
}

// Validate file name
if (empty($_POST["dataFile"]))
    exit("No data file specified");
$dataFileRoot = basename($_POST["dataFile"],'.js');
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dataFileRoot))
    exit("Invalid data file name, '$dataFileRoot'");
$dataFileName = $dataFileDir . $dataFileRoot . ".js";

// Check file readability and writability
if (!is_readable($dataFileName))
    exit("Cannot read from '$dataFileName'");
if (!is_writable($dataFileName))
    exit("Cannot write to '$dataFileName'");

###
# Load current file contents
###
$fileContents = extractJSON($dataFileName);
if (!isset($fileContents["orders"]))
    $fileContents["orders"] = [];

###
# Process incoming orders
###
$flagDelete = -1;

foreach ($_POST as $key => $value) {
    // Only process OrderEntryNNX keys
    if (!preg_match('/^OrderEntry(\d+)([ABCD])$/', $key, $matches))
        continue;

    $orderNum = (int)$matches[1];
    $orderPos = strtolower($matches[2]);

    // Handle delete or "<-- No Order -->"
    if ($value === "<-- No Order -->" || $flagDelete === $orderNum) {
        $flagDelete = $orderNum;
        if (isset($fileContents["orders"][$orderNum]))
            unset($fileContents["orders"][$orderNum]);
        continue;
    }

    // Ensure order entry exists
    if (!isset($fileContents["orders"][$orderNum]))
        $fileContents["orders"][$orderNum] = [];

    // Assign field based on position
    switch ($orderPos) {
        case 'a': $fileContents["orders"][$orderNum]["type"]     = trim($value); break;
        case 'b': $fileContents["orders"][$orderNum]["reciever"] = trim($value); break;
        case 'c': $fileContents["orders"][$orderNum]["target"]   = trim($value); break;
        case 'd': $fileContents["orders"][$orderNum]["note"]     = trim($value); break;
    }
}

###
# Validate orders
###
foreach (array_keys($fileContents["orders"]) as $orderNum) {
    $order = &$fileContents["orders"][$orderNum];

    // Ensure all fields exist
    foreach (['type','reciever','target','note'] as $f)
        if (!isset($order[$f]))
            $order[$f] = "";

    // Check valid order type
    if (!isset($orderTable[$order["type"]])) {
        $errors[] = "Unknown order type '{$order["type"]}' at #$orderNum.";
        unset($fileContents["orders"][$orderNum]);
        continue;
    }

    $requirements = $orderTable[$order["type"]];
    $desc = $requirements[3];

    // Validate required fields
    $requiredMap = ['reciever','target','note'];
    foreach ($requiredMap as $i => $field) {
        if ($requirements[$i] && empty($order[$field])) {
            $errors[] = "Order type '$desc' requires a $field. None given (order #$orderNum).";
            unset($fileContents["orders"][$orderNum]);
            break;
        }
    }
}
unset($order);

// Reindex array
$fileContents["orders"] = array_values($fileContents["orders"]);

###
# Write updated file
###
if (!writeJSON($fileContents, $dataFileName, true))
    exit("Failed to write updated data to '$dataFileName'");

###
# Redirect back to player sheet
###
$redirectUrl = sprintf(
    "http://%s/%s?data=%s&e=%s&t=%d",
    $_SERVER["HTTP_HOST"],
    $EXIT_PAGE,
    urlencode($dataFileRoot),
    urlencode(implode("\n", $errors)),
    time()
);

header("Location: $redirectUrl", true, 302);
exit;

