#!/usr/bin/php -q
<?php
###
# Generates the system data.
# Command Line interface
###
/*
if( ! isset($argv[1]) )
{
  echo "\nGenerates system data randomly\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]."\n\n";
  exit(1);
}
*/

###
# Initialization
###

$systemTable = array( 2=>0, 3=>0, 4=>0, 5=>0, 6=>1, 7=>1, 8=>1, 9=>1, 10=>1, 
                      11=>1, 12=>1
                    ); // do we generate a system here? values are true/false
$systemImportance = array( 2=>'minor outpost', 3=>'minor outpost', 4=>'outpost', 
                           5=>'outpost', 6=>'minor colony', 7=>'minor colony', 
                           8=>'minor colony', 9=>'colony', 10=>'colony', 
                           11=>'major colony', 12=>'major colony'
                         ); // how important is the colony?
$systemTraits = array( 'minor outpost'=>array( 'census'=>1, 'morale'=>1,'raw'=>1,'productivity'=>0, 'capacity'=>2 ),
                       'outpost'      =>array( 'census'=>2, 'morale'=>2,'raw'=>1,'productivity'=>0, 'capacity'=>4 ),
                       'minor colony' =>array( 'census'=>3, 'morale'=>2,'raw'=>2,'productivity'=>1, 'capacity'=>6 ),
                       'colony'       =>array( 'census'=>5, 'morale'=>4,'raw'=>2,'productivity'=>2, 'capacity'=>8 ),
                       'major colony' =>array( 'census'=>7, 'morale'=>6,'raw'=>3,'productivity'=>3, 'capacity'=>10 ),
                       'homeworld'    =>array( 'census'=>10,'morale'=>9,'raw'=>6,'productivity'=>10,'capacity'=>12 )
                     ); // system stats
$systemSpecials = array( 0=>'', 1=>'', 2=>'', 3=>'RAW+1', 4=>'RAW+1', 5=>'RAW+1',
                       6=>'', 7=>'RAW+2', 8=>'', 9=>'', 10=>'Capacity+2', 
                       11=>'Capacity+2', 12=>'reroll', 13=>'reroll', 14=>'reroll',
                     ); // How to modify the system
$systemSpecialMod = array( 'minor outpost'=>-2, 'outpost'=>-1, 'minor colony' =>0, 
                           'colony' =>1, 'major colony' =>2, 'homeworld' => 0
                     ); // modification to the special traits
$NPEchance = 5; // percentage chance of finding an NPE
$NPEtech = array( 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0, 9=>1, 10=>1, 11=>1, 
                  12=>2, 13=>2, 14=>2, 15=>3, 16=>3, 17=>4, 18=>4, 19=>1, 20=>2
                ); // 0 is no tech (no NPE), 1+ is 'INT-N' levels
$NPEexploration = array( array( 0,0,0,0,0 ), // INT-0 has no exploration chances
                         array( 50,0,0,0,0 ), // INT-1 exploration chances
                         array( 75,25,0,0,0 ), // INT-2 exploration chances
                         array( 100,50,25,0,0 ), // INT-3 exploration chances
                         array( 100,75,50,25,0 ), // INT-4 exploration chances
                         array( 100,100,75,50,25 ), // INT-5 exploration chances
                         array( 100,100,100,75,50 ), // INT-6 exploration chances
                       ); // pre-contact exploration
$NPEcolonyCount = array( 0, 1, 2, 4, 6, 10, 15 ); // num of colonies, + 1/3 explored sites (round down)
$terrainChance = 25; // percentage chance of finding any special terrain
$terrainChart = array(
// SFB terrains
                       1=>'Asteroid Field (P3.1)', 2=>'Asteroid Field (P3.1)', 3=>'Nebula (P6.0)',
                       4=>'Heat Zone (P10.0)', 5=>'Dust Cloud (P13.0)', 6=>'Dust Cloud (P13.0)',
                       7=>'Intense Dust Cloud (P13.5)', 8=>'Radiation Zone (P15.0)',
                       9=>'Gas Giant (P2.22)', 10=>'Gas Giant (P2.22)'
/* // Cannonical Terrain Chart (CC pg 40)
                       1=>'Asteroid Field', 2=>'Asteroid Field', 3=>'Dense Asteroid Field', 
                       4=>'Nebula', 5=>'Nebula', 6=>'Nebula', 7=>'Dark Matter Nebula', 
                       8=>'Maser Nebula', 9=>'Dust Cloud', 10=>'Dust Cloud'
*/
                     ); // Choose a terrain type
###
# Program
###
$result = 0;
$text = '';
$explorationRing = '';
$exploredCount = 0;
$techDieRoll = 0;
$NPEtechLevel = 0;

// is there a system in this sector?
$result = twoSix();
echo "Sector Generation:\n- System in sector? [".$result."] ";
if( ! $systemTable[ $result ] )
{
  echo "No system\n\n";
  exit(0);
}
else
  echo "System present\n\n";

// Determine system importance
$result = twoSix();
$importance = $systemImportance[ $result ];
echo "System Generation:\n- System importance: [".$result."] ";
$text = ucfirst($importance);
echo $text."\n";

// Determine system stats
echo "- Census: ".$systemTraits[ $importance ]['census'];
echo " Morale: ".$systemTraits[ $importance ]['morale'];
echo " RAW: ".$systemTraits[ $importance ]['raw'];
echo " Productivity: ".$systemTraits[ $importance ]['productivity'];
echo " Capacity: ".$systemTraits[ $importance ]['capacity']."\n";

// Determine special traits
$text = rerollSpecials( $systemSpecialMod[$importance] );
echo "- System special traits: \n".$text."\n";

// Determine terrain
$result = hundred();
echo "Terrain generation chance is ".$terrainChance."%. Rolled [".$result."] ";
if( $terrainChance < $result )
{
  echo "nothing special: Class-M planet.\n\n";
}
else
{
  echo "and [";
  $result = oneTen();
  echo $result."] ".$terrainChart[ $result ].".\n\n";
}

// determine NPE activation
$result = hundred();
echo "NPE generation chance is ".$NPEchance."%. Rolled [";
if( $NPEchance < $result )
{
  echo $result."] No NPE\n\n";
  exit(0);
}
else
{
  $outputString .= $result."] Someone lives here!\n\n";
}

// Determine NPE tech level (may remove NPE)
$techDieRoll = twoTen();
$NPEtechLevel = $NPEtech[ $techDieRoll ];
if( $NPEtechLevel == 0 )
{
  echo "NPE Tech Level: [".$techDieRoll."] Too low for meaningful opponent\n\n";
  exit(0);
}

// Determine NPE stats
$result = round((hundred()+hundred())/2);
echo "- Aggressiveness (AG): [".$result."]\n";
$result = round((hundred()+hundred())/2);
echo "- Integrity (IN): [".$result."]\n";
$result = round((hundred()+hundred())/2);
echo "- Xenophobia (XE): [".$result."]\n";

// Apply NPE tech level
echo "NPE Tech Level: [".$techDieRoll."] INT-".$NPEtechLevel."\n";

// explain starting state
echo "- Starting Points: ".($NPEtechLevel+1)."x Total Domestic Product\n";
echo "- Pre-contact exploration: ".$NPEexploration[$NPEtechLevel][0]."/";
echo $NPEexploration[$NPEtechLevel][1]."/".$NPEexploration[$NPEtechLevel][2]."/";
echo $NPEexploration[$NPEtechLevel][3]."/".$NPEexploration[$NPEtechLevel][4]."\n";

// determine NPE exploration state
$explorationRing = calcExploration( $NPEexploration[$NPEtechLevel][0], 6 );
echo "- 1st ring: ".$explorationRing[0]." systems explored.\n";
$exploredCount += array_shift($explorationRing);
echo "[".implode( "], [", $explorationRing )."]\n";
if( $NPEexploration[$NPEtechLevel][1] > 0 )
{
  $explorationRing = calcExploration( $NPEexploration[$NPEtechLevel][1], 12 );
  echo "- 2nd ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  echo "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][2] > 0 )
{
  $explorationRing = calcExploration( $NPEexploration[$NPEtechLevel][2], 18 );
  echo "- 3rd ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  echo "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][3] > 0 )
{
  $explorationRing = calcExploration( $NPEexploration[$NPEtechLevel][3], 24 );
  echo "- 4th ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  echo "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][4] > 0 )
{
  $explorationRing = calcExploration( $NPEexploration[$NPEtechLevel][4], 30 );
  echo "- 5th ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  echo "[".implode( "], [", $explorationRing )."]\n";
}

// Determine number of colonized systems
echo "# of colonized planets: ".(floor($exploredCount/3)+$NPEcolonyCount[$NPEtechLevel]);
echo " (".$NPEcolonyCount[$NPEtechLevel]." + one third of ".$exploredCount.")\n";

// determine location of homeworld
echo "NPE homeworld is ".$NPEtechLevel." sectors from initial contact\n";
echo "NPE worlds have ".$NPEtechLevel." more productivity than indicated on the system generation tables.\n\n";

exit(0);

function twoSix ()
{
  return mt_rand(1,6)+mt_rand(1,6);
}
function oneTen ()
{
  return mt_rand(1,10);
}
function twoTen ()
{
  return mt_rand(1,10)+mt_rand(1,10);
}
function hundred ()
{
  return mt_rand(1,100);
}
function rerollSpecials( $specialMod )
{
  global $systemSpecials;
  $output = '';
  $DieRoll = twoSix() + $specialMod;
  if( $DieRoll < 0 )
    $DieRoll = 0;

  if( $systemSpecials[$DieRoll] == 'reroll' )
  {
    $output .= rerollSpecials( $specialMod );
    $output .= rerollSpecials( $specialMod );
  }
  else if( $systemSpecials[$DieRoll] == '' )
    $output .= "     ".$DieRoll.": No Special\n";
  else
    $output .= "     ".$DieRoll.": ".$systemSpecials[$DieRoll]."\n";

  return $output;
}
function calcExploration( $chance, $numSectors )
{
  $output = array( 0 ); // 0th element is system count
  for( $i=1; $i<=$numSectors; $i++ )
  {
    $DieRoll = hundred();
    $output[ $i ] = $DieRoll;
    if( $DieRoll<=$chance )
      $output[0]++;
  }
  return $output;
}
?>
