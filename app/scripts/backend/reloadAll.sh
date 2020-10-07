d=$1
for d in `cat processed_paths.txt`;do 
	/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/scripts/backend/loadVarPatients.pl -i /mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/storage/ProcessedResults/$d -t tier -u  https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics/public
done
