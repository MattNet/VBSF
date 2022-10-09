<?php
###
# Generates the system data
# PHP interface
###

function VBAMExploration ()
{

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
                       11=>'Capacity+2', 12=>'reroll', 13=>'reroll', 14=>'reroll'
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
$outputString = "";
$outputData = array("name"=>"", "census"=>0, "owner"=>"General",
                    "morale"=>0, "raw"=>0,"prod"=>0, "capacity"=>0, 
                    "intel"=>0, "fixed"=>array(), "notes"=>""
                   );

// is there a system in this sector?
$result = twoSix();
$outputString .= "Sector Generation:\n- System in sector? [".$result."] ";
if( ! $systemTable[ $result ] )
{
  $outputString .= "No system\n\n";
  $outputData["owner"] = "";
  $outputData["notes"] = "Empty System";
  array_unshift( $outputData, $outputString );
  return( $outputData );
}
else
  $outputString .= "System present\n\n";

// Determine system importance
$result = twoSix();
$importance = $systemImportance[ $result ];
$outputString .= "System Generation:\n- System importance: [".$result."] ";
$text = ucfirst($importance);
$outputString .= $text."\n";

// Determine system stats
$outputString .= "- Census: ".$systemTraits[ $importance ]['census'];
$outputString .= " Morale: ".$systemTraits[ $importance ]['morale'];
$outputString .= " RAW: ".$systemTraits[ $importance ]['raw'];
$outputString .= " Productivity: ".$systemTraits[ $importance ]['productivity'];
$outputString .= " Capacity: ".$systemTraits[ $importance ]['capacity']."\n";
$outputData["raw"] = $systemTraits[ $importance ]['raw'];
$outputData["capacity"] = $systemTraits[ $importance ]['capacity'];

// Determine special traits
$text = exploreRerollSpecials( $systemSpecialMod[$importance], $systemSpecials );
$outputString .= "- System special traits: \n".$text."\n";
if( strpos( $text, "RAW+" ) !== false )
{
  $outputData["raw"] += intval( substr( $text, -2 ) );
}
if( strpos( $text, "Capacity+" ) !== false )
{
  $outputData["capacity"] += intval( substr( $text, -2 ) );
}

// Determine terrain
$result = hundred();
$outputString .= "Terrain generation chance is ".$terrainChance."%. Rolled [".$result."] ";
if( $terrainChance < $result )
{
  $outputString .= "nothing special: Class-M planet.\n\n";
}
else
{
  $outputString .= "and [";
  $result = oneTen();
  $outputString .= $result."] ".$terrainChart[ $result ].".\n\n";
  $outputData["notes"] = $terrainChart[ $result ];
}

// determine NPE activation
$result = hundred();
$outputString .= "NPE generation chance is ".$NPEchance."%. Rolled [";
if( $NPEchance < $result )
{
  $outputString .= $result."] No NPE\n\n";
  array_unshift( $outputData, $outputString );
  return( $outputData );
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
  $outputString .= "NPE Tech Level: [".$techDieRoll."] Too low for meaningful opponent\n\n";
  array_unshift( $outputData, $outputString );
  return( $outputData );
}
$outputData["owner"] = "NPE";

// Determine NPE stats
$result = round((hundred()+hundred())/2);
$outputString .= "- Aggressiveness (AG): [".$result."]\n";
$result = round((hundred()+hundred())/2);
$outputString .= "- Integrity (IN): [".$result."]\n";
$result = round((hundred()+hundred())/2);
$outputString .= "- Xenophobia (XE): [".$result."]\n";

// Apply NPE tech level
$outputString .= "NPE Tech Level: [".$techDieRoll."] INT-".$NPEtechLevel."\n";

// explain starting state
$outputString .= "- Starting Points: ".($NPEtechLevel+1)."x Total Domestic Product\n";
$outputString .= "- Pre-contact exploration: ".$NPEexploration[$NPEtechLevel][0]."/";
$outputString .= $NPEexploration[$NPEtechLevel][1]."/".$NPEexploration[$NPEtechLevel][2]."/";
$outputString .= $NPEexploration[$NPEtechLevel][3]."/".$NPEexploration[$NPEtechLevel][4]."\n";

// determine NPE exploration state
$explorationRing = exploreCalcExploration( $NPEexploration[$NPEtechLevel][0], 6 );
$outputString .= "- 1st ring: ".$explorationRing[0]." systems explored.\n";
$exploredCount += array_shift($explorationRing);
$outputString .= "[".implode( "], [", $explorationRing )."]\n";
if( $NPEexploration[$NPEtechLevel][1] > 0 )
{
  $explorationRing = exploreCalcExploration( $NPEexploration[$NPEtechLevel][1], 12 );
  $outputString .= "- 2nd ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  $outputString .= "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][2] > 0 )
{
  $explorationRing = exploreCalcExploration( $NPEexploration[$NPEtechLevel][2], 18 );
  $outputString .= "- 3rd ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  $outputString .= "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][3] > 0 )
{
  $explorationRing = exploreCalcExploration( $NPEexploration[$NPEtechLevel][3], 24 );
  $outputString .= "- 4th ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  $outputString .= "[".implode( "], [", $explorationRing )."]\n";
}
if( $NPEexploration[$NPEtechLevel][4] > 0 )
{
  $explorationRing = exploreCalcExploration( $NPEexploration[$NPEtechLevel][4], 30 );
  $outputString .= "- 5th ring: ".$explorationRing[0]." systems explored.\n";
  $exploredCount += array_shift($explorationRing);
  $outputString .= "[".implode( "], [", $explorationRing )."]\n";
}

// Determine number of colonized systems
$outputString .= "# of colonized planets: ".(floor($exploredCount/3)+$NPEcolonyCount[$NPEtechLevel]);
$outputString .= " (".$NPEcolonyCount[$NPEtechLevel]." + one third of ".$exploredCount.")\n";

// determine location of homeworld
$outputString .= "NPE homeworld is ".$NPEtechLevel." sectors from initial contact\n";
$outputString .= "NPE worlds have ".$NPEtechLevel." more productivity than indicated on the system generation tables.\n\n";

array_unshift( $outputData, $outputString );
return( $outputData );
}

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
function exploreRerollSpecials( $specialMod, $specialsArray )
{
  $output = '';
  $DieRoll = twoSix() + $specialMod;
  if( $DieRoll < 0 )
    $DieRoll = 0;

  if( $specialsArray[$DieRoll] == 'reroll' )
  {
    $output .= exploreRerollSpecials( $specialMod, $specialsArray );
    $output .= exploreRerollSpecials( $specialMod, $specialsArray );
  }
  else if( $specialsArray[$DieRoll] == '' )
    $output .= "     ".$DieRoll.": No Special\n";
  else
    $output .= "     ".$DieRoll.": ".$specialsArray[$DieRoll]."\n";

  return $output;
}
function exploreCalcExploration( $chance, $numSectors )
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
