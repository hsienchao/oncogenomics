#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $refresh_all = 0;
my $do_cnv = 0;
my $do_prj_summary = 0;
my $do_avia = 0;
my $do_cohort = 0;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -a            Refresh all
  -c            Refresh CNV views
  -p            Refresh Project views
  -v            Refresh AVIA views
  -h            Refresh Cohort views
  
__EOUSAGE__



GetOptions (
  'a' => \$refresh_all,
  'c' => \$do_cnv,
  'p' => \$do_prj_summary,
  'v' => \$do_avia,
  'h' => \$do_cohort
);

if (!$refresh_all && !$do_cnv && !$do_prj_summary && !$do_avia && !$do_cohort) {
    die "Please specifiy options!\n$usage";
}

my $script_dir = dirname(__FILE__);

my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 1,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);


if ($refresh_all || $do_prj_summary) {
	print "Refrshing project views...on $sid\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PATIENTS','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('SAMPLE_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROCESSED_SAMPLE_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PROCESSED_CASES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_CASES','C');END;");	
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_PATIENT_SUMMARY','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLE_SUMMARY','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('USER_PROJECTS','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('FUSION_COUNT','C');END;");	
	#$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_SAMPLES','C');END;");	
	#$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_SAMPLE_KHANLAB','C');END;");
#	$dbh->do("truncate table cache");
}

if ($refresh_all || $do_avia) {
	print "Refrshing AVIA view...\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_SAMPLE_AVIA','C');END;");
}

if ($refresh_all || $do_cohort) {
	print "Refrshing cohort views...on $sid\n";
	print "VAR_AA_COHORT\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_AA_COHORT','C');END;");
	print "VAR_GENES\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENES','C');END;");
	print "VAR_COUNT\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_COUNT','C');END;");
	print "FUSION_COUNT\n";	
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_DIAGNOSIS_AA_COHORT','C');END;");
	print "VAR_DIAGNOSIS_GENE_COHORT\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_DIAGNOSIS_GENE_COHORT','C');END;");
	print "VAR_GENE_COHORT\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENE_COHORT','C');END;");
	print "VAR_GENE_TIER\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_GENE_TIER','C');END;");	
	print "PROJECT_DIAGNOSIS_GENE_TIER\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_DIAGNOSIS_GENE_TIER','C');END;");
	print "PROJECT_GENE_TIER\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('PROJECT_GENE_TIER','C');END;");
	print "PROJECT_GENE_TIER\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_TIER_AVIA_COUNT','C');END;");
	print "VAR_TOP20\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_TOP20','C');END;");
	
}

if ($refresh_all || $do_cnv) {
	print "Refrshing CNV views...on $sid\n";
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CNV_GENES','C');END;");
	$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_CNVKIT_GENES','C');END;");
}
#$dbh->do("BEGIN Dbms_Mview.Refresh('VAR_PATIENT_ANNOTATION','C');END;");
$dbh->disconnect();
print "done updating on $host ($sid)\n";
