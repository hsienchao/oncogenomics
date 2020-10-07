script_home=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/scripts/backend
data_home=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/storage/data
#$script_home/xls_2_text.pl $1 > $data_home/$tsv_file
$script_home/load_clinomics_log.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -i $data_home/ClinomicsLog.txt