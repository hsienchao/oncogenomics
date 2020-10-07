#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);

# ./text_to_db.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -t var_annotation
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my @files = <*.data>;
my $out_file = "frma.tsv";
open(OUT_FILE, ">>$out_file") or die "Cannot open file $out_file";

foreach my $file (@files) {
	print "processing $file\n";
	open(IN_FILE, "$file") or die "Cannot open file $file";
	my $line = <IN_FILE>;
	chomp $line;
	my @headers = split(/\t/, $line);
	while (<IN_FILE>) {
		chomp;
		my @fields = split(/\t/);
		my $prob = $fields[0]; 
		for (my $i=1;$i<=$#fields;$i++) {
			my $header_idx = $i - ($#fields - $#headers);
			print OUT_FILE $headers[$header_idx]."\t".$prob."\t".$fields[$i]."\n";			
		}
	}
	close(IN_FILE);
}

close(OUT_FILE);

