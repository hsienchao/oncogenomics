#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Cwd 'abs_path';
use Getopt::Long qw(GetOptions);
use File::Basename;

my $host;
my $sid;
my $username;
my $passwd;

my $study_id;
my $out_dir;
$ENV{'PATH'}="/opt/nasapps/development/R/3.5.0/bin:".$ENV{'PATH'};#Ubuntu16
$ENV{'R_LIBS'}="/opt/nasapps/applibs/r-3.5.0_libs/";#Ubuntu16
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

required options:

  -h  <string>  DB Host
  -i  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -s  <integer> Study id
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
	print "study_id: $study_id\n";
	print "out_dir: $out_dir\n";
	print "host: $host\n";
	print "sid: $sid\n";
	print "username: $username\n";
	print "passwd: $passwd\n";
    die "Some parameters are missing\n$usage";
}

#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';
#/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/scripts/post_save_study.pl -h 'fr-s-oracle-d.ncifcrf.gov' -i oncosnp11d -u os_admin -p osa0520 -s 535 -o /mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/public/expression/535

my $exp_file = "$out_dir/exp.tsv";
my $exp_ensembl_file = "$out_dir/exp_ensembl.tsv";
my $exp_trans_file = "$out_dir/exp_trans.tsv";
my $gene_stat_file = "$out_dir/gene_stat.tsv";
my $gene_stat_ensembl_file = "$out_dir/gene_stat_ensembl.tsv";
my $trans_stat_file = "$out_dir/trans_stat.tsv";

my $start = time;

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

my $sql = "select e.gene, e.sample_id, e.exp_value, s.tissue_cat from expr_gene e, study_samples s where s.study_id=$study_id and s.sample_id=e.sample_id";
my $sql_ensembl = "select e.gene, e.sample_id, e.exp_value, s.tissue_cat from expr_gene_ensembl e, study_samples s where s.study_id=$study_id and s.sample_id=e.sample_id";
my $sql_trans = "select e.trans, e.sample_id, e.exp_value, s.tissue_cat from expr_trans e, study_samples s where s.study_id=$study_id and s.sample_id=e.sample_id";

my $sql_insert = "insert into STUDY_GENES values(?,?,?,?,?,?,?,?)";
my $sql_insert_trans = "insert into STUDY_TRANS values(?,?,?,?,?,?,?,?)";

my $duration = time - $start;
#gene level
my ($gene_normal_list, @gene_sample_list) = &saveExpFile($sql, $exp_file);
my ($gene_normal_list_ensembl, @gene_sample_list_ensembl) = &saveExpFile($sql_ensembl, $exp_ensembl_file);
print "Gene level query time: $duration s\n";
$start = time;
#trans level
#my ($trans_normal_list, @trans_sample_list) = &saveExpFile($sql_trans, $exp_trans_file);
print "Trans level query time: $duration s\n";

my $loading_file = "$out_dir/loading.tsv";
my $coord_file = "$out_dir/coord.tsv";
my $coord_tmp_file = "$out_dir/coord_tmp.tsv";
my $std_file = "$out_dir/std.tsv";
my $cmd = "Rscript ".dirname($0)."/postSaveStudy.r $exp_file '$gene_normal_list' $gene_stat_file $loading_file $coord_tmp_file $std_file";
system($cmd);
$cmd = "Rscript ".dirname($0)."/postSaveStudy.r $exp_ensembl_file '$gene_normal_list_ensembl' $gene_stat_ensembl_file";
system($cmd);
#$cmd = "Rscript ".dirname($0)."/postSaveStudy.r $exp_trans_file '$trans_normal_list' $trans_stat_file";
#system($cmd);

#fix the sample id in corrd file (R will change them)
open(COORD_FILE, ">$coord_file") or die "Cannot open file $coord_file";
open(COORD_TMP_FILE, $coord_tmp_file) or die "Cannot open file $coord_tmp_file";
my $i = 0;
while(<COORD_TMP_FILE>) {
	chomp;
	my @fields = split(/\s+/);
	$fields[0] = $gene_sample_list[$i++];
	print COORD_FILE join("\t", @fields)."\n";
}
close(COORD_FILE);
close(COORD_TMP_FILE);
system("rm $coord_tmp_file");

&saveStatFile($sql_insert, $gene_stat_file);
&saveStatFile($sql_insert, $gene_stat_ensembl_file);

$dbh->do("update studies set status=1 where id=$study_id");

close(STATFILE);
$dbh->commit();
$dbh->disconnect();
$duration = time - $start;
print "Total time: $duration s\n";

#read gene expression data and write result to text file
sub saveExpFile {
	my ($sql_query, $file_name) = @_;

	open(EXPFILE, ">$file_name") or die "Cannot open file $file_name";
	my $sth = $dbh->prepare($sql_query);
	
	print $sql_query."\n";
	$sth->execute();

	my %exp = ();
	my %exp_trans = ();
	my %samples = ();
	while (my @row = $sth->fetchrow_array) {
	   #$exp{$row[0]}{$row[1]} = $row[2];
	   $exp{$row[0]}{$row[1]} = log(2**$row[2] + 1)/log(2);
	   $samples{$row[1]} = $row[3];
	}

	my @sample_list = keys %samples;
	my @normal_list = ();
	for (my $i=0; $i<=$#sample_list; $i++) {
		print EXPFILE "\t".$sample_list[$i];
		if ($samples{$sample_list[$i]} eq "normal") {
			push @normal_list, ($i+1);
		}
	}


	my $normal_list_str = join(',', @normal_list);
	print EXPFILE "\n";

	while (my ($gene, $samples) = each(%exp) ) {
		my $line = $gene;
		my @exp_array = ();
		my @exp_normal_array = ();
		for my $sample ( keys %samples ) {
			my $value = $exp{$gene}{$sample};
			if (!$value) {
				#$value = -6.6439;
				$value = 0;
			}
			$line .= "\t".$value;
			push @exp_array, $exp{$gene}{$sample};		
		}
		#$sth_insert->execute($study_id, $gene, $gene_avg, $gene_stdev, $gene_median, $gene_normal_avg, $gene_normal_stdev, $gene_normal_median);
		print EXPFILE "$line\n";
	}
	%exp = ();
	close(EXPFILE);
	return ($normal_list_str,@sample_list) ;
}

sub saveStatFile {
	my ($sql_insert, $file_name) = @_;

	my $sth_insert = $dbh->prepare($sql_insert);
	open(STATFILE, $file_name) or die "Cannot open file $file_name";

	while(<STATFILE>) {
	chomp;
	my @fields = split(/\s+/);
	$fields[0] =~ s/"//g;
	$fields[4] = 0 if ($fields[4] eq "NA");
	$fields[5] = 0 if ($fields[5] eq "NA");
	$fields[6] = 0 if ($fields[6] eq "NA");
	$sth_insert->execute($study_id, $fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[5], $fields[6]);
}
}


