#!/usr/bin/perl -w

use strict;
use warnings;
use Getopt::Long qw(GetOptions);

my $dir;
my $out_file;
my $field_name = "frequency";
my $len_cutoff = 50;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

	-d  <string>  Directory
	-o  <string>  Output file

optional options:
	-f  <string>  Field name if no header info found. (Default: $field_name)
	-c  <int>     Length cutoff. (Default: $len_cutoff)
__EOUSAGE__



GetOptions (
	'd=s' => \$dir,
	'o=s' => \$out_file,
	'f=s' => \$field_name,
	'c=i' => \$len_cutoff

);

if (!$dir || !$out_file) {
    die "Some parameters are missing\n$usage";
}

my $failed_file = "$out_file.failed.tsv";

opendir (DIR, $dir) or die $!;
open(OUTFILE, ">$out_file") or die "Cannot open file $out_file";
open(FAILFILE, ">$failed_file") or die "Cannot open file $failed_file";

while (my $file = readdir(DIR)) {	
	my $type = "";
	if ($file =~ /var_(.*)\.txt/) {
		$type = $1;
	} else {
		next;
	}
	print "doing file: $file\n";
	open(INFILE, "$file") or die "Cannot open file $file";

	my @headers = ();
	my $set_header = 0;
	while (<INFILE>) {
		$_ =~ s/\r|\n//g;
		chomp;		
		my @fields = split(/\t/);
		if (length($fields[3]) > $len_cutoff || length($fields[4]) > $len_cutoff) {
			print FAILFILE $_."\n";
			next;
		}
		if ($#fields > 5 && !$set_header) {
			@headers = @fields;
			if ($headers[0] !~ /Chr/) {
				last;
			}
			$set_header = 1;
			next;
		}
		my $key = "";
		if ($fields[0] !~ /^chr/) {
			$fields[0] = "chr".$fields[0];
		}
		for (my $i=0;$i<5;$i++) {
			$key.= $fields[$i]."\t";
		}		
		if ($set_header) {
			for (my $i=5;$i<=$#fields;$i++) {
				if ($fields[$i] ne ".") {
					if ($type eq "clinseq") {
						if ($headers[$i] eq "Clinseqfreq_varallele") {
							print OUTFILE $key.$type."\tClinSeq\t".$fields[$i]."\n";
						}
					} else {
						print OUTFILE $key.$type."\t".$headers[$i]."\t".$fields[$i]."\n";
					}
				}
			}
		} else {
			if ($fields[5] ne ".") {
				print OUTFILE $key.$type."\t".$field_name."\t".$fields[5]."\n";
			}
		}
	}
	close(INFILE);
}

close(OUTFILE);
