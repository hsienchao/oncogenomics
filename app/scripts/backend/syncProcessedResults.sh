#!/bin/bash
target_project=$1
target_type=$2
target_db=$3
EXPECTED_ARGS=3
E_BADARGS=65
if [ $# -ne $EXPECTED_ARGS ]
then
	echo "Usage: `basename $0` {target project} {process type} {prod/dev/all}"
	exit $E_BADARGS
fi
#projects=( "clinomics":"tgen:/projects/Clinomics/ProcessedResults/" "processed_DATA":"biowulf2.nih.gov:/data/khanlab/projects/processed_DATA/" "cmpc":"biowulf2.nih.gov:/data/Clinomics/Analysis/CMPC/" "nbl":"biowulf2.nih.gov:/data/khanlab/projects/NBL/" "guha":"biowulf2.nih.gov:/data/GuhaData/" "alex":"biowulf2.nih.gov:/data/AlexanderP3/Alex/" "collobaration":"biowulf2.nih.gov:/data/khanlab2/collobaration_DATA/" "toronto":"biowulf2.nih.gov:/data/AlexanderP3/batch_11/")
#projects=( "clinomics":"tgen:/projects/Clinomics/ProcessedResults/" )
#projects=( "cmpc":"biowulf2:/data/Clinomics/Analysis/CMPC/" )
data_home=/is2/projects/CCR-JK-oncogenomics/static/ProcessedResults
script_home=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/scripts/backend
script_home_dev=/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/scripts/backend
db_name='production'
db_name_dev='development'
url='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics/public'
url_dev='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev/public'

prefix=${data_home}/update_list/db_`date +"%Y%m%d-%H%M%S"`
missing_var_file=${data_home}/missing_list/avia_not_exists_`date +"%Y%m%d-%H%M%S"`
case_log=${prefix}_case.log
project_file=$script_home_dev/project_mapping.txt

while IFS=$'\t' read -r -a cols
do
	project=${cols[0]}
	source_path=${cols[1]}
	if [ "$target_project" == "$project" ] || [ "$target_project" == "all" ]
	then		
		project_home=${data_home}/${project}
		if [ ! -d ${project_home} ]; then
			mkdir ${project_home}
		fi
		prefix=${data_home}/update_list/${project}_db_`date +"%Y%m%d-%H%M%S"`
		#echo ${prefix}		
		update_list=${prefix}.txt
		log_file=${prefix}.log
		date >> ${log_file}
		echo "[ Processing project: $project ]" >> ${log_file}
		#echo "[ Processing project: $project ]"
#		if [ "$target_type" == "all" ]
#		then

			rsync -tirm --include '*/' --include "*.txt" --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.bwa.loh" --include "*hotspot.depth" --include "*selfSM" --include 'db/*' --include "*tracking" --include "*exonExpression*" --include "TPM_ENS/*" --include "qc/rnaseqc/*" --include "TPM_UCSC/*" --include "RSEM_ENS/*" --include "RSEM_UCSC/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include '*fastqc/*' --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/" --exclude "calls/" --exclude '*' ${source_path} ${project_home} >>${log_file} 2>&1

#		fi
		egrep '^>' ${log_file} | cut -d ' ' -f 2 > ${update_list}
		#find ${data_home}/ -empty -type d -exec rmdir {} \; 2>/dev/null
		date >> ${log_file}
		if [ "$target_db" == "all" ] || [ "$target_db" == "prod" ]
		then
			if [ "$target_type" == "all" ] 
			then
				echo " ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name} -u ${url} ">>${log_file}
				LC_ALL="en_US.utf8" ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name} -u ${url} 2>&1 1>>${log_file}
				LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
			else
				LC_ALL="en_US.utf8" ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name} -u ${url} 2>&1 1>>${log_file}
				LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
			fi
		fi
		echo "Done updating Patients and Cases for PROD" >> ${log_file}
		if [ "$target_db" == "all" ] || [ "$target_db" == "dev" ]
		then
			if [ "$target_type" == "all" ] 
			then
				echo "${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev}">> ${log_file}
				LC_ALL="en_US.utf8" ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_file}
			else
				echo "${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name_dev} -u ${url_dev} ">>${log_file}
				LC_ALL="en_US.utf8" ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_file}			
			fi
		fi
		chmod -f -R 775 ${project_home}
		#rm -rf ${project_home}/*/*/successful.txt
		echo "Done updating patients for DEV" >> ${log_file}
	fi
done < $project_file
echo "About to update VarCases" >> ${log_file}
if [ "$target_type" == "variants" ] || [ "$target_type" == "all" ]
then	
	LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 1>>${case_log}
	# LC_ALL="en_US.utf8" ${script_home_dev}/refreshViews.pl -c -p -h 2>&1 1>>${case_log}##refresh on the  syncProcessedResults_test.sh script;reinstate if this changes
fi

echo "Done running syncProcessedResults.sh" >> ${log_file}
date >> ${log_file}
#${script_home}/getMissingVar.pl -o ${missing_var_file}
#cp ${missing_var_file} /bioinfoC/AVA/INTERNAL_SUBMISSIONS/KhanLab/

