# Victory by Star Fleet
This project offers some automation to moderating a first-edition [Victory by any means](http://www.vbamgames.com) campaign game. The data set is based on the Star Trek wargame [Star Fleet Battles](http://starfleetgames.com).

In it's current form, This is geared towards a standard single-player game or a multi-player exploration style game. A Campaign Moderator (CM) is required, in order to handle the items that are not automated.

Currently the code is script-based, except for the webpage that is player-facing. It is intended to eventually release a version that is truely multi-player (with player log-ins and a webserver back-end.) This would require a certain amount of re-write to the two principal scripts that handle the automation. However, the routines that handle the automation should not need much help, when this is tackled.

## What it does
* The player sheet displays all of the information that a player would normally see on the stock VBAM player sheets.
* Income is added from colonies and previous turns
* Research is advanced on the end of the year. This means making percentage rolls and deducting research spent if successful.
* Certain intelligence actions are factored into the processes that are affected.
* Raids are calculated from chance of occurance, all the way to determining how large the raiding force is
* Loading of Census is handled
* Completion of unit construction
* Morale checks for full employment

## What it will do
This list is necessarily fluid, since these are the items being added to the scripts.
* Tracking of trade fleets and their income.
* A movement system - A system that moves units to different systems, after checking for connectivity of the systems, treaty effects of that movement, and the catching hostile fleets passing each other.
* The handling of some intelligence actions. Not all can be reasonably handled by code.
* Checking for units out of supply.
* Handle mothballed units.
* Convert units from one variant to another variant of the same base-hull
* Manage the effects of orbital bombardment and ground combat.
* Increase population and productivity

## What it will not do
In effect, this is a list of things that are not in the vision of what this will automate.
* All optional rules provided - The aim is to provide automation for the full basic game and also allow certain optional rules needed for the source background. Other optional rules may be added, based on ease of inclusion.
* All Morale checks - Some of these checks are subjective.
* All intelligence actions - Again, some of these actions have subjective results.
* Combat - There may be an attempt to handle an automated combat system, but the expected end-use will not use automation for combat resolution.

## Code requirements
The player sheets are in HTML, CSS, and javascript. They should probably be sourced from a website - if sourced from a local drive, recent browser "inovations" have shut down the ability to call in certain locally-sourced files to a webpage. If a person wanted to set up a local webserver, this should work fine.
The scripts used on the back-end are written in PHP with a few BASH scripts for dealing with corner-cases. The intended environment is a linux system, but it's plausable that it could be used in any environment that provided a [PHP engine](https://windows.php.net/download/) (ver 8.0+) and a BASH environment.
The libraries used by the code are [D3JS](d3js.org) and it's [HexBin](https://d3-graph-gallery.com/hexbinmap.html) module. Both are included, so there is no need to independantly fetch them.

## How to use the code
### Installation
The "sheet/" and "files/" directories (and files) are used by the player sheet. "orderForm.php" is used by the player sheet to add the player-entered orders to the data sheet. These should be sourced on a webserver.
The remaining files in the root directory are used for back-end moderation. They expect to be used from the PHP Command Line Interace. You should be able to use them from a normal terminal, if you have PHP-CLI installed.
### Program workflow
To set up a game, create a data sheet for each player involved and place it in the "files/" directory. There is a sample file in that directory with the code.
Run the "postTurn.php" file on each data sheet for the current turn. This will perform the end-of-turn functions on the data sheet, create a new one for the next turn, and run the start-of-turn functions on the new sheet. Note that each finished data sheet will represent the state of the player's position at the end of the turn.
Players then need to access the new sheets and enter thier orders for that turn. Players will access "./sheet/index.html?data=" with an argument that represent which data sheet they will access. For security purposes, it's suggested that data filenames not be easily-guessable. e.g. not "bob-turn-1". Probably some sort of hashing scheme is best: "hf93mdf7". This will limit a player's ability to access the wrong sheet.
The moderator then runs the "postOrders.php" file on each data sheet. This runs through the steps that occur between laying in the orders and setting up combat. The players are then ready to resolve any battles that arose during that turn.
The players enter in "orders" to their player sheet to cripple, destroy, or "give away" (capture) their units. The moderator then runs "postTurn.php" on the data sheet and a new turn's sheet is produced.
![Player Interface](./readme_screenshot.png)
