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
my $table_name = "var_annotation";

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -h  <string>  DB Host
  -s  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -i  <string>  Input text file
  -t  <string>  Table name (default: $table_name)
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  's=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  't=s' => \$table_name,
  'i=s' => \$input_file
);

if (!$input_file || !$table_name || !$host || !$sid || !$username || !$passwd) {
    die "Some parameters are missing\n$usage";
}
# ./text_to_db.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -t var_annotation
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

my $tbl_var_smp = "var_annotation_samples";

$dbh->do("delete from $table_name");
$dbh->do("delete from $tbl_var_smp");
open(IN_FILE, "$input_file") or die "Cannot open file $input_file";

my $line = <IN_FILE>;
chomp $line;
my @headers = split(/\t/,$line);
my $num_fields = $#headers;
my $sql_select = "select ID from variants_annotation where chr=? and start_pos=? and end_pos=? and ref_base=? and alt_base=?";
my $sql_smp = "insert into $table_name values(?,?,?,?,?,?,?,?,?,?,?,?,?)";
my $sql_smp_var = "insert into $tbl_var_smp values(?,?)";
my $sth_smp = $dbh->prepare($sql_smp);
my $sth_smp_var = $dbh->prepare($sql_smp_var);
my $sth_select = $dbh->prepare($sql_select);

my %samples = ();
while (<IN_FILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);
	my $chr = $fields[0];
	my $start = $fields[1];
	my $end = $fields[2];
	my $ref = $fields[3];
	my $alt = $fields[4];
	my $sample_id = $fields[8];
	my $qual = $fields[5];
	my $filter = $fields[6];
	my $geno_type = $fields[9];
	my $total_cov = $fields[10];
	my $ref_cov = $fields[11];
	my $var_cov = $fields[12];
	my $vaf = $fields[13];
	my $rnaseq_sample = $fields[14];
	my $rnaseq_total_cov = $fields[15];
	my $rnaseq_ref_cov = $fields[16];
	my $rnaseq_var_cov = $fields[17];
	my $rnaseq_vaf = $fields[18];
	$sth_select->execute($chr, $start, $end, $ref, $alt);
	my $var_id = "";
	if (my @row = $sth_select->fetchrow_array) {
		$var_id = $row[0];
	}
	$sth_smp_var->bind_param(1, $var_id);
	$sth_smp_var->bind_param(2, $sample_id);
	$sth_smp_var->execute();
	if (! exists $samples{$sample_id}) {
		$sth_smp->execute($sample_id, $qual, $filter, $geno_type, $total_cov, $ref_cov, $var_cov, $vaf, $rnaseq_sample, $rnaseq_total_cov, $rnaseq_ref_cov, $rnaseq_var_cov, $rnaseq_vaf);
		$samples{$sample_id} = "";
	}	
}


close(IN_FILE);
$dbh->commit();
$dbh->disconnect();


