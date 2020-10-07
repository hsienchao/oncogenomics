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
#/mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/app/scripts/postSaveStudy.pl -h 'fr-s-oracle-d.ncifcrf.gov' -i oncosnp11d -u os_admin -p osa0520 -s 535 -o /mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.sandbox1/public/expression/535

my $exp_file = "$out_dir/exp.tsv";
my $exp_ensembl_file = "$out_dir/exp_ensembl.tsv";
my $exp_trans_file = "$out_dir/exp_trans.tsv";
my $gene_stat_file = "$out_dir/gene_stat.tsv";
my $gene_stat_ensembl_file = "$out_dir/gene_stat_ensembl.tsv";
my $trans_stat_file = "$out_dir/trans_stat.tsv";
#my $min_value = -6.6439;
my $min_value = 0;

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );

my $sql = "select e.sample_id,e.target,e.target_type,e.target_level,e.value,s.tissue_cat from sample_value e, study_samples s where s.study_id=$study_id and s.sample_id=e.sample_id";

my $sql_insert_stat = "insert into STUDY_STAT values(?,?,?,?,?,?,?,?)";
my $sql_insert_sample_list = "insert into STUDY_SAMPLE_LIST values(?,?,?,?)";
my $sql_insert_study_value = "insert into STUDY_VALUE values(?,?,?,?,?,?)";

$dbh->do("delete from STUDY_STAT where study_id=$study_id");
$dbh->do("delete from STUDY_SAMPLE_LIST where study_id=$study_id");
$dbh->do("delete from STUDY_VALUE where study_id=$study_id");
$dbh->do("alter index STUDY_VALUE_TARGET INVISIBLE");
$dbh->do("alter index STUDY_VALUE_STUDY_ID INVISIBLE");

my $sth = $dbh->prepare($sql);
my $sth_insert_stat = $dbh->prepare($sql_insert_stat);
my $sth_insert_sample_list = $dbh->prepare($sql_insert_sample_list);
my $sth_insert_study_value = $dbh->prepare($sql_insert_study_value);


my %data = ();
my %types = ();
my %levels = ();
my %samples = ();
my %n_samples = ();
my %targets = ();
my $start = time;
print "Fetching sample data...";
$sth->execute();
while (my @row = $sth->fetchrow_array) {
	#save data to hash...type->level->sample->target
	#print $row[4];
	my $value = $row[4];
	#antilog, for old data
	$value = log(2**$value + 1)/log(2);

	$data{$row[2]}{$row[3]}{$row[0]}{$row[1]} = $value;
	$types{$row[2]} = '';
	$levels{$row[3]} = '';
	$samples{$row[0]} = '';
	$targets{$row[2]}{$row[3]}{$row[1]} = '';
	if ($row[5] eq "normal") {
		$n_samples{$row[0]} = '';
	}
}
$sth->finish();
my $duration = time - $start;
print "time: $duration s\n";
foreach my $type (keys %types) {		
	foreach my $level (keys %levels) {
		print "Processing type: $type, level: $level";
		$start = time;
		my @sorted_samples = sort keys %{$data{$type}{$level}};
		my $file_name = "$out_dir/$type-$level.tsv";
		my @normal_samples = ();
		foreach my $sample(@sorted_samples) {
			if ($n_samples{$sample}) {
				push @normal_samples, $sample;
			}
		}		

		$sth_insert_sample_list->execute($study_id, $type, $level, join(",", @sorted_samples));
		my $sample_list = join("\t", @sorted_samples);
		my $normal_sample_list = join(",", @normal_samples);
		open(FILE, ">$file_name") or die "Cannot open file $file_name";
		print FILE "\t$sample_list\n";
		my @target_list = keys %{$targets{$type}{$level}};
		#save value to text file
		my $row_count = 0;
		foreach my $target(@target_list) {
			my $line = $target;
			my @value_list = ();
			foreach my $sample(@sorted_samples) {
				my $value = $data{$type}{$level}{$sample}{$target};
				if (!$value) {
					$value = $min_value;
				}				
				push @value_list, $value;
			}
			print FILE "$line\t".join("\t", @value_list)."\n";
			$sth_insert_study_value->execute($study_id, $target, $type, $level, "log2", join(",", @value_list)); 
			$row_count++;
			if ($row_count % 500 == 0) {
				$row_count = 0;
				$dbh->commit();		
			}
		}
		close(FILE);

		#run R to calculate stats
		my $stat_file = "$out_dir/$type-$level-stat.tsv";
		my $loading_file = "$out_dir/$type-$level-loading.tsv";
		my $coord_file = "$out_dir/$type-$level-coord.tsv";
		my $coord_tmp_file = "$out_dir/$type-$level-coord_tmp.tsv";
		my $std_file = "$out_dir/$type-$level-std.tsv";
		my $cmd = "Rscript ".dirname($0)."/postSaveStudy.r $file_name '$normal_sample_list' $stat_file $loading_file $coord_tmp_file $std_file";
		system($cmd);

		#fix the sample id in corrd file (R will change them)
		open(COORD_FILE, ">$coord_file") or die "Cannot open file $coord_file";
		open(COORD_TMP_FILE, $coord_tmp_file) or die "Cannot open file $coord_tmp_file";
		my $i = 0;
		while(<COORD_TMP_FILE>) {
			chomp;
			my @fields = split(/\s+/);
			print COORD_FILE join("\t", @fields)."\n";
		}
		close(COORD_FILE);
		close(COORD_TMP_FILE);
		system("rm $coord_tmp_file");

		#save stats
		my $sth_insert_stat = $dbh->prepare($sql_insert_stat);
		open(STATFILE, $stat_file) or die "Cannot open file $stat_file";

		$row_count = 0;
		while(<STATFILE>) {
			chomp;
			my ($target, $mean, $std, $median, $normal_mean, $normal_std, $normal_median) = split(/\s+/);
			$target =~ s/"//g;
			$normal_mean = $min_value if ($normal_mean eq "NA");
			$normal_std = 1 if ($normal_std eq "NA");
			$normal_median = $min_value if ($normal_median eq "NA");			
			$sth_insert_stat->execute($study_id, $target, $type, $level, "all", $mean, $std, $median);
			$sth_insert_stat->execute($study_id, $target, $type, $level, "normal", $normal_mean, $normal_std, $normal_median);

			my @cmedian = ();
			my @zscore = ();
			my @cmedian_normal = ();
			my @zscore_normal = ();
			foreach my $sample(@sorted_samples) {
				my $value = $data{$type}{$level}{$sample}{$target};
				if (!$value) {
					$value = $min_value;
				}			
				push @cmedian, $value - $median, ;
				push @cmedian_normal, $value - $normal_median;
				push @zscore, ($std == 0)? 0:($value - $mean)/$std;
				push @zscore_normal, ($normal_std == 0)? 0:($value - $normal_mean)/$normal_std;
			}
			$sth_insert_study_value->execute($study_id, $target, $type, $level, "mcenter", join(",", @cmedian)); 
			$sth_insert_study_value->execute($study_id, $target, $type, $level, "zscore", join(",", @zscore)); 
			$sth_insert_study_value->execute($study_id, $target, $type, $level, "mcenter_normal", join(",", @cmedian_normal)); 
			$sth_insert_study_value->execute($study_id, $target, $type, $level, "zscore_normal", join(",", @zscore_normal)); 
			$row_count++;
			if ($row_count % 500 == 0) {
				$row_count = 0;
				$dbh->commit();		
			}
		}			
		close(STATFILE);
		$duration = time - $start;
		print "time: $duration s\n";
		$dbh->commit();
	}
}

$dbh->commit();
$dbh->do("alter index STUDY_VALUE_TARGET VISIBLE");
$dbh->do("alter index STUDY_VALUE_STUDY_ID VISIBLE");

$dbh->disconnect();

