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
    echo "Could not read file '".$argv[1]."'\n";
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
# - (boolean) if true, skips trade, transport, and colony fleets
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
# Calculates the remaining EPs, after maintenance and purchases.
###
# Args:
# - (array) the data sheet
# Return:
# - (integer) The amount of income remaining. False for an error
###
function getLeftover( $dataArray )
{
  $out = 0;
  // list of units present, for maint purposes
  // format is: [hull type] => count
  $unitList = array();

  // leave if something isn't set
  if( ! isset($dataArray["empire"]) || ! isset($dataArray["purchases"]) || ! isset($dataArray["fleets"]) || ! isset($dataArray["unitList"]) )
    return false;

  // add domestic product
  $out = getTDP( $dataArray );

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
