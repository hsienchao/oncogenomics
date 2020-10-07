#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);

my $host;
my $sid;
my $username;
my $passwd;
my $dir;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -h  <string>  DB Host
  -s  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -d  <string>  Directory

example: 

$0 -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -d .
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  's=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  'd=s' => \$dir,

);

if (!$dir || !$host || !$sid || !$username || !$passwd) {
    die "Some parameters are missing\n$usage";
}
# ./text_to_db.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -t var_annotation
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 0,
}) || die( $DBI::errstr . "\n" );

opendir (DIR, $dir) or die $!;

while (my $file = readdir(DIR)) {	
	my $table_name = "";
	if ($file =~ /(.*)\.txt/) {
		$table_name = $1;
	} else {
		next;
	}
	print "doing file: $file\n";
	open(INFILE, "$file") or die "Cannot open file $file";

	my $sql = "CREATE TABLE $table_name (\n";
	my $line = <INFILE>;
	chomp $line;
	my @headers = split(/\t/,$line);
	my $num_fields = $#headers;
	my $insert_sql = "insert into $table_name values(";
	foreach my $header (@headers) {
		$header =~ s/^\s+|\s+$//g;
		$header =~ s/\(.*\)//g;
		$header =~ s/[\s\.]/_/g;
		$header =~ s/\#/Num_/g;
		if (lc $header eq "chr") {
			$header = "chromosome";
		}
		if (lc $header eq "start") {
			$header = "start_pos";
		}
		if (lc $header eq "end") {
			$header = "end_pos";
		}
		$sql .= "$header varchar2(256),\n";
		$insert_sql.="?,";
	}	
	chop($sql);
	chop($sql);
	$sql .= ")";
	#print $sql."\n"."\n";
	$dbh->do("Truncate TABLE $table_name");
	#$dbh->do($sql);
	#next;
	chop($insert_sql);
	$insert_sql .= ")";
	my $sth = $dbh->prepare($insert_sql);
	my $rec_cnt = 0;
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < $num_fields);
		for (my $i=0;$i<=$#fields;$i++) {
			$sth->bind_param( $i+1, $fields[$i]);
		}
		$sth->execute();
		$rec_cnt++;
		if ($rec_cnt % 10000 == 0) {
			$dbh->commit();	
		}
	}
	close(INFILE);
	$dbh->commit();	
}

$dbh->disconnect();

