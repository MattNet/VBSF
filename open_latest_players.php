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
$LOCAL_DOMAIN = "vbsf.local";

include( "./objects/DataSheet.php" ); // So that we can get the function that decodes the data sheets

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

  $obj = new DataSheet( $FILE_DIR.$value, "", true ); // open the data file (read-only)
  if( $obj === false )
  {
    echo "Could not open '".$FILE_DIR.$value."' for reading.\n";
    unset( $files[$key], $obj );
    continue;
  }
  $gameData = $obj->ReadGame ();
  if( ! is_array($gameData) || $gameData["turn"] != $turn )
  {
    unset( $files[$key], $obj );
    continue;
  }

  // reference each file to it's sheet
  $files[$key] = "http://$LOCAL_DOMAIN/sheet/index.html?data=".substr( $value, 0, -3 );

  unset( $obj ); // clean up the DataSheet object
}

shell_exec( $BROWSER_PROGRAM." ".implode( " ", $files ).' > /dev/null 2>&1 &' );

