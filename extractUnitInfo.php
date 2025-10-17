#!/usr/bin/php -q
<?php
###
# Retrieves from the master unit list in an HTML table
###

if( ! isset($argv[1]) )
{
  echo "\nExtracts an HTML file for the units used in the VBSF game\n\n";
  echo "Called by:\n";
  echo "  ".$argv[0]." EMPIRE\n\n";
  exit(1);
}

###
# Initialization
###
$WRITE_HTML = true; // if true, writes it to an HTML-formatted file
$WRITE_JSON = true; // if true, writes it to a JSON-formatted file
$SHOW_DB_ERRORS = true;
$SHOW_DEBUG_INFO = true;
$EXCLUDE_CONJECTURALS = true; // set to false to allow conjectural and unbuilt variants. Allows campaign units
$EXCLUDE_CMD_COST = true; // set to false to allow the "Command Cost" column
$EXCLUDE_ANTIFTR = true; // set to false to allow the "Anti-Fighter" column
$ONLY_BASIC = true; // set to true to only show the hull, the cost, and any specials
$fileName = "unitOutput"; // the file to write the data to

// Setup the Database so to query the table
// Note: GIT repo does not include this database, nor any other tools to access it
require_once( "/home/www/sfbdb/objects/database.php" ); // load the database object file

$database = Database::giveme();
$output = ""; // final output of units
$EMPIRE="";
$empireID = 0; // DB ID of the empire

if( isset($argv[1]) ) // optional EMPIRE argument
  $EMPIRE = $database->wash( strtolower($argv[1]) );

###
# Get the Empire ID
###
$query = "SELECT ID FROM empires WHERE empire='$EMPIRE'";
$result = $database->genquery( $query, $queryOut );
if( ! $result )
{
  if( $SHOW_DB_ERRORS )
    echo $result."\n";
  echo $queryOut."\n";
  exit(1);
}

$empireID = $queryOut[0]["ID"];

###
# Retrieve all units from the SFBDB database
###

// these are convenience variables to assemble the query for the units out of the database
$excludeCNJ = "";
$queryColumns = "shiptype as unitname,";
$queryColumns .= "round(cbpv/9) as cost,";
$queryColumns .= "yearinsrvc as servicedate,";
$queryColumns .= "hulltype as design,";
if( ! $ONLY_BASIC )
{
  $queryColumns .= "attackfactors as antiship,"; // to break up this string to the first value. Done in post-process
  $queryColumns .= "attackfactors as defval,"; // to break this up to the second or first value. Done in post-process
  $queryColumns .= "cmdrating as cmdrate,";
  if( ! $EXCLUDE_CMD_COST )
    $queryColumns .= "1 as cmdcost,";
}
$assaultAmt = "CASE hulltype WHEN 'BB' THEN 5 WHEN 'DN' THEN 4 WHEN 'DNH' THEN 4 WHEN 'DNW' THEN 4 ";
$assaultAmt .= "WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 3 WHEN 'CA' THEN 2 ";
$assaultAmt .= "WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 ";
$assaultAmt .= "WHEN 'HDW' THEN 1 WHEN 'DW' THEN 1 WHEN 'NDD' THEN 1 WHEN 'DDH' THEN 1 WHEN 'DD' THEN 1 ";
$assaultAmt .= "WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 1 WHEN 'FFH' THEN 1 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 ";
$assaultAmt .= "WHEN 'FT' THEN 1 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 3 WHEN 'SAux' THEN 2 END";
$supplyAmt = "CASE hulltype WHEN 'BB' THEN 5 WHEN 'DN' THEN 4 WHEN 'DNH' THEN 4 WHEN 'DNW' THEN 4 ";
$supplyAmt .= "WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 3 WHEN 'CA' THEN 2 ";
$supplyAmt .= "WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 ";
$supplyAmt .= "WHEN 'HDW' THEN 1 WHEN 'DW' THEN 1 WHEN 'NDD' THEN 1 WHEN 'DDH' THEN 1 WHEN 'DD' THEN 1 ";
$supplyAmt .= "WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 1 WHEN 'FFH' THEN 1 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 ";
$supplyAmt .= "WHEN 'FT' THEN 1 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 3 WHEN 'SAux' THEN 2 END";
$special = "CONCAT_WS( ', ',";
$special .= "IF(find_in_set('t',morenotes) OR find_in_set('tanks',morenotes),CONCAT('Assault(',$assaultAmt,')'),NULL),"; // assault
$special .= "IF(find_in_set('al',notes) OR find_in_set('pl',notes) OR sizeclass=6,'Atmospheric',NULL),"; // atmospheric
$special .= "IF(find_in_set('v',morenotes) OR find_in_set('v10',morenotes) OR find_in_set('v14',morenotes)"; // carrier
$special .= " OR find_in_set('v15',morenotes) OR find_in_set('vh',morenotes),CONCAT('Carrier(',ftrs,')'),NULL),"; // carrier
$special .= "IF(strategicnotes like '%command %','Command',NULL),"; // command. note the trailing space
$special .= "IF(find_in_set('f',notes) OR find_in_set('x',morenotes),'Fast',NULL),"; // fast
$special .= "IF(hulltype='pol' OR shiptype='POL','Police',NULL),"; // police
$special .= "IF(shiptype='Q-S' OR shiptype='Q-L','Q-Ship',NULL),"; // Q-ship
$special .= "IF(find_in_set('sc',morenotes) OR find_in_set('(YG24.0)',notes),"; // scout
$special .= "CONCAT('Scout(',if(locate('EW=',strategicnotes),substr(strategicnotes,locate('EW=',strategicnotes)+3,1),'1'),')'),NULL),"; // scout
$special .= "IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR "; // stealth
$special .= "(empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes),'Stealth',NULL),"; // stealth
$special .= "IF(find_in_set('ml',notes) OR yearinsrvc<120 OR movecost='sub','Slow',NULL),"; // slow
$special .= "IF(find_in_set('tg',morenotes),CONCAT('Supply(',$supplyAmt,')'),NULL),"; // [Tug] Supply
$special .= "IF( PFs>0,CONCAT('Tender(',PFs,')'),NULL)"; // Tender
$special .= ")";

$queryFtrColumns = "shiptype as unitname,";
$queryFtrColumns .= "round(cbpv/3.5) as cost,";
$queryFtrColumns .= "yearinsrvc as servicedate,";
$queryFtrColumns .= "IF(breakdown=1, 'LF', IF(breakdown=2, 'HF', '' )) as design,";
if( ! $ONLY_BASIC )
{
  $queryFtrColumns .= "IF(breakdown=1, '1', IF(breakdown=2, '2', '' )) as antiship,";
  $queryFtrColumns .= "IF(breakdown=1, '1', IF(breakdown=2, '2', '' )) as defval,";
  $queryFtrColumns .= "'N/A' as cmdrate,";
  if( ! $EXCLUDE_CMD_COST )
    $queryFtrColumns .= "'N/A' as cmdcost,";
  $queryFtrColumns .= "'N/A' as basing,";
}
$queryFtrColumns .= "'' as special";

$queryPfColumns = "shiptype as unitname,";
$queryPfColumns .= "round(cbpv/3.5) as cost,";
$queryPfColumns .= "yearinsrvc as servicedate,";
$queryPfColumns .= "'AB' as design,";
if( ! $ONLY_BASIC )
{
  $queryPfColumns .= "3 as antiship,";
  $queryPfColumns .= "2 as defval,";
  $queryPfColumns .= "'N/A' as cmdrate,";
  if( ! $EXCLUDE_CMD_COST )
    $queryPfColumns .= "'N/A' as cmdcost,";
}
$queryPfColumns .= "'' as special";

// the command to allow only the hulltypes we want in the database
$queryEmpireHulls = "FIELD(HullType,'BB','DN','DNH','DNW','DNL','BCH','BC','CCH','CA','TUG','CL',";
$queryEmpireHulls .= "'NCA','CWH','CW','HDW','DW','NDD','DDH','DD','FFW','NFF','FFH','FF','POL')";
$queryEmpireHulls .= " OR (empire='24' and hulltype='FT')"; // If an Orion, allow some freighters
$queryEmpireHulls .= " OR (empire='34' and hulltype='BOOM')"; // if a Tholian, allow hullType "BOOM"
$queryEmpireHulls .= " OR (empire='37' and (hulltype='LAux' OR hulltype='SAux'))"; // If a WYN, allow Auxiliaries

// exclude refits, tournament ships, and minesweepers
$excludeHulls = "NOT find_in_set('r',notes) AND warshipstatus<>'TOUR' AND NOT find_in_set('ms',notes) AND yearinsrvc>120";
if( $EXCLUDE_CONJECTURALS )
  $excludeCNJ = "AND NOT find_in_set('unv',morenotes) AND NOT find_in_set('cnj',notes) AND warshipstatus<>'CNJ' AND warshipstatus<>'IMP' AND warshipstatus<>'UNV'";
// Exclude EW and Megapack fighters. Don't exclude the 'D', 'K', or 'C' refits (would capture 'real' ftrs)
$excludeFtrs = "NOT right(shiptype,1)='E' AND NOT right(shiptype,1)='M' AND (breakdown=1 OR breakdown=2) $excludeCNJ";
// Exclude PF variants
$excludePFs = "hulltype='pf' and cbpv<50 $excludeCNJ";

// Natural Sort the YearInSrvc
$natSortYIS = "length(yearinsrvc),yearinsrvc";

// the full query
$unitQuery = "SELECT $queryColumns $special as special FROM units WHERE ($excludeHulls $excludeCNJ) AND ($queryEmpireHulls) AND empire='$empireID' ORDER BY $natSortYIS,cost DESC";
$pfUnitQuery = "SELECT $queryPfColumns FROM units WHERE hulltype='pf' AND $excludePFs AND empire='$empireID' ORDER BY $natSortYIS,cost DESC";
$ftrUnitQuery = "SELECT $queryFtrColumns FROM units WHERE hulltype='fighter' AND $excludeFtrs AND empire='$empireID' ORDER BY $natSortYIS, cost DESC";

//echo($unitQuery.";\n");exit;

/*
SELECT shiptype as unitname,
   round(cbpv/9) as cost, 
   CONCAT_WS( ', ',IF(find_in_set('t',morenotes) OR find_in_set('tanks',morenotes),CONCAT('Assault(',CASE hulltype WHEN 'BB' THEN 5 WHEN 'DN' THEN 4 WHEN 'DNH' THEN 4 WHEN 'DNW' THEN 4 WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 3 WHEN 'CA' THEN 2 WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 WHEN 'HDW' THEN 1 WHEN 'DW' THEN 1 WHEN 'NDD' THEN 1 WHEN 'DDH' THEN 1 WHEN 'DD' THEN 1 WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 1 WHEN 'FFH' THEN 1 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 WHEN 'FT' THEN 1 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 3 WHEN 'SAux' THEN 2 END,')'),NULL),IF(find_in_set('al',notes) OR find_in_set('pl',notes) OR sizeclass=6,'Atmospheric',NULL),IF(find_in_set('v',morenotes) OR find_in_set('v10',morenotes) OR find_in_set('v14',morenotes) OR find_in_set('v15',morenotes) OR find_in_set('vh',morenotes),CONCAT('Carrier(',ftrs,')'),NULL),IF(strategicnotes like '%command %','Command',NULL),IF(find_in_set('f',notes) OR find_in_set('x',morenotes),'Fast',NULL),IF(hulltype='pol' OR shiptype='POL','Police',NULL),IF(shiptype='Q-S' OR shiptype='Q-L','Q-Ship',NULL),IF(find_in_set('sc',morenotes) OR find_in_set('(YG24.0)',notes),CONCAT('Scout(',if(locate('EW=',strategicnotes),substr(strategicnotes,locate('EW=',strategicnotes)+3,1),'1'),')'),NULL),IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR (empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes),'Stealth',NULL),IF(find_in_set('ml',notes) OR yearinsrvc<120 OR movecost='sub','Slow',NULL),IF(find_in_set('tg',morenotes),CONCAT('Supply(',CASE hulltype WHEN 'BB' THEN 5 WHEN 'DN' THEN 4 WHEN 'DNH' THEN 4 WHEN 'DNW' THEN 4 WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 3 WHEN 'CA' THEN 2 WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 WHEN 'HDW' THEN 1 WHEN 'DW' THEN 1 WHEN 'NDD' THEN 1 WHEN 'DDH' THEN 1 WHEN 'DD' THEN 1 WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 1 WHEN 'FFH' THEN 1 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 WHEN 'FT' THEN 1 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 3 WHEN 'SAux' THEN 2 END,')'),NULL),IF( PFs>0,CONCAT('Tender(',PFs,')'),NULL),) as special
   FROM units
   WHERE (NOT find_in_set('r',notes)
      AND warshipstatus<>'TOUR' 
      AND NOT find_in_set('ms',notes) 
      AND yearinsrvc>130 
      AND NOT find_in_set('unv',morenotes) AND NOT find_in_set('cnj',notes) AND warshipstatus<>'CNJ' AND warshipstatus<>'IMP' AND warshipstatus<>'UNV') 
      AND (FIELD(HullType,'BB','DN','DNH','DNW','DNL','BCH','BC','CCH','CA','TUG','CL','NCA','CWH','CW','HDW','DW','NDD','DDH','DD','FFW','NFF','FFH','FF','POL') OR (empire='24' and hulltype='FT') OR (empire='34' and hulltype='BOOM') OR (empire='37' and (hulltype='LAux' OR hulltype='SAux'))) 
      AND empire='14' 
   ORDER BY length(yearinsrvc),yearinsrvc, cost DESC;

Resultant HullTypes:
'BB','DN','DNH','DNW','DNL','BCH','BC','CCH','CA','TUG','CL','NCA','CWH','CW','HDW','DW','NDD','DDH','DD','FFW','NFF','FFH','FF','POL','FT','BOOM','LAux','SAux'
*/

// make the actual unit query (naval ships)
$result = $database->genquery( $unitQuery, $queryNavyOut );

if( ! $result )
{
  if( $SHOW_DB_ERRORS )
    echo $result."\n";
  echo $queryNavyOut."\n";
  exit(1);
}

$hdwEntries = array(); // array for the HDW-variant entries


if( ! $ONLY_BASIC )
{
  // scrape the DefVal and Anti-ship vals in PHP
  foreach( $queryNavyOut as &$row )
  {
    $result = preg_match( "/^(\d+)-?(\d+)?/", $row["antiship"], $matches );

    // Make fake SIDCORS entry if it is not present, above.
    if( ! $result )
    {
      switch( $row["design"] )
      {
      case 'BB':
        $row["antiship"] = 20;
        $row["defval"] = 20;
        break;
      case 'DN':
        $row["antiship"] = 12;
        $row["defval"] = 12;
        break;
      case 'DNH':
        $row["antiship"] = 14;
        $row["defval"] = 14;
        break;
      case 'DNW':
        $row["antiship"] = 12;
        $row["defval"] = 12;
        break;
      case 'DNL':
        $row["antiship"] = 11;
        $row["defval"] = 11;
        break;
      case 'BCH':
        $row["antiship"] = 11;
        $row["defval"] = 11;
        break;
      case 'BC':
        $row["antiship"] = 10;
        $row["defval"] = 10;
        break;
      case 'CCH':
        $row["antiship"] = 9;
        $row["defval"] = 9;
        break;
      case 'CA':
      case 'NCA':
        $row["antiship"] = 8;
        $row["defval"] = 8;
        break;
      case 'TUG':
        $row["antiship"] = 4;
        $row["defval"] = 8;
        break;
      case 'CL':
      case 'DW':
        $row["antiship"] = 6;
        $row["defval"] = 6;
        break;
      case 'CWH':
        $row["antiship"] = 10;
        $row["defval"] = 10;
        break;
      case 'CW':
      case 'NDD':
        $row["antiship"] = 7;
        $row["defval"] = 7;
        break;
      case 'HDW':
        $row["antiship"] = 6;
        $row["defval"] = 7;
        break;
      case 'DDH':
        $row["antiship"] = 8;
        $row["defval"] = 7;
        break;
      case 'DD':
      case 'NFF':
      case 'FFH':
        $row["antiship"] = 5;
        $row["defval"] = 5;
        break;
      case 'FFW':
        $row["antiship"] = 3;
        $row["defval"] = 5;
        break;
      case 'FF':
      case 'POL':
        $row["antiship"] = 4;
        $row["defval"] = 4;
        break;
      case 'FT':
      case 'BOOM':
        $row["antiship"] = 1;
        $row["defval"] = 2;
        break;
      case 'LAux':
        $row["antiship"] = 1;
        $row["defval"] = 4;
        break;
      case 'SAux':
        $row["antiship"] = 0;
        $row["defval"] = 2;
        break;
      }
      continue; // skip the rest of this loop
    }

    // assign the attack val
    $row["antiship"] = $matches[1];

    // assign the toughness val
    if( isset($matches[2]) )
      $row["defval"] = $matches[2];
    else
      $row["defval"] = $matches[1]; // assign the toughness val
  }
  unset($row); // getting a double-entry for the last item
}

/*
Insert the rows for HDW variants
• HDW - Nothing re-assigned. The APRs are still APRs. The NWOs are empty (void). The Option Mounts are also empty. Filling these cost CO points (as above.)
• HDW-C - +12 BPV +10-ish. A command variant. The Option Mounts are filled with some common weapon to the empire and the NWOs are filled with Flag Bridge to bring the command rating of the ship up to 10.
• HDW-H - +20 BPV. A Heavy-fighter carrier. Filled with 10 additional shuttle boxes with fighter ready racks and designed for double-space fighters.
• HDW-K - +10-ish BPV. A "killer" variant. NWOs are un-assigned and Option Mounts are filled with the same weapon as the command variant.
• HDW-P - +30 BPV. A PF Tender. Each has 2 sensor channels, 6 mech links, and 4 repair.
• HDW-S - +20 BPV. Scout. It merely has 2 sensor channels. NWOs are void.
• HDW-V - +20 BPV. A carrier variant. Like the Heavy-carrier variant, has 10 more fighter boxes and is designed for single-space fighters. 
*/
/*
foreach( $queryNavyOut as &$row )
{
  if( $row["design"] == "HDW" )
  {
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-C","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> 10,"basing"=>$row["basing"],"special"=>"Command"
                           );
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-H","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> $row["cmdrate"],"basing"=>($row["basing"]/2+5)."H","special"=>"Carrier"
                           );
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-K","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> $row["cmdrate"],"basing"=> $row["basing"],"special"=>""
                           );
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-P","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> $row["cmdrate"],"basing"=> $row["basing"]."+6SH","special"=>"Scout"
                           );
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-S","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> $row["cmdrate"],"basing"=> $row["basing"],"special"=>"Scout(2)"
                           );
    $hdwEntries[] = array( "unitname"=> $row["unitname"]."-V","servicedate"=> $row["servicedate"],"design"=> "HDW",
                             "cost"=>$row["cost"] ,"maint"=> $row["maint"],"antiship"=> $row["antiship"],"defval"=> $row["defval"],
                             "cmdcost"=> 1,"cmdrate"=> $row["cmdrate"],"basing"=>($row["basing"]+10),"special"=>"Carrier"
                           );
  }
}
unset($row); // getting a double-entry for the last item

$queryNavyOut = array_merge( $queryNavyOut, $hdwEntries );
*/

// Increase the cost due to specials
foreach( $queryNavyOut as &$row )
{
  $commaCount = 0;

  // skip if there are no specials
  if( empty( $row["special"] ) )
    continue;

  $commaCount = substr_count( $row["special"], "," );

  // skip the "Slow" special
  if( str_contains( $row["special"], "Slow" ) )
    $commaCount--;

   // Skip if all the special traits are excepted
   if( $commaCount <= -1 )
    continue;

  $row["cost"] += floor($commaCount / 2);
}
unset($row); // getting a double-entry for the last item

// Modify heavy-fighter counts so that they are regular-fighter counts
foreach ($queryNavyOut as &$unit) {
    if (isset($unit['special'])) {
        // Handle pattern: Carrier(<num>H)
        $unit['special'] = preg_replace_callback(
            '/Carrier\((\d+)H\)/',
            function ($matches) {
                $num = (int)$matches[1];
                return 'Carrier(' . ($num * 2) . ')';
            },
            $unit['special']
        );

        // Handle pattern: Carrier(<num>+<num>H)
        $unit['special'] = preg_replace_callback(
            '/Carrier\((\d+)\+(\d+)H\)/',
            function ($matches) {
                $a = (int)$matches[1];
                $b = (int)$matches[2];
                return 'Carrier(' . ($a + ($b * 2)) . ')';
            },
            $unit['special']
        );
    }
}
unset($unit);

// Add bases
$units = [
    ["Early Base Station",    65,  "BSE", 15, 8,  8,  1, 8,  "Fixed"],
    ["Early Base Station-I",  105, "BSE", 16, 10, 10,  1, 8,  "Fixed, Supply Depot"],
    ["Base Station",      120, "BS",  18, 10, 10, 1, 8,  "Fixed, Scout(1), Supply Depot"],
    ["Base Station-I",    160, "BS",  18, 10, 10, 1, 8,  "Carrier(12), Fixed, Scout(2), Supply Depot"],
    ["Base Station-II",   185, "BS",  18, 10, 10, 1, 8,  "Carrier(12), Fixed, Scout(2), Supply Depot"],
    ["Battle Station",    130, "BATS",24, 12, 12, 1, 9,  "Carrier(12), Fixed, Scout(2), Supply Depot"],
    ["Battle Station-I",  170, "BATS",24, 12, 12, 1, 9,  "Carrier(12), Fixed, Scout(4), Supply Depot"],
    ["Battle Station-II", 190, "BATS",24, 12, 12, 1, 9,  "Carrier(12), Fixed, Scout(4), Supply Depot"],
    ["StarBase",          140, "SB",  36, 36, 36, 1, 10, "Carrier(24), Fixed, Scout(6), Supply Depot"],
    ["StarBase-I",        180, "SB",  36, 36, 36, 1, 10, "Carrier(24), Fixed, Scout(6), Supply Depot"],
    ["Mobile Base",       140, "MB",  9,  8,  8,  1, 6,  "Carrier(6), Fixed, Scout(1), Supply Depot"],
    ["Mobile Base-I",     180, "MB",  9,  8,  8,  1, 6,  "Carrier(6), Fixed, Scout(1), Supply Depot"],
    ["Convoy",            65,  "Convoy",    20, 0,  10, 1, 0,  "Civilian, Convoy"],
    ["Convoy-I",          105, "Convoy",    20, 0,  10, 1, 0,  "Civilian, Convoy"],
    ["Convoy-II",         145, "Convoy",    20, 0,  10, 1, 0,  "Civilian, Convoy"],
    ["Convoy-III",        185, "Convoy",    20, 0,  10, 1, 0,  "Civilian, Convoy"],
    ["Shipyard",          65,  "Shipyard",20, 0,  10, 1, 0, "Civilian, Shipyard, Fixed"],
    ["Shipyard-I",        105,  "Shipyard",20, 0,  10, 1, 0, "Civilian, Shipyard, Fixed"],
    ["Shipyard-II",       145, "Shipyard",20, 0,  10, 1, 0, "Civilian, Shipyard, Fixed"],
    ["Shipyard-III",      185, "Shipyard",20, 0,  10, 1, 0, "Civilian, Shipyard, Fixed"],
    ["Supply Depot",      65,  "Depot",20,0,10,1,0,"Civilian, Fixed, Supply Depot"],
    ["Supply Depot-I",    105, "Depot",20,0,10,1,0,"Civilian, Fixed, Supply Depot"],
    ["Supply Depot-II",   145, "Depot",20,0,10,1,0,"Civilian, Fixed, Supply Depot"],
    ["Supply Depot-III",  185, "Depot",20,0,10,1,0,"Civilian, Fixed, Supply Depot"],
];

$queryNavyOut = array_merge(
    $queryNavyOut,
    array_map(function($u) {
    return [
        "unitname"    => $u[0],
        "servicedate" => $u[1],
        "design"      => $u[2],
        "cost"        => $u[3],
        "antiship"    => $u[4],
        "defval"      => $u[5],
        "cmdcost"     => $u[6],
        "cmdrate"     => $u[7],
        "special"     => $u[8]
    ];
    }, $units)
);
// Web as Fortification
/*
if( strtolower($EMPIRE) == "tholian" )
  $queryNavyOut[] = array( "unitname"=> "Pre-laid web","servicedate"=> 83,"design"=> "Web",
                           "cost"=> 1/3,"maint"=> "1/12","antiship"=> 0,"defval"=> 0,
                           "cmdcost"=> 0,"cmdrate"=> 0,"basing"=>0,"special"=>""
                         );
*/
print_r($queryNavyOut);exit();

// make the actual unit query (PFs)
$result = $database->genquery( $pfUnitQuery, $queryPfOut );

if( ! $result )
{
  if( $SHOW_DB_ERRORS )
    echo $result."\n";
  echo $pfUnitQuery."\n";
  exit(1);
}

$queryFtrOut = array_values( $queryPfOut );

$queryOut = array_merge( $queryNavyOut, $queryPfOut );

// make the actual unit query (Fighters)
$result = $database->genquery( $ftrUnitQuery, $queryFtrOut );

if( ! $result )
{
  if( $SHOW_DB_ERRORS )
    echo $result."\n";
  echo $queryFtrOut."\n";
  exit(1);
}

// Scrape out the 'D', 'K', 'B', or 'C' ftr refits
foreach( $queryFtrOut as $key=>$row )
{
  $result = preg_match( "/(\w)([a-zA-Z+])$/", $row["unitname"], $matches ); // get the rightmost letter

  // skip if the match failed
  if( ! $result )
    continue;

  if( $matches[2] == "B" || $matches[2] == "C" || $matches[2] == "D" || $matches[2] == "K" || $matches[2] == "+" )
    unset( $queryFtrOut[$key] );
}
$queryFtrOut = array_values( $queryFtrOut );

$queryOut = array_merge( $queryOut, $queryFtrOut );

// Add Infantry
$units = [
    ["Militia",       65,  "Ground Unit",1,4,4,"N/A","N/A","Garrison"],
    ["Militia-I",     105, "Ground Unit",1,4,4,"N/A","N/A","Garrison"],
    ["Militia-II",    145, "Ground Unit",1,4,4,"N/A","N/A","Garrison"],
    ["Militia-III",   185, "Ground Unit",1,4,4,"N/A","N/A","Garrison"],
    ["Police",        65,  "Ground Unit",1,2,6,"N/A","N/A","Peacekeeper"],
    ["Police-I",      105, "Ground Unit",1,2,6,"N/A","N/A","Peacekeeper"],
    ["Police-II",     145, "Ground Unit",1,2,6,"N/A","N/A","Peacekeeper"],
    ["Police-III",    185, "Ground Unit",1,2,6,"N/A","N/A","Peacekeeper"],
    ["Prime Team",    65,  "Ground Unit",2,6,4,"N/A","N/A","Special Forces"],
    ["Prime Team-I",  105, "Ground Unit",2,6,4,"N/A","N/A","Special Forces"],
    ["Prime Team-II", 145, "Ground Unit",2,6,4,"N/A","N/A","Special Forces"],
    ["Prime Team-III",185, "Ground Unit",2,6,4,"N/A","N/A","Special Forces"],
    ["Light Infantry",     65,  "Ground Unit",1,5,3,"N/A","N/A","Marines"],
    ["Light Infantry-I",   105, "Ground Unit",1,5,3,"N/A","N/A","Marines"],
    ["Light Infantry-II",  145, "Ground Unit",1,5,3,"N/A","N/A","Marines"],
    ["Light Infantry-III", 185, "Ground Unit",1,5,3,"N/A","N/A","Marines"],
    ["Light Armor",        65,  "Ground Unit",2,7,6,"N/A","N/A",""],
    ["Light Armor-I",      105, "Ground Unit",2,7,6,"N/A","N/A",""],
    ["Light Armor-II",     145, "Ground Unit",2,7,6,"N/A","N/A",""],
    ["Light Armor-III",    185, "Ground Unit",2,7,6,"N/A","N/A",""],
    ["Heavy Armor",        65,  "Ground Unit",3,9,9,"N/A","N/A",""],
    ["Heavy Armor-I",      105, "Ground Unit",3,9,9,"N/A","N/A",""],
    ["Heavy Armor-II",     145, "Ground Unit",3,9,9,"N/A","N/A",""],
    ["Heavy Armor-III",    185, "Ground Unit",3,9,9,"N/A","N/A",""],
];

$queryOut = array_merge(
    $queryOut,
    array_map(function($u) {
    return [
        "unitname"    => $u[0],
        "servicedate" => $u[1],
        "design"      => $u[2],
        "cost"        => $u[3],
        "antiship"    => $u[4],
        "defval"      => $u[5],
        "cmdcost"     => $u[6],
        "cmdrate"     => $u[7],
        "special"     => $u[8]
    ];
    }, $units)
);

$count = count($queryOut);
//$database->close();
unset( $database );

###
# Write the output file
###

###
# For HTML-formatted output
###

// write the header
$output = "<a name='".strtolower($EMPIRE)."'></a>\n<h3 class='darkhead' onclick='flip(this)'>".strtoupper($EMPIRE)."</h3>\n<table class='visually-hidden zebra'>\n<thead>\n<tr><th>Unit<br>Name</th><th>Design</th><th>Service<br>Date</th><th>Cost</th>";
if( ! $ONLY_BASIC )
{
  $output .= "<th>Anti-Ship<br>Rate</th>";
  if( ! $EXCLUDE_ANTIFTR )
    $output .= "<th>Anti-Ftr<br>Rate</th>";
  $output .= "<th>Defense<br>Value</th><th>Cmd<br>Rate</th>";
  if( ! $EXCLUDE_CMD_COST )
    $output .= "<th>Cmd<br>Cost</th>";
}
$output .= "<th>Special</th></tr>\n</thead>\n<tbody>\n";

// write the body
foreach( $queryOut as $row )
{
  $output .= "<tr><td>".$row["unitname"];
  $output .= "</td><td>".$row["design"];
  $output .= "</td><td>".$row["servicedate"];
  $output .= "</td><td>".$row["cost"];
  if( ! $ONLY_BASIC )
  {
    $output .= "</td><td>".$row["antiship"];
    if( ! $EXCLUDE_CMD_COST )
      $output .= "</td><td>".$row["antiship"]; // Anti-Fighter same value as Anti-Ship
    $output .= "</td><td>".$row["defval"];
    $output .= "</td><td>".$row["cmdrate"];
    if( ! $EXCLUDE_CMD_COST )
      $output .= "</td><td>".$row["cmdcost"];
  }
  $output .= "</td><td>".$row["special"]."</td></tr>\n";
}

// add the footer
$output .= "</tbody><tfoot>\n<tr><th>Unit<br>Name</th><th>Design</th><th>Service<br>Date</th><th>Cost</th>";
if( ! $ONLY_BASIC )
{
  $output .= "<th>Anti-Ship<br>Rate</th>";
  if( ! $EXCLUDE_ANTIFTR )
    $output .= "<th>Anti-Ftr<br>Rate</th>";
  $output .= "<th>Defense<br>Value</th><th>Cmd<br>Rate</th>";
  if( ! $EXCLUDE_CMD_COST )
    $output .= "<th>Cmd<br>Cost</th>";
}
$output .= "<th>Special</th></tr>\n</tfoot>\n</table>\n<!-- ************************************************************************** -->\n";

// write out the output file
if( $WRITE_HTML )
  $result = file_put_contents( $fileName.".html", $output ); // $output is formatted to HTML
unset( $output );

if( $result === false )
  echo "Could not write file '".$fileName.".html'\n";
else
  echo "Wrote $count entries to '".$fileName.".html'\n";

###
# For JSON-formatted output
###

// write the header
$output = "var unitList = [\n";

// write the body
foreach( $queryOut as $row )
{
  $output .= '   {"ship":"'.$row["unitname"];
  $output .= '","yis":'.$row["servicedate"];
  $output .= ',"design":"'.$row["design"];
  $output .= '","cost":'.$row["cost"];
  if( ! $ONLY_BASIC )
  {
    $output .= ',"cmdrate":'.$row["cmdrate"];
    if( ! $EXCLUDE_CMD_COST )
      $output .= ',"cmdcost":'.$row["cmdcost"];
  }
  $output .= ',"notes":"'.$row["special"]."\"},\n";
}
$output = trim( $output, ",\n" );
// add the footer
$output .= "];\n";


// write out the output file
if( $WRITE_HTML )
  $result = file_put_contents( $fileName.".json", $output ); // $output is formatted to HTML
unset( $output, $queryOut );

if( $result === false )
  echo "Could not write file '".$fileName.".json'\n";
else
  echo "Wrote $count entries to '".$fileName.".json'\n";

exit(0); // all done

?>
