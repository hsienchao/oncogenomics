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
use Time::Piece;
use Time::Seconds;

my $in_file;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -i  <string>  input file
  
__EOUSAGE__



GetOptions (
  'i=s' => \$in_file  
);

if (!$in_file) {
	print "$usage\n";
	exit(0);
}

my $script_dir = dirname(__FILE__);
my $outdir = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics/app/storage/data/jsons";
my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);

open (INFILE, $in_file) or die "file not found: $in_file";
open (OUTFILE, ">$outdir/patient_id_not_found.txt");

<INFILE>;
my $time = localtime;
my $date = $time->ymd("");
my $i=0;
while (<INFILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < 4);
	my $patient_id = $fields[0];
	my $case_name = $fields[1];
	my $case_id = $fields[2];
	my $version = $fields[3];
	my $status = $fields[4];
	my $path = $fields[5];
	$version = "NA" if ($version eq "");
	my $found = 0;
	my $type = "with_case_name";
	if ($case_name ne "") {
		#check if case exists
		my $sql = "select distinct case_name from sample_case_mapping where patient_id = '$patient_id' and case_name='$case_name'";
		my $sth_read_cases = $dbh->prepare($sql);
		$sth_read_cases->execute();
		if ($sth_read_cases->fetchrow_array) {
			$found = 1;
			system("mkdir -p $outdir/$path/$version/$type");
			my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics/public/getPatientsJson/$patient_id/$case_name";
			$url =~s/\s/%20/g;
			$cmd = "curl $url > '$outdir/$path/$version/$type/${patient_id}=${case_name}=$date.json'";
			print("$cmd\n");
			system($cmd);
		} else {
			$case_name = "";
		}
		$sth_read_cases->finish;
	}
	if ($case_name eq "") {
		my $sql = "select distinct case_name from sample_case_mapping where patient_id = '$patient_id'";
		my $sth_read_cases = $dbh->prepare($sql);
		$sth_read_cases->execute();
		my @cases = ();
		while (my ($case_name) = $sth_read_cases->fetchrow_array) {
			$found = 1;
			push @cases, $case_name;
		}
		$sth_read_cases->finish;
		$type = ($#cases > 0)?"empty_case_name_multiple":"empty_case_name_single";
		foreach my $case_name(@cases) {
			system("mkdir -p $outdir/$path/$version/$type");
			my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics/public/getPatientsJson/$patient_id/$case_name";
			$url =~s/\s/%20/g;		
			$cmd = "curl $url > '$outdir/$path/$version/$type/${patient_id}=${case_name}=$date.json'";
			print("$cmd\n");
			system($cmd);
		}
	}

	if (!$found) {
		print OUTFILE "$patient_id\n";	
	}
}
close(INFILE);
close(OUTFILE);

$dbh->disconnect();

