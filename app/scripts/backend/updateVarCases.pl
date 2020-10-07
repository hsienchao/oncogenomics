#!/usr/bin/perl -w
# Note to developers debugging this code.  This code written by either Hsien Chao or Scott Goldweber 
# This update script requires that there are at least one variant in the VAR_SAMPLES table for a particular patient.
# If it does not exist, it will keep the case_name fields ='' in the CASES and SAMPLE_CASES table
# Without the case_name information in the table, the application will not function\
# HR added code to send email so the lab can manually review 
# --HR 2019/08/15
use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $default_case_name = "20160415";
my $verbose = 0;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -c  <string>  Default case name (default: $default_case_name)
  -v            Verbose output
  
__EOUSAGE__



GetOptions (
  'c=s' => \$default_case_name,
  'v' => \$verbose
);

my $script_dir = dirname(__FILE__);

my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);

my $sth_read_cases = $dbh->prepare("select patient_id, case_id, version from processed_cases where status <> 'not_successful'");
#my $sth_var_samples = $dbh->prepare("select distinct patient_id, case_id, sample_id from var_samples");
my $sth_var_samples = $dbh->prepare("select distinct * from (select distinct patient_id, case_id, sample_id from var_samples union select distinct patient_id, case_id, sample_id from var_qc)");
my $sth_sample_cases = $dbh->prepare("select distinct patient_id, case_name, sample_id from sample_cases where exp_type <> 'Methylseq'");
my $sth_write_cases = $dbh->prepare("update sample_case_mapping set case_id=?, match_type=? where case_name=? and patient_id=?");
my $sth_orphan_cases = $dbh->prepare("select patient_id,case_id,path from processed_cases p where not exists(select * from sample_case_mapping c where p.patient_id=c.patient_id and p.case_id=c.case_id) order by patient_id,case_id");
#my $sth_write_var_cases = $dbh->prepare("update var_type set case_name=? where case_id=? and patient_id=?");
my %processed_cases = ();
my %processed_samples = ();
my %master_file_samples = ();
my %sample_cases = ();
my %case_samples = ();

$sth_read_cases->execute();
while (my ($patient_id, $case_id, $version) = $sth_read_cases->fetchrow_array) {
	$processed_cases{$patient_id}{$case_id} = $version;
}
#processed samples
$sth_var_samples->execute();
while (my ($patient_id, $case_id, $sample_id) = $sth_var_samples->fetchrow_array) {
	$processed_samples{$patient_id}{$case_id}{$sample_id} = '';
}
$sth_var_samples->finish;

#case in master file
$sth_sample_cases->execute();
while (my ($patient_id, $case_name, $sample_id) = $sth_sample_cases->fetchrow_array) {
	push @{$master_file_samples{$patient_id}{$case_name}}, $sample_id;
}
$sth_sample_cases->finish;

open(PERFECT_MATCH, ">perfectly_matched_cases.txt");
open(PARTIAL_MATCH, ">partial_matched_cases.txt");
open(NOTMATCH, ">notmatched_cases.txt");
open(NOT_PIPELINE, ">processed_not_pipeline_cases.txt");
open(PARTIAL_PROCESSED, ">partial_processed_cases.txt");
open(NEW_PATIENT, ">new_patient_cases.txt");

#check all patients in master file
foreach my $patient_id (sort { $master_file_samples{$b} <=> $master_file_samples{$a} } keys %master_file_samples) {
	my %case_names = %{$master_file_samples{$patient_id}};
	#check all cases in master file
	foreach my $case_name (sort { $case_names{$b} <=> $case_names{$a} } keys %case_names) {
		#if not processed at all
		if (!exists $processed_samples{$patient_id}) {
			#if TCGA or GTEX
			if (exists $processed_cases{$patient_id}{$case_name}) {
				print NOT_PIPELINE "$patient_id\t$case_name\n";
				$sth_write_cases->execute($case_name, "not_pipeline", $case_name, $patient_id);
			} else {
				print NEW_PATIENT "$patient_id\t$case_name\n";
				$sth_write_cases->execute("", "new_patient", $case_name, $patient_id);
			}
			last;
		} else {
			my @case_ids = sort(keys %{$processed_samples{$patient_id}});
			my @samples = @{$master_file_samples{$patient_id}{$case_name}};
			my $total_master_samples = $#samples + 1;
			my $perfect_case_id = "";
			my $perfect_version = "";
			my $partial_case_id = "";
			my $partial_processed_case_id = "";
			my $partial_processed_cnt = 0;
			#check all processed cases
			foreach my $case_id(@case_ids) {
				#check every sample in this case_name
				my $match_cnt = 0;
				foreach my $sample_id(@samples) {
					if (exists $processed_samples{$patient_id}{$case_id}{$sample_id}) {
						$match_cnt++;						
					} 
				}
				my @processed_samples = keys %{$processed_samples{$patient_id}{$case_id}};
				my $total_processed_samples = $#processed_samples + 1;
				if ($total_master_samples == $total_processed_samples && $match_cnt == $total_master_samples) {
					my $version = $processed_cases{$patient_id}{$case_id};
					if (!$version) {
						print("$patient_id/$case_id not in processed_cases\n");
					} else {
						if ($perfect_version lt $version) {
							$perfect_version = $version;
							$perfect_case_id = $case_id;
						}
					}					
				}
				if ($total_master_samples < $total_processed_samples && $match_cnt == $total_master_samples) {
					$partial_case_id = $case_id;
				}
				if ($total_master_samples > $total_processed_samples && $match_cnt == $total_processed_samples) {
					if ($match_cnt > $partial_processed_cnt) {
						$partial_processed_case_id = $case_id;
						$partial_processed_cnt = $match_cnt;
					}					
				}
			}
			# priority: perfect > partial matched > partial processed > not processed
			if ($perfect_case_id ne '' ) {
				print PERFECT_MATCH "$patient_id\t$case_name\t$perfect_case_id\n";
				$sth_write_cases->execute($perfect_case_id, "matched", $case_name, $patient_id);
			} else {
				if ($partial_case_id ne '' ) {
					print PARTIAL_MATCH "$patient_id\t$case_name\t$partial_case_id\n";
					$sth_write_cases->execute($partial_case_id, "partial_matched", $case_name, $patient_id);
				}
				else {
					if ($partial_processed_case_id ne '' ) {
						print PARTIAL_PROCESSED "$patient_id\t$case_name\t$partial_processed_case_id\n";
						$sth_write_cases->execute($partial_processed_case_id, "partial_processed", $case_name, $patient_id);
					} else {
						print NOTMATCH "$patient_id\t$case_name\n";
						$sth_write_cases->execute("", "not_matched", $case_name, $patient_id);
					}
				}
			}

		}
	}
}
close(PERFECT_MATCH);
close(PARTIAL_MATCH);
close(NOTMATCH);
close(NOT_PIPELINE);
close(PARTIAL_PROCESSED);
close(NEW_PATIENT);

$dbh->commit();

open(ORPHAN_CASE, ">orphan_cases.txt");
$sth_orphan_cases->execute();
while (my ($patient_id, $case_id, $path) = $sth_orphan_cases->fetchrow_array) {
	print ORPHAN_CASE "$patient_id\t$case_id\t$path\n";
}
$sth_orphan_cases->finish;
close(ORPHAN_CASE);

$dbh->disconnect();

system("$script_dir/refreshViews.pl -p");

