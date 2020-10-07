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
# ./text_to_db.pl -h 'fr-s-oracle-da.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -t var_annotation
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

$dbh->do("delete from $table_name");
open(IN_FILE, "$input_file") or die "Cannot open file $input_file";

my @discard_columns = ('GeneDetail.refGene','DoCM Disease','INFO','IF.Actionable-exomic-incidental-findings','IF.Name','IF.Condition(s)','IF.Frequency','IF.Clinical-significance (Last reviewed)','IF.Review status');
my $line = <IN_FILE>;
chomp $line;
my @headers = split(/\t/,$line);
my $num_fields = $#headers;
my $sql = "insert into $table_name values(null,";
for (my $i=0;$i<=$num_fields;$i++) {
	next if (&checkElementExists($headers[$i]));	print $headers[$i]."\n";
	$sql.="?,";
}
chop($sql);
$sql .= ")";
my $sth = $dbh->prepare($sql);

while (<IN_FILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);
	my $col_idx = 1;
	for (my $i=0;$i<=$#fields;$i++) {
		next if (&checkElementExists($headers[$i]));
		my $field_data = $fields[$i];
		if ($field_data eq "-1" || $field_data eq "NA" || $field_data eq "-" || $field_data eq ".") {
			$field_data = "";
		}
		if ($headers[$i] eq 'AAChange.refGene') {
			my @fs = split(/,/,$fields[$i]);
			$field_data = $fs[0];
		}
		if ($headers[$i] eq 'clinvar_20150330') {
			if ($field_data ne 'NA') {
				$field_data = 'YES';
			}			
		}
		if ($headers[$i] eq 'cosmic70') {
			my @fs = split(/;/,$fields[$i]);
			$field_data = $fs[0];			
		}
		$sth->bind_param( $col_idx++, $field_data);
	}
	$sth->execute();
}

my $update_sql = "update $table_name set docm_pmid=(select hgvs from var_webpage where $table_name.chr=var_webpage.chr and $table_name.start_pos=var_webpage.start_pos and $table_name.end_pos=var_webpage.stop_pos and $table_name.ref_base=var_webpage.ref_base and $table_name.alt_base=var_webpage.alt_base)";

$dbh->do($update_sql);

close(IN_FILE);
$dbh->commit();
$dbh->disconnect();

sub checkElementExists {
	my ($field) = @_;
	foreach my $discard_column (@discard_columns) {
		if ($field eq $discard_column) {
			return 1;
		}
	}
	return 0;
}


