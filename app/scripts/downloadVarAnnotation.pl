#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);
use File::Basename;
use Data::Dumper;
use threads;
use MIME::Lite;

my $project_id;
#my $out_dir = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/project_data";
my $email = "";
my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics/public";
my $project_name = "";
my $include_pub=0;

my $script_dir = abs_path(dirname(__FILE__));
my $app_path = abs_path($script_dir."/..");
my $out_dir = "$app_path/storage/project_data";

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

options:

  -p  <string> Project id or 'all' for all projects
  -o  <string> Output directory (default: $out_dir)
  -e  <string> Notification email
  -u  <string> OncogenomicsDB URL
  -i           Include public projects
  
__EOUSAGE__



GetOptions (
  'p=s' => \$project_id,
  'o=s' => \$out_dir,
  'e=s' => \$email,
  'u=s' => \$url,  
);
my $start = time;
if (!$project_id) {
    die "Project id is missing\n$usage";
}

my $cmd = "php $script_dir/backend/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);
my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );

my $pid_clause = "";
if ($project_id ne "all") {
	$pid_clause = " and project_id = $project_id";
}
my $sql = "select distinct project_id,p2.patient_id,p2.case_id,p2.type from project_cases p1, var_cases p2 where p1.patient_id=p2.patient_id and p1.case_id=p2.case_id and p2.type<>'hotspot' and p2.type <> 'fusion' and p2.case_id is not null $pid_clause";
my $sth = $dbh->prepare($sql);
$sth->execute();
my %data = ();
while (my @row = $sth->fetchrow_array) {
	if (!$data{$row[0]}) {
		$data{$row[0]} = [];
	}
	push $data{$row[0]}, \@row;
}
$sth->finish();

foreach my $pid (keys %data) {	
	my $var_dir = "$out_dir/$pid/variants";
	system("mkdir -p $var_dir");
	my @rows = @{$data{$pid}};
	my %types = ();
	for my $r(@rows) {
		my @row = @{$r};
		print join("\t", @row)."\n";
		my $cmd = "curl -F project_id=$row[0] -F type=$row[3] -F annotation=avia -F patient_id=$row[1] -F case_id=$row[2] -F stdout=true -F include_details=y $url/downloadVariants > $var_dir/$row[1].$row[2].$row[3].tsv";
		$types{$row[3]} = '';
		system($cmd);
	}
	system("rm $var_dir/*.zip");
	foreach my $type (keys %types) {
		system("zip -j $var_dir/$pid.$type.zip $var_dir/*.$type.tsv");
	}
	#print("$cmd\n");
	#system($cmd);
	
}

$dbh->disconnect();
my $total_duration = time - $start;
print "total time: $total_duration s\n";
if ($project_id ne "all" && $email ne "") {
	sendEmail($email, $url, $project_id, $project_name);
}

sub process {
	my ($pid, $type, $level) = @_;
	$out_dir = &formatDir($out_dir)."$pid";
	system("mkdir -p $out_dir");
	my $cmd = "$script_dir/preprocessProject_RSEM.pl -p $pid -o $out_dir -t $type -l $level";
	print "$cmd\n";
	eval{
		system($cmd);	
	};
	if ($? || $@){
		print "on $pid ($sid) could not run $cmd\nOutput directory set to $out_dir\n";
	}
}

sub formatDir {
    my ($dir) = @_;
    if ($dir !~ /\/$/) {
        $dir = $dir."/";
    }
    return $dir;
}

sub sendEmail {
	my ($email, $url, $project_id, $project_name) = @_;
	my $subject   = "OncogenomicsDB project status";
	my $sender    = 'oncogenomics@mail.nih.gov';
	my $recipient = $email;
	my $content = "<H4>Project <a href=$url/viewProjectDetails/$project_id>$project_name</a> is ready!</H4>";
	my $mime = MIME::Lite->new(
	    'From'    => $sender,
	    'To'      => $recipient,
	    'Subject' => $subject,
	    'Type'    => 'text/html',
	    'Data'    => $content,
	);

	$mime->send();
}
