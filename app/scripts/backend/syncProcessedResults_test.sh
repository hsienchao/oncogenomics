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

export ADMIN_ADDY='vuonghm@mail.nih.gov';
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


project_file=$script_home_dev/project_mapping_test.txt
#project_file=$script_home_dev/project_mapping_wu.txt
echo "project_file = $project_file";
while IFS=$'\t' read -r -a cols
do
	project=${cols[0]}
	succ_list_path=${cols[1]}
	source_path=${cols[2]}
	prefix=${data_home}/update_list/${project}_db_`date +"%Y%m%d-%H%M%S"`
	case_log=${prefix}_case.log
	echo "working on $project ...."
	if [ "$target_project" == "$project" ] || [ "$target_project" == "all" ]
	then

		update_list=${prefix}.txt
		sync_list=${prefix}_sync.txt
		log_file=${prefix}.log
		project_home=${data_home}/${project}

		if [ ! -d ${project_home} ]; then
			mkdir ${project_home}
		fi
		date >> ${log_file}
		echo "[ Processing project: $project ]" >> ${log_file}
		echo "update_list=$update_list,sync_list=$sync_list, log_file=$log_file, project_home=$project_home"  >> ${log_file}
#		if [ "$target_type" == "all" ]
#		then

#			rsync -tirm --include '*/' --include "*.txt" --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.bwa.loh" --include "*hotspot.depth" --include "*selfSM" --include 'db/*' --include "*tracking" --include "*exonExpression*" --include "TPM_ENS/*" --include "qc/rnaseqc/*" --include "TPM_UCSC/*" --include "RSEM_ENS/*" --include "RSEM_UCSC/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include '*fastqc/*' --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/" --exclude "calls/" --exclude '*' ${source_path} ${project_home} >>${log_file} 2>&1
			rsync ${succ_list_path} ${data_home}/update_list 2>&1
			echo "rsync ${succ_list_path} ${data_home}/update_list" >> ${log_file}
			new_list=${prefix}_newList.txt
			echo "mv ${data_home}/update_list/new_list_${project}.txt $new_list" >> ${log_file}
			mv ${data_home}/update_list/new_list_${project}.txt $new_list
			
			#egrep '^>' ${new_list}| cut -d ' ' -f 1 > ${update_list}
			#egrep '^>' ${new_list}| cut -f 1
			awk -F" " '{print $1}' ${new_list} > ${sync_list}
			while IFS='' read -r line || [[ -n "$line" ]]
			do
				# E.g /data/Acc_19/processed_DATA/TCGA_ACC_A5JI/TCGA_ACC_A5JI/successful.txt 1568792330 
				##This does not work on cron!!!!
				# char='/';
				# case_idx=`awk -F"${char}" '{print NF-1}' <<< "${var}"`;
				# let pat_idx=${case_idx}-1;
				# let status_idx=${case_idx}+1;
				# if [ -z $pat_idx ] ; then
				# 	echo "Error syncing in $project and determining pat_id=($pat_idx) on " `hostname` " on "  `date` >> $log_file
				# 	echo "Terminating script..." >> $log_file
				# 	mail -s "Error in syncing $project" '$ADMIN_ADDY' < $log_file
				# 	exit
				# fi
				#old way not dynamic
				pat_idx=6;case_idx=7;status_idx=8;
				if [[ $project == "clinomics" ]] || [[ $project == 'Acc_19' ]] || [[ $project == 'wulab' ]];then
					pat_idx=5;case_idx=6;status_idx=7;
				fi

				pat_id="$(echo "$line" |cut -d/ -f $pat_idx)"
				case_id="$(echo "$line" |cut -d/ -f $case_idx)"
				status="$(echo "$line" |cut -d/ -f $status_idx)"
				folder=${pat_id}/${case_id}
				if [ ! -d ${project_home}/${pat_id} ]; then
					mkdir ${project_home}/${pat_id}
				fi
				echo ${pat_id}/${case_id}/${status} >> ${update_list}
				echo "syncing ${source_path}${folder} ${project_home}/${pat_id}"
				rsync -tirm --include '*/' --include "*.txt" --include '*.tsv'  --include '*.vcf' --include "*.png" --include '*.pdf' --include "*.bwa.loh" --include "*hotspot.depth" --include "*selfSM" --include 'db/*' --include "*tracking" --include "*exonExpression*" --include "TPM_ENS/*" --include "qc/rnaseqc/*" --include "TPM_UCSC/*" --include "RSEM_ENS/*" --include "RSEM_UCSC/*" --include 'HLA/*' --include 'NeoAntigen/*' --include 'HLA/*' --include 'MHC_Class_I/*' --include 'sequenza/*' --include 'cnvkit/*' --include '*fastqc/*' --exclude "log/" --exclude "igv/" --exclude "topha*/" --exclude "fusion/" --exclude "calls/" --exclude '*' ${source_path}${folder} ${project_home}/${pat_id} 2>&1

			done < $sync_list
			echo "done syncing writing to log file ${log_file}"
#		fi
		date >> ${log_file}
#		if [ ! -s ${update_list} ]; then
			echo "uploading" >> ${log_file}

			if [ "$target_db" == "all" ] || [ "$target_db" == "prod" ]
			then
				if [ "$target_type" == "all" ] 
				then
					echo "${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name} -u ${url}" >> ${log_file}
					LC_ALL="en_US.utf8" ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name} -u ${url} 2>&1 1>>${log_file}
					echo "${script_home}/updateVarCases.pl 2>&1 1>>${case_log}" >>${log_file}
					LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
				else
					LC_ALL="en_US.utf8" ${script_home}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name} -u ${url} 2>&1 1>>${log_file}
					LC_ALL="en_US.utf8" ${script_home}/updateVarCases.pl 2>&1 1>>${case_log}
				fi
				echo "refreshing views -c -p -h">>${log_file}
				echo "refreshing views on prod"
				LC_ALL="en_US.utf8" ${script_home}/refreshViews.pl -c -p -h 2>&1 1>>${case_log} &

			fi
			if [ "$target_db" == "all" ] || [ "$target_db" == "dev" ]
			then
				if [ "$target_type" == "all" ] 
				then
					echo "${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev}" >>${log_file}
					LC_ALL="en_US.utf8" ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_file}
					LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 1>>${case_log}
				else
					LC_ALL="en_US.utf8" ${script_home_dev}/loadVarPatients.pl -i ${project_home} -l ${update_list} -t $target_type -d ${db_name_dev} -u ${url_dev} 2>&1 1>>${log_file}	
					LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 1>>${case_log}		
				fi
				LC_ALL="en_US.utf8" ${script_home_dev}/refreshViews.pl -c -p -h 2>&1 1>>${case_log} &
			fi
			chmod -f -R 775 ${project_home}
			echo " done uploading" >> ${log_file}
		fi
		
#	fi
done < $project_file

if [ "$target_type" == "variants" ] 
then	
	echo "refreshing dev??" >> ${log_file}
	echo "${script_home_dev}/updateVarCases.pl"
	LC_ALL="en_US.utf8" ${script_home_dev}/updateVarCases.pl 2>&1 
	LC_ALL="en_US.utf8" ${script_home_dev}/refreshViews.pl -c -p -h 2>&1 
fi

echo "Done syncing! at " `date`
#/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/scripts/backend/syncProcessedResults.sh $target_project $target_type $target_db


