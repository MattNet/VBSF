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

# encodeValue(mixed $value): string
#   Helper method to encode arrays/strings/numbers/booleans/null into a JSON-like string
#   Arguments: $value – value to encode
#   Output: encoded string
#   Access: private

# isAssoc(array $arr): bool
#   Determines whether an array is associative.
#   Arguments: $arr – array to check
#   Output: boolean
#   Access: private

# calculateSystemIncome(): void
#   Calculates income for a colony based on population, RAW, and status notes (Opposition/Rebellion)
#   Arguments: $name - colony name
#   Output: integer
#   Access: public

# getUnitsAtLocation(string $location): array
#   Returns all units present at a given location, including colonies, fleets, and mothballs.
#   Arguments: $location – name of colony or sector
#   Output: array of unit strings
#   Access: public

# getFleetByName(string $name): ?array
#   Returns a fleet array by its name.
#   Arguments: $name – fleet name
#   Output: fleet array or null if not found
#   Access: public

# getColonyByName(string $name): ?array
#   Returns a colony array by its name.
#   Arguments: $name – colony name
#   Output: colony array or null if not found
#   Access: public

# getUnitByName(string $ship): ?array
#   Returns a unit object from unitList by ship name.
#   Arguments: $ship – ship designator
#   Output: unit array or null if not found
#   Access: public

# findPath(string $start, string $end, bool $excludeRestricted = false): array
#   Finds a path and distance between two systems using BFS, ignoring "Unexplored" and optionally "Restricted" links.
#   Arguments: $start – starting system, $end – ending system, $excludeRestricted – ignore restricted links if true
#   Output: array, where 'path' is the list of systems and 'distance' is the number of links
#   Access: public

# getErrors(): array
#   Returns any errors encountered during file read/write or decoding.
#   Arguments: none. Optionally erase the errors if true
#   Output: array of error messages
#   Access: public

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

# string $fileName
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
    $output = '';
    $keys = array_keys(get_object_vars($this));
    sort($keys);
    // Move unitList to end
    $keys = array_filter($keys, fn($k) => $k !== 'unitList');
    $keys[] = 'unitList';
    foreach ($keys as $key) {
      if (!property_exists($this, $key)) continue;
      $value = $this->$key;
      if (!is_array($value)) continue; // Only write properties that are arrays
      $encoded = $this->encodeValue($value);
      $output .= "var {$key} = {$encoded};\n";
    }

    if ($file == "") // if the argument is empty, write the original filename
      $file = $this->fileName;
    else // if the argument is given, treat that as our original filename
      $this->fileName = $file;
    if (file_put_contents($file, $output) === false) {
      $this->errors[] = "Failed to write file: {$file}";
    }
  }

###
#   Helper method to encode arrays/strings/numbers/booleans/null into a JSON-like string
#   Arguments: $value – value to encode
#   Output: encoded string
###
  private function encodeValue(mixed $value): string
  {
    if (is_array($value)) {
      $isAssoc = $this->isAssoc($value);
      if ($isAssoc) {
        $parts = [];
        foreach ($value as $k => $v) {
          $parts[] = '"' . $k . '":' . $this->encodeValue($v);
        }
        return '{' . implode(',', $parts) . '}';
      } else {
        $parts = array_map(fn($v) => $this->encodeValue($v), $value);
        return '[' . implode(',', $parts) . ']';
       }
    } elseif (is_string($value))
      return '"' . addcslashes($value, "\"\\") . '"';
    elseif (is_bool($value))
      return $value ? 'true' : 'false';
    elseif (is_null($value))
      return 'null';
    else
      return (string)$value;
  }

###
#   Determines whether an array is associative.
#   Arguments: $arr – array to check
#   Output: boolean
###
  private function isAssoc(array $arr): bool
  {
    if ([] === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
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
    $output = [$colony]['population'] * $colony['raw'];
    $notes = $colony['notes'] ?? '';
    if (str_contains($notes, 'Rebellion'))
      $output = 0;
    elseif (str_contains($notes, 'Opposition'))
      $output = intdiv($output, 2);
    return $output;
  }

###
#   Returns all units present at a given location, including colonies, fleets, and mothballs.
#   Arguments: $location – name of colony or sector
#   Output: array of unit strings
###
  public function getUnitsAtLocation(string $location): array
  {
    $units = [];
    foreach ($this->colonies as $colony) {
      if ($colony['name'] === $location)
        $units = array_merge($units, $colony['fixed']);
    }
    foreach ($this->fleets as $fleet) {
      if ($fleet['location'] === $location)
        $units = array_merge($units, $fleet['units']);
    }
    foreach ($this->unitsInMothballs as $fleet) {
      if ($fleet['location'] === $location)
        $units = array_merge($units, $fleet['units']);
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
#   Returns a unit object from unitList by ship name.
#   Arguments: $ship – ship designator
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
}
