/*
This file provides specific code used to generate the client-side interface. It pulls in data given by the server, function code provided in "indexlib.js", and emits the salient interface html.
*/

var filePath = document.location.origin+document.location.pathname+"?data=";

/***
Build Game Data
***/
// extractions from data file
var colonyNames = [];
var currentFleets = [];
var currentUnits = [];
var currentFlights = [];
var buildableShips = [];
var buildableGround = [];
var buildableFlights = [];
var otherSystems = [];
var unitsWithBasing = [];
var unitsWithCarry = [];
// groups of data collections
var allBasablePlaces = [];
var allBuildableUnits = [];
var allIntelProjects = [];
var allLoadableUnits = [];
var allMovablePlaces = [];
var allTreatyTypes = [];
var orderTable = [];

/***
Emit Game Data
***/
onLoadStartUp( function () {

// Iterate through the list of colonies
for( var i=0; i<colonies.length; i++ )
  if( colonies[i].owner == empire.empire )
  {
    // assemble the colonyNames array
    colonyNames.push( colonies[i].name );
    // figure colony econ output
    empire.planetaryIncome += calcColonyOutput(colonies[i]);
    // Assemble the lists of fighter units
    for( var b=0; b<colonies[i].fixed.length; b++ )
      for( var c=0; c<unitList[i].length; c++ )
        if( unitList[c].ship == colonies[i].fixed[b] )
          if( unitList[c].notes.indexOf('Flight') !== -1 )
            currentFlights.ppopitupEventush( unitList[c].ship+" w/ "+colonies[i].name );
  }
  else
  {
    // fill the list of known colonies not owned by this position
    otherSystems.push( colonies[i].name );
  }

// Iterate through the list of fleet
for( var a=0; a<fleets.length; a++ )
{
  // assemble the currentFleets array
  currentFleets.push( fleets[a].name );
  // Assemble various lists of current units
  for( var b=0; b<fleets[a].units.length; b++ )
  {
    // Assemble the list of current units
    currentUnits.push( fleets[a].units[b]+" w/ "+fleets[a].name );

    // Assemble the lists of current units with certain traits
    for( var c=0; c<unitList.length; c++ )
      if( unitList[c].ship == fleets[a].units[b] )
      {
        // Assemble the lists of units that can carry
        if( unitList[c].notes.indexOf('Supply') !== -1 )
          unitsWithCarry.push( unitList[c].ship+" w/ "+fleets[a].name );
        // Assemble the lists of units that can carry fighters
        if( unitList[c].basing > 0 )
          unitsWithBasing.push( unitList[c].ship+" w/ "+fleets[a].name );
        // Assemble the lists of fighter units
        if( unitList[c].notes.indexOf('Flight') !== -1 )
          currentFlights.push( unitList[c].ship+" w/ "+fleets[a].name );
      }
  }
}
// Iterate through the list of units
for( var i=0; i<unitList.length; i++ )
{
  // skip if the unit could not be built yet
  if( unitList[i].yis > empire.techYear )
    continue;

  // Assemble the list of buildable units
  if( unitList[i].design.indexOf('Ground') !== -1 )
    // List of Ground Units, because notes contain 'Ground'
    buildableGround.push( unitList[i].ship );
  else if( unitList[i].notes.indexOf('Flight') !== -1 )
    // List of Fighter Units, because notes does not contain 'Ground'
    buildableFlights.push( unitList[i].ship );
  else
    // List of Orbital Units, because its whats left
    buildableShips.push( unitList[i].ship );
}

 allBuildableUnits = JsonConcatArrays(buildableShips, buildableGround, buildableFlights);
 allLoadableUnits = JsonConcatArrays(buildableGround, ['Census']);
 allMovablePlaces = JsonConcatArrays(colonyNames, otherSystems, unknownMovementPlaces);
 allKnownPlaces = JsonConcatArrays(colonyNames, otherSystems);
 allBasablePlaces = JsonConcatArrays(unitsWithBasing, colonyNames);
 allIntelProjects = ['System Espionage', 'Fleet Espionage', 'Intel Espionage', 'Tech Espionage', 'Trade Espionage', 'Troop Espionage', 'Raider Espionage', 'Industrial Sabotage', 'Counter-Intelligence', 'Starship Sabotage', 'Installation Sabotage', 'Population Sabotage', 'Insurgency', 'Counter-Insurgency', 'Reduce Raiding', 'NPE Diplomatic Shift', 'NPE Treaty Acceptance'];
 allTreatyTypes = ['Declaration of War', 'Declaration of Hostilities', 'Non-Aggression Treaty', 'Peace Treaty', 'Trade Treaty', 'Mutual-Defense Treaty', 'Unification Treaty'];

 orderTable = [];
orderTable['break'] = [ [], [], '', 'Break a treaty' ];
orderTable['build_unit'] = [ allBuildableUnits, colonyNames, 'New fleet name', 'Build unit' ];
orderTable['build_intel'] = [ colonyNames, [], 'Amount of Intel Points', 'Build intel points' ];
orderTable['colonize'] = [ otherSystems, [], '', 'Colonize system' ];
orderTable['convert'] = [ currentUnits, buildableShips, '', 'Convert Unit' ];
orderTable['cripple'] = [ currentUnits, [], '', 'Cripple unit' ];
orderTable['destroy'] = [ currentUnits, [], '', 'Destroy unit' ];
orderTable['flight'] = [ currentFlights, allBasablePlaces, '', 'Assign flights' ];
orderTable['intel'] = [ allIntelProjects, allKnownPlaces, 'Amount of Points to Use', 'Perform an intel action' ];
orderTable['load'] = [ unitsWithCarry, allLoadableUnits, 'Amount to Load', 'Load units' ];
orderTable['mothball'] = [ currentUnits, [], '', 'Mothball a unit' ];
orderTable['move'] = [ currentFleets, allMovablePlaces, '', 'Move fleet' ];
orderTable['move_unit'] = [ currentUnits, currentFleets, 'New Fleet Name', 'Move unit' ];
orderTable['name'] = [ colonyNames, [], '', '(Re) name a place' ];
orderTable['offer'] = [ otherEmpires, allTreatyTypes, '', 'Offer a treaty' ];
orderTable['productivity'] = [ colonyNames, [], '', 'Increase productivity' ];
orderTable['repair'] = [ unitsNeedingRepair, [], '', 'Repair unit' ];
orderTable['research'] = [ [], [], 'Amount to Invest', 'Invest into research' ];
orderTable['sign'] = [ offeredTreaties, otherEmpires, '', 'Sign a treaty' ];
orderTable['trade_route'] = [ currentFleets, allKnownPlaces, '', 'Set a trade route' ];
orderTable['unload'] = [ unitsWithCarry, [], 'Amount to unload', 'Unload units' ];
orderTable['unmothball'] = [ unitsInMothballs, [], '', 'Unmothball a unit' ];

  // Assemble the AIX line
  var AIXOut = "<a title='";
  if( empire.AIX[0] >82 )
    AIXOut += "Hostile";
  else if( empire.AIX[0] >66 )
     AIXOut += "Combative";
  else if( empire.AIX[0] >50 )
     AIXOut += "Belligerent";
  else if( empire.AIX[0] >33 )
     AIXOut += "Calm";
  else if( empire.AIX[0] >18 )
    AIXOut += "Peaceful";
  else
    AIXOut += "Pacifistic";
  AIXOut += "'>"+empire.AIX[0]+"</a> / <a title='";
  if( empire.AIX[1] >82 )
    AIXOut += "Rigidly Honorable";
  else if( empire.AIX[1] >66 )
    AIXOut += "Principled";
  else if( empire.AIX[1] >50 )
    AIXOut += "Reputable";
  else if( empire.AIX[1] >33 )
    AIXOut += "Irresponsible";
  else if( empire.AIX[1] >18 )
    AIXOut += "Corrupt";
  else
    AIXOut += "Devious";
  AIXOut += "'>"+empire.AIX[1]+"</a> / <a title='";
  if( empire.AIX[2] >82 )
    AIXOut += "Very Insular";
  else if( empire.AIX[2] >66 )
    AIXOut += "Narrow-Minded";
  else if( empire.AIX[2] >50 )
    AIXOut += "Biased";
  else if( empire.AIX[2] >33 )
    AIXOut += "Tolerant";
  else if( empire.AIX[2] >18 )
    AIXOut += "Social";
  else
    AIXOut += "Xenophilic";
  AIXOut += "'>"+empire.AIX[2]+"</a>";
  ElementFind('AIX').innerHTML = AIXOut;

  // create the previous/next document buttons
  if( game.nextDoc )
  {
    ElementFind('NextDoc').href = filePath+game.nextDoc; // Edit the next-doc link
  }
  else
  {
    ElementFind('NextDoc').innerHTML = ""; // remove the next-doc link
    ElementFind('NextDoc').parentElement.classList.remove("button");
  }
  if( game.previousDoc )
  {
    ElementFind('PrevDoc').href = filePath+game.previousDoc; // Edit the next-doc link
  }
  else
  {
    ElementFind('PrevDoc').innerHTML = ""; // remove the next-doc link
    ElementFind('PrevDoc').parentElement.classList.remove("button");
  }

  // Assemble the System Assets area
  var SystemOut = '';
  for( var i=0; i<colonies.length; i++ )
  {
    SystemOut += "<tr><td>"+colonies[i].name+"</td><td>"+colonies[i].census+" "+String(colonies[i].owner).substr(0,3)+"</td><td>";
    SystemOut += colonies[i].morale+"</td><td>"+colonies[i].raw+"</td><td>"+colonies[i].prod+"</td><td>"+colonies[i].capacity;
    SystemOut += "</td><td>"+calcColonyOutput(colonies[i])+"</td><td>"+colonies[i].intel+"</td><td>"+colonies[i].notes+"</td><td>";
    // add in the fixed-defenses units
    for( var a=0; a<colonies[i].fixed.length; a++ )
      SystemOut += colonies[i].fixed[a]+", ";
    if( colonies[i].fixed.length > 0 ) // if there were fixed units
      SystemOut = SystemOut.substr(0, (SystemOut.length - 2) ); // trim off the last deliminator
    SystemOut += "</td></tr>\n";
  }
  ElementFind('systemData').innerHTML = ElementFind('systemData').innerHTML + SystemOut;

  // Assemble the Maintenance Cost area
  var MaintOut = '<tr><th>Maintenance Item</th><th>Number</th><th>Cost</th></tr>';
  var unitCount = []; // format is [ ['designation','count', 'index'], ... ]

  for( var i=0; i<colonies.length; i++ )
    // count the units in colonies
    unitCount = UnitCounts( colonies[i].fixed, unitCount );
  for( var i=0; i<fleets.length; i++ )
    // count the units in fleets
    unitCount = UnitCounts( fleets[i].units, unitCount );
  // emit the unit lists
  for( var i=0; i<unitCount.length; i++ )
  {
    var unitMaintCost = 0;
    // as long as the index to unitList is numeric
    if( ! isNaN(unitCount[i][2]) )
      // figure the costs for each type of unit
      unitMaintCost = Math.ceil( unitCount[i][1] / unitList[ unitCount[i][2] ].maintNum ) * unitList[ unitCount[i][2] ].maintCost;
    // list the data
    MaintOut += "<tr><td>"+unitCount[i][0]+"</td><td>"+unitCount[i][1]+"</td><td>";
    empire.maintExpense += unitMaintCost; // add up the total maintenance budget
    MaintOut += unitMaintCost+"</td></tr>"
  }
  empire.maintExpense = ( empire.maintExpense );
  MaintOut += "<tr><td colspan=2 class='summation'>Total Maintenance Expense</td><td class='summation'>"+empire.maintExpense+"</td></tr>";
  ElementFind('maintData').innerHTML = ElementFind('maintData').innerHTML + MaintOut;

  // Assemble the Fleet Assets area
  var FleetOut = '';
  for( var a=0; a<fleets.length; a++ )
  {
    var unitCount = []; // format is [ ['designation','count', 'index'], ... ]

    if( a%2 == 0 )
      FleetOut += "\n<table style='float:right'>";
    else
      FleetOut += "\n<table>";

    FleetOut += "<tr><th>Fleet Name</th><td>"+fleets[a].name+"</td><th>Location</th><td>"+fleets[a].location+"</td></tr>";
    FleetOut += "<tr><th># of Units</th><th>Class</th><th colspan=2>Notes</th></tr>";

    // count the units
    unitCount = UnitCounts( fleets[a].units );

    // list the units
    for( var b=0; b<unitCount.length; b++ )
    {
      FleetOut += "<tr><td>"+unitCount[b][1]+"</td><td>"+unitCount[b][0]+"</td><td>";
      if( isNaN(unitCount[b][2]) )
        FleetOut += unitCount[b][2];
      else
        FleetOut += unitList[ unitCount[b][2] ].notes;
      FleetOut += "</td></tr>";
    }
    FleetOut += "</table>";
  }
  ElementFind('fleetData').innerHTML = ElementFind('fleetData').innerHTML + FleetOut;

  // Assemble the Purchases area
  var purchaseOut = '<tr><th>New Purchases</th><th>Cost</th></tr>';
  var purchaseTotal = 0;
  for( var i=0; i<purchases.length; i++)
  {
    purchaseOut += "<tr><td>"+purchases[i].name+"</td><td>"+purchases[i].cost+"</td></tr>";
    purchaseTotal += purchases[i].cost; // running total of funds spent on purchases
  }
  purchaseTotal = newRound( purchaseTotal, 3 ); // Round purchaseTotal to 3 places
  purchaseOut += "<tr><td class='summation'>Total Purchases</td><td class='summation'>"+purchaseTotal+"</td></tr>";
  ElementFind('purchaseData').innerHTML = ElementFind('purchaseData').innerHTML + purchaseOut;

  // Assemble the Construction area
  var constructionOut = "";
  for( var i=0; i<underConstruction.length; i++)
    constructionOut += "<tr><td>"+underConstruction[i].location+"</td><td>"+underConstruction[i].unit+"</td></tr>";
  ElementFind('constructionData').innerHTML = ElementFind('constructionData').innerHTML + constructionOut;

  // Assemble the Events area
  var eventOut = '';
  for( var i=0; i<events.length; i++)
    eventOut += "<br><a onclick='popitupEvent(&quot;"+events[i].text+"&quot;)'>"+events[i].time+": "+events[i].event+"</a>";
  eventOut += "<p style='font-size:smaller;'>Click on event for description</p>";
  ElementFind('eventArea').innerHTML = ElementFind('eventArea').innerHTML + eventOut;

  // Assemble the Treaty area
  var treatyOut = '';
  for( var i=0; i<treaties.length; i++)
    treatyOut += "<br>"+treaties[i].empire+" &bull; "+treaties[i].type+"</a>";
  ElementFind('treatyArea').innerHTML = ElementFind('treatyArea').innerHTML + treatyOut;

  // Assemble the Intel area
  var intelOut = '';
  for( var i=0; i<intelProjects.length; i++)
  {
    intelOut += "<tr><td>"+intelProjects[i].type+"</td><td>"+intelProjects[i].target+"</td><td>"+intelProjects[i].location
    intelOut += "</td><td>"+intelProjects[i].points+"</td><td>"+intelProjects[i].notes+"</td></tr>";
  }
  ElementFind('IntelArea').innerHTML = ElementFind('IntelArea').innerHTML + intelOut;

  // calculate the EPs spent
  empire.totalIncome = empire.previousEP + empire.planetaryIncome + empire.tradeIncome
  empire.totalIncome += empire.miscIncome - empire.maintExpense - empire.miscExpense;

  // write the surplus EPs from this turn.
  // put here because of the total EP is unknown until now
  ElementFind('purchaseData').innerHTML = ElementFind('purchaseData').innerHTML + 
    "<tr><td class='summation'>Ending Point Pool</td><td class='summation'>"+
    newRound(empire.totalIncome-purchaseTotal, 2)+"</td></tr>";

  // write the one-liners
  ElementFind('empireName').innerHTML = empire.name+" ("+empire.empire+")";
  ElementFind('gameTurn').innerHTML = game.turn;
  ElementFind('gameMonth').innerHTML = (game.turn%game.monthsPerYear)?(game.turn%game.monthsPerYear):game.monthsPerYear;
  ElementFind('previousEPs').innerHTML = empire.previousEP;
  ElementFind('planetaryIncome').innerHTML = empire.planetaryIncome;
  ElementFind('commerceIncome').innerHTML = empire.tradeIncome;
  ElementFind('miscIncome').innerHTML = empire.miscIncome;
  ElementFind('maintTotal').innerHTML = empire.maintExpense;
  ElementFind('miscExpense').innerHTML = empire.miscExpense;
  ElementFind('totalIncome').innerHTML = empire.totalIncome;
  ElementFind('techYear').innerHTML = empire.techYear;
  ElementFind('researchInvested').innerHTML = empire.researchInvested;
  ElementFind('researchNeeded').innerHTML = Math.floor( empire.planetaryIncome / 2);
  ElementFind('map').src = empire.mapFile;
  ElementFind('mapLink').href = empire.mapFile;
  ElementFind('UnitListDoc').href= "../units.html#"+String(empire.empire).toLowerCase(); // Edit the units link

  // Write the Orders section
  var ordersOut = "";
  var namePrefix = 'OrderEntry'; // what to put in the name trait for each input

  // write the known orders
  for( i=0; i<orders.length; i++ )
    // this is a static order. Does not change
    if( orders[i].perm )
    {
      ordersOut += "<input type='hidden' name='"+namePrefix+i+"A' value='"+orders[i].type+"'>";
      ordersOut += "<input type='hidden' name='"+namePrefix+i+"B' value='"+orders[i].reciever+"'>";
      ordersOut += "<input type='hidden' name='"+namePrefix+i+"C' value='"+orders[i].target+"'>";
      ordersOut += "<input type='hidden' name='"+namePrefix+i+"D' value='"+orders[i].note+"'>";
      ordersOut += "Order \""+orders[i].reciever+"\" to do \""+orders[i].type+"\"";
      if( orders[i].target != '' )
        ordersOut += " to \""+orders[i].target+"\"";
      if( orders[i].note != '' )
        ordersOut += " with \""+orders[i].note+"\"";
      ordersOut += "<br>";
    }
    else
    {
    // this is a dynamic order. Can change
      ordersOut += OrderOutput( i, i );
    }

  // write the blank orders
  for( i=0; i<game.blankOrders; i++ )
    ordersOut += OrderOutput( orders.length+i );

  ElementFind('ordersArea').innerHTML = ordersOut;


});


