#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);

my $host;
my $sid;
my $username;
my $passwd;

my $study_id;
my $out_dir;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -h  <string>  DB Host
  -i  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -s  <integer> study id
  -o  <string>  Output directory
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  'i=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  's=i' => \$study_id,
  'o=s' => \$out_dir
);

if (!$study_id || !$out_dir || !$host || !$sid || !$username || !$passwd) {
    die "Some parameters are missing\n$usage";
}

#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my $outfile = "$out_dir/exp.tsv";
my $normal_outfile = "$out_dir/exp_normal.tsv";
system("mkdir -p $out_dir");
open(OUTFILE, ">$outfile") or die "Cannot open file $outfile";
open(OUTFILE, ">$normal_outfile") or die "Cannot open file $normal_outfile";
my $start = time;

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

my $sql = "select e.gene, e.sample_id, e.exp_value, s.tissue_cat from expr e, study_samples s where s.study_id=$study_id and s.sample_id=e.sample_id";

my $sql_insert = "insert into STUDY_GENES values(?,?,?,?,?,?,?,?)";
my $sth = $dbh->prepare($sql);
my $sth_insert = $dbh->prepare($sql_insert);
$sth->execute();

my $duration = time - $start;
print "SQL query time: $duration s\n";

my %exp = ();
my %samples = ();

while (my @row = $sth->fetchrow_array) {
   $exp{$row[0]}{$row[1]} = $row[2];
   $samples{$row[1]} = $row[3];
}

for my $sample ( keys %samples ) {
	print OUTFILE "\t$sample";
	print NORMAL_OUTFILE "\t$sample";
}

print OUTFILE "\n";

my %gene_expr = ();
my %gene_mean = ();
my %gene_std = ();
while (my ($gene, $samples) = each(%exp) ) {
	my $line = $gene;
	my @exp_array = ();
	my @exp_normal_array = ();
	   for my $sample ( keys %samples ) {
		$line .= "\t".$exp{$gene}{$sample};
		push @exp_array, $exp{$gene}{$sample};
		if ($samples{$sample} eq "normal") {
			push @exp_normal_array, $exp{$gene}{$sample};
		}
	}
	my $gene_avg = &mean(\@exp_array);
	my $gene_stdev = &stdev(\@exp_array);
	my $gene_median = &median(\@exp_array);
	my $gene_normal_avg = &mean(\@exp_normal_array);
	my $gene_normal_stdev = &stdev(\@exp_normal_array);
	my $gene_normal_median = &median(\@exp_normal_array);
	$gene_expr{$gene} = \@exp_array;
	$gene_mean{$gene} = $gene_avg;
	$gene_std{$gene} = $gene_stdev;
	$sth_insert->execute($study_id, $gene, $gene_avg, $gene_stdev, $gene_median, $gene_normal_avg, $gene_normal_stdev, $gene_normal_median);
	print OUTFILE "$line\n";
}
%exp = ();
close(OUTFILE);
#$sth_insert->execute($row[0], $1, $2);
my $loading_file = "$out_dir/loading.tsv";
my $coord_file = "$out_dir/coord.tsv";
my $std_file = "$out_dir/std.tsv";
my $cmd = "Rscript ".abs_path($0)."/PCA.r $outfile $loading_file $coord_file $std_file T";
$dbh->do("update studies set status=1 where id=$study_id");

=pod
my $sql_corr = "insert into gene_coexpr values(?,?,?,?)";
my $sth_corr = $dbh->prepare($sql_corr);

while (my ($gene1, $gene1_expr) = each(%gene_expr) ) {
	my $mean1 = $gene_mean{$gene1};
	my $std1 = $gene_std{$gene1};
	while (my ($gene2, $gene2_expr) = each(%gene_expr) ) {
		my $mean2 = $gene_mean{$gene2};
		my $std2 = $gene_std{$gene2};
		my $corr = &corr($gene1_expr, $gene2_expr, $mean1, $mean2, $std1, $std2);
		if (abs($corr) > 0.3) {
			$sth_corr->execute($study_id, $gene1, $gene2, $corr);
		}
	}	
}
=cut

close(OUTFILE);
$dbh->commit();
$dbh->disconnect();
$duration = time - $start;
print "Total time: $duration s\n";

sub corr {
	my ($exp1, $exp2, $mean1, $mean2, $std1, $std2) = @_;
	my $correlation = 0;      
	my $sum = 0;
	my $n = @$exp1;
	for (my $i=0;$i<$n;$i++) {
		$sum += @$exp1[$i] * @$exp2[$i];
	}
	$correlation = ($sum - $mean1*$mean2*$n)/(($n-1)*$std1*$std2);
	return $correlation;
}

sub mean{
        my($data) = @_;
        if (not @$data) {
                die("Empty array");
        }
        my $total = 0;
        foreach (@$data) {
                $total += $_;
        }
        my $average = $total / @$data;
        return $average;
}

sub stdev{
        my($data) = @_;
        if(@$data == 1){
                return 0;
        }
        my $average = &mean($data);
        my $sqtotal = 0;
        foreach(@$data) {
                $sqtotal += ($average-$_) ** 2;
        }
        my $std = ($sqtotal / (@$data-1)) ** 0.5;
        return $std;
}

sub median {
	my($data) = @_;
	my @vals = sort {$a <=> $b} @$data;
	my $len = @vals;
	if ($len % 2) {
		return $vals[int($len/2)];
	}
	else {
		return ($vals[int($len/2)-1] + $vals[int($len/2)])/2;
	}
}

