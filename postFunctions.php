<?php
###
# Order names
###
# ORDER TYPE	-	ORDER NAME
# Break a treaty -	break
# Build unit -		build_unit
# Build intel points -	build_intel
# Colonize system -	colonize
# Convert Unit -	convert
# Cripple unit -	cripple
# Destroy unit -	destroy
# Assign flights -	flight
# Perform an intel action - intel
# Load units -		load
# Mothball a unit -	mothball
# Move fleet -		move
# Move unit -		move_unit
# (Re) name a place -	name
# Offer a treaty -	offer
# Increase productivity - productivity
# Repair unit -		repair
# Invest into research - research
# Sign a treaty -	sign
# Set a trade route -	trade_route
# Unload units -	unload
# Unmothball a unit -	unmothball
###

###
# Reads the data variable from a JSON file
###
# Args:
# - (string) Name of file to load
# Return:
# - (array) The data file converted to a series of PHP arrays.
#           False for an error
###
function extractJSON( $inputFile )
{
  $endPos = 0; // the end of the line selection
  $inFileContents = ""; // string contents of input JSON file
  $midPos = 0; // the end of the top-level variable selection
  $output = array();
  $startPos = 0; // the start of the line selection

  $inFileContents = file_get_contents( $inputFile );
  if( $inFileContents === false )
  {
    echo "Could not read file '".$inputFile."'\n";
    exit(1);
  }

  if( strlen($inFileContents) < 5 )
  {
    echo "Order file '$inputFile' is empty.\n";
    return false;
  }

  while( $endPos < (strlen($inFileContents)-5) )
  {
    $varName = ""; // the top-level variable of the data-file 

    # Create the variable name of the top-level JSON variable
    $startPos += 4; // advance past the "var" statement
    $midPos = strpos( $inFileContents, "=", $startPos )-1; // catch the first assignment operator
    $varName = substr( $inFileContents, $startPos, ($midPos-$startPos) ); // assign the top-level variable name
    $varName = trim($varName); // trim off whitespace

    # Select the entire JSON assignment for a top-level variable
    $endPos = strpos( $inFileContents, ";\n", $midPos ); // set the end of the selection
    $midPos += 3; // advance past the assignment operator
    // make string from $midPos to $endPos of $inFileContents
    $JSON = substr( $inFileContents, $midPos, ($endPos-$midPos) );

    # Decode the JSON and assign to the PHP variable
    // decode the selection
    $output[$varName] = json_decode( $JSON, true, 6, JSON_OBJECT_AS_ARRAY );
    if( $output[$varName] === false )
      echo "Could not decode variable '".$output[$varName]."'\n";

    # Clean up this loop
    // the next selection is where this one leaves off
    $startPos = $endPos+2;
  }

  // Deal with un-written variables in the input stream
  if( ! isset($output["colonies"]) ) // panic
  {
    echo "Mal-formed or empty 'colonies' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["empire"]) ) // panic
  {
    echo "Mal-formed or empty 'empire' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["game"]) ) // panic
  {
    echo "Mal-formed or empty 'game' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["mapPoints"]) ) // panic
  {
    echo "Mal-formed or empty 'mapPoints' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["orders"]) ) // panic
  {
    echo "Mal-formed or empty 'orders' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["unknownMovementPlaces"]) ) // panic
  {
    echo "Mal-formed or empty 'unknownMovementPlaces' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["unitList"]) ) // panic
  {
    echo "Mal-formed or empty 'unitList' array in the input data.\n";
    return false;
  }
  else if( ! isset($output["fleets"]) ) // warning
  {
    echo "Caution: Mal-formed or empty 'fleets' array in the input data.\n";
  }

  return $output;
}

###
# Finds all of the orders of the specified type
###
# Args:
# - (array) the data sheet
# - (string) Name of order type to look for
# Return:
# - (array) Keys to the $inputData["orders"] array that match the order type
#           False for an error
###
function findOrder( $dataArray, $orderType )
{
  $out = array();

  // leave if something isn't set
  if( ! isset($dataArray["orders"]) )
    return false;

  foreach( $dataArray["orders"] as $key=>$order )
    if( strtolower($order["type"]) == strtolower($orderType) )
      $out[] = $key;

  return $out;
}

###
# Determines the purchase-value of a list of units (e.g. present in a fleet)
###
# Args:
# - (array) the data sheet
# - (array) a list of hull types present
# - (boolean) [optional] if true, skips trade, transport, and colony fleets
# Return:
# - (integer) The combined purchased amount. False for an error
###
function getFleetValue( $dataArray, $unitArray, $skipCivilian = false )
{
  $output = 0;

  // leave if something isn't set
  if( ! isset($dataArray["unitList"]) )
    return false;

  foreach( $unitArray as $hull )
  {
    if( $skipCivilian &&
        ( $hull == "Colony Fleet" ||
          $hull == "Trade Fleet" ||
          $hull == "Transport Fleet" )
      )
    {
      continue; // skip civilian units
    }
    foreach( $dataArray["unitList"] as $unit )
    {
      if( $unit["ship"] == $hull )
        $output += $unit["cost"];
    }
  }
  return $output;
}

###
# Determines the total supply value of a list of units (e.g. present in a fleet)
###
# Args:
# - (array) the data sheet
# - (array) a list of hull types present
# - (boolean) [optional] if true, skips trade, transport, and colony fleets
# Return:
# - (integer) The combined supply amount. False for an error
###
function getFleetSupplyValue( $dataArray, $unitArray, $skipCivilian = false )
{
  $output = 0;

  // leave if something isn't set
  if( ! isset($dataArray["unitList"]) )
    return false;

  foreach( $unitArray as $hull )
  {
    if( $skipCivilian &&
        ( $hull == "Colony Fleet" ||
          $hull == "Trade Fleet" ||
          $hull == "Transport Fleet" )
      )
    {
      continue; // skip civilian units
    }
    foreach( $dataArray["unitList"] as $unit )
    {
      if( $unit["ship"] == $hull )
      {
        $cutPos = strpos( strtolower($unit["notes"]), "supply(" );
        if( $cutPos === false ) // skip if there is no supply trait
          continue;
        // find the end of the supply value. may be single or double digit
        $doublePos = strpos( $unit["notes"], ")", $cutPos );
        $output += substr( $unit["notes"], $cutPos+7, $doublePos-$cutPos-7 ); // get the value
      }
    }
  }
  return $output;
}

###
# Determines the total supply value being used by a list of units (e.g. present in a fleet)
###
# Args:
# - (array) The fleet entry of the data sheet
# Return:
# - (integer) The combined supply used. False for an error
###
function getFleetloadedValue( $fleet )
{
  $output = 0;
  $notes = explode( ".", $fleet["notes"] );

  foreach( $notes as $value ) // iterate through each sentence of the notes
  {
    // Capture what is loaded and how much
    $flag = preg_match( "/(\d+) (\w+)+ loaded/i", $value, $match );
    if( $flag == 0 ) // return if there is none loaded
      return $output;

    $amt = $match[1];
    $type = strtolower( $match[2] );

    switch( $type )
    {
    case "census":
    case "light infantry":
    case "heavy infantry":
    case "light armor":
    case "heavy armor":
      $output += $amt*10;
      break;
    }  
  }

  return $output;
}

###
# Calculates the remaining EPs, after maintenance and purchases.
###
# Args:
# - (array) the data sheet
# Return:
# - (integer) The amount of income remaining. False for an error
###
function getLeftover( $dataArray )
{
  // "planetaryIncome" is set when the turn file is written.
  // It is set to TDP + previous EPs + trade income + misc income
  $out = $dataArray["empire"]["planetaryIncome"];
  // list of units present, for maint purposes
  // format is: [hull type] => count
  $unitList = array();

  // leave if something isn't set
  if( ! isset($dataArray["empire"]) || ! isset($dataArray["purchases"]) || ! isset($dataArray["fleets"]) || ! isset($dataArray["unitList"]) )
    return false;

  // If "pointPool" is zero, it has probably not been properly set yet
  if( $dataArray["empire"]["planetaryIncome"] == 0 )
  {
    // add domestic product
    $out = getTDP( $dataArray );
  }

  // add misc income
  $out += $dataArray["empire"]["previousEP"] + $dataArray["empire"]["tradeIncome"] + $dataArray["empire"]["miscIncome"];

  // deduct misc expenses
  $out -= $dataArray["empire"]["miscExpense"];

  // deduct purchases
  foreach( $dataArray["purchases"] as $item )
    $out -= $item["cost"];

  // deduct fleet maintenance
  foreach( $dataArray["fleets"] as $item )
  {
    // count up how many of each hull are present
    foreach( $item["units"] as $hull )
    {
      if( isset($unitList[$hull]) )
        $unitList[ $hull ]++ ;
      else
        $unitList[ $hull ] = 1 ;
    }
  }
  // deduct fixed item maintenance
  foreach( $dataArray["colonies"] as $item )
  {
    // count up how many of each hull are present
    foreach( $item["fixed"] as $hull )
    {
      if( isset($unitList[$hull]) )
        $unitList[ $hull ]++ ;
      else
        $unitList[ $hull ] = 1 ;
    }
  }
  // deduct cost for total number of hulls
  foreach( $dataArray["unitList"] as $item )
  {
    foreach( $unitList as $hull=>$count )
    {
      if( $item["ship"] == $hull )
        $out -= ceil($count / $item["maintNum"]) * $item["maintCost"];
    }
  }

  return $out;
}

###
# Calculates the Total Domestic Product of the game position
# This is the utilized production of every colony
###
# Args:
# - (array) the data sheet
# Return:
# - (integer) The amount of income from the TDP. False for an error
###
function getTDP( $dataArray )
{
  $out = 0;

  // leave if something isn't set
  if( ! isset($dataArray["colonies"]) ||  ! isset($dataArray["empire"]) )
    return false;
    
  foreach( $dataArray["colonies"] as $place )
    if( $place["owner"] == $dataArray["empire"]["empire"] )
    {
      $factories = $place["prod"]; // how much productivity to use
      if( $place["census"] < $place["prod"] )
        $factories = $place["census"]; // if census is less, use it instead of prod
      if( $place["morale"] <= ($place["census"]/2) )
        $factories /= 2; // halve productivity if morale is low
      if( $place["morale"] <= 0 )
        $factories = 0; // no productivity if morale is too low
      $out += ( $factories * $place["raw"] );
    }
  return $out;
}

###
# Determines the location key to the colony array for the given location name
###
# Args:
# - (string) The location name
# - (array) the data sheet
# Return:
# - (integer) The key to the colony array. False for error or non-existant
# NOTE: Can return 0, which is the first entry and which is falsey. Use '===' to check return value
###
function getcolonyLocation( $name, $dataArray )
{
  $output = false;
  foreach( $dataArray["colonies"] as $key=>$value )
  {
    if( strtolower($value["name"]) != strtolower($name) )
      continue;
    $output = $key;
    break; // skip to the end, since we found the location
  }
  return $output;
}

###
# Cerates the look-up arrays for the data file
###
# Args:
# - (array) the data sheet
# Return:
# - (Array) An array of arrays of look-up tables
# look-up tables provided (by array index):
# 1) By colony name - Key is colony name, value is colony index
# 2) By colony owner - Key is owner, value is array of colony indexes
# 3) By fleet name - Key is name, value is fleet index
# 4) By fleet location - Key is location name, value is array of fleet indexes
# 5) By unit - Key is unit hull type, value is array of fleet indexes
# 6) By location name - Key is location, value is map index
# 7) By location owner - Key is location owner, value is array of map indexes
###
function makeLookUps( $dataArray )
{
  $colonyName = array();
  $colonyOwner = array();
  $fleetName = array();
  $fleetLocation = array();
  $fleetUnits = array();
  $mapLocation = array();
  $mapOwner = array();

  foreach( $dataArray["colonies"] as $key=>$value )
  {
    // by colony name
    $colonyName[ $value["name"] ] = $key;

    // by colony owner
    if( ! isset( $colonyOwner[ $value["owner"] ] ) )
      $colonyOwner[ $value["owner"] ] = array();
    $colonyOwner[ $value["owner"] ][] = $key;
  }

  foreach( $dataArray["fleets"] as $key=>$value )
  {
    // by fleet name
    $fleetName[ $value["name"] ] = $key;

    // by fleet location
    if( ! isset( $fleetLocation[ $value["location"] ] ) )
      $fleetLocation[ $value["location"] ] = array();
    $fleetLocation[ $value["location"] ][] = $key;
  }

  // by fleet units
  foreach( $dataArray["fleets"] as $fleetKey=>$value )
    foreach( $value["units"] as $unitValue )
    {
      if( ! isset( $fleetUnits[ $unitValue ] ) )
        $fleetUnits[ $unitValue ] = array();
      $fleetUnits[ $unitValue ][] = $fleetKey;
    }

  foreach( $dataArray["mapPoints"] as $key=>$value )
  {
    // by map location
    $mapLocation[ $value[3] ] = $key;

    // by map owner
    if( ! isset( $mapOwner[ $value[2] ] ) )
      $mapOwner[ $value[2] ] = array();
    $mapOwner[ $value[2] ] = $key;
  }

  return array( $colonyName, $colonyOwner, $fleetName, $fleetLocation, $fleetUnits, $mapLocation, $mapOwner );
}

###
# A key-sorting function that puts "unitList" at the end
###
function UKSortFunc( $a, $b )
{
  // no sort if they are the same
  if( $a == $b )
    return 0;

  // if $a = our string, sort it after the other key
  if( $a == "unitList" )
    return 1;
  // if $b = our string, sort it after the other key
  if( $b == "unitList" )
    return -1;

  // sort normally
  return ($a < $b) ? -1 : 1;
}

###
# Writes the data variable as a JSON file
###
# Args:
# - (array) the data sheet
# - (string) the file name to write to
# Return:
# - (boolean) True for success, false for failure
###
function writeJSON( $dataArray, $writeFile )
{
  $output = "";

  if( empty($writeFile) )
  {
    echo "writeJSON() given no file to write.\n";
    return false;
  }

  ksort( $dataArray ); // sort the keys of the incoming array
  uksort( $dataArray, "UKSortFunc" );
  foreach( $dataArray as $key => $line )
  {
    $result = json_encode($line);
    if( $result === false )
    {
      echo "Could not encode JSON: ".json_last_error()."\n";
      return false;
    }
    $output .= "var $key = $result;\n";
  }

  $inReplace = array( "},{", "],[", "null", "[[", "[{", "]]", "}]" );
  $outReplace = array( "},\n   {", "], [", "{}", "[\n   [", "[\n   {", "]\n]", "}\n]" );
  $output = str_replace( $inReplace, $outReplace, $output ); // insert newlines for easier human-readability

  $result = file_put_contents( $writeFile, $output );
  if( $result === false )
  {
    echo "Could not write file '$writeFile'\n";
    return false;
  }

  echo "Wrote to file '$writeFile'\n";

  return true;
}
?>
