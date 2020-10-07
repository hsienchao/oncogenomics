#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);

my $host;
my $sid;
my $username;
my $passwd;
my $input_file;
my $table_name = "var_cnv";
my $has_header=0;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -h  <string>  DB Host
  -s  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -i  <string>  Input text file
  -c            Has header
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  's=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  'i=s' => \$input_file,
  'c' => \$has_header
);

if (!$input_file || !$host || !$sid || !$username || !$passwd) {
  die "Some parameters are missing\n$usage";
}
# ./load_cnv.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -i Omics_copyNumber.txt -c

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

$dbh->do("delete from $table_name");
open(IN_FILE, "$input_file") or die "Cannot open file $input_file";

my $num_fields = 0;
if ($has_header) {
  my $line = <IN_FILE>;
  chomp $line;
  my @headers = split(/\t/,$line);
  $num_fields = $#headers;
}
my $sql_insert = "insert into $table_name values(?,?,?,?,?,?,?)";
my $sql_update = "update $table_name set probe_median = ?, het_pct = ? where patient_id = ? and gene = ?";
my $sth_insert = $dbh->prepare($sql_insert);
my $sth_update = $dbh->prepare($sql_update);

my %patient_genes = ();

while (<IN_FILE>) {
  chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);
	my $patient_id = $fields[0];
  my $chromosome = "chr".$fields[1];
  my $event = $fields[2];
  my $cytoband = $fields[1].$fields[3];
  my $prob_median = $fields[4];
  my $het_pct = $fields[5];
  my @genes = split(/,/, $fields[6]);
  foreach my $gene (@genes) {
    $gene =~ tr/ //ds;
    $patient_genes{$patient_id}{$gene}{"prob_median"} += $prob_median;
    $patient_genes{$patient_id}{$gene}{"het_pct"} += $het_pct;
    $patient_genes{$patient_id}{$gene}{"cnt"}++;
    if ($patient_genes{$patient_id}{$gene}{"cnt"} > 1) {
      $prob_median = $patient_genes{$patient_id}{$gene}{"prob_median"} / $patient_genes{$patient_id}{$gene}{"cnt"};
      $het_pct = $patient_genes{$patient_id}{$gene}{"het_pct"} / $patient_genes{$patient_id}{$gene}{"cnt"};
      $sth_update->execute($prob_median, $het_pct, $patient_id, $gene);
    } else {
      $sth_insert->execute($patient_id, $chromosome, $event, $cytoband, $prob_median, $het_pct, $gene);
    }

  }	
}

close(IN_FILE);
$dbh->commit();
$dbh->disconnect();


