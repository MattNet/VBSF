#!/bin/sh

if [ -z "$1" ]; then
  echo "Die Roller";
  echo;
  echo "Called by:";
  echo "  `basename $0` DIE_TYPE";
  echo;
  exit 1
fi



echo $(( 1 + RANDOM % $1 ))

