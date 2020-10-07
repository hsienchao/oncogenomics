target_type=$1
target_folder=$2
EXPECTED_ARGS=2
E_BADARGS=65
if [ $# -ne $EXPECTED_ARGS ]
then
	echo "Usage: `basename $0` [dev/prod] {distination folder}"
	echo "     example: cp_db_config.sh dev /mnt/webrepo/fr-s-bsg-onc-d/htdocs/sandbox"
	exit $E_BADARGS
fi

if [ "$target_type" == "prod" ]
then
	source_folder='/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics'
	ln -s /is2/projects/CCR-JK-oncogenomics/static/project_data ${target_folder}app/storage/project_data
else
	source_folder='/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev'
	ln -s /is2/projects/CCR-JK-oncogenomics/static/project_data_dev ${target_folder}app/storage/project_data
fi

cp ${source_folder}/app/config/database.php ${target_folder}/app/config/
cp ${source_folder}/app/config/packages/jacopo/laravel-authentication-acl/database.php ${target_folder}/app/config/packages/jacopo/laravel-authentication-acl/
cp ${source_folder}/app/config/session.php ${target_folder}/app/config/
cp ${source_folder}/app/config/site.php ${target_folder}/app/config/
cp -r ${source_folder}/public/ref ${target_folder}/public
cp ${source_folder}/app/scripts/backend/getDBConfig.php ${target_folder}/app/scripts/backend/
#These are directories in the storage directory
cp ${source_folder}/app/storage/meta/ ${target_folder}app/storage/. -r
cp ${source_folder}/app/storage/survival/ ${target_folder}app/storage/. -r
ln -s /is2/projects/CCR-JK-oncogenomics/static/GSEA_data ${target_folder}app/storage/GSEA 

