#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);
use File::Basename;
use threads;
use MIME::Lite;
use Time::HiRes qw (sleep);

my $threads="";
my $out_file="";
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

options:

  -t  <string> number of threads
  
__EOUSAGE__



GetOptions (
  't=s' => \$threads,
);

#system("cd ../app");
#system($cmd);
#open(my $fh, '>', $out_file);

my @thrs = ();
for (my $i=1; $i <= $threads; $i++) {
	my $start = time;
	my $thr = threads->create(\&Test);
#	push(@thrs, $thr);
#	$thr->join();
	my $duration = time - $start;
	sleep(0.5);
#	print $fh $duration."\n";

}


sub Test{
my $cmd="phpunit --configuration ../phpunit.xml";
system($cmd);

}
