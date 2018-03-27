#!/bin/bash

  sku=`echo $1 | sed 's/\(^.*\)-[0-9]\.jpg/\1/'`

  if [ ! -d "$sku" ]; then
    mkdir $sku
  fi

  mv $1 $sku
