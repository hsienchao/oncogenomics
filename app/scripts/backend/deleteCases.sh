input_file=$1
d=$( dirname "${BASH_SOURCE[0]}")

echo $d
while read -r line
do
set $line
	patient_id=$1
	case_id=$2
	path=$3
	if [ -z $path ]; then
		echo "${d}/deleteCase.pl -p $patient_id -c $case_id -r"
		${d}/deleteCase.pl -p $patient_id -c $case_id -r
	else
		echo "${d}/deleteCase.pl -p $patient_id -c $case_id -t $path -r"
		${d}/deleteCase.pl -p $patient_id -c $case_id -t $path -r
	fi	
done < $input_file
