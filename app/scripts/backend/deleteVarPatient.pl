#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $processed_data_dir = '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/ProcessedResults/';
my $patient_id;
my $remove_folder = 0;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -p  <string>  Patient ID
  -r            Remove patient folder
  
__EOUSAGE__



GetOptions (
  'p=s' => \$patient_id,
  'r' => \$remove_folder
);

if (!$patient_id) {
    die "Please input patient_id\n$usage";
}


my $script_dir = dirname(__FILE__);

my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );

my $sth_var_cases = $dbh->prepare("select distinct path from var_cases where patient_id = '$patient_id'");
$sth_var_cases->execute();
while (my @row = $sth_var_cases->fetchrow_array) {
	my $path = $row[0];
	my $patient_folder = "$processed_data_dir/$path/$patient_id";
	if ($remove_folder) {
		system("rm -rf $patient_folder");
	}
}

$sth_var_cases->finish;

$dbh->do("delete var_samples where patient_id='$patient_id'");
$dbh->do("delete var_cases where patient_id='$patient_id'");
$dbh->do("delete patients where patient_id='$patient_id'");
$dbh->do("delete patient_details where patient_id='$patient_id'");
$dbh->do("delete sample_details s1 where exists(select * from samples s2 where s2.patient_id='$patient_id' and s1.sample_id=s2.sample_id)");
$dbh->do("delete sample_values s1 where exists(select * from samples s2 where s2.patient_id='$patient_id' and s1.sample_id=s2.sample_id)");
$dbh->do("delete samples where patient_id='$patient_id'");
$dbh->do("delete sample_cases where patient_id='$patient_id'");
$dbh->do("delete var_acmg_guide where patient_id='$patient_id'");
$dbh->do("delete var_acmg_guide_details where patient_id='$patient_id'");
$dbh->do("delete var_flag where patient_id='$patient_id'");
$dbh->do("delete var_flag_details where patient_id='$patient_id'");
$dbh->do("delete var_cnv where patient_id='$patient_id'");
$dbh->do("delete var_cnvkit where patient_id='$patient_id'");
$dbh->do("delete var_fusion where patient_id='$patient_id'");
$dbh->do("delete var_tier where patient_id='$patient_id'");
$dbh->do("delete var_tier_avia where patient_id='$patient_id'");
$dbh->do("delete var_qc where patient_id='$patient_id'");
$dbh->do("delete mutation_burden where patient_id='$patient_id'");
$dbh->do("delete neo_antigen where patient_id='$patient_id'");
$dbh->do("delete cases where patient_id='$patient_id'");
$dbh->do("delete project_patients where patient_id='$patient_id'");

$dbh->commit();
$dbh->disconnect();
