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
$SHOW_DB_ERRORS = true;
$SHOW_DEBUG_INFO = true;
$EXCLUDE_CONJECTURALS = true; // set to false to allow conjectural and unbuilt variants. Allows campaign units
$EXCLUDE_CMD_COST = true; // set to false to allow the "Command Cost" column
$EXCLUDE_ANTIFTR = true; // set to false to allow the "Anti-Fighter" column
$fileName = "unitOutput.html"; // the file to write the data to

// Setup the Database so to query the table
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
$queryColumns .= "yearinsrvc as servicedate,";
$queryColumns .= "HullType as design,";
$queryColumns .= "CASE hulltype WHEN 'BB' THEN 14 WHEN 'DN' THEN 10 WHEN 'DNH' THEN 11 WHEN 'DNW' THEN 11 ";
$queryColumns .= "WHEN 'DNL' THEN 9 WHEN 'BCH' THEN 9 WHEN 'BC' THEN 8 WHEN 'CCH' THEN 8 WHEN 'CA' THEN 7 ";
$queryColumns .= "WHEN 'TUG' THEN 7 WHEN 'CL' THEN 6 WHEN 'NCA' THEN 7 WHEN 'CWH' THEN 8 WHEN 'CW' THEN 7 ";
$queryColumns .= "WHEN 'HDW' THEN 6 WHEN 'DW' THEN 5 WHEN 'NDD' THEN 5 WHEN 'DDH' THEN 6 WHEN 'DD' THEN 5 ";
$queryColumns .= "WHEN 'FFW' THEN 4 WHEN 'NFF' THEN 4 WHEN 'FFH' THEN 5 WHEN 'FF' THEN 4 WHEN 'POL' THEN 4 ";
$queryColumns .= "WHEN 'FT' THEN 3 WHEN 'BOOM' THEN 3 WHEN 'LAux' THEN 5 WHEN 'SAux' THEN 4 END as cost,"; // cost
$queryColumns .= "CASE hulltype WHEN 'BB' THEN '3/2' WHEN 'DN' THEN '2/2' WHEN 'DNH' THEN '3/2' ";
$queryColumns .= "WHEN 'DNW' THEN '2/2' WHEN 'DNL' THEN '3/3' WHEN 'BCH' THEN '2/2' WHEN 'BC' THEN '3/3' ";
$queryColumns .= "WHEN 'CCH' THEN '3/3' WHEN 'CA' THEN '2/3' WHEN 'TUG' THEN '2/3' WHEN 'CL' THEN '2/4' ";
$queryColumns .= "WHEN 'NCA' THEN '2/3' WHEN 'CWH' THEN '3/3' WHEN 'CW' THEN '2/3' WHEN 'HDW' THEN '2/4' ";
$queryColumns .= "WHEN 'DW' THEN '2/5' WHEN 'NDD' THEN '2/6' WHEN 'DDH' THEN '2/5' WHEN 'DD' THEN '2/6' ";
$queryColumns .= "WHEN 'FFW' THEN '1/6' WHEN 'NFF' THEN '1/6' WHEN 'FFH' THEN '1/5' WHEN 'FF' THEN '1/6' ";
$queryColumns .= "WHEN 'POL' THEN '1/8' WHEN 'FT' THEN '1/8' WHEN 'BOOM' THEN '1/8' WHEN 'LAux' THEN '2/6' ";
$queryColumns .= "WHEN 'SAux' THEN '1/6' END as maint,"; // maint
$queryColumns .= "attackfactors as antiship,"; // to break up this string to the first value. Done in post-process
$queryColumns .= "attackfactors as defval,"; // to break this up to the second or first value. Done in post-process
$queryColumns .= "cmdrating as cmdrate,";
if( ! $EXCLUDE_CMD_COST )
  $queryColumns .= "1 as cmdcost,";
$queryColumns .= "IF(pfs>0,CONCAT(ftrs,'+',pfs,'SH'),ftrs) as basing"; // basing
$assaultAmt = "CASE hulltype WHEN 'BB' THEN 50 WHEN 'DN' THEN 40 WHEN 'DNH' THEN 40 WHEN 'DNW' THEN 40 ";
$assaultAmt .= "WHEN 'DNL' THEN 40 WHEN 'BCH' THEN 30 WHEN 'BC' THEN 30 WHEN 'CCH' THEN 20 WHEN 'CA' THEN 20 ";
$assaultAmt .= "WHEN 'TUG' THEN 30 WHEN 'CL' THEN 20 WHEN 'NCA' THEN 20 WHEN 'CWH' THEN 20 WHEN 'CW' THEN 20 ";
$assaultAmt .= "WHEN 'HDW' THEN 15 WHEN 'DW' THEN 15 WHEN 'NDD' THEN 15 WHEN 'DDH' THEN 15 WHEN 'DD' THEN 15 ";
$assaultAmt .= "WHEN 'FFW' THEN 15 WHEN 'NFF' THEN 15 WHEN 'FFH' THEN 15 WHEN 'FF' THEN 15 WHEN 'POL' THEN 10 ";
$assaultAmt .= "WHEN 'FT' THEN 15 WHEN 'BOOM' THEN 5 WHEN 'LAux' THEN 60 WHEN 'SAux' THEN 30 END";
$supplyAmt = "CASE hulltype WHEN 'BB' THEN 50 WHEN 'DN' THEN 40 WHEN 'DNH' THEN 40 WHEN 'DNW' THEN 40 ";
$supplyAmt .= "WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 2 WHEN 'CA' THEN 2 ";
$supplyAmt .= "WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 ";
$supplyAmt .= "WHEN 'HDW' THEN 2 WHEN 'DW' THEN 2 WHEN 'NDD' THEN 2 WHEN 'DDH' THEN 2 WHEN 'DD' THEN 2 ";
$supplyAmt .= "WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 2 WHEN 'FFH' THEN 2 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 ";
$supplyAmt .= "WHEN 'FT' THEN 2 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 6 WHEN 'SAux' THEN 3 END";
$special = "CONCAT_WS( ', ',";
$special .= "IF(find_in_set('t',morenotes) OR find_in_set('tanks',morenotes),CONCAT('Assault(',$assaultAmt,')'),NULL),"; // assault
$special .= "IF(find_in_set('db',notes) OR find_in_set('missile',notes),'Ballistic',NULL),"; // ballistic
$special .= "IF(find_in_set('v',morenotes) OR find_in_set('v10',morenotes) OR find_in_set('v14',morenotes)"; // carrier
$special .= " OR find_in_set('v15',morenotes) OR find_in_set('vh',morenotes),'Carrier',NULL),"; // carrier
$special .= "IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR "; // cloak
$special .= "(empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes),'Cloak',NULL),"; // cloak
$special .= "IF(strategicnotes like '%command %','Command',NULL),"; // command. note the trailing space
$special .= "IF(strategicnotes like '%survey%','Explorer',NULL),"; // explorer
$special .= "IF(find_in_set('f',notes) OR find_in_set('x',morenotes),'Fast',NULL),"; // fast
$special .= "IF(find_in_set('sc',morenotes) OR find_in_set('(YG24.0)',notes),"; // scout
$special .= "CONCAT('Scout(',if(locate('EW=',strategicnotes),substr(strategicnotes,locate('EW=',strategicnotes)+3,1),'1'),')'),NULL),"; // scout
$special .= "IF(find_in_set('ml',notes) OR yearinsrvc<120 OR movecost='sub','Slow',NULL),"; // slow
$special .= "IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR (empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes) OR (empire=24 AND yearinsrvc>120 AND NOT FIELD(HullType,'FT','F-L','F-S')),'Stealth',NULL),"; // stealth
$special .= "IF(find_in_set('tg',morenotes),CONCAT('Supply(',$supplyAmt,')'),NULL)"; // [Tug] Supply
//$special .= "if(find_in_set('tg',morenotes),CONCAT('Towing(',if(sizeclass=2,2,if(sizeclass=3,1,0)),')'),NULL)"; // towing
$special .= ")";

$queryFtrColumns = "shiptype as unitname,";
$queryFtrColumns .= "yearinsrvc as servicedate,";
$queryFtrColumns .= "IF(breakdown=1, 'LF', IF(breakdown=2, 'HF', '' )) as design,";
$queryFtrColumns .= "IF(breakdown=1, '1/3', IF(breakdown=2, '1/2', '' )) as cost,";
$queryFtrColumns .= "IF(breakdown=1, '1/12', IF(breakdown=2, '1/8', '' )) as maint,";
$queryFtrColumns .= "IF(breakdown=1, '1', IF(breakdown=2, '2', '' )) as antiship,";
$queryFtrColumns .= "IF(breakdown=1, '1', IF(breakdown=2, '2', '' )) as defval,";
$queryFtrColumns .= "'N/A' as cmdrate,";
if( ! $EXCLUDE_CMD_COST )
  $queryFtrColumns .= "'N/A' as cmdcost,";
$queryFtrColumns .= "'N/A' as basing,";
$queryFtrColumns .= "'' as special";

$queryPfColumns = "shiptype as unitname,";
$queryPfColumns .= "yearinsrvc as servicedate,";
$queryPfColumns .= "'SHF' as design,";
$queryPfColumns .= "'2/3' as cost,";
$queryPfColumns .= "'1/6' as maint,";
$queryPfColumns .= "3 as antiship,";
$queryPfColumns .= "2 as defval,";
$queryPfColumns .= "'N/A' as cmdrate,";
if( ! $EXCLUDE_CMD_COST )
  $queryPfColumns .= "'N/A' as cmdcost,";
$queryPfColumns .= "'N/A' as basing,";
$queryPfColumns .= "'' as special";

// the command to allow only the hulltypes we want in the database
$queryEmpireHulls = "FIELD(HullType,'BB','DN','DNH','DNW','DNL','BCH','BC','CCH','CA','TUG','CL',";
$queryEmpireHulls .= "'NCA','CWH','CW','HDW','DW','NDD','DDH','DD','FFW','NFF','FFH','FF','POL')";
$queryEmpireHulls .= " OR (empire='24' and hulltype='FT')"; // If an Orion, allow some freighters
$queryEmpireHulls .= " OR (empire='34' and hulltype='BOOM')"; // if a Tholian, allow hullType "BOOM"
$queryEmpireHulls .= " OR (empire='37' and (hulltype='LAux' OR hulltype='SAux'))"; // If a WYN, allow Auxiliaries

// exclude refits, tournament ships, and minesweepers
$excludeHulls = "NOT find_in_set('r',notes) AND warshipstatus<>'TOUR' AND NOT find_in_set('ms',notes)";
if( $EXCLUDE_CONJECTURALS )
  $excludeCNJ = "AND NOT find_in_set('unv',morenotes) AND NOT find_in_set('cnj',notes) AND warshipstatus<>'CNJ' AND warshipstatus<>'IMP' AND warshipstatus<>'UNV'";
// Exclude EW and Megapack fighters. Don't exclude the 'D', 'K', or 'C' refits (would capture 'real' ftrs)
$excludeFtrs = "NOT right(shiptype,1)='E' AND NOT right(shiptype,1)='M' AND (breakdown=1 OR breakdown=2) $excludeCNJ";
// Exclude PF variants
$excludePFs = "basehull='' $excludeCNJ";

// Natural Sort the YearInSrvc
$natSortYIS = "length(yearinsrvc),yearinsrvc";

// the full query
$unitQuery = "SELECT $queryColumns, $special as special FROM units WHERE ($excludeHulls $excludeCNJ) AND ($queryEmpireHulls) AND empire='$empireID' ORDER BY $natSortYIS,cost DESC,cmdrate DESC";
$pfUnitQuery = "SELECT $queryPfColumns FROM units WHERE hulltype='pf' AND $excludePFs AND empire='$empireID' ORDER BY $natSortYIS,cost DESC,cmdrate DESC";
$ftrUnitQuery = "SELECT $queryFtrColumns FROM units WHERE hulltype='fighter' AND $excludeFtrs AND empire='$empireID' ORDER BY $natSortYIS,cost DESC,cmdrate DESC";

//echo($unitQuery.";\n");exit;

/*
SELECT shiptype as unitname,yearinsrvc as servicedate,HullType as design,CASE hulltype WHEN 'BB' THEN 14 WHEN 'DN' THEN 10 WHEN 'DNH' THEN 11 WHEN 'DNW' THEN 11 WHEN 'DNL' THEN 9 WHEN 'BCH' THEN 9 WHEN 'BC' THEN 8 WHEN 'CCH' THEN 8 WHEN 'CA' THEN 7 WHEN 'TUG' THEN 7 WHEN 'CL' THEN 6 WHEN 'NCA' THEN 7 WHEN 'CWH' THEN 8 WHEN 'CW' THEN 7 WHEN 'HDW' THEN 6 WHEN 'DW' THEN 5 WHEN 'NDD' THEN 5 WHEN 'DDH' THEN 6 WHEN 'DD' THEN 5 WHEN 'FFW' THEN 4 WHEN 'NFF' THEN 4 WHEN 'FFH' THEN 5 WHEN 'FF' THEN 4 WHEN 'POL' THEN 4 WHEN 'FT' THEN 3 WHEN 'BOOM' THEN 3 WHEN 'LAux' THEN 5 WHEN 'SAux' THEN 4 END as cost,CASE hulltype WHEN 'BB' THEN '3/2' WHEN 'DN' THEN '2/2' WHEN 'DNH' THEN '3/2' WHEN 'DNW' THEN '2/2' WHEN 'DNL' THEN '3/3' WHEN 'BCH' THEN '2/2' WHEN 'BC' THEN '3/3' WHEN 'CCH' THEN '3/3' WHEN 'CA' THEN '2/3' WHEN 'TUG' THEN '2/3' WHEN 'CL' THEN '2/4' WHEN 'NCA' THEN '2/3' WHEN 'CWH' THEN '3/3' WHEN 'CW' THEN '2/3' WHEN 'HDW' THEN '2/4' WHEN 'DW' THEN '2/5' WHEN 'NDD' THEN '2/6' WHEN 'DDH' THEN '2/5' WHEN 'DD' THEN '2/6' WHEN 'FFW' THEN '1/6' WHEN 'NFF' THEN '1/6' WHEN 'FFH' THEN 5 WHEN 'FF' THEN '1/6' WHEN 'POL' THEN '1/8' WHEN 'FT' THEN '1/8' WHEN 'BOOM' THEN '1/8' WHEN 'LAux' THEN '2/6' WHEN 'SAux' THEN '1/6' END as maint,attackfactors as antiship,attackfactors as defval,cmdrating as cmdrate,IF(pfs>0,CONCAT(ftrs,'+',pfs,'SH'),ftrs) as basing, CONCAT_WS( ', ',IF(find_in_set('t',morenotes) OR find_in_set('tanks',morenotes),CONCAT('Assault(',CASE hulltype WHEN 'BB' THEN 50 WHEN 'DN' THEN 40 WHEN 'DNH' THEN 40 WHEN 'DNW' THEN 40 WHEN 'DNL' THEN 40 WHEN 'BCH' THEN 30 WHEN 'BC' THEN 30 WHEN 'CCH' THEN 20 WHEN 'CA' THEN 20 WHEN 'TUG' THEN 30 WHEN 'CL' THEN 20 WHEN 'NCA' THEN 20 WHEN 'CWH' THEN 20 WHEN 'CW' THEN 20 WHEN 'HDW' THEN 15 WHEN 'DW' THEN 15 WHEN 'NDD' THEN 15 WHEN 'DDH' THEN 15 WHEN 'DD' THEN 15 WHEN 'FFW' THEN 15 WHEN 'NFF' THEN 15 WHEN 'FFH' THEN 15 WHEN 'FF' THEN 15 WHEN 'POL' THEN 10 WHEN 'FT' THEN 15 WHEN 'BOOM' THEN 5 WHEN 'LAux' THEN 60 WHEN 'SAux' THEN 30 END,')'),NULL),IF(find_in_set('db',notes) OR find_in_set('missile',notes),'Ballistic',NULL),IF(find_in_set('v',morenotes) OR find_in_set('v10',morenotes) OR find_in_set('v14',morenotes) OR find_in_set('v15',morenotes) OR find_in_set('vh',morenotes),'Carrier',NULL),IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR (empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes),'Cloak',NULL),IF(strategicnotes like '%command %','Command',NULL),IF(strategicnotes like '%survey%','Explorer',NULL),IF(find_in_set('f',notes) OR find_in_set('x',morenotes),'Fast',NULL),IF(find_in_set('sc',morenotes) OR find_in_set('(YG24.0)',notes),CONCAT('Scout(',if(locate('EW=',strategicnotes),substr(strategicnotes,locate('EW=',strategicnotes)+3,1),'1'),')'),NULL),IF(find_in_set('ml',notes) OR yearinsrvc<120 OR movecost='sub','Slow',NULL),IF(find_in_set('cloak',notes) OR find_in_set('mask',notes) OR find_in_set('veil',morenotes) OR (empire=30 AND NOT find_in_set('no cloak',notes)) OR find_in_set('sub',morenotes) OR (empire=24 AND yearinsrvc>120 AND NOT FIELD(HullType,'FT','F-L','F-S')),'Stealth',NULL),IF(find_in_set('tg',morenotes),CONCAT('Supply(',CASE hulltype WHEN 'BB' THEN 50 WHEN 'DN' THEN 40 WHEN 'DNH' THEN 40 WHEN 'DNW' THEN 40 WHEN 'DNL' THEN 4 WHEN 'BCH' THEN 3 WHEN 'BC' THEN 3 WHEN 'CCH' THEN 2 WHEN 'CA' THEN 2 WHEN 'TUG' THEN 3 WHEN 'CL' THEN 2 WHEN 'NCA' THEN 2 WHEN 'CWH' THEN 2 WHEN 'CW' THEN 2 WHEN 'HDW' THEN 2 WHEN 'DW' THEN 2 WHEN 'NDD' THEN 2 WHEN 'DDH' THEN 2 WHEN 'DD' THEN 2 WHEN 'FFW' THEN 1 WHEN 'NFF' THEN 2 WHEN 'FFH' THEN 2 WHEN 'FF' THEN 1 WHEN 'POL' THEN 1 WHEN 'FT' THEN 2 WHEN 'BOOM' THEN 0 WHEN 'LAux' THEN 6 WHEN 'SAux' THEN 3 END,')'),NULL)) as special FROM units WHERE (NOT find_in_set('r',notes) AND warshipstatus<>'TOUR' AND NOT find_in_set('ms',notes) AND NOT find_in_set('unv',morenotes) AND NOT find_in_set('cnj',notes) AND warshipstatus<>'CNJ' AND warshipstatus<>'IMP' AND warshipstatus<>'UNV') AND (FIELD(HullType,'BB','DN','DNH','DNW','DNL','BCH','BC','CCH','CA','TUG','CL','NCA','CWH','CW','HDW','DW','NDD','DDH','DD','FFW','NFF','FFH','FF','POL') OR (empire='24' and hulltype='FT') OR (empire='34' and hulltype='BOOM') OR (empire='37' and (hulltype='LAux' OR hulltype='SAux'))) AND empire='14' ORDER BY length(yearinsrvc),yearinsrvc,cost DESC,cmdrate DESC;

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

// scrape the DefVal and Anti-ship vals in PHP
foreach( $queryNavyOut as &$row )
{
  $result = preg_match( "/^(\d+)-?(\d+)?/", $row["antiship"], $matches );

  // Make fake SIDCORS entry if it is note present, above.
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

// Increase the cost and maint due to specials
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

  if( $commaCount < 2 ) // 1 or 2 specials. increase the cost by 1
    $row["cost"]++;
  else // 3+ specials. increase the cost and increase the maint values
  {
    preg_match( "/^(\d+)\/(\d+)/", $row["maint"], $matches ); // get the maint numbers
    $row["cost"]++;
    $row["maint"] = ($matches[1]+1)."/".$matches[2];
  }
}
unset($row); // getting a double-entry for the last item

// Add bases
$queryNavyOut[] = array( "unitname"=> "Early Base Station","servicedate"=> 65,"design"=> "BSE",
                         "cost"=>16 ,"maint"=> "2/1","antiship"=> 10,"defval"=> 10,
                         "cmdcost"=> 1,"cmdrate"=> 8,"basing"=>0,"special"=>"Supply Depot"
                       );
$queryNavyOut[] = array( "unitname"=> "Base Station","servicedate"=> 120,"design"=> "BS",
                         "cost"=>16 ,"maint"=> "2/1","antiship"=> 10,"defval"=> 10,
                         "cmdcost"=> 1,"cmdrate"=> 8,"basing"=>12,"special"=>"Carrier, Scout(2), Supply Depot"
                       );
$queryNavyOut[] = array( "unitname"=> "Battle Station","servicedate"=> 130,"design"=> "BATS",
                         "cost"=>24 ,"maint"=> "3/1","antiship"=> 12,"defval"=> 12,
                         "cmdcost"=> 1,"cmdrate"=> 9,"basing"=>12,"special"=>"Carrier, Scout(4), Supply Depot"
                       );
$queryNavyOut[] = array( "unitname"=> "StarBase","servicedate"=> 140,"design"=> "SB",
                         "cost"=> 36,"maint"=> "4/1","antiship"=> 36,"defval"=> 36,
                         "cmdcost"=> 1,"cmdrate"=> 10,"basing"=>24,"special"=>"Carrier, Command Post, Scout(6), Supply Depot"
                       );
$queryNavyOut[] = array( "unitname"=> "Mobile Base","servicedate"=> 140,"design"=> "MB",
                         "cost"=>9 ,"maint"=> "1/1","antiship"=> 8,"defval"=> 8,
                         "cmdcost"=> 1,"cmdrate"=> 6,"basing"=>6,"special"=>"Carrier, Scout(1), Supply Depot"
                       );
$queryNavyOut[] = array( "unitname"=> "Defense Satellite","servicedate"=> 120,"design"=> "DEFSAT",
                         "cost"=>2 ,"maint"=> "1/12","antiship"=> 1,"defval"=> 1,
                         "cmdcost"=> 0,"cmdrate"=> 0,"basing"=>0,"special"=>""
                       );
$queryNavyOut[] = array( "unitname"=> "Trade Fleet","servicedate"=> 65,"design"=> "",
                         "cost"=> 15,"maint"=> "1/1","antiship"=> 0,"defval"=> 6,
                         "cmdcost"=> 1,"cmdrate"=> 0,"basing"=>0,"special"=>"Trade(0)"
                       );
$queryNavyOut[] = array( "unitname"=> "Transport Fleet","servicedate"=> 65,"design"=> "",
                         "cost"=> 20,"maint"=> "1/1","antiship"=> 0,"defval"=> 8,
                         "cmdcost"=> 1,"cmdrate"=> 0,"basing"=>0,"special"=>"Supply(10)"
                       );
$queryNavyOut[] = array( "unitname"=> "Colony Fleet","servicedate"=> 65,"design"=> "",
                         "cost"=> 30,"maint"=> "1/1","antiship"=> 0,"defval"=> 8,
                         "cmdcost"=> 1,"cmdrate"=> 0,"basing"=>0,"special"=>"Colonize"
                       );
$queryNavyOut[] = array( "unitname"=> "Orbital Shipyard","servicedate"=> 65,"design"=> "Shipyard",
                         "cost"=> 20,"maint"=> "2/1","antiship"=> 0,"defval"=> 10,
                         "cmdcost"=> 1,"cmdrate"=> 0,"basing"=>0,"special"=>"Shipyard"
                       );
// Web as Fortification
if( strtolower($EMPIRE) == "tholian" )
  $queryNavyOut[] = array( "unitname"=> "Pre-laid web","servicedate"=> 83,"design"=> "Web",
                           "cost"=> 1/3,"maint"=> "1/12","antiship"=> 0,"defval"=> 0,
                           "cmdcost"=> 0,"cmdrate"=> 0,"basing"=>0,"special"=>""
                         );

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
$queryOut[] = array( "unitname"=> "Light Infantry","servicedate"=> 65,"design"=> "Ground Unit",
                         "cost"=> 1,"maint"=> "1/3","antiship"=> "N/A","defval"=> 1,
                         "cmdcost"=> "N/A","cmdrate"=> "N/A","basing"=>"N/A","special"=>""
                       );
$queryOut[] = array( "unitname"=> "Heavy Infantry","servicedate"=> 65,"design"=> "Ground Unit",
                         "cost"=> 2,"maint"=> "1/2","antiship"=> "N/A","defval"=> 2,
                         "cmdcost"=> "N/A","cmdrate"=> "N/A","basing"=>"N/A","special"=>""
                       );
$queryOut[] = array( "unitname"=> "Light Armor","servicedate"=> 65,"design"=> "Ground Unit",
                         "cost"=> 3,"maint"=> "2/3","antiship"=> "N/A","defval"=> 3,
                         "cmdcost"=> "N/A","cmdrate"=> "N/A","basing"=>"N/A","special"=>""
                       );
$queryOut[] = array( "unitname"=> "Heavy Armor","servicedate"=> 65,"design"=> "Ground Unit",
                         "cost"=> 6,"maint"=> "2/2","antiship"=> "N/A","defval"=> 6,
                         "cmdcost"=> "N/A","cmdrate"=> "N/A","basing"=>"N/A","special"=>""
                       );

//$database->close();
unset($database);


###
# Write the output file
###
// write the header
$output = "<a name='".strtolower($EMPIRE)."'></a>\n<h3 class='darkhead' onclick='flip(this)'>".strtoupper($EMPIRE)."</h3>\n<table class='visually-hidden zebra'>\n<thead>\n<tr><th>Unit<br>Name</th><th>Service<br>Date</th><th>Design</th><th>Cost</th><th>Maint</th><th>Anti-Ship<br>Rate</th>";
if( ! $EXCLUDE_ANTIFTR )
  $output .= "<th>Anti-Ftr<br>Rate</th>";
$output .= "<th>Defense<br>Value</th><th>Cmd<br>Rate</th>";
if( ! $EXCLUDE_CMD_COST )
  $output .= "<th>Cmd<br>Cost</th>";
$output .= "<th>Basing</th><th>Special</th></tr>\n</thead>\n<tbody>\n";

foreach( $queryOut as $row )
{
  $output .= "<tr><td>".$row["unitname"];
  $output .= "</td><td>".$row["servicedate"];
  $output .= "</td><td>".$row["design"];
  $output .= "</td><td>".$row["cost"];
  $output .= "</td><td>".$row["maint"];
  $output .= "</td><td>".$row["antiship"];
  if( ! $EXCLUDE_CMD_COST )
    $output .= "</td><td>".$row["antiship"]; // Anti-Fighter same value as Anti-Ship
  $output .= "</td><td>".$row["defval"];
  $output .= "</td><td>".$row["cmdrate"];
  if( ! $EXCLUDE_CMD_COST )
    $output .= "</td><td>".$row["cmdcost"];
  $output .= "</td><td>".$row["basing"];
  $output .= "</td><td>".$row["special"]."</td></tr>\n";
}

// add the footer
$output .= "</tbody><tfoot>\n<tr><th>Unit<br>Name</th><th>Service<br>Date</th><th>Design</th><th>Cost</th><th>Maint</th><th>Anti-Ship<br>Rate</th>";
if( ! $EXCLUDE_ANTIFTR )
  $output .= "<th>Anti-Ftr<br>Rate</th>";
$output .= "<th>Defense<br>Value</th><th>Cmd<br>Rate</th>";
if( ! $EXCLUDE_CMD_COST )
  $output .= "<th>Cmd<br>Cost</th>";
$output .= "<th>Basing</th><th>Special</th></tr>\n</tfoot>\n</table>\n<!-- ************************************************************************** -->\n";

// write the unit file

// write out the output file
$result = file_put_contents( $fileName, $output );
$count = count($queryOut);
unset( $queryOut, $output );
if( $result === false )
{
  echo "Could not write file '".$fileName."'\n";
  exit(1);
}

echo "Wrote $count entries to '".$fileName."'\n";
exit(0); // all done

?>
