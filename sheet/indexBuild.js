/*
This file provides specific code used to generate the client-side interface. It pulls in data given by the server, function code provided in "indexlib.js", and emits the salient interface html.
*/

const filePath = (document.location.origin==="null"||document.location.origin===null?"":document.location.origin)+document.location.pathname+"?data=";

// names of the themes to allow the player to select 
const themeNames = [
                   { name: "Default", file: ""},
                   { name: "Federation", file: "federation"},
                   { name: "Frax", file: "frax"},
                   { name: "Gorn", file: "gorn"},
                   { name: "Klingon", file: "klingon"},
                   { name: "Kzinti", file: "kzinti"},
                   { name: "Peladine", file: "peladine"},
                   { name: "Quari", file: "quari"},
                   { name: "Romulan", file: "romulan"},
                   { name: "Tholian", file: "tholian"},
                   { name: "Vudar", file: "vudar"}
                   ];
// The prefix name for each permenant order input 
const namePrefix = 'OrderEntry';

/***
Build Game Data
***/
// extractions from data file
let colonyNames = [];
let currentFleets = [];
let currentUnits = [];
let currentFlights = [];
let buildableShips = [];
let buildableGround = [];
let buildableFlights = [];
let BuildableCivUnits = [];
let buildableBases = [];
let otherSystems = [];
let repairUnits = [];
let unitsWithBasing = [];
let unitsWithCarry = [];
// groups of data collections
let allBasablePlaces = [];
let allBuildableUnits = [];
let allIntelProjects = [];
let allLoadableUnits = [];
let allMovablePlaces = [];
let orderTable  = {};

/***
Receive Game Data
***/

/***
# Loads up the JSON data file
# Placed in this file so that the load can start before the library file is 
# loaded. It's a timing thing.
###
# PARAMS: 
#       (STRING) - the file to fetch
# RETURN: None
***/
function fetchData ( url, callback )
{
  if( !url )
  {
    console.error( "Unable to load data file. Missing filename." );
    document.getElementById('errorArea').innerHTML = "Unable to load data file. Missing filename.";
    return false;
  }

  const scriptTag = document.createElement('script');
  scriptTag.type = "text/javascript";
  scriptTag.src = url+"?d="+Date.now();
  scriptTag.async = false; // make it synchronous in order, but still fire onload
  scriptTag.onload = () =>
  {
    // After data is ready, load maps.js
    const mapsScript = document.createElement( "script" );
    mapsScript.type = "text/javascript";
    mapsScript.src = "./maps.js";
    mapsScript.defer = false;
    mapsScript.async = false; // make it synchronous in order

    mapsScript.onload = () =>
    {
      if( typeof callback === "function" )
        callback();
    };

    mapsScript.onerror = () => {
      console.error( "Failed to load maps.js" );
      document.getElementById('errorArea').innerHTML = "Failed to load maps.js";
    };

    document.head.appendChild( mapsScript );
  };

  scriptTag.onerror = () => {
      console.error("Failed to load data file:", url);
      document.getElementById('errorArea').innerHTML = "Failed to load data file: "+url;
    };

  document.head.appendChild(scriptTag);
};

/***
Emit Game Data
***/
function emitGameData()
{
  const ordersArea = document.getElementById( "ordersArea" );

/***
Build Orders Lists
***/
// Colonies
  for (const colony of colonies)
  {
    if (colony.owner === empire.empire)
    {
      // assemble the colonyNames array
      colonyNames.push(colony.name);

      // figure colony econ output
      if (empire.systemIncome === 0)
        empire.systemIncome += calcColonyOutput(colony);
      if (empire.systemIncome != calcColonyOutput(colony))
        console.warn("*NOTE* Calculated system income ("+calcColonyOutput(colony)+") differs from data file ("+empire.systemIncome+")");

      // The list of fighter units
      for (const fixedUnit of colony.fixed)
      {
        const match = unitList.find(u => u.ship === fixedUnit && u.notes.includes("Flight"));
        if (match) currentFlights.push(`${match.ship} w/ ${colony.name}`);
      }
    } else {
      // The list of known colonies not owned by this position
      otherSystems.push(colony.name);
    }
    colony.censusLoad = 0; // Amt of census being loaded / unloaded
  }

// Fleets
  for (const fleet of fleets) {
    // assemble the currentFleets array
    currentFleets.push(fleet.name);

    for (const unit of fleet.units) {
      // Assemble the list of current units
      currentUnits.push(`${unit} w/ ${fleet.name}`);

      const found = unitList.find(u => u.ship === unit);
      if (!found) continue;

      // Assemble the lists of current units with certain traits
      // The lists of units that can carry
      if (found.notes.includes("Supply")) unitsWithCarry.push(`${unit} w/ ${fleet.name}`);
      // The lists of units that can carry fighters
      if (found.notes.includes("Carrier")) unitsWithBasing.push(`${unit} w/ ${fleet.name}`);
      // The lists of fighter units
      if (/^(LF|HF|SHF|AB)/.test(found.design)) currentFlights.push(`${unit} w/ ${fleet.name}`);
    }
  }
  
// Buildable Units
  for (const u of unitList) {
    // skip if the unit could not be built yet
    if (u.yis > empire.techYear) continue;
    // skip if the unit is obsolete
    if (u.yis < empire.techYear-50) continue;

    if (u.design.includes("Ground")) 
    { // List of Ground Units, because design contain 'Ground'
      buildableGround.push(u.ship);
    } else if (/^(LF|HF|SHF|AB)/.test(u.design))
    { // List of Fighter Units, because the design is some sort of flight
      buildableFlights.push(u.ship);
    } else if (u.notes.includes("Civilian")) 
    { // List of civilian units, because specials contain 'Civilian'
      BuildableCivUnits.push(u.ship);
    } else if (u.notes.includes("Fixed")) 
    { // List of bases, because specials contain 'Fixed'
      buildableBases.push(u.ship);
    }
    else
    { // List of Orbital Units and mobile units, because they are remaining
      buildableShips.push(u.ship);
    }
  }

// Repairable units
  repairUnits = unitsNeedingRepair.map(item => {
    const [unit, fleet] = item.split(" w/ ");
    return [unit, fleet];
  });

// Sort lists
  buildableShips.sort();
  buildableFlights.sort();
  buildableGround.sort();

// Build lists into super-lists
  allBuildableUnits = JsonConcatArrays(buildableShips, buildableFlights, buildableBases);
  allLoadableUnits = JsonConcatArrays(buildableGround, ["Census"]);
  allMovablePlaces = JsonConcatArrays(colonyNames, otherSystems, unknownMovementPlaces);
  allKnownPlaces = JsonConcatArrays(colonyNames, otherSystems);
  allBasablePlaces = JsonConcatArrays(unitsWithBasing, colonyNames);

  // Format is orderTable['internal "type" keyword'] = [ [auto-populated "reciever"], [auto-populated "target"], 'auto-populated "note"', 'external "type" phrase' ]
  orderTable = {
    header_fleet:  [ "Fleet Deployment" ],
    add_fleet:     [ currentUnits, [], 'New Fleet Name', 'Add to Fleet', "pre" ],
    flight:        [ currentFlights, allBasablePlaces, '', 'Assign flights', "pre" ],
    name_fleet:    [ currentFleets, [], 'New fleet name', 'Rename a fleet', "pre" ],

    header_intel:  [ "Intelligence Orders" ],
    covert:        [ colonyNames, allKnownPlaces, 'Mission Type', 'Perform covert mission', "pre" ],
    special_force: [ colonyNames, allKnownPlaces, 'Mission Type', 'Perform special-forces mission', "pre" ],

    header_move:   [ "Movement Orders" ],
    convoy_raid:   [ currentFleets, allMovablePlaces, '', 'Convoy Raid', "pre" ],
    explore_lane:  [ currentFleets, [], '', 'Explore Jump-Lane', "pre" ],
    move:          [ currentFleets, allMovablePlaces, '', 'Move fleet', "pre" ],
    load:          [ unitsWithCarry, allLoadableUnits, 'Amount to Load', 'Load units', "pre" ],
    long_range:    [ currentFleets, allMovablePlaces, '', 'Long-Range Scan', "pre" ],
    start_trade:   [ currentFleets, allKnownPlaces, 'Third system of trade route', 'Set a trade route', "pre" ],
    stop_trade:    [ currentFleets, allKnownPlaces, '', 'Stop a trade route', "pre" ],
    unload:        [ unitsWithCarry, [], 'Amount to unload', 'Unload units', "pre" ],

    header_diplomatic: [ "Diplomatic Orders" ],
    hostile_check: [ otherEmpires, [], '', 'Declare War', "pre" ],
    diplo_check:   [ otherEmpires, [], '', 'Offer a treaty', "pre" ],
    sign_treaty:   [ offeredTreaties, otherEmpires, '', 'Sign a treaty', "post" ],
    sneak_attack:  [ currentFleets, [], '', 'Sneak Attack', "pre" ],

    header_construction: [ "Construction orders" ],
    build_unit:    [ allBuildableUnits, colonyNames, 'New fleet name', 'Build unit at system', "pre" ],
    convert:       [ currentUnits, buildableShips, '', 'Convert/Refit Unit', "pre" ],
    mothball:      [ currentUnits, [], '', 'Mothball a unit', "pre" ],
    purchase_civ:  [ BuildableCivUnits, colonyNames, 'New fleet name', 'Purchase civilian unit at system', "pre" ],
    purchase_troop:[ buildableGround, colonyNames, 'Quantity', 'Purchase troop at system', "pre" ],
    remote_build:  [ buildableBases, unitsWithCarry, '', 'Remote build unit', "pre" ],
    repair:        [ unitsNeedingRepair, [], '', 'Repair unit', "pre" ],
    scrap:         [ currentUnits, [], '', 'Scrap a unit', "pre" ],
    unmothball:    [ unitsInMothballs, [], '', 'Unmothball a unit', "pre" ],

    header_investment: [ "Investment Orders" ],
    colonize:      [ otherSystems, [], '', 'Colonize system', "pre" ],
    downgrade_lane:[ allKnownPlaces, allKnownPlaces, '', 'Downgrade Lane', "pre" ],
    imp_capacity:  [ colonyNames, [], '', 'Improve capacity', "pre" ],
    imp_pop:       [ colonyNames, [], '', 'Improve Population', "pre" ],
    imp_intel:     [ colonyNames, [], '', 'Improve Intelligence', "pre" ],
    imp_fort:      [ colonyNames, [], '', 'Improve Fortifications', "pre" ],
    research:      [ [], [], 'Amount to Invest', 'Invest into research', "pre" ],
    name_place:    [ colonyNames, [], '', '(Re) name a colony', "pre" ],
    research_new:  [ ['Research New Unit','Upgrade Unit'], allBuildableUnits, '', 'Research Target', "pre" ],
    upgrade_lane:  [ allKnownPlaces, allKnownPlaces, '', 'Upgrade Lane', "pre" ],

    header_combat: [ "Combat Orders" ],
    cripple:       [ currentUnits, [], '', 'Cripple unit', "post" ],
    destroy:       [ currentUnits, [], '', 'Destroy unit', "post" ],
    gift:          [ currentUnits, otherEmpires, '', 'Transfer ownership of unit', "post" ]
  };

/***
Orders
***/
  ordersArea.innerHTML = ""; // clear old orders drop-downs
  // write the known orders
  orders.forEach((order, i) => {
    if (order.perm) {
      // --- Permanent orders: hidden inputs + text description ---
      const frag = document.createDocumentFragment();

      ["A", "B", "C", "D"].forEach((suffix, idx) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = `OrderEntry${i}${suffix}`;
        input.value = [order.type, order.reciever, order.target, order.note][idx] || "";
        frag.appendChild(input);
      });

      const desc = document.createElement("div");
      switch( order.type )
      {
        case "build_unit":
          desc.textContent = `Order for "${orders[i].target}" to build unit "${orders[i].reciever}" to fleet "${orders[i].note}"`;
          break;
        case "colonize":
          desc.textContent = `Order to colonize system "${orders[i].reciever}"`;
          break;
        case "load":
          desc.textContent = `Order for "${orders[i].reciever}" to load "${orders[i].note}" of "${orders[i].target}" units`;
          break;
        case "move":
          desc.textContent = `Order for "${orders[i].reciever}" to move to "${orders[i].target}"`;
          break;
        case "research":
          desc.textContent = `Order to do research for "${orders[i].note}" EP`;
          break;
        default:
          desc.textContent = `Order "${orders[i].reciever}" to do "${orders[i].type}"`
            + (orders[i].target ? ` to "${orders[i].target}"` : "")
            + (orders[i].note ? ` with "${orders[i].note}"` : "");
      }

      frag.appendChild(desc);
      ordersArea.appendChild(frag);
    } else {
      // Interactive
      OrderOutput(i, i);
    }
  });

  // write the blank orders
  for (let i = 0; i < game.blankOrders; i++) {
    OrderOutput(orders.length + i, NaN);
  }
  if (game.blankOrders > 0) {
    const p = document.createElement("p");
    const span = document.createElement("span");
    span.className = "button";

    const a = document.createElement("a");
    a.href = "#";
    a.textContent = "Save Orders";
    a.onclick = e => {
        e.preventDefault();
        document.getElementById("ordersForm").submit();
    };
    span.appendChild(a);
    p.appendChild(span);
    ordersArea.appendChild(p);
  }

/***
Turn Selection
***/
  // create the previous/next document buttons
  const nextDocEl = document.getElementById("NextDoc");
  const prevDocEl = document.getElementById("PrevDoc");
  if (game.nextDoc) {
    nextDocEl.href = `${filePath}${game.nextDoc}`;
  } else {
    // remove the next-doc link
    nextDocEl.textContent = "";
    nextDocEl.parentElement.classList.remove("button");
  }
  if (game.previousDoc) {
    prevDocEl.href = `${filePath}${game.previousDoc}`;
  } else {
    // remove the previous-doc link
    prevDocEl.textContent = "";
    prevDocEl.parentElement.classList.remove("button");
  }
//  document.getElementById("UnitListDoc").href = `../docs/units.html#${String(empire.empire).toLowerCase()}`;

/***
Month / Year
***/
  const gameMonth =
    game.turn % game.monthsPerYear || game.monthsPerYear;

/***
Systems
***/
  // Adjust colony census accounting for load/unload orders
  for (const order of orders) {
    const fleetIndex = fleets.findIndex(f => order.reciever.endsWith(f.name));
    if (fleetIndex === -1) continue;

    const colonyIndex = colonies.findIndex(c => c.name === fleets[fleetIndex].location);
    if (colonyIndex === -1) continue;

    if (order.target === "Census") {
      if (order.type === "load") {
        colonies[colonyIndex].censusLoad -= 1;
      } else if (order.type === "unload") {
        colonies[colonyIndex].censusLoad += 1;
      }
    }
  }

  let systemRows = colonies.map(colony => {
    const unitCount = UnitCounts(colony.fixed);
    const fixedUnits = unitCount
      .map(([designation, count]) =>
        count === 1 ? designation : `${count}x ${designation}`
      )
      .join(", ");

    // adjust how population is reported when loading or unloading
    const censusLoad = colony.censusLoad < 0
      ? `(${colony.censusLoad}) `
      : colony.censusLoad > 0
        ? `(+${colony.censusLoad}) `
        : "";

    return `
      <tr>
        <td>${colony.name}</td>
        <td class="sysTableType">${colony.type}</td>
        <td>${colony.capacity}</td>
        <td>${colony.raw}</td>
        <td>${colony.population} ${censusLoad}${colony.owner.slice(0,3)}</td>
        <td>${colony.morale}</td>
        <td>${colony.intel}</td>
        <td>${colony.fort}</td>
        <td>${calcColonyOutput(colony)}</td>
        <td class="sysTableNotes">${colony.notes}</td>
        <td class="sysTableFixed">${fixedUnits}</td>
      </tr>`;
  }).join("");
  document.getElementById("systemData").insertAdjacentHTML("beforeend", systemRows);

/***
Maintenance
***/
  let unitCount = [];
  let totalMaintExpense = 0;
  colonies.forEach(c => unitCount = UnitCounts(c.fixed, unitCount));
  fleets.forEach(f => unitCount = UnitCounts(f.units, unitCount));

  let maintRows = unitCount.map(([designation, count, index]) => {
    let unitMaintCost = 0;
    if (index != -1) {
      const unit = unitList[index];
      unitMaintCost = newRound(count * unit.cost * 0.1, 2);
    }
    totalMaintExpense += Math.ceil(unitMaintCost);
    return `<tr><td>${designation}</td><td>x${count}</td><td>${unitMaintCost}</td></tr>`;
  }).join("");

  if (empire.maintExpense == 0)
    empire.maintExpense = totalMaintExpense;
  if (empire.maintExpense != totalMaintExpense)
    console.warn("*NOTE* Calculated maintenance ("+totalMaintExpense+") differs from data file ("+empire.maintExpense+")");

  maintRows += `
    <tr>
      <td colspan="2" class="summation">Total Maintenance Expense</td>
      <td class="summation">${empire.maintExpense}</td>
    </tr>`;

  document.getElementById("maintData").insertAdjacentHTML("beforeend", maintRows);

  // Prepare the mothballs for display
  for (const unit of unitsInMothballs) {
    unit.name = "Mothballs";
    unit.notes = "Mothballed";
  }

/***
Fleets
***/
  const assetOut = [...fleets, ...unitsInMothballs]; // show the mothballs as fleets

  let fleetTables = assetOut.map(fleet => {
    let unitCount = UnitCounts(fleet.units);
    const seperateRepairs = repairUnits
      .filter(([unitName, fleetName]) => fleetName === fleet.name)
      .map(([unitName]) => unitName);

    let rows = `
      <tr><th>Fleet<br>Name</th><td>${fleet.name}</td><th>Location</th><td>${fleet.location}</td></tr>
      <tr><th>Units</th><th>Class</th><th colspan="2">Notes</th></tr>
    `;

    for (const unit of unitCount) {
      const [designation, count, index] = unit;

      // Handle crippled units
      const repairIndex = seperateRepairs.indexOf(designation);
      if (repairIndex >= 0) {
        seperateRepairs.splice(repairIndex, 1);
        const notes = index == -1 ? "" : unitList[index].notes;
        rows += `
          <tr>
            <td>1</td>
            <td>${designation}</td>
            <td colspan="2">${notes} (Crippled)</td>
          </tr>`;
        unit[1]--; // reduce remaining count
      }

      // Non-crippled units
      if (unit[1] > 0) {
        const notes = index == -1 ? "" : unitList[index].notes;
        rows += `
          <tr>
            <td>${unit[1]}</td>
            <td>${designation}</td>
            <td colspan="2">${notes}</td>
          </tr>`;
      }
    }

    // Fleet notes, if present
    if (fleet.notes) {
      rows += `
        <tr>
          <td colspan="2">&nbsp;</td>
          <td colspan="2">${fleet.notes}</td>
        </tr>`;
    }

    return `<table class="fleetEntry">${rows}</table>`;
  }).join("");

  document
    .getElementById("fleetData")
    .insertAdjacentHTML("beforeend", fleetTables);

/***
Trade Fleets
***/
  let seen = new Set(); // track duplicates
  let tradeFleets = fleets
    .filter(fleet => fleet.location.toLowerCase() === "trade" && fleet.notes)
    .map(fleet => {
      let locations = fleet.notes.split(",").map(loc => loc.trim());
      let row = `<tr><td>${fleet.name}<t/td>`;

      locations.forEach(locName => {
        // Try to find a colony with matching name
        let colony = colonies.find(c => c.name.toLowerCase() === locName.toLowerCase());
        if (!seen.has(colony.name)) {
          row += `<td>${colony.name}</td><td>${calcColonyOutput(colony)}</td>`;
          seen.add(colony.name);
        } else {
          row += `<td><em>${colony.name}</em></td><td><em>0</em></td>`;
        }
      });

      row += "</tr>";
      return row;
    }).join("");

  document
    .getElementById("tradeArea")
    .insertAdjacentHTML("beforeend", tradeFleets);

/***
Purchases
***/
  let purchaseRows = `
    <tr><th>New Purchases</th><th>Cost</th></tr>
  `;

  let purchaseTotal = 0;

  for (const item of purchases) {
    purchaseRows += `
      <tr>
        <td>${item.name}</td>
        <td>${item.cost}</td>
      </tr>`;

    // compare cost in data file to cost in unit list
    const idx = unitList.findIndex(u => u.ship === item.name);
    if (idx != -1 && unitList[idx].cost != item.cost)
      console.warn("*NOTE* Purchase: Unit cost of '"+item.name+"' ("+item.cost+") does not match data file ("+unitList[idx].cost+")");

    purchaseTotal += Number(item.cost);
  }

  empire.purchaseTotal = newRound(purchaseTotal, 2);

  purchaseRows += `
    <tr>
      <td class="summation">Total Purchases</td>
      <td class="summation">${empire.purchaseTotal}</td>
    </tr>
  `;

  document
    .getElementById("purchaseData")
    .insertAdjacentHTML("beforeend", purchaseRows);

/***
Construction
***/
  let constructionTotal = 0;
  const constructionRows = underConstruction.map(item => {
    const idx = unitList.findIndex(u => u.ship === item.unit);
    if (idx == -1)
      Console.warn("Construction: Unit '"+item.unit+"' not in list");
    else
    {
      constructionTotal += Number(unitList[idx].cost);
      return `<tr>
        <td>${item.location}</td>
        <td>${item.unit}</td>
        <td></td>
      </tr>`
    }
  }).join("");

  empire.constructionTotal = newRound(constructionTotal, 2);

  document
    .getElementById("constructionData")
    .insertAdjacentHTML("beforeend", constructionRows);

/***
Events
***/
  let eventRows = events.map(e => {
    const safeText = e.text.replace(/'/g, "&apos;");
    if (e.text.length > 300) {
      return `<br><a onclick="popitupEvent(&quot;${safeText}&quot;)">${e.time}: ${e.event}</a>`;
    }
    return `<br><a title="${safeText}">${e.time}: ${e.event}</a>`;
  }).join("");

  eventRows += `<p style="font-size:smaller;">Mouseover or click on event for description</p>`;

  document
    .getElementById("eventArea")
    .insertAdjacentHTML("beforeend", eventRows);

/***
Treaties
***/
  const treatyRows = treaties.map(t => `
    <tr>
      <td>${t.empire}</td>
      <td>${t.cooldown}</td>
      <td>${t.type}</td>
      <td>${t.income}</td>
      <td>${t.navy}</td>
    </tr>`).join("");

  document
    .getElementById("treatyArea")
    .insertAdjacentHTML("beforeend", treatyRows);

/***
Intel
***/
  const intelRows = intelProjects.map(p => `
    <tr>
      <td>${p.type}</td>
      <td>${p.target}</td>
      <td>${p.location}</td>
    </tr>`).join("");

  document
    .getElementById("IntelArea")
    .insertAdjacentHTML("beforeend", intelRows);

/***
Economy
***/
  // Calculate the EPs spent
  empire.totalIncome =
    empire.systemIncome +
    empire.previousEP +
    empire.tradeIncome +
    empire.miscIncome -
    empire.maintExpense -
    empire.miscExpense;
  empire.nextEPs =
    empire.totalIncome -
    empire.purchaseTotal;

  // Determine expenditure on research
  empire.techSpent = empire.purchaseTotal - empire.constructionTotal;
  if( empire.techSpent > Math.round(empire.systemIncome / 2) )
    errorVal += "*NOTE* Research purchase ("+empire.techSpent+") exceeds max per turn ("+Math.round(empire.systemIncome / 2)+")";

  // Surplus EPs from this turn
  const endingPoolRow = `
    <tr>
      <td class="summation">Ending Point Pool</td>
      <td class="summation">${newRound(empire.nextEPs, 2)}</td>
    </tr>`;
  document
    .getElementById("purchaseData")
    .insertAdjacentHTML("beforeend", endingPoolRow);

/***
Map References
***/
  document.querySelectorAll('[name="map"]').forEach(el => el.src = empire.mapFile);
  document.querySelectorAll('[name="mapLink"]').forEach(el => el.href = empire.mapFile);

/***
One-Liners
***/
  // Write the one-liners
  document.getElementById("empireName").textContent =
    `${empire.name} (${empire.empire})`;
  document.getElementById("gameTurn").textContent = game.turn;
  document.getElementById("gameMonth").textContent =
    `${gameMonth} (${makeFancyMonth(gameMonth, game.monthsPerYear)})`;
  document.getElementById("previousEPs").textContent = empire.previousEP;
  document.getElementById("systemIncome").textContent = empire.systemIncome;
  document.getElementById("tradeIncome").textContent = empire.tradeIncome;
  document.getElementById("miscIncome").textContent = empire.miscIncome;
  document.getElementById("maintTotal").textContent = empire.maintExpense;
  document.getElementById("miscExpense").textContent = empire.miscExpense;
  document.getElementById("totalIncome").textContent = empire.totalIncome;
  document.getElementById("techYear").textContent = empire.techYear;
  document.getElementById("techSpent").textContent = empire.techSpent;
  document.getElementById("constructionTotal").textContent = empire.constructionTotal;
  document.getElementById("researchInvested").textContent = empire.researchInvested;
  document.getElementById("nextEPs").textContent = newRound(empire.nextEPs, 2);
  document.getElementById("maxTechCost").textContent = Math.round(empire.systemIncome / 2);
  document.getElementById("techAdvCost").textContent = Math.round(empire.systemIncome * 4);

/***
Write Errors
***/
  if( errorVal )
    document.getElementById('errorArea').innerHTML = errorVal;

}; // end emitData() declaration

