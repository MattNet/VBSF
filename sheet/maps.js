// https://github.com/d3/d3-hexbin Version 0.2.2. Copyright 2017 Mike Bostock.
!function(n,t){"object"==typeof exports&&"undefined"!=typeof module?t(exports):"function"==typeof define&&define.amd?define(["exports"],t):t(n.d3=n.d3||{})}(this,function(n){"use strict";function t(n){return n[0]}function r(n){return n[1]}var e=Math.PI/3,u=[0,e,2*e,3*e,4*e,5*e],o=function(){function n(n){var t,r={},e=[],u=n.length;for(t=0;t<u;++t)if(!isNaN(i=+d.call(null,o=n[t],t,n))&&!isNaN(c=+p.call(null,o,t,n))){var o,i,c,s=Math.round(c/=f),h=Math.round(i=i/a-(1&s)/2),l=c-s;if(3*Math.abs(l)>1){var v=i-h,M=h+(i<h?-1:1)/2,x=s+(c<s?-1:1),g=i-M,m=c-x;v*v+l*l>g*g+m*m&&(h=M+(1&s?1:-1)/2,s=x)}var y=h+"-"+s,j=r[y];j?j.push(o):(e.push(j=r[y]=[o]),j.x=(h+(1&s)/2)*a,j.y=s*f)}return e}function o(n){var t=0,r=0;return u.map(function(e){var u=Math.sin(e)*n,o=-Math.cos(e)*n,i=u-t,a=o-r;return t=u,r=o,[i,a]})}var i,a,f,c=0,s=0,h=1,l=1,d=t,p=r;return n.hexagon=function(n){return"m"+o(null==n?i:+n).join("l")+"z"},n.centers=function(){for(var n=[],t=Math.round(s/f),r=Math.round(c/a),e=t*f;e<l+i;e+=f,++t)for(var u=r*a+(1&t)*a/2;u<h+a/2;u+=a)n.push([u,e]);return n},n.mesh=function(){var t=o(i).slice(0,4).join("l");return n.centers().map(function(n){return"M"+n+"m"+t}).join("")},n.x=function(t){return arguments.length?(d=t,n):d},n.y=function(t){return arguments.length?(p=t,n):p},n.radius=function(t){return arguments.length?(i=+t,a=2*i*Math.sin(e),f=1.5*i,n):i},n.size=function(t){return arguments.length?(c=s=0,h=+t[0],l=+t[1],n):[h-c,l-s]},n.extent=function(t){return arguments.length?(c=+t[0][0],s=+t[0][1],h=+t[1][0],l=+t[1][1],n):[[c,s],[h,l]]},n.radius(1)};n.hexbin=o,Object.defineProperty(n,"__esModule",{value:!0})});

// Radius of the hexes.
// Width = radius × 2 × sin(π / 3)
// Height = radius × 3 / 2
var HexRadius = 20;
//var FinalMapWidth = 1870;
var FinalMapWidth = window.screen.width-40;
var FinalMapHeight = 700;
//###
// NOTE: Primitives rotated so that they are flat-topped.
// Pointy-top is the default in D3-Hexbin
// This affects where X and Y are used, in nearly every place
//###

// this message changed to reflect flat-tops
console.log( "Hex Radius is "+HexRadius+"px. Hex Width is "+(HexRadius*1.5)+"px. Hex Height is "+(HexRadius*1.732)+"px. Hexes are flat-topped" );

// set the dimensions and margins of the graph
var margin = {top: 20, right: 80, bottom: 50, left: 20},
    width = FinalMapWidth - margin.left - margin.right,
    height = FinalMapHeight - margin.top - margin.bottom;

// append the svg object to the body of the page
var svg = d3.select("#mapImg")
  .append("svg")
    .attr("width", width + margin.left + margin.right)
    .attr("height", height + margin.top + margin.bottom)
  .append("g")
    .attr("transform",
          "translate(" + margin.left + "," + margin.top + ")");
//###
// the Hex data
//var mapPoints = [ [1,30,"Kzinti","Kzintar"], [35,60,"Kzinti","Rowph"], [35,90,"Klingon","Klancher"], [1,120,"","empty"], [70,150,"Romulan","Atredies"] ];
// Color Data
var EmpireBackColors = d3.scaleOrdinal()
    .domain(["","Andromedan","Barbarian","Borak","Britanian","Canadi'en","Carnivon","Deltan","Federation","Flivver","Frax","General","Gorn","Hispaniolan","Hydran","ISC","Jindarian","Klingon","Kzinti","Kzinti Faction","LDR","Lyran","Neo-Tholian","Orion","Paravian","Peladine","Quari","Romulan","Romulan Imperial","Seltorian","Sharkhunter","Tholian","Triaxian","Vudar","WYN","Maesron","Koligahr","Trobrin","Vari","Probr","Chlorophon","Drex","Alunda","Hiver","Sigvirion","Loriyill","Souldra","Iridani","Ymatrian","Worb","FRA","Singer","Juggernaught"])
    .range(["#ccf","Green","White","","Turquoise","White","Green","Turquoise","Blue","White","Gray","White","White","","Green","Yellow","Gray","Black","White","Orange","White","Yellow","Rose","Blue","Red","Black","Tan","Red","Pink","White","Purple","Red","Purple","Black","Yellow","Red","Blue","Grey","Green","Yellow","Brown","Grey","Orange","Blue","Green","Brown","Purple","Pink","Pink","Pink","Blue","Purple","White"]);
var EmpireTextColors = d3.scaleOrdinal()
    .domain(["","Andromedan","Barbarian","Borak","Britanian","Canadi'en","Carnivon","Deltan","Federation","Flivver","Frax","General","Gorn","Hispaniolan","Hydran","ISC","Jindarian","Klingon","Kzinti","Kzinti Faction","LDR","Lyran","Neo-Tholian","Orion","Paravian","Peladine","Quari","Romulan","Romulan Imperial","Seltorian","Sharkhunter","Tholian","Triaxian","Vudar","WYN","Maesron","Koligahr","Trobrin","Vari","Probr","Chlorophon","Drex","Alunda","Hiver","Sigvirion","Loriyill","Souldra","Iridani","Ymatrian","Worb","FRA","Singer","Juggernaught"])
    .range(["#33f","Black","Purple","","Black","Red","Yellow","White","Black","Turquoise","Purple","Blue","Red","","White","Black","Black","White","Black","White","Green","Green","White","White","Yellow","Blue","Black","Black","Black","Orange","Black","White","White","Yellow","Red","White","White","White","Black","Red","White","Purple","White","Grey","Yellow","Pink","Yellow","Green","White","Purple","Red","Pink","Blue"]);
//###

// create a tooltip
var tooltip = d3.select("#mapImg")
  .append("div")
    .style("opacity", 0)
    .attr("class", "tooltip")
    .style("background-color", "white")
    .style("border", "solid")
    .style("border-width", "2px")
    .style("border-radius", "5px")
    .style("padding", "5px")
    .style("position", "absolute");
      // Three function that change the tooltip when user hover / move / leave a cell
  var mouseover = function(d) {
    tooltip
      .style("opacity", 1)
    d3.select(this)
      .style("stroke", "black")
  }
  var mousemove = function(d) {
    tooltip
      .html( d3.select(this)._groups[0][0].id )
      .style("left", (event.pageX+20) + "px")
      .style("top", (event.pageY) + "px");
  }
  var mouseleave = function(d) {
    tooltip
      .style("opacity", 0)
    d3.select(this)
      .style("stroke", "grey");
  }

onLoadStartUp( function () {

// create the hexagons
const hexbin = d3.hexbin();
hexbin.radius(HexRadius);
var hexelem = svg.selectAll("g")
  .data(hexbin(mapPoints));

var elemEnter = hexelem.enter()
  .append("g")
    .attr("transform", d => `translate(${d.y},${d.x}) rotate(90)`)
    .attr("id", function(d){ return String(d).split(',')[3] })
    .on("mouseover", mouseover)
    .on("mousemove", mousemove)
    .on("mouseleave", mouseleave);
    
elemEnter.append("path")
    .attr("d", function(d){ return hexbin.hexagon() })
    .style("fill", function(d){ return EmpireBackColors( String(d).split(',')[2] ) })
    .attr("stroke", "grey");

// create the text inside the hex
elemEnter.append("text")
    .attr("x", function(d){ return -1*(HexRadius/3) }) // swapped for flat-topped hexes
    .attr("y", function(d){ return (HexRadius/2.5) }) // swapped for flat-topped hexes
    .text( function(d){ return String(d).split(',')[2].substring(0,1) })
    .attr("transform", `rotate(270)`) // added for flat-topped hexes
    .style("fill", function(d){ return EmpireTextColors( String(d).split(',')[2] ) })
    .attr("stroke", function(d){ return EmpireTextColors( String(d).split(',')[2] ) })
    .style("font-size", function(d){ return HexRadius+"px" });

});

