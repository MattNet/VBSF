var colonies = [
   {"name":"Fraxee","census":10,"owner":"Frax","morale":9,"raw":6,"prod":10,"capacity":12,"intel":0,"fixed":["Orbital Shipyard","Base Station"],"notes":"Homeworld"}
];
var empire = {"AIX":[42,79,85],"empire":"Frax","maintExpense":0,"miscExpense":49,"miscIncome":0,"name":"The FRAX Battle Line","planetaryIncome":0,"previousEP":0,"techYear":166,"tradeIncome":0,"researchInvested":0};
var events = [];
var fleets = [
   {"name":"Exploration Beta","location":"Fraxee","units":["DW","DW","DWS"],"notes":""},
   {"name":"Exploration Alpha","location":"Fraxee","units":["DW","DW","DWS"],"notes":""},
   {"name":"Wolf Pack One","location":"Fraxee","units":["SFF","SFF","SFF"],"notes":""}
];
var game = {"turn":0,"monthsPerYear":6,"blankOrders":0,"nextDoc":"sample01","previousDoc":""};
var intelProjects = [];
var mapPoints = [
   [210,210,"","Fraxee dir F"], [210,240,"","Fraxee dir A"], [210,270,"","Fraxee dir B"], [245,210,"","Fraxee dir E"], [245,240,"Frax","Fraxee"], [245,270,"","Fraxee dir C"], [280,240,"","Fraxee dir D"]
];
var offeredTreaties = [];
var orders = [];
var otherEmpires = [];
var purchases = [];
var treaties = [];
var underConstruction = [];
var unitStates = [];
var unitsInMothballs = [];
var unitsNeedingRepair = [];
var unknownMovementPlaces = ["Fraxee dir A","Fraxee dir B","Fraxee dir C","Fraxee dir D","Fraxee dir E","Fraxee dir F"];
var unitList = [
   {"ship":"FF","yis":121,"design":"FF","cost":3,"maintNum":6,"maintCost":1,"cmd":3,"basing":0,"notes":""},
   {"ship":"SFF","yis":121,"design":"FF","cost":4,"maintNum":6,"maintCost":2,"cmd":3,"basing":0,"notes":"Cloak"},
   {"ship":"CA","yis":122,"design":"CA","cost":7,"maintNum":3,"maintCost":2,"cmd":8,"basing":0,"notes":""},
   {"ship":"TUG","yis":124,"design":"TUG","cost":8,"maintNum":3,"maintCost":3,"cmd":8,"basing":0,"notes":"Supply(10), Towing(1)"},
   {"ship":"DW","yis":135,"design":"DW","cost":4,"maintNum":6,"maintCost":2,"cmd":4,"basing":0,"notes":""},
   {"ship":"SDD","yis":135,"design":"DD","cost":5,"maintNum":6,"maintCost":3,"cmd":4,"basing":0,"notes":"Cloak"},
   {"ship":"POL","yis":135,"design":"FF","cost":3,"maintNum":6,"maintCost":1,"cmd":3,"basing":0,"notes":""},
   {"ship":"DWD","yis":137,"design":"DW","cost":4,"maintNum":6,"maintCost":2,"cmd":4,"basing":0,"notes":"Ballistic, Gunship"},
   {"ship":"FFD","yis":137,"design":"FF","cost":3,"maintNum":6,"maintCost":1,"cmd":3,"basing":0,"notes":"Ballistic, Gunship"},
   {"ship":"DWS","yis":138,"design":"DW","cost":5,"maintNum":6,"maintCost":3,"cmd":4,"basing":0,"notes":"Scout(1)"},
   {"ship":"CC","yis":143,"design":"CA","cost":7,"maintNum":3,"maintCost":2,"cmd":9,"basing":0,"notes":""},
   {"ship":"DWL","yis":143,"design":"DW","cost":4,"maintNum":6,"maintCost":2,"cmd":5,"basing":0,"notes":""},
   {"ship":"FFL","yis":143,"design":"FF","cost":3,"maintNum":6,"maintCost":1,"cmd":4,"basing":0,"notes":""},
   {"ship":"DWG","yis":155,"design":"DW","cost":5,"maintNum":6,"maintCost":3,"cmd":4,"basing":0,"notes":"Assault"},
   {"ship":"DNL","yis":158,"design":"DNL","cost":11,"maintNum":2,"maintCost":3,"cmd":9,"basing":0,"notes":"Fast Ship"},
   {"ship":"CF","yis":158,"design":"CA","cost":8,"maintNum":3,"maintCost":3,"cmd":8,"basing":0,"notes":"Fast Ship"},
   {"ship":"SR","yis":160,"design":"CA","cost":7,"maintNum":3,"maintCost":2,"cmd":8,"basing":0,"notes":""},
   {"ship":"DN","yis":167,"design":"DN","cost":10,"maintNum":2,"maintCost":2,"cmd":10,"basing":0,"notes":""},
   {"ship":"CV","yis":167,"design":"CA","cost":8,"maintNum":3,"maintCost":3,"cmd":8,"basing":24,"notes":"Carrier"},
   {"ship":"DWV","yis":167,"design":"DW","cost":5,"maintNum":6,"maintCost":3,"cmd":4,"basing":8,"notes":"Carrier"},
   {"ship":"DWE","yis":167,"design":"DW","cost":5,"maintNum":6,"maintCost":3,"cmd":4,"basing":0,"notes":"Guardian"},
   {"ship":"SCL","yis":168,"design":"CL","cost":6,"maintCost":3,"maintNum":4,"cmd":6,"basing":0,"notes":"Cloak"},
   {"ship":"CW","yis":168,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":""},
   {"ship":"SCW","yis":168,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Cloak"},
   {"ship":"DWM","yis":168,"design":"DW","cost":4,"maintCost":2,"maintNum":6,"cmd":4,"basing":0,"notes":""},
   {"ship":"FCR","yis":168,"design":"FF","cost":4,"maintCost":2,"maintNum":6,"cmd":3,"basing":0,"notes":"Guardian"},
   {"ship":"CWL","yis":169,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":7,"basing":0,"notes":""},
   {"ship":"ACW","yis":169,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":""},
   {"ship":"CWS","yis":169,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Scout(2)"},
   {"ship":"SCG","yis":170,"design":"CA","cost":8,"maintCost":3,"maintNum":3,"cmd":8,"basing":0,"notes":"Ballistic, Gunship, Cloak"},
   {"ship":"CWG","yis":170,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Assault"},
   {"ship":"CWE","yis":170,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Guardian"},
   {"ship":"CWD","yis":170,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":"Ballistic, Gunship"},
   {"ship":"CWM","yis":170,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":""},
   {"ship":"CWV","yis":170,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":12,"notes":"Carrier"},
   {"ship":"LTT","yis":170,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Supply(6), Towing(1)"},
   {"ship":"MCW","yis":170,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":""},
   {"ship":"MDW","yis":170,"design":"DW","cost":4,"maintCost":2,"maintNum":6,"cmd":4,"basing":0,"notes":""},
   {"ship":"FFE","yis":170,"design":"FF","cost":4,"maintCost":2,"maintNum":6,"cmd":3,"basing":0,"notes":"Guardian"},
   {"ship":"CVA","yis":174,"design":"DN","cost":11,"maintCost":3,"maintNum":2,"cmd":10,"basing":24,"notes":"Carrier"},
   {"ship":"CWA","yis":175,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Guardian"},
   {"ship":"DWA","yis":175,"design":"DW","cost":5,"maintCost":3,"maintNum":6,"cmd":4,"basing":0,"notes":"Guardian"},
   {"ship":"FFA","yis":175,"design":"FF","cost":4,"maintCost":2,"maintNum":6,"cmd":3,"basing":0,"notes":"Guardian"},
   {"ship":"CWU","yis":177,"design":"CW","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":18,"notes":"Carrier"},
   {"ship":"DNH","yis":178,"design":"DNH","cost":10,"maintCost":3,"maintNum":2,"cmd":10,"basing":0,"notes":""},
   {"ship":"BCH","yis":178,"design":"BCH","cost":8,"maintCost":2,"maintNum":2,"cmd":10,"basing":0,"notes":""},
   {"ship":"SCS","yis":179,"design":"DN","cost":11,"maintCost":3,"maintNum":2,"cmd":10,"basing":12,"notes":"Carrier"},
   {"ship":"BCV","yis":179,"design":"BCH","cost":9,"maintCost":3,"maintNum":2,"cmd":10,"basing":12,"notes":"Carrier"},
   {"ship":"PFT","yis":179,"design":"CW","cost":7,"maintCost":2,"maintNum":3,"cmd":6,"basing":0,"notes":""},
   {"ship":"BCS","yis":180,"design":"BCH","cost":9,"maintCost":3,"maintNum":2,"cmd":10,"basing":6,"notes":"Carrier"},
   {"ship":"CCX","yis":181,"design":"CA","cost":8,"maintCost":3,"maintNum":3,"cmd":10,"basing":0,"notes":"Fast Ship"},
   {"ship":"SWX","yis":181,"design":"CA","cost":8,"maintCost":3,"maintNum":3,"cmd":6,"basing":0,"notes":"Cloak, Fast Ship"},
   {"ship":"DWX","yis":181,"design":"DW","cost":5,"maintCost":3,"maintNum":6,"cmd":4,"basing":0,"notes":"Fast Ship"},
   {"ship":"DSX","yis":181,"design":"DW","cost":5,"maintCost":3,"maintNum":6,"cmd":4,"basing":0,"notes":"Fast Ship, Scout(2)"},
   {"ship":"StarBase","yis":140,"design":"SB","cost":36,"maintNum":1,"maintCost":4,"cmd":10,"basing":24,"notes":"Carrier, Command Post, Scout(6), Supply Depot"},
   {"ship":"Battle Station","yis":130,"design":"BATS","cost":24,"maintNum":1,"maintCost":3,"cmd":9,"basing":12,"notes":"Carrier, Scout(4), Supply Depot"},
   {"ship":"Base Station","yis":120,"design":"BS","cost":16,"maintNum":1,"maintCost":2,"cmd":8,"basing":12,"notes":"Carrier, Scout(2), Supply Depot"},
   {"ship":"Early Base Station","yis":65,"design":"BS","cost":16,"maintNum":1,"maintCost":2,"cmd":8,"basing":0,"notes":"Supply Depot"},
   {"ship":"Mobile Base","yis":140,"design":"MB","cost":9,"maintNum":1,"maintCost":1,"cmd":6,"basing":6,"notes":"Carrier, Scout(1), Supply Depot"},
   {"ship":"Defense Satellite","yis":120,"design":"DEFSAT","cost":1,"maintNum":2,"maintCost":12,"cmd":0,"basing":0,"notes":""},
   {"ship":"Trade Fleet","yis":65,"design":"","cost":15,"maintNum":1,"maintCost":1,"cmd":0,"basing":0,"notes":"Trade(0)"},
   {"ship":"Transport Fleet","yis":65,"design":"","cost":20,"maintNum":1,"maintCost":1,"cmd":0,"basing":0,"notes":"Supply(10)"},
   {"ship":"Colony Fleet","yis":65,"design":"","cost":30,"maintNum":1,"maintCost":1,"cmd":0,"basing":0,"notes":"Supply(10)"},
   {"ship":"Orbital Shipyard","yis":65,"design":"","cost":20,"maintNum":1,"maintCost":2,"cmd":0,"basing":0,"notes":""},
   {"ship":"Light Infantry","yis":65,"design":"Ground Unit","cost":1,"maintNum":3,"maintCost":1,"cmd":"N\/A","basing":"N\/A","notes":""},
   {"ship":"Heavy Infantry","yis":65,"design":"Ground Unit","cost":2,"maintNum":2,"maintCost":1,"cmd":"N\/A","basing":"N\/A","notes":""},
   {"ship":"Light Armor","yis":65,"design":"Ground Unit","cost":3,"maintNum":3,"maintCost":2,"cmd":"N\/A","basing":"N\/A","notes":""},
   {"ship":"Heavy Armor","yis":65,"design":"Ground Unit","cost":6,"maintNum":2,"maintCost":2,"cmd":"N\/A","basing":"N\/A","notes":""}
];
