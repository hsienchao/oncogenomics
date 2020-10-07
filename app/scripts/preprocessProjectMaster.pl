#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);
use File::Basename;
use threads;
use MIME::Lite;

my $project_id;
#my $out_dir = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/project_data";
my $email = "";
my $url = "https://clinomics.ncifcrf.gov/production/public";
my $project_name = "";
my $include_pub=0;

$ENV{'PATH'}="/opt/nasapps/development/R/3.5.0/bin:".$ENV{'PATH'};#Ubuntu16
$ENV{'R_LIBS'}="/opt/nasapps/applibs/r-3.5.0_libs/";#Ubuntu16

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
  'i'   => \$include_pub
);

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

my @projects = ();
if ($project_id eq "all") {
	my $sql = "select id, name, ispublic from projects";
	my $sth = $dbh->prepare($sql);
	$sth->execute();
	while (my ($id, $name, $ispublic) = $sth->fetchrow_array) {
		if ($ispublic eq "0" || $include_pub) {
			push @projects, $id;
		}
	}
	$sth->finish();
} else {
	my $sql = "select id, name, isstudy from projects where id = $project_id";
	my $sth = $dbh->prepare($sql);
	$sth->execute();
	if (my ($id, $name, $isstudy) = $sth->fetchrow_array) {
		push @projects, $project_id;
		$project_name = $name;
	} else {
		$sth->finish();
		$dbh->disconnect();
		die("Project $project_id cannot be found!\n");
	}
}
$dbh->do("alter index PROJECT_VALUES_PK INVISIBLE");
$dbh->do("alter index PROJECT_STAT_PK INVISIBLE");
my $all_start = time;
foreach my $pid (@projects) {
	print "Clean up old data...$sid";
	my $start = time;
	$dbh->do("delete from PROJECT_STAT where project_id=$pid");
	$dbh->do("delete from PROJECT_VALUES where project_id=$pid");	
	$dbh->do("update PROJECTS set status=0 where id=$pid");	
	$dbh->commit();
	my $duration = time - $start;
	print "time: $duration s\n";

	$start = time;
	my @types = ('refseq','ensembl');
	#my @levels = ('gene', 'trans');
	#my @types = ('refseq');
	my @levels = ('gene');
	my @thrs = ();
	foreach my $type (@types) {
		foreach my $level (@levels) {
			my $thr = threads->create(\&process, $pid, $type, $level);
			push(@thrs, $thr);
		}
	}

	foreach my $thr(@thrs) {
		$thr->join();
	}

	$duration = time - $start;
	print "Total time for project $pid: $duration s\n";
}
$dbh->do("alter index PROJECT_VALUES_PK VISIBLE");
$dbh->do("alter index PROJECT_STAT_PK VISIBLE");

$dbh->disconnect();
my $total_duration = time - $all_start;
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
