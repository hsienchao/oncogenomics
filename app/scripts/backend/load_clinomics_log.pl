#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);

my $host;
my $sid;
my $username;
my $passwd;
my $input_file;

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -h  <string>  DB Host
  -s  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -i  <string>  Input text file
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  's=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  'i=s' => \$input_file
);

if (!$input_file || !$host || !$sid || !$username || !$passwd) {
    die "Some parameters are missing\n$usage";
}
# ./load_clinomics_log.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -i ClinomicsLog.txt
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);
open(IN_FILE, "$input_file") or die "Cannot open file $input_file";

my $line = <IN_FILE>;
chomp $line;
my @headers = split(/\t/,$line);
my %header_idx = ();
my $idx = 0;
foreach my $header(@headers) {
	$header_idx{$header} = $idx++;
}
my $libray_idx = $header_idx{'sample'};
my $tissue_cat_idx = $header_idx{'normal/tumor'};
my $fcid_idx = $header_idx{'run_id'};
my $sample_type_idx = $header_idx{'sample_type'};
my $normal_sample_idx = $header_idx{'sampleN'};
my $patient_idx = $header_idx{'CustomID'};
my $diagnosis_idx = $header_idx{'diagnosis'};
my $partitioning_idx = $header_idx{'partitioning'};


my $num_fields = $#headers;
my $sql_smp = "merge into samples s1 using (select ? as sample_id from dual) s2 on (s1.sample_id=s2.sample_id)
when matched then update set 
	s1.patient_id = ?, 
	s1.material_type = ?, 
	s1.exp_type = ?,
	s1.platform = ?,
	s1.tissue_cat = ?, 
	s1.tissue_type = ?, 
	s1.normal_sample = ?, 
	s1.sample_capture =? 
when not matched then insert (
	sample_id, 
	sample_name, 
	patient_id, 
	material_type, 
	exp_type, 
	platform, 
	library_type, 
	tissue_cat, 
	tissue_type, 
	normal_sample, 
	sample_capture,relation) values(?,?,?,?,?,?,?,?,?,?,?,'self')";

=commnet
$sql_smp = "insert into samples (
	sample_id, 
	sample_name, 
	patient_id, 
	material_type, 
	exp_type, 
	platform, 
	library_type, 
	tissue_cat, 
	tissue_type, 
	normal_sample, 
	sample_capture) values(?,?,?,?,?,?,?,?,?,?,?)";
=cut

my $sql_pat = "merge into patients p1 using (select ? as patient_id from dual) p2 on (p1.patient_id=p2.patient_id)
when matched then update set 
	p1.diagnosis = ? 
when not matched then insert (
	patient_id,	 
	diagnosis, 
	project_name, 
	is_cellline 
	) values(?,?,?,?)";
my $sth_smp = $dbh->prepare($sql_smp);
my $sth_pat = $dbh->prepare($sql_pat);

my %patients = ();
my %samples = ();
my %normal_samples = ();

while (<IN_FILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);
	if ($fields[$tissue_cat_idx] eq 'Normal' || $fields[$tissue_cat_idx] eq 'Saliva') {
		my $sample_id = $fields[$libray_idx]."_".$fields[$fcid_idx];
		$normal_samples{$fields[$libray_idx]} = $sample_id;
	}
}
seek(IN_FILE,0,0); 
<IN_FILE>;
while (<IN_FILE>) {
	chomp;
	my @fields = split(/\t/);
	next if ($#fields < $num_fields);

	for (my $i = 0; $i<=$#fields; $i++) {
		if ($fields[$i] eq '#N/A!' || $fields[$i] eq 'Unknown' || $fields[$i] eq '0') {
			$fields[$i] = '';
		}
	}
	
	my $custom_id = $fields[$patient_idx];
	my $diagnosis = $fields[$diagnosis_idx];
	my $platform = "Illumina";
	my $material_type = "DNA";
	my $exp_type = "Exome";
	my $capture = "/data/Clinomics/Ref/serpentine_resources/design/Agilent_SureSelect_Clinical_Research_Exome.target.hg19.merged.bed";
	my $library_type = "";
	my $isCellline = "N";
	if ($fields[$sample_type_idx] =~ /RNA/) {
		$material_type = "RNA";
		$exp_type = "RNAseq";
		$capture = "";
		$library_type = 'polyA';
	}
	my $tissue_cat = lc($fields[$tissue_cat_idx]);
	if ($tissue_cat eq "saliva") {
		$tissue_cat = "normal";
	}
	
	my $library = $fields[$libray_idx];
	my $fcid = $fields[$fcid_idx];
	my $sample_id= $library."_".$fcid;

	my $normal = "";
	if ($normal_samples{$fields[$normal_sample_idx]}) {
		$normal = $normal_samples{$fields[$normal_sample_idx]};
	}

	my $partitioning = $fields[$partitioning_idx];
	if ($partitioning =~ /clin.snv/) {
		$exp_type = "Panel";
	}
	if ($partitioning =~ /Killian/) {
		$exp_type = "Panel";
	}

	if ($exp_type eq "Panel") {
		$capture = "/data/Clinomics/Ref/serpentine_resources/design/Agilent_SureSelect_Killian_Version4.target.hg19.merged.bed";
	}
		
	my $project = "Clinomics";
	
	if ($diagnosis eq "GIST") {
		$diagnosis = 'Gastrointestinal stromal tumor';
	}
	if ($diagnosis eq "Cell line") {
		$isCellline = "Y";
	}
	
	if (!exists $patients{$custom_id}) {
		$sth_pat->execute($custom_id, $diagnosis, $custom_id, $diagnosis,$project, $isCellline);
		print "insert/updating $custom_id\n";
		$patients{$custom_id} = '';
	}
	print "insert/updating $sample_id\n";
	$sth_smp->execute($sample_id, $custom_id, $material_type, $exp_type, $platform, $tissue_cat, $diagnosis, $normal, $capture, $sample_id, $sample_id, $custom_id, $material_type, $exp_type, $platform, $library_type, $tissue_cat, $diagnosis, $normal, $capture);
	#$sth_smp->execute($sample_id, $sample_id, $custom_id, $material_type, $exp_type, $platform, $library_type, $tissue_cat, $diagnosis, $normal, $capture);
	my $count = $sth_smp->rows;
	print("insert/update $count rows.\n");

}

$dbh->commit();
$dbh->disconnect();


