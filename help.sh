#!/bin/bash

set -e

if [ -L phpunit ] ; then true ; else
    ln -s vendor/bin/phpunit phpunit
fi

if [ -d vendor/xinc/core/.git ] ; then
    (cd vendor/xinc/core ; git pull ../../../../xinc-core)
else
    rm -rf vendor/xinc/core
    (cd vendor/xinc ; git clone ../../../xinc-core core)
fi

if [ -d vendor/xinc/getopt/.git ] ; then
    (cd vendor/xinc/getopt ; git pull ../../../../xinc-getopt)
else
    rm -rf vendor/xinc/getopt
    (cd vendor/xinc ; git clone ../../../xinc-getopt getopt)
fi
