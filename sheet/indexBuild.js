/*
This file provides specific code used to generate the client-side interface. It pulls in data given by the server, function code provided in "indexlib.js", and emits the salient interface html.
*/

const filePath = document.location.origin+document.location.pathname+"?data=";

// names of the themes to allow the player to select 
// format is: displayed, filename, displayed, filename, ...
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
let allTreatyTypes = [];
let orderTable = [];


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
    console.log( "Unable to load data file. Missing filename." );
    document.getElementById('errorArea').innerHTML = "Unable to load data file. Missing filename.";
    return false;
  }

  const scriptTag = document.createElement('script');
  scriptTag.type = "text/javascript";
  scriptTag.src = url+"?d="+Date.now();
  scriptTag.async = false; // make it synchronous in order, but still fire onload
  scriptTag.onload = () =>
  {
    console.log("Data file loaded:", url);

    // After data is ready, load maps.js
    const mapsScript = document.createElement( "script" );
    mapsScript.type = "text/javascript";
    mapsScript.src = "./maps.js";
    mapsScript.defer = false;   // we want it now, not waiting for DOM

    mapsScript.onload = () =>
    {
      console.log( "Maps script loaded.", mapsScript.src );
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

// Colony Setup
  for (const colony of colonies)
  {
    if (colony.owner === empire.empire)
    {
      // assemble the colonyNames array
      colonyNames.push(colony.name);

      // figure colony econ output
      if (empire.planetaryIncome === 0)
        empire.planetaryIncome += calcColonyOutput(colony);

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


// Fleet Setup
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
      if (found.basing > 0) unitsWithBasing.push(`${unit} w/ ${fleet.name}`);
      // The lists of fighter units
      if (found.notes.includes("Flight")) currentFlights.push(`${unit} w/ ${fleet.name}`);
    }
  }
  
  // Buildable Units
  for (const u of unitList) {
    // skip if the unit could not be built yet
    if (u.yis > empire.techYear) continue;

    if (u.design.includes("Ground")) 
    { // List of Ground Units, because design contain 'Ground'
      buildableGround.push(u.ship);
    } else if (/^(LF|HF|SHF)/.test(u.design))
    { // List of Fighter Units, because the design is some sort of flight
      buildableFlights.push(u.ship);
    }
    else
    { // List of Orbital Units and mobile units, because they are remaining
      buildableShips.push(u.ship);
    }
  }

  repairUnits = unitsNeedingRepair.map(item => {
    const [unit, fleet] = item.split(" w/ ");
    return [unit, fleet];
  });

  buildableShips.sort();
  buildableFlights.sort();
  buildableGround.sort();

  allBuildableUnits = JsonConcatArrays(buildableShips, buildableFlights, buildableGround);
  allLoadableUnits = JsonConcatArrays(buildableGround, ["Census"]);
  allMovablePlaces = JsonConcatArrays(colonyNames, otherSystems, unknownMovementPlaces);
  allKnownPlaces = JsonConcatArrays(colonyNames, otherSystems);
  allBasablePlaces = JsonConcatArrays(unitsWithBasing, colonyNames);

  allIntelProjects = [
    "System Espionage", "Fleet Espionage", "Intel Espionage",
    "Tech Espionage", "Trade Espionage", "Troop Espionage",
    "Raider Espionage", "Industrial Sabotage", "Counter-Intelligence",
    "Starship Sabotage", "Installation Sabotage", "Population Sabotage",
    "Insurgency", "Counter-Insurgency", "Reduce Raiding",
    "NPE Diplomatic Shift", "NPE Treaty Acceptance"
  ];

  allTreatyTypes = [
    "Declaration of War", "Declaration of Hostilities", "Non-Aggression Treaty",
    "Peace Treaty", "Trade Treaty", "Mutual-Defense Treaty", "Unification Treaty"
  ];

  // Format is orderTable['internal "type" keyword'] = [ [auto-populated "reciever"], [auto-populated "target"], 'auto-populated "note"', 'external "type" phrase' ]
  orderTable = {
    break:        [ [], [], '', 'Break a treaty' ],
    build_unit:   [ allBuildableUnits, colonyNames, 'New fleet name', 'Build unit' ],
    colonize:     [ otherSystems, [], '', 'Colonize system' ],
    convert:      [ currentUnits, buildableShips, '', 'Convert Unit' ],
    cripple:      [ currentUnits, [], '', 'Cripple unit' ],
    destroy:      [ currentUnits, [], '', 'Destroy unit' ],
    flight:       [ currentFlights, allBasablePlaces, '', 'Assign flights' ],
    load:         [ unitsWithCarry, allLoadableUnits, 'Amount to Load', 'Load units' ],
    mothball:     [ currentUnits, [], '', 'Mothball a unit' ],
    move:         [ currentFleets, allMovablePlaces, '', 'Move fleet' ],
    move_unit:    [ currentUnits, [], 'New Fleet Name', 'Move unit' ],
    name:         [ colonyNames, [], '', '(Re) name a place' ],
    name_fleet:   [ currentFleets, [], 'New fleet name', 'Rename a fleet' ],
    offer:        [ otherEmpires, allTreatyTypes, '', 'Offer a treaty' ],
    productivity: [ colonyNames, [], '', 'Increase productivity' ],
    repair:       [ unitsNeedingRepair, [], '', 'Repair unit' ],
    research:     [ [], [], 'Amount to Invest', 'Invest into research' ],
    sign:         [ offeredTreaties, otherEmpires, '', 'Sign a treaty' ],
    trade_route:  [ currentFleets, allKnownPlaces, 'Third system of trade route', 'Set a trade route' ],
    unload:       [ unitsWithCarry, [], 'Amount to unload', 'Unload units' ],
    unmothball:   [ unitsInMothballs, [], '', 'Unmothball a unit' ]
  };


  // Assemble the AIX line
  document.getElementById("AIX").innerHTML = [
    { value: empire.AIX[0], labels: ["Pacifistic","Peaceful","Calm","Belligerent","Combative","Hostile"] },
    { value: empire.AIX[1], labels: ["Devious","Corrupt","Irresponsible","Reputable","Principled","Rigidly Honorable"] },
    { value: empire.AIX[2], labels: ["Xenophilic","Social","Tolerant","Biased","Narrow-Minded","Very Insular"] }
  ].map(axis => {
    const { value, labels } = axis;
    const thresholds = [18, 33, 50, 66, 82];
    const label = labels[thresholds.findIndex(t => value <= t)] || labels.at(-1);
    return `<a title="${label}">${value}</a>`;
  }).join(" / ");

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

  // Assemble the System Assets area
  let systemRows = colonies.map(colony => {
    const unitCount = UnitCounts(colony.fixed);
    const fixedUnits = unitCount
      .map(([designation, count]) =>
        count === 1 ? designation : `${count}x ${designation}`
      )
      .join(", ");

    const censusLoad = colony.censusLoad < 0
      ? `(${colony.censusLoad})`
      : colony.censusLoad > 0
        ? `(+${colony.censusLoad})`
        : "";

    return `
      <tr>
        <td>${colony.name}</td>
        <td>${colony.census} ${censusLoad} ${colony.owner.slice(0,3)}</td>
        <td>${colony.morale}</td>
        <td>${colony.raw}</td>
        <td>${colony.prod}</td>
        <td>${colony.capacity}</td>
        <td>${calcColonyOutput(colony)}</td>
        <td>${colony.intel}</td>
        <td>${colony.notes}</td>
        <td>${fixedUnits}</td>
      </tr>`;
  }).join("");
  document.getElementById("systemData").insertAdjacentHTML("beforeend", systemRows);

  // Assemble the Maintenance Cost area
  let unitCount = [];
  colonies.forEach(c => unitCount = UnitCounts(c.fixed, unitCount));
  fleets.forEach(f => unitCount = UnitCounts(f.units, unitCount));

  let maintRows = unitCount.map(([designation, count, index]) => {
    let unitMaintCost = 0;
    if (!isNaN(index)) {
      const unit = unitList[index];
      unitMaintCost = Math.ceil(count / unit.maintNum) * unit.maintCost;
    }
    empire.maintExpense += unitMaintCost;
    return `<tr><td>${designation}</td><td>${count}</td><td>${unitMaintCost}</td></tr>`;
  }).join("");

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

  // Assemble the Fleet Assets area
  const assetOut = [...fleets, ...unitsInMothballs]; // show the mothballs as fleets

  let fleetTables = assetOut.map(fleet => {
    let unitCount = UnitCounts(fleet.units);
    const seperateRepairs = repairUnits
      .filter(([unitName, fleetName]) => fleetName === fleet.name)
      .map(([unitName]) => unitName);

    let rows = `
      <tr><th>Fleet Name</th><td>${fleet.name}</td><th>Location</th><td>${fleet.location}</td></tr>
      <tr><th># of Units</th><th>Class</th><th colspan="2">Notes</th></tr>
    `;

    for (const unit of unitCount) {
      const [designation, count, index] = unit;

      // Handle crippled units
      const repairIndex = seperateRepairs.indexOf(designation);
      if (repairIndex >= 0) {
        seperateRepairs.splice(repairIndex, 1);
        const notes = isNaN(index) ? index : unitList[index].notes;
        rows += `
          <tr>
            <td>1</td>
            <td>${designation}</td>
            <td colspan="2">${notes} (Crippled)</td>
          </tr>`;
        unit[1]--; // reduce remaining count
      }

      if (unit[1] > 0) {
        const notes = isNaN(index) ? index : unitList[index].notes;
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

  // Assemble the Purchases area
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

    // convert fractional notation to decimal
    if (String(item.cost).includes("/")) {
      const [top, bottom] = String(item.cost).split("/").map(Number);
      purchaseTotal += newRound(top / bottom, 4); // Round to 4 digits
    } else {
      purchaseTotal += Number(item.cost);
    }
  }

  purchaseTotal = newRound(purchaseTotal, 3);

  purchaseRows += `
    <tr>
      <td class="summation">Total Purchases</td>
      <td class="summation">${purchaseTotal}</td>
    </tr>
  `;

  document
    .getElementById("purchaseData")
    .insertAdjacentHTML("beforeend", purchaseRows);

  // Assemble the Construction area
  const constructionRows = underConstruction.map(item => `
    <tr>
      <td>${item.location}</td>
      <td>${item.unit}</td>
    </tr>`).join("");

  document
    .getElementById("constructionData")
    .insertAdjacentHTML("beforeend", constructionRows);

  // Assemble the Events area
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

  // Assemble the Treaty area
  const treatyRows = treaties
    .map(t => `<br>${t.empire} &bull; ${t.type}`)
    .join("");

  document
    .getElementById("treatyArea")
    .insertAdjacentHTML("beforeend", treatyRows);

  // Assemble the Intel area
  const intelRows = intelProjects.map(p => `
    <tr>
      <td>${p.type}</td>
      <td>${p.target}</td>
      <td>${p.location}</td>
      <td>${p.points}</td>
      <td>${p.notes}</td>
    </tr>`).join("");

  document
    .getElementById("IntelArea")
    .insertAdjacentHTML("beforeend", intelRows);

  // Calculate the EPs spent
  empire.totalIncome =
    empire.planetaryIncome +
    empire.previousEP +
    empire.tradeIncome +
    empire.miscIncome -
    empire.maintExpense -
    empire.miscExpense;

  // Surplus EPs from this turn
  const endingPoolRow = `
    <tr>
      <td class="summation">Ending Point Pool</td>
      <td class="summation">${newRound(empire.totalIncome - purchaseTotal, 2)}</td>
    </tr>`;
  document
    .getElementById("purchaseData")
    .insertAdjacentHTML("beforeend", endingPoolRow);

  // Determine which month of the year this turn is
  const gameMonth =
    game.turn % game.monthsPerYear || game.monthsPerYear;

  // Write the one-liners
  document.getElementById("empireName").textContent =
    `${empire.name} (${empire.empire})`;
  document.getElementById("gameTurn").textContent = game.turn;
  document.getElementById("gameMonth").textContent =
    `${gameMonth} (${makeFancyMonth(gameMonth, game.monthsPerYear)})`;
  document.getElementById("previousEPs").textContent = empire.previousEP;
  document.getElementById("planetaryIncome").textContent = empire.planetaryIncome;
  document.getElementById("commerceIncome").textContent = empire.tradeIncome;
  document.getElementById("miscIncome").textContent = empire.miscIncome;
  document.getElementById("maintTotal").textContent = empire.maintExpense;
  document.getElementById("miscExpense").textContent = empire.miscExpense;
  document.getElementById("totalIncome").textContent = empire.totalIncome;
  document.getElementById("techYear").textContent = empire.techYear;
  document.getElementById("researchInvested").textContent = empire.researchInvested;
  document.getElementById("researchNeeded").textContent =
    Math.floor(empire.planetaryIncome / 2);

  // Update map references
  document.querySelectorAll('[name="map"]').forEach(el => el.src = empire.mapFile);
  document.querySelectorAll('[name="mapLink"]').forEach(el => el.href = empire.mapFile);
  document.getElementById("UnitListDoc").href =
    `../docs/units.html#${String(empire.empire).toLowerCase()}`;

  // Orders
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

// emit any errors given in the URL
  if( errorVal )
    document.getElementById('errorArea').innerHTML = errorVal;

}; // end emitData() declaration

