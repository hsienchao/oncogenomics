#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);
use File::Basename;
use MIME::Lite;
use Data::Dumper;
my $email = 'vuonghm@mail.nih.gov';
my $project_id;
my $out_dir;
my $type;
my $level;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -p  <integer> project id
  -t  <string>  type
  -l  <string>  level
  -o  <string>  output directory
  
__EOUSAGE__



GetOptions (
  'p=i' => \$project_id,
  't=s' => \$type,
  'l=s' => \$level,
  'o=s' => \$out_dir,
);
if (!$project_id) {
	die "Some parameters are missing\n$usage";
}

my $script_dir = dirname(__FILE__);

my $cmd = "php $script_dir/backend/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );

#
#my %normal_list = ();
#my $normal_list_file = "$script_dir/backend/normal_list.tsv";
#open(NORMAL_FILE, "$normal_list_file") or die "Cannot open file $normal_list_file";
#while (<NORMAL_FILE>) {
#	chomp;
#	my @fields = split(/\t/);
#	if ($#fields == 2) {
#		$normal_list{$fields[0]} = [$fields[1], $fields[2]];
#	}
#}
#close(NORMAL_FILE);
my $sql_insert_stat = "insert into /*+ APPEND */ PROJECT_STAT values(?,?,?,?,?,?,?,?)";
my $sql_insert_project_value = "insert into /*+ APPEND */ PROJECT_VALUES values(?,?,?,?,?,?,?,?)";

my $sth_insert_stat = $dbh->prepare($sql_insert_stat);
my $sth_insert_project_value = $dbh->prepare($sql_insert_project_value);

my $value_type = "exp";
my %sample_name_mapping = ();

&process($type, $level);
&sendEmail("$email","Completed running preprocessProject on $sid for $project_id\nCheck log file /mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/logs/sync.log for uncaught errors" . `date`,"Completed preprocessing script $project_id ($type)","$project_id");
$dbh->disconnect();

sub process {
	my ($type, $level) = @_;
	#my $sql = "select s.sample_id, s.sample_name,e.target,e.gene,e.symbol,e.value from sample_values e, project_samples s where s.exp_type='RNAseq' and e.target_type='$type' and e.target_level = '$level' and s.project_id=$project_id and s.sample_id=e.sample_id";
	my $sql_prj = "select name, version from projects where id=$project_id";
	my $sql_samples = "select distinct s.sample_id, s.sample_name, c.patient_id, c.case_id, path, library_type, tissue_type from project_samples s,cases c where c.patient_id = s.patient_id and s.exp_type='RNAseq' and s.project_id=$project_id order by s.sample_id";
	my $sql_coding = "select distinct symbol from gene where type='protein-coding'";
	my %data = ();
	my %targets = ();
	my %lib_types = ();
	my %tissue_types = ();
	my %coding_symbols = ();
	my %sample_names = ();
	my $start = time;
	#print "$sql_samples\n";
	my $sth_prj = $dbh->prepare($sql_prj);
	my $sth_samples = $dbh->prepare($sql_samples);
	my $sth_coding = $dbh->prepare($sql_coding);

	$sth_prj->execute();
	my $project_name;

	my $version = "";
	($project_name, $version) = $sth_prj->fetchrow_array;
	if (!$version) {
		$version = "19";
	}

	$sth_prj->finish();
	if (!$project_name) {
		print "Project $project_id not found!\n";
		return;
	}
	print "=> in $0 processing project: $project_name($project_id)... level=$level, type=$type\n";
	$sth_coding->execute();
	while (my ($symbol) = $sth_coding->fetchrow_array) {
		#save coding symbol to hash
		$coding_symbols{$symbol} = '';		
	}
	$sth_coding->finish();

	my $rds_list_file = "$out_dir/rds_list-$type-$level.tsv";
	open(RDS_LIST_FILE, ">$rds_list_file") or die "Cannot open file $rds_list_file";
	
	#my %sample_id_list = (); 
	#foreach my $sample_id (keys %normal_list) {
	#	my $sample_name = $normal_list{$sample_id}[0];
	#	$sample_name_mapping{$sample_name} = $sample_id;
	#	my $path = $script_dir."/../storage/ProcessedResults/".$normal_list{$sample_id}[1];	
	#	my $rdsfile = &getRDSFile($path, $sample_id, $sample_name, $type, $level);
	#	if ($rdsfile ne "") {
	#		$sample_id_list{$sample_id} = '';
	#		print RDS_LIST_FILE "$sample_id\t$sample_name\t$rdsfile\n";
	#	}
	#}
	$sth_samples->execute();
	while (my ($sample_id, $sample_name, $patient_id, $case_id, $path, $lib_type, $tissue_type) = $sth_samples->fetchrow_array) {
		#save coding symbol to hash
		my $path = $script_dir."/../storage/ProcessedResults/$path/$patient_id/$case_id";
		$sample_name =~ s/\s*$//;
		$sample_id =~ s/\s*$//;
		
		my $rdsfile = &getRDSFile($path, $sample_id, $sample_name, $type, $level);
		if ($rdsfile ne "") {
			if (!exists $sample_name_mapping{$sample_name}) {
				$sample_name_mapping{$sample_name} = $sample_id;
				$lib_types{$sample_id} = (lc($lib_type) eq "polya");
				$tissue_types{$sample_id} = $tissue_type;
				print RDS_LIST_FILE "$sample_id\t$sample_name\t$rdsfile\n";
				
				#$sample_id_list{$sample_id} = '';
		}


		} else {
			#print "RDS file not found!: $path $case_id$ sample_id\n";
		}
		

	}

	close(RDS_LIST_FILE);
	$sth_samples->finish();

	my $size = keys %sample_name_mapping;
	if ( $size == 0 ) {
		print "No RNAseq data\n";
		$dbh->disconnect();
		exit(0);
	}

	$dbh->do("update projects set status=1 where id=$project_id");
	$dbh->commit();	

	my $type_str = ($type eq "refseq")? "UCSC" : "ENSEMBL";
	my $level_str = ($level eq "gene")? "gene" : "transcript";
	#my $annotation_file = "$script_dir/../storage/data/AnnotationRDS/annotation_".$type_str."_".$level_str."_38.RDS";
	my $annotation_file = "$script_dir/../storage/data/AnnotationRDS/annotation_".$type_str."_".$level_str."_".$version.".RDS";
	print($annotation_file."\n");
	my $normalized_file = "$type-$level";
	print "\nUse annotation file: $annotation_file\n";
	print "\nRscript $script_dir/tmmNormalize.r $rds_list_file $annotation_file $level $out_dir/ $normalized_file\n";
	my $cmd = "Rscript $script_dir/tmmNormalize.r $rds_list_file $annotation_file $level $out_dir/ $normalized_file";
	print "TMM normalizing...\n";
	print "Command: $cmd\n";
	eval{
		system($cmd);
	};
	my $flag=0;
	if ($?||$@){
		&sendEmail("$email","Could not complete running $cmd on $sid\n$?$@","Error running Rscript $script_dir/tmmNormalize.r for $project_name... See log:/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/logs/sync.log \n"," $project_name");
	}


	my $duration = time - $start;
	print "time (TMM): $duration s\n";
	print "\t===> in $0 processing type=$type, level=$level ...\n";
	$start = time;
	
	my $exp_file = "$out_dir/$type-$level";
	my $exp_coding_file = "$out_dir/$type-$level-coding";
	
	my @norm_types = ('tmm-rpkm','tpm');
	my @library_types = ('all','polya','nonpolya');
	foreach my $norm_type (@norm_types) {
		$flag=&insertProjectValues($exp_file, $exp_coding_file, $norm_type, \%coding_symbols, \%lib_types, \%tissue_types);
		if ($flag){
			print "------------------ ERROR in running command -------------------\n";
			print "Could not insert $exp_file, $exp_coding_file, $norm_type, " .Dumper(\%coding_symbols,\%lib_types, \%tissue_types) . "\n";
			print "---------------------------------------------------------------\n";
		}
	}

	$duration = time - $start;
	print "time (Insert DB): $duration s\n";
	$start = time;
	#save value to text file
	my $min_value = 0;	

	$dbh->commit();

	#run R to calculate stats
	my $stat_file = "$out_dir/$type-$level-stat";
	my $loading_file = "$out_dir/$type-$level-loading";
	my $coord_file = "$out_dir/$type-$level-coord";
	my $rds_file = "$out_dir/$type-$level-coding";
	my $coord_tmp_file = "$out_dir/$type-$level-coord_tmp";
	my $std_file = "$out_dir/$type-$level-std";
		
	my $file_prefix = "$out_dir/$type-$level";
	foreach my $norm_type (@norm_types) {
		foreach my $library_type (@library_types) {
			&runStat($library_type, $norm_type);
			#&runStat("$exp_coding_file.$library_type.$norm_type.tsv", "$stat_file.$library_type.$norm_type.tsv", "$loading_file.$library_type.$norm_type.tsv", "$coord_tmp_file.$library_type.$norm_type.tsv", "$std_file.$library_type.$norm_type.tsv", "$rds_file.$library_type.$norm_type.rds", "$coord_file.$library_type.$norm_type.tsv", $norm_type);
		}
	}
	
	#&runStat($exp_coding_tpm_file, $stat_file_tpm, $loading_file_tpm, $coord_tmp_file_tpm, $std_file_tpm, $rds_file_tpm, $coord_file_tpm, "tpm");

	$duration = time - $start;
	print "time(runStat): $duration s\n";
	$flag = $dbh->do("update projects set status=2 where id=$project_id");
	if ($flag){
		print "------------------ Updating status for command #1--------------\n";
		print "Updated projects' status=2 for $project_id with error message ($sid) flag=$flag\n";
		print "---------------------------------------------------------------\n";
	}
	$dbh->commit();	
	$dbh->disconnect();
}

sub runStat {
	#my ($exp_coding_file, $stat_file, $loading_file, $coord_tmp_file, $std_file, $rds_file, $coord_file, $value_type) = @_;
	my ($library_type, $norm_type) = @_;

	my $prefix = "$out_dir/$type-$level";
	my $stat_file = "$prefix-stat.$library_type.$norm_type.tsv";
	my $coord_tmp_file = "$prefix-coord_tmp.$library_type.$norm_type.tsv";
	my $coord_file = "$prefix-coord.$library_type.$norm_type.tsv";
	my $z_coord_tmp_file = "$prefix-coord_tmp.$library_type.$norm_type.zscore.tsv";
	my $z_coord_file = "$prefix-coord.$library_type.$norm_type.zscore.tsv";
	my $cmd = "Rscript ".dirname($0)."/preprocessProject.r $prefix $library_type $norm_type";
	my $input_file = "$prefix-coding.$library_type.$norm_type.tsv";
	if (-e $input_file && `head -n1 $input_file`=~/\w/){
		print "head of file = " . `head -n1 $input_file`; 
		if ($level ne "gene" && -e $input_file) {
			$cmd = "Rscript ".dirname($0)."/preprocessProject.r $prefix $library_type $norm_type";
		}
		eval{
			system($cmd);
		};
		if ($?||$@){
			print "------------------ ERROR in running command #2 ($sid)-------------------\n";
			print "(runStat) $cmd\n";
			print "---------------------------------------------------------------------\n";
			&sendEmail("$email","Could not complete running $cmd (runStat) in $0 on $sid\n(error = $?$@) \nSee log:/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/logs/sync.log for project_id=$project_id\n","Error running ","$project_id");
			die "[ERROR] Running $cmd in $0 for $sid\n";
		}
	}else{
		print "[INFO] Did not run preProcessProject.r for $input_file because there were no samples for combination...that's ok\n";
	}

	#save stats
	if (-e $stat_file) {
		my $sth_insert_stat = $dbh->prepare($sql_insert_stat);
		open(STATFILE, $stat_file) or die "Cannot open file $stat_file";

		my $row_count = 0;
		while(<STATFILE>) {
				chomp;
				my ($target, $mean, $std, $median) = split(/\s+/);
				$target =~ s/"//g;
				if ($std eq 'NA') {
					$std = 0;
				}
				#$sth_insert_stat->execute($project_id, $target, $type, $level, $value_type, $mean, $std, $median);
				$row_count++;
				#if ($row_count % 500 == 0) {
				#	$row_count = 0;
				#	$dbh->commit();		
				#}
		}			
		close(STATFILE);
	}

	if ($level eq "gene") {
		#fix the sample id in corrd file (R will change them)
		if (-e $coord_tmp_file) {	
			open(COORD_FILE, ">$coord_file") or die "Cannot open file $coord_file";
			open(COORD_TMP_FILE, $coord_tmp_file) or die "Cannot open file $coord_tmp_file";
			my $i = 0;
			while(<COORD_TMP_FILE>) {
					chomp;
					my @fields = split(/\s+/);
					print COORD_FILE join("\t", @fields)."\n";
			}
			close(COORD_FILE);
			close(COORD_TMP_FILE);
			system("rm $coord_tmp_file");
		}

		if (-e $z_coord_tmp_file) {	
			open(COORD_FILE, ">$z_coord_file") or die "Cannot open file $z_coord_file";
			open(COORD_TMP_FILE, $z_coord_tmp_file) or die "Cannot open file $z_coord_tmp_file";
			my $i = 0;
			while(<COORD_TMP_FILE>) {
					chomp;
					my @fields = split(/\s+/);
					print COORD_FILE join("\t", @fields)."\n";
			}
			close(COORD_FILE);
			close(COORD_TMP_FILE);
			system("rm $z_coord_tmp_file");
		}
	}
}

sub insertProjectValues {
	my ($exp_file, $exp_coding_file, $norm_type, $coding_symbols_ref, $lib_types_ref, $tissue_type_ref) = @_;
	my %coding_symbols = %$coding_symbols_ref;
	my %lib_types = %$lib_types_ref;
	my %tissue_types = %$tissue_type_ref;
	$exp_file = "$exp_file.$norm_type.tsv";

	open(FILE_CODING_ALL, ">$exp_coding_file.all.$norm_type.tsv") or die "Cannot open file $exp_coding_file.all.$norm_type.tsv";
	open(FILE_CODING_POLYA, ">$exp_coding_file.polya.$norm_type.tsv") or die "Cannot open file $exp_coding_file.polya.$norm_type.tsv";
	open(FILE_CODING_NONPOLYA, ">$exp_coding_file.nonpolya.$norm_type.tsv") or die "Cannot open file $exp_coding_file.nonpolya.$norm_type.tsv";
	open(FILE, "$exp_file") or die "Cannot open file $exp_file";
	# die "opening $exp_file\n";
	my $header = <FILE>;
	chomp $header;
	my @sorted_samples = split(/\t/, $header);
	splice(@sorted_samples, 0, 3);
	# print Dumper (\@sorted_samples);
	# print Dumper (\%coding_symbols);
	# print Dumper (\%lib_types);
	# print Dumper (\%tissue_types)
	my @sample_ids = ();
	my %selected_cols = ();
	my @lib_type_list = ();
	my @polya_samples = ();
	my @nonpolya_samples = ();
	my @all_tissue_types = ();
	my @polya_tissue_types = ();
	my @nonpolya_tissue_types = ();
	foreach my $sample_name (@sorted_samples) {
		my $sample_id = $sample_name_mapping{$sample_name};
		my $is_polya = $lib_types{$sample_id};
		my $tissue_type = $tissue_types{$sample_id};
		push @all_tissue_types, $tissue_type;
		push @lib_type_list, $is_polya;
		push @sample_ids, $sample_id;
		if ($is_polya) {
			push @polya_samples, $sample_name;
			push @polya_tissue_types, $tissue_type;
		} else {
			push @nonpolya_samples, $sample_name;
			push @nonpolya_tissue_types, $tissue_type;
		}
	}
	my $sample_name_list = join("\t", @sorted_samples);
	my $polya_sample_name_list = join("\t", @polya_samples);
	my $nonpolya_sample_name_list = join("\t", @nonpolya_samples);
	my $tissue_type_list = join("\t", @all_tissue_types);
	my $polya_tissue_type_list = join("\t", @polya_tissue_types);	
	my $nonpolya_tissue_type_list = join("\t", @nonpolya_tissue_types);

	$sth_insert_project_value->execute($project_id, "_list", "_list", "_list", $type, $level, $norm_type, join(",", @sample_ids));
	my $sample_list = join("\t", @sorted_samples);
	print FILE_CODING_ALL "\t$sample_name_list\n";
	print FILE_CODING_POLYA "\t$polya_sample_name_list\n";
	print FILE_CODING_NONPOLYA "\t$nonpolya_sample_name_list\n";
	print FILE_CODING_ALL "Diagnosis\t$tissue_type_list\n";
	print FILE_CODING_POLYA "Diagnosis\t$polya_tissue_type_list\n";
	print FILE_CODING_NONPOLYA "Diagnosis\t$nonpolya_tissue_type_list\n";
	
	while(<FILE>) {
		chomp;
		my @fields = split(/\t/);
		if ($#fields > 3) {
			my $target = $fields[0];
			my $gene = $fields[1];
			my $symbol = $fields[2];
			splice(@fields, 0, 3);
			my $value_list = join(",", @fields);
			my @value_log_list = ();
			my @polya_value_log_list = ();
			my @nonpolya_value_log_list = ();
			my $sum = 0;
			my $sum_polya = 0;
			my $sum_nonpolya = 0;
			my $i = 0;
			foreach my $value (@fields) {
				#my $new_value = log($value + 1)/log(2);
				my $new_value = sprintf("%.2f", $value);
				if ($lib_type_list[$i]) {
					$sum_polya += $new_value;				
					push @polya_value_log_list, $new_value;
				} else {
					$sum_nonpolya += $new_value;				
					push @nonpolya_value_log_list, $new_value;
				}
				$sum += $new_value;				
				push @value_log_list, $new_value;
				$i++;
			}
			if (exists $coding_symbols{$symbol}) {
				if ($sum > 0) {
					print FILE_CODING_ALL "$symbol\t".join("\t", @value_log_list)."\n";
				}
				if ($sum_polya > 0) {
					print FILE_CODING_POLYA "$symbol\t".join("\t", @polya_value_log_list)."\n";
				}
				if ($sum_nonpolya > 0) {
					print FILE_CODING_NONPOLYA "$symbol\t".join("\t", @nonpolya_value_log_list)."\n";
				}
				$sth_insert_project_value->execute($project_id, $target, $gene, $symbol, $type, $level, $norm_type, $value_list); 
			}			
		}
	}
	close(FILE);
	close(FILE_CODING_ALL);
	close(FILE_CODING_POLYA);
	close(FILE_CODING_NONPOLYA);
	if ($#sorted_samples == 0) {
		print "Removing empty file $exp_coding_file.all.$norm_type.tsv\n";
		system("rm $exp_coding_file.all.$norm_type.tsv -f");
	}
	if ($#polya_samples == 0) {
		print "Removing empty file $exp_coding_file.polya.$norm_type.tsv\n";
		system("rm $exp_coding_file.polya.$norm_type.tsv -f");
	}
	if ($#nonpolya_samples == 0) {
		print "Removing empty file $exp_coding_file.nonpolya.$norm_type.tsv\n";
		system("rm $exp_coding_file.nonpolya.$norm_type.tsv -f");
	}	
}

sub getRDSFile {
	my ($path, $sample_id, $sample_name, $type, $level) = @_;
	my $level_str = ($level eq "gene")? "gene" : "transcript";
	my $suffix = ".$level_str.fc.RDS";
	my $folder = ($type eq "refseq")? "TPM_UCSC" : "TPM_ENS";
	my $sample_file = "$path/Sample_$sample_id/$folder/Sample_$sample_id$suffix";

	if ( -e $sample_file) {
		return $sample_file;
	}
	$sample_file = "$path/Sample_$sample_name/$folder/Sample_$sample_name$suffix";	
	if ( -e $sample_file) {
		return $sample_file;
	}
	$sample_file = "$path/$sample_id/$folder/$sample_id$suffix";
	if ( -e $sample_file) {
		return $sample_file;
	}
	$sample_file = "$path/$sample_name/$folder/$sample_name$suffix";
	if ( -e $sample_file) {
		
		return $sample_file;
	}

	return "";			
}

sub formatDir {
    my ($dir) = @_;
    if ($dir !~ /\/$/) {
        $dir = $dir."/";
    }
    return $dir;
}

sub sendEmail {
	my ($email, $content, $subject, $project_name) = @_;
	$subject   ||= "OncogenomicsDB preProcessProject Error for $sid";
	my $sender    = 'oncogenomics@mail.nih.gov';
	my $recipient = $email;
	
	my $mime = MIME::Lite->new(
	    'From'    => $sender,
	    'To'      => $recipient,
	    'Subject' => $subject,
	    'Type'    => 'text/html',
	    'Data'    => $content,
	);

	$mime->send();
}
