<?php
declare(strict_types=1);

###
# Class GameData
# Handles reading, writing, and manipulating game state data from a primary data file.

# __construct(string $filePath)
#   Initializes the object and reads data from the specified data file.
#   Arguments: $filePath – path to the data file
#   Output: none
#   Access: public

# readFromFile(string $file): void
#   Reads the data file, decodes JSON-like sections, and populates object properties.
#   Arguments: $file – path to the data file
#   Output: none
#   Access: public

# writeToFile(string $file): void
#   Encodes object properties into the file format and writes back to the data file.
#   Arguments: $file – path to the [new] data file. Uses original data file if left blank
#   Output: none
#   Access: public

# calculateSystemIncome(): int
#   Calculates income for a colony based on population, RAW, and status notes (Opposition/Rebellion)
#   Arguments: $name - colony name
#   Output: integer
#   Access: public

# calculatePurchaseExpense(): int
#   Calculates all of the cost from purchases made on this data sheet. Note that construction is considered a purchase
#   Arguments: None
#   Output: integer
#   Access: public

# checkBlockaded(string $name): boolean
#   Determines if the named colony is blockaded
#   Arguments: $name – colony name
#   Output: boolean yes/no
#   Access: public

# getUnitsAtLocation(string $location): array
#   Returns all units present at a given location, including colonies, fleets, and mothballs.
#   Arguments: $location – name of colony or sector
#   Output: array of unit strings. Multiple unit strings if there is more than one quantity
#   Access: public

# getFleetByName(string $name): ?array
#   Returns a fleet array by its name.
#   Arguments: $name – fleet name
#   Output: fleet array or null if not found
#   Access: public

# getFleetByLocation(string $location): ?array
#   Returns a fleet array by its location.
#   Arguments: $name – colony name
#   Output: fleet array or null if not found
#   Access: public

# getColonyByName(string $name): ?array
#   Returns a colony array by its name.
#   Arguments: $name – colony name
#   Output: colony array or null if not found
#   Access: public

# getUnitByName(string $ship): ?array
#   Returns a unit object from unitList by ship designation.
#   Arguments: $ship – ship designation
#   Output: unit array or null if not found
#   Access: public

# findPath(string $start, string $end, bool $excludeRestricted = false): array
#   Finds a path and distance between two systems using BFS, ignoring "Unexplored" and optionally "Restricted" links.
#   Arguments: $start – starting system, $end – ending system, $excludeRestricted – ignore restricted links if true
#   Output: array, where 'path' is the list of systems and 'distance' is the number of links
#   Access: public

# fleetHasAbility(string $fleet, string $ability): string
#   Determines if a fleet has a unit with a certain ability. e.g. is a scout fleet?
#   Arguments: $fleet – fleet to check, $ability – the ability keyword to check
#   Output: string - The unit name with this ability. False if none

# getErrors(): array
#   Returns any errors encountered during file read/write or decoding.
#   Arguments: none. Optionally erase the errors if true
#   Output: array of error messages
#   Access: public

# locationHasAbility(string $location, string $ability): bool
#   Determines if a location has a unit with a certain ability. e.g. is a scout fleet?
#   Arguments: $location – locaiton to check, $ability – the ability keyword to check
#   Output: Boolean yes/no

# syncUnitStates(): void
#   Synchronizes unitsNeedingRepair and unitStates with current units in colonies, fleets, and mothballs.
#   Arguments: none
#   Output: none
#   Access: public

# parseUnitQuantity(string $unit): array
#   Parses a unit string like "3xDD-II" into quantity and unit name.
#   Arguments: $unit – string unit identifier
#   Output: array [quantity:int, name:string]
#   Access: private

# atLeastPoliticalState(string $treatyState, string $stateCheck): bool
#   Determines if one treaty type is more hostile than another
#   Example: "Do we have at least a trade treaty with them?"
#     atLeastPoliticalState($ourTreaty, 'Trade'); // true if $ourTreaty is 'Trade' or 'Mutual Defense' or 'Alliance'
#   Arguments: $treatyState – string Current treaty state
#              $stateCheck - string Treaty type to check against
#   Output: boolean yes/no
#   Access: public

# atLeastShipSize(string $shipDesign, string $designCheck): bool
#   Determines if one hull type (design type) is larger than another
#   Example: "Is this ship at least a CL"
#     atLeastShipSize($ourShipDesign, 'CL'); // true if $ourShipDesign is 'CL' or 'CW' or 'CA' or 'NCA' etc...
#   Arguments: $shipDesign – string Current design type
#              $designCheck - string Design type to check against
#   Output: boolean yes/no
#   Access: public

# traceSupplyLines(string $start, int $distance): ?array
#   Finds one or more paths where supply can be traced to the given location
#   Supply locations includes supply ships, supply depots, and colonies
#   Arguments: $start – string The loction to check the supply status on
#              $distance - integer An optional amount of hops to trace 
#   Output: array [paths:array, source:string] - A collection of paths that satisfy supply. Source is 'colony' or 'supply ship'
#   Access: public

# Public properties, as defined by the data file specification:
# array $colonies
# array $empire
# array $events
# array $fleets
# array $game
# array $intelProjects
# array $mapConnections
# array $mapPoints
# array $offeredTreaties
# array $orders
# array $otherEmpires
# array $purchases
# array $treaties
# array $underConstruction
# array $unitsInMothballs
# array $unitsNeedingRepair
# array $unitStates
# array $unknownMovementPlaces
# array $unitList

# Misc property for reading/writing object to disk
# string $fileName
###

###
# TODO: Convert these methods from "Loop always" to "Loop Once, lookup always"
# Make a private method that loops through the data once and builds a series of lookup tables. Convert the methods to use the lookup tables.
# Invoke that private method only once, when there is a first-need for that data.
# Do not invoke it as a matter-of-course during object construction, because sometimes the object is loaded only for the read/write routines.
###

###
# Class GameData
# Handles reading, writing, and manipulating game state data from a primary data file.
###
class GameData
{
  // Properties
  public array $colonies = [];
  public array $empire = [];
  public array $events = [];
  public array $fleets = [];
  public array $game = [];
  public array $intelProjects = [];
  public array $mapConnections = [];
  public array $mapPoints = [];
  public array $offeredTreaties = [];
  public array $orders = [];
  public array $otherEmpires = [];
  public array $purchases = [];
  public array $treaties = [];
  public array $underConstruction = [];
  public array $unitsInMothballs = [];
  public array $unitsNeedingRepair = [];
  public array $unitStates = [];
  public array $unknownMovementPlaces = [];
  public array $unitList = [];

  public string $fileName = "";

  private array $errors = [];

###
#   Initializes the object and reads data from the specified data file.
#   Arguments: $filePath – path to the data file
#   Output: none
###
  public function __construct(string $filePath)
  {
    $this->readFromFile ($filePath);
    $this->fileName = $filePath;
  }

###
#   Reads the data file, decodes JSON-like sections, and populates object properties.
#   Arguments: $file – path to the data file
#   Output: none
###
  public function readFromFile(string $file): void
  {
    if (!file_exists($file)) {
      $this->errors[] = "File not found: {$file}";
      return;
    }

    $content = file_get_contents($file);
    if ($content === false) {
      $this->errors[] = "Failed to read file: {$file}";
      return;
    }

    // Remove "var " assignments and semicolons for JSON decode
    $pattern = '/var\s+(\w+)\s*=\s*(.*?);(\s*\/\/.*)?(?=\r?\n|$)/s';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $key = $match[1];
        $jsonStr = trim($match[2]);
        $decoded = json_decode($jsonStr, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
          $this->errors[] = "JSON decode error for key '{$key}': " . json_last_error_msg();
          continue;
        }
        if (property_exists($this, $key))
          $this->$key = $decoded;
      }
    }
  }

###
#   Encodes object properties into the file format and writes back to the data file.
#   Arguments: $file – path to the [new] data file. Uses original data file if left blank
#   Output: none
###
  public function writeToFile(string $file = ""): void
  {
    // copy data into $dataArray. Exclude named keys (errors[], etc..)
    $dataArray = array_filter(
        get_object_vars($this),
        fn($value, $key) => is_array($value) && !in_array($key, ['errors', 'fileName']),
        ARRAY_FILTER_USE_BOTH
    );

    // Custom sort: sort all keys alphabetically, then move 'unitList' to the end
    $unitListValue = null;
    if (array_key_exists('unitList', $dataArray)) {
      $unitListValue = $dataArray['unitList'];
      unset($dataArray['unitList']);
    }
    ksort($dataArray); // Sort remaining keys alphabetically
    $dataArray['unitList'] = $unitListValue; // Append 'unitList' at the end

    $outputLines = [];

    foreach ($dataArray as $key => $value) {
        // Ensure key is a valid JS variable name
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key))
            $this->errors[] = "Invalid JS variable name: {$key}";

        // Encode JSON safely
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false)
            $this->errors[] = "Could not encode JSON for key '{$key}': " . json_last_error_msg();
        $outputLines[] = "var $key = $json;";
    }

    // Combine all lines with newlines
    $output = implode("\n", $outputLines) . "\n";

    // Add extra newlines for readability in arrays of objects
    $output = preg_replace('/},\s*{/', "},\n   {", $output);

    if ($file == "") // if the argument is empty, write the original filename
      $file = $this->fileName;
    else // if the argument is given, treat that as our original filename
      $this->fileName = $file;
    if (file_put_contents($file, $output) === false)
      $this->errors[] = "Failed to write file: {$file}";
  }

###
#   Calculates income for a colony based on population, RAW, and status notes (Opposition/Rebellion)
#   Arguments: $name - colony name
#   Output: integer
###
  public function calculateSystemIncome( $name ): int
  {
    $colony = $this->getColonyByName($name);
    if (!$colony) {
      $this->errors[] = "Missing colony '{$name}' in calculateSystemIncome()";
      return null;
    }
    $output = $colony['population'] * $colony['raw'];
    $notes = $colony['notes'] ?? '';
    if (str_contains(strToLower($notes), 'rebellion'))
      $output = 0;
    elseif (str_contains(strToLower($notes), 'opposition')) {
      // Martial law restores full productivity, at the cost of making it likely to rebel
      // So if not Martial Law but is in opposition, then reduce output
      if (strpos($col['notes'] ?? '', 'Martial Law') === false)
        $output = intdiv($output, 2);
    }
    // No Blockade check here
    // This value may affect other rules outside of being blockaded.
    // In addition, this allows us to fund in-system items, despite the blockade

    return $output;
  }

###
#   Calculates all of the cost from purchases made on this data sheet. Note that construction is considered a purchase
#   Arguments: None
#   Output: integer
###
  public function calculatePurchaseExpense(): int
  {
    $output = 0;
    foreach ($this->purchases as $purchase) {
        $output += $purchase['cost'];
    }
    return $output;
  }

###
# checkBlockaded(string $name): boolean
#   Determines if the named colony is blockaded
#   Arguments: $name – colony name
#   Output: boolean yes/no
#   Access: public
###
  function checkBlockaded(string $name): boolean
  {
    $colony = $this->getColonyByName($name);
    if (!$colony) {
      $this->errors[] = "Failure to get colony {$name} in checkBlockaded().";
      return false;
    }
    if (str_contains(strToLower($colony['notes']), 'blockaded') !== false)
      return true;
    return false;
  }

###
#   Returns all units present at a given location, including colonies, fleets, and mothballs.
#   Arguments: $location – name of colony or sector
#   Output: array of unit strings. Multiple unit strings if there is more than one quantity
###
  public function getUnitsAtLocation(string $location): array
  {
    $entries = [];
    $units = [];
    foreach ($this->colonies as $colony) {
      if ($colony['name'] === $location)
        $entries = array_merge($entries, $colony['fixed']);
    }
    foreach ($this->fleets as $fleet) {
      if ($fleet['location'] === $location)
        $entries = array_merge($entries, $fleet['units']);
    }
    foreach ($this->unitsInMothballs as $fleet) {
      if ($fleet['location'] === $location)
        $entries = array_merge($entries, $fleet['units']);
    }
    foreach ($entries as $row) {
      [$qty, $name]= $this->parseUnitQuantity($row);
      $units = array_merge($units, array_fill(0, $qty, $name));
    }
    return $units;
  }

###
#   Returns a fleet array by its name.
#   Arguments: $name – fleet name
#   Output: fleet array or null if not found
###
  public function getFleetByName(string $name): ?array
  {
    foreach ($this->fleets as $fleet) {
      if ($fleet['name'] === $name) return $fleet;
    }
    return null;
  }

# getFleetByLocation(string $location): ?array
#   Returns a fleet array by its location.
#   Arguments: $name – colony name
#   Output: fleet array or null if not found
#   Access: public
public function getFleetByLocation(string $location): ?array
{
  // Search for a fleet matching the given location
  foreach ($this->fleets as $fleet) {
    if (isset($fleet['location']) && strcasecmp($fleet['location'], $location) === 0)
      return $fleet;
  }
  // Return null if no fleet is found at that location
  return null;
}

###
#   Returns a colony array by its name.
#   Arguments: $name – colony name
#   Output: colony array or null if not found
###
  public function getColonyByName(string $name): ?array
  {
    foreach ($this->colonies as $colony) {
      if ($colony['name'] === $name) return $colony;
    }
    return null;
  }

###
#   Returns a unit object from unitList by ship designation.
#   Arguments: $ship – ship designation
#   Output: unit array or null if not found
###
  public function getUnitByName(string $ship): ?array
  {
    foreach ($this->unitList as $unit) {
      if ($unit['ship'] === $ship) return $unit;
    }
    return null;
  }

###
#   Finds a path and distance between two systems using BFS, ignoring "Unexplored" and optionally "Restricted" links.
#   Arguments: $start – starting system, $end – ending system, $excludeRestricted – ignore restricted links if true
#   Output: array, where 'path' is the list of systems and 'distance' is the number of links
###
  public function findPath(string $start, string $end, bool $excludeRestricted = false): array
  {
    $graph = [];
    foreach ($this->mapConnections as $conn) {
      [$from, $to, $status] = $conn;
      if ($status === 'Unexplored') continue;
      if ($excludeRestricted && $status === 'Restricted') continue;

      if (!isset($graph[$from])) $graph[$from] = [];
      if (!isset($graph[$to])) $graph[$to] = [];
      $graph[$from][] = $to;
      $graph[$to][] = $from;
    }

    $queue = [[$start]];
    $visited = [$start => true];

    while ($queue) {
      $path = array_shift($queue);
      $node = end($path);
      if ($node === $end)
        return ['path' => $path, 'distance' => count($path) - 1];
      foreach ($graph[$node] ?? [] as $neighbor) {
        if (!isset($visited[$neighbor])) {
          $visited[$neighbor] = true;
          $queue[] = array_merge($path, [$neighbor]);
        }
      }
    }
    return ['path' => [], 'distance' => -1];
  }

###
#   Determines if a fleet has a unit with a certain ability. e.g. is a scout fleet?
#   Arguments: $fleet – fleet to check, $ability – the ability keyword to check
#   Output: string - The unit name with this ability. False if none
###
  public function fleetHasAbility(string $fleet, string $ability): bool
  {
    $fleetObj = $this->getFleetbyName($fleet); // get fleet
    foreach ($fleetObj["units"] as $unit) { // get each unit of the fleet
      $unitData = $this->getUnitByName($unit); // get the named unit
      if (str_contains(strToLower($unitData["notes"]), strToLower($ability))) // if the named unit has the ability, end here
        return $unit;
    }
    return false; // we didn't find the ability in the fleet
  }

###
#   Returns any errors encountered during file read/write or decoding.
#   Arguments: none. Optionally erase the errors if true
#   Output: array of error messages
###
  public function getErrors(bool $eraseErrors = false): array
  {
    if (!$eraseErrors)
      return $this->errors;
    // Temporarily store errors in another variable
    $errors = $this->errors;
    // Erase the errors array
    $this->errors = [];
    return $errors;
  }

###
# locationHasAbility(string $location, string $ability): bool
#   Determines if a location has a unit with a certain ability. e.g. is a scout?
#   Arguments: $location – locaiton to check, $ability – the ability keyword to check
#   Output: Boolean yes/no
###
  public function locationHasAbility(string $location, string $ability): bool
  {
    $units = $this->getUnitsAtLocation($fleet); // get units
    foreach ($units as $u) { // get each unit of the fleet
      $unitData = $this->getUnitByName($u); // get the named unit
      if (str_contains(strToLower($unitData["notes"]), strToLower($ability))) // if the named unit has the ability, end here
        return true;
    }
    return false; // we didn't find the ability in the location
  }

###
#   Synchronizes unitsNeedingRepair and unitStates with current units in colonies, fleets, and mothballs.
#   Arguments: none
#   Output: none
###
  public function syncUnitStates(): void
  {
    // Reset unitsNeedingRepair and unitStates
    $repair = [];
    $states = [];
    foreach ($this->colonies as $colony) {
      $units = array_merge($colony['fixed'] ?? []);
      foreach ($units as $u) {
        [$qty, $name] = $this->parseUnitQuantity($u);
        if (in_array("{$name} w/ {$colony['name']}", $this->unitsNeedingRepair))
          $repair[] = "{$name} w/ {$colony['name']}";
        else
          $states[] = ["{$name} w/ {$colony['name']}", "Active"];
      }
    }
    foreach ($this->fleets as $fleet) {
      $units = $fleet['units'] ?? [];
      foreach ($units as $u) {
        [$qty, $name] = $this->parseUnitQuantity($u);
        if (in_array("{$name} w/ {$fleet['name']}", $this->unitsNeedingRepair))
          $repair[] = "{$name} w/ {$fleet['name']}";
        else
          $states[] = ["{$name} w/ {$fleet['name']}", "Active"];
      }
    }
    $this->unitsNeedingRepair = array_unique($repair);
    $this->unitStates = array_unique($states, SORT_REGULAR);
  }

###
#   Parses a unit string like "3xDD-II" into quantity and unit name.
#   Arguments: $unit – string unit identifier
#   Output: array [quantity:int, name:string]
###
  private function parseUnitQuantity(string $unit): array
  {
    if (preg_match('/^(\d+)x(.+)$/', $unit, $matches)) {
      return [(int)$matches[1], trim($matches[2])];
    }
    return [1, $unit];
  }

###
#   Determines if one treaty type is more hostile than another
#   Example: "Do we have at least a trade treaty with them?"
#     atLeastPoliticalState($ourTreaty, 'Trade'); // true if $ourTreaty is 'Trade' or 'Mutual Defense' or 'Alliance'
#   Arguments: $treatyState – string Current treaty state
#              $stateCheck - string Treaty type to check against
#   Output: boolean yes/no
###
  public function atLeastPoliticalState(string $treatyState, string $stateCheck): bool
  {
    $treatyOrder = ['War', 'Hostilities', 'Neutral', 'Non-Aggression', 'Trade', 'Mutual Defense', 'Alliance'];
    $treatyIdx = array_search($treatyState, $treatyOrder);
    $checkIdx = array_search($stateCheck, $treatyOrder);
    // False if the current treaty is unknown or the treaty to check is unknown or the current treaty is more hostile than the treaty to check
    if ($treatyState === false || $treatyState === false || $treatyIdx < $checkIdx)
      return false;
    return true;
  }

###
#   atLeastShipSize(string $shipDesign, string $designCheck): bool
#   Determines if one hull type (design type) is larger than another
#   Example: "Is this ship at least a CL"
#     atLeastShipSize($ourShipDesign, 'CL'); // true if $ourShipDesign is 'CL' or 'CW' or 'CA' or 'NCA' etc...
#   Arguments: $shipDesign – string Current design type
#              $designCheck - string Design type to check against
#   Output: boolean yes/no
#   Access: public
###
  public function atLeastShipSize(string $shipDesign, string $designCheck): bool
  {
    // sorted by size. "New" hull versions considered larger than standard versions.
    // "War" versions considered smaller than "Heavy" versions. CWs considered smaller than CAs
    $hullOrder = [ 'BOOM','FT','SAux','POL','FF','NFF','FFW','FFH','DD','NDD','DDH','DW','HDW',
                   'LAux','CL','CW','CWH','CA','TUG','NCA','CCH','BC','BCH',
                   'DNL','DNW','DN','DNH','BB'
                 ];
    $designIdx = array_search($shipDesign, $hullOrder);
    $checkIdx = array_search($designCheck, $hullOrder);
    // False if the current treaty is unknown or the treaty to check is unknown or the current treaty is more hostile than the treaty to check
    if ($treatyState === false || $treatyState === false || $designIdx < $checkIdx)
      return false;
    return true;
  }

###
#   traceSupplyLines(string $start, int $distance): ?array
#   Finds one or more paths where supply can be traced to the given location.
#   Supply locations includes supply ships, supply depots, and colonies
#   Arguments: $start – string The loction to check the supply status on
#              $distance - integer An optional amount of hops to trace 
#   Output: array [paths:array, source:string] - A collection of paths that satisfy supply. Source is 'colony' or 'supply ship'
#   Access: public
###
  public function traceSupplyLines(string $start, int $distance=3): ?array
  {
    $output = array();
    // Identify Supply Sources
    $supplySources = [];
    foreach ($this->colonies as $colony) {
      if ($colony['owner'] != $this->empire['name']) continue;

      $isCore = ($colony['population'] >= 5);
      $isGoodOrder = ($colony['morale'] >= ($colony['population'] / 2));
      $hasDepot = false;

      if ($file->locationHasAbility($colony['name'], 'Supply Depot') === true)
          $hasDepot = true;

      if (($isCore && $isGoodOrder) || $hasDepot)
        $supplySources[] = [$colony['name'],'colony'];
    }
    // Any unit with "Supply" in its notes can resupply units in the same location that cannot otherwise trace supply.
    foreach ($file->fleets as $fleet) {
      $supplyShip = $this->fleetHasAbility($fleet['name'], 'Supply');
      if ($supplyShip !== false) {
        $isExhausted = false;
        foreach ($file->unitStates as $state) {
          if ( in_array(array("{$unitName} w/ {$fleet['name']}",'Exhausted'), $state) {
            $isExhausted = true;
            break;
          }
        }
        // Skip fleet if its supply unit is exhausted
        if ($isExhausted) continue;

        $supplySources[] = [$fleet['location'],'fleet'];
      }
    }
    // Identify paths
    foreach ($supplySources as $key=>$place) {
      $paths = $this->findPath($start, $place[0], true);
      if ($paths['distance'] > $distance) continue;
      $isBad = false;
      foreach($paths['path'] as $intermediate) {
        if ($intermediate == $start) continue; // skip if looking at the start location (it can be blockaded)
        if ($file->checkBlockaded($intermediate) !== true) continue;
        // intermediate is blockaded
        $isBad = true;
      }
      if (!$isBad)
        $output[] = ['paths'=>$paths,'source'=>$place[1]];
    }
    return $output;
  }
}
