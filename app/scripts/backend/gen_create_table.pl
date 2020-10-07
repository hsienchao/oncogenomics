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
my $table_name;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -i  <string>  Input text file
  -t  <string>  Table name
  
__EOUSAGE__



GetOptions (
  't=s' => \$table_name,
  'i=s' => \$input_file
);

if (!$input_file || !$table_name) {
    die "Some parameters are missing\n$usage";
}
# ./text_to_db.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -t var_annotation
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

open(INFILE, "$input_file") or die "Cannot open file $input_file";

my $sql = "create table $table_name (\n";
my $line = <INFILE>;
chomp $line;
my @headers = split(/\t/,$line);
foreach my $header (@headers) {
	$header =~ s/^\s+|\s+$//g;
	$header =~ s/\(.*\)//g;
	$header =~ s/[\s\.]/_/g;
	$header =~ s/\#/Num_/g;
	$sql .= "$header varchar2(1000),\n";
}
chop($sql);
chop($sql);
$sql .= ")\n";
print $sql;
close(INFILE);


