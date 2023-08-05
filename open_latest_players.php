#!/usr/bin/php -q
<?php
#####
# Opens each player's files for the given turn in the web browser
#####

if( ! isset($argv[1]) )
{
  echo "\nOpens each player's files for the given turn in the web browser\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." GAME_TURN\n\n";
  exit(1);
}

$FILE_DIR = "./files/";
$BROWSER_PROGRAM = "firefox";

include( "./postFunctions.php" );

$files = scandir( $FILE_DIR );
$turn = (int) $argv[1];

foreach( $files as $key => $value )
{
  // remove those files from the list that are not javascript files
  if( substr( $value, -3 ) != ".js" )
  {
    unset( $files[$key] );
    continue;
  }

  // remove those files where their ["game"]["turn"] does not match the input argument
  $fileData = extractJSON( $FILE_DIR.$value );
  if( ! is_array($fileData) || $fileData["game"]["turn"] != $turn )
  {
    unset( $files[$key] );
    continue;
  }

  // reference each file to it's sheet
  $files[$key] = "http://vbsf.local/sheet/index.html?data=".substr( $value, 0, -3 );

}

shell_exec( $BROWSER_PROGRAM." ".implode( " ", $files ).' > /dev/null 2>&1 &' );

