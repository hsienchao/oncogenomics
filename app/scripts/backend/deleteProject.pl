#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $processed_data_dir = '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/project_data/';
my $project_id;
my $remove_folder = 0;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -p  <string>  Project ID
  -r            Remove project folder
  
__EOUSAGE__



GetOptions (
  'p=s' => \$project_id,
  'r' => \$remove_folder  
);

if (!$project_id) {
    die "Please input project_id\n$usage";
}

my $script_dir = dirname(__FILE__);

my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );

$dbh->do("delete projects where id=$project_id");
$dbh->do("delete project_patients where project_id='$project_id'");
$dbh->do("delete project_values where project_id='$project_id'");
$dbh->do("delete project_stat where project_id='$project_id'");
$dbh->do("delete project_samples where project_id='$project_id'");

if ($remove_folder) {
  system("rm -rf $processed_data_dir$project_id");
}

$dbh->commit();
$dbh->disconnect();
