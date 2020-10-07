#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use File::Basename;
use DBD::Oracle qw(:ora_types);
use Getopt::Long qw(GetOptions);
use Cwd 'abs_path';

my $input_file;
my $table_name;
my $has_header = 0;
my $num_commit = -1;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -i  <string>  Input text file
  -t  <string>  Table name
  -c            Has header
  -n  <integer> Commit after <n> inserts (default: no early commits)
  
__EOUSAGE__



GetOptions (
  't=s' => \$table_name,
  'i=s' => \$input_file,
  'n=i' => \$num_commit,
  'c' => \$has_header
);

if (!$input_file || !$table_name) {
    die "Some parameters are missing\n$usage";
}

my $script_dir = abs_path(dirname(__FILE__));
my $app_path = $script_dir."/../..";
my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );

#$dbh->do("truncate table $table_name");
open(IN_FILE, "$input_file") or die "Cannot open file $input_file";

my $num_fields = 0;
my $line = <IN_FILE>;
chomp $line;
my @headers = split(/\t/,$line);
$num_fields = $#headers;

my $sql = "insert into $table_name values(";
for (my $i=0;$i<=$num_fields;$i++) {
	$sql.="?,";
}
chop($sql);
$sql .= ")";
print "The SQL command: $sql\n";
my $sth = $dbh->prepare($sql);

if (!$has_header) {
  seek IN_FILE, 0, 0;
}

my $num_insert = 0;
while (<IN_FILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);
	for (my $i=0;$i<=$#fields;$i++) {
		$sth->bind_param( $i+1, $fields[$i]);
	}
	$sth->execute();
  $num_insert++;
  if ($num_insert == $num_commit) {
      $dbh->commit();
      $num_insert = 0;
  }
}

close(IN_FILE);
$dbh->commit();
$dbh->disconnect();


