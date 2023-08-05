#!/usr/bin/php -q
<?php
###
# Converts the HTML unit list into the data file format for a certain empire
###


if( ! isset($argv[1]) )
{
  echo "\Converts the HTML unit list into the data file format for a certain empire\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." empire\n\n";
  exit(1);
}

$UNIT_FILE = "./docs/units.html"; // HTML file containing all of the units
$WRITE_FILE = "unit_data.json"; // JSON format output file

$empireName = strtolower($argv[1]);
$unitContents = file_get_contents( $UNIT_FILE );
$output = "var unitList = [\n"; 

if( $unitContents === false )
{
  echo "Unable to open '$UNIT_FILE'\n";
  exit(1);
}

// Find the start and end section that brackets the proper unit list
$empireStartPos = stripos( $unitContents, ">$empireName</h3>" ); // get the empire name
if( $empireStartPos === false )
{
  echo "Unable to find '$empireName'\n";
  exit(1);
}

$empireStartPos = stripos( $unitContents, "<tbody>", $empireStartPos ) +8; // skip ahead to the end of the header
$empireEndPos = stripos( $unitContents, "</tbody>", $empireStartPos ) -1; // catch the end of the unit list

// re-set the $unitContents to hold the unit list of the empire
// $unitContents is set to a series of lines like the following:
// <tr><td>WDN</td><td>64</td><td>DN</td><td>10</td><td>2/2</td><td>12</td><td>12</td><td>10</td><td>0</td><td>Slow</td></tr>

$unitContents = substr( $unitContents, $empireStartPos, $empireEndPos-$empireStartPos );


// break up $unitContents into an array of lines
$unitContents = explode( "\n", $unitContents );

foreach( $unitContents as $unitLine )
{
  $dataArray = array();
  $maintTop = 1;
  $maintBottom = 1;

  // trim off the starting and ending markups
  $unitLine = str_replace( "<tr><td>", "", $unitLine );
  $unitLine = str_replace( "</td></tr>", "", $unitLine );
  $unitLine = trim( $unitLine );

  // break up the string by "</td><td>" markup
  $dataArray = explode( "</td><td>", $unitLine );

  // create the maintenance numbers
  list( $maintTop, $maintBottom ) = explode( "/", $dataArray[4] );

  // assemble the JSON string for this array
  // append to the script output
  $output .= "   {";
  $output .= "\"ship\":\"{$dataArray[0]}\",";
  $output .= "\"yis\":\"{$dataArray[1]}\",";
  $output .= "\"design\":\"{$dataArray[2]}\",";
  $output .= "\"cost\":\"{$dataArray[3]}\",";
  $output .= "\"maintNum\":\"$maintBottom\",";
  $output .= "\"maintCost\":\"$maintTop\",";
  $output .= "\"cmd\":\"{$dataArray[7]}\",";
  $output .= "\"basing\":\"{$dataArray[8]}\","; // Combine "H" numbers with this number? Same with "SH"?
  $output .= "\"notes\":\"{$dataArray[9]}\"";
  $output .= "},\n";
}

  // remove the ending comma so we can close the JSON object
  $output = substr( $output, 0, -2 );

  // close the output JSON object
  $output .= "\n];\n";

  // write the file
  file_put_contents( $WRITE_FILE, $output );
?>
