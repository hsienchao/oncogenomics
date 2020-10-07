#!/bin/bash

module load oracle

cd $PBS_O_WORKDIR
sqlldr userid=os_admin/osa0520@//fr-s-oracle-d.ncifcrf.gov:1521/oncosnp11d.ncifcrf.gov control=$fn.ctrl log=$fn.log bad=$fn.bad ERRORS=999999999
