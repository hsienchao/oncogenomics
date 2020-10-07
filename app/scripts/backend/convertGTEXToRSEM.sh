export R_LIBS=/opt/nasapps/applibs/r-3.5.0_libs/
data_home=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/storage/ProcessedResults
script_file=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/scripts/backend/convertGTEXToRSEM.R
for tpm_file in $data_home/GTEX/*/*/*/TPM_ENS/*.gene.TPM.txt;do
	#echo $tpm_file
	d=$(dirname $(dirname $tpm_file))
	sample_id=$(basename $d)
	count_file=$d/TPM_ENS/${sample_id}.gene.fc.RDS
	mkdir -p $d/RSEM_ENS
	out_file=$d/RSEM_ENS/${sample_id}.rsem_ENS.genes.results	
	/opt/nasapps/development/R/3.5.0/bin/Rscript $script_file $count_file $tpm_file $out_file
	wc -l $out_file
done
