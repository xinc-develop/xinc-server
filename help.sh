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

