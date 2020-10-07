#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $host;
my $sid;
my $username;
my $passwd;
my $dir;
my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/onco.sandbox1/public";
my $replaced_old = 0;
my $load_type = "all";

my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -h  <string>  DB Host
  -s  <string>  Instance ID
  -u  <string>  User name
  -p  <string>  Password
  -i  <string>  Input folder  
  -l  <string>  Oncogenomic url (default: $url)
  -r            Replace old data
  -t  <string>  Load type: ('all': all, 'actionable' : actionable only, 'mutation' : mutation only), default: $load_type
  
__EOUSAGE__



GetOptions (
  'h=s' => \$host,
  's=s' => \$sid,
  'u=s' => \$username,
  'p=s' => \$passwd,
  'i=s' => \$dir,
  'l=s' => \$url,
  'r' => \$replaced_old,
  't=s' => \$load_type,
);

my $script_dir = dirname(__FILE__);

if (!$dir || !$host || !$sid || !$username || !$passwd) {
    die "Some parameters are missing\n$usage";
}
# copy data from: biowulf2:/data/khanlab/projects/DNASeq/*/*/db/*
# /mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/scripts/backend/load_var_patients.pl -h 'fr-s-oracle-d.ncifcrf.gov' -s 'oncosnp11d' -u 'os_admin' -p 'osa0520' -i /mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/variants/patients
#my $host = 'fr-s-oracle-d.ncifcrf.gov';
#my $sid = 'oncosnp11d';
#my $username = 'os_admin';
#my $passwd = 'osa0520';

$dir = &formatDir($dir);

my $db_dir = $dir."db/";
my $ano_dir = $dir."annotation/";
my $ac_dir = $dir."actionable/";
my $qc_dir = $dir."qc/";
opendir (DBDIR, $db_dir) or die $!;
opendir (ANODIR, $ano_dir) or die $!;
opendir (ACDIR, $ac_dir) or die $!;
my %var_anno = ();

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);

my $sql_fu = "insert into gene_fusion values(?,?,?,?,?,?,?,?,?,?,?,?)";
my $sql_fu_dtl = "insert into gene_fusion_details values(?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
my $sql_pat = "insert into var_patient values(?,?,?,?,?,?,?,?,?,?,?)";
my $sql_smp = "insert into var_sample values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
my $sql_ano = "insert into var_annotation values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
my $sql_act = "insert into var_actionable_site values(?,?,?,?,?,?,?,?,?,?)";
my $sql_ano_dtl = "insert into var_annotation_details values(?,?,?,?,?,?,?,?)";
my $sth_fu = $dbh->prepare($sql_fu);
my $sth_fu_dtl = $dbh->prepare($sql_fu_dtl);
my $sth_pat = $dbh->prepare($sql_pat);
my $sth_smp = $dbh->prepare($sql_smp);
my $sth_ano = $dbh->prepare($sql_ano);
my $sth_act = $dbh->prepare($sql_act);
my $sth_ano_dtl = $dbh->prepare($sql_ano_dtl);


my $sth_smp_cat = $dbh->prepare("select sample_id, alias, tissue_cat, exp_type, relation from samples where platform='Illumina'");
$sth_smp_cat->execute();
my %sample_type = ();
my %sample_alias = ();
my %sample_exp_type = ();
my %sample_relation = ();
while (my @row = $sth_smp_cat->fetchrow_array) {
	$sample_type{$row[0]} = $row[2];
	$sample_alias{$row[1]} = $row[0];
	$sample_exp_type{$row[0]} = $row[3];
	$sample_relation{$row[0]} = $row[4];
}

print "load type: $load_type\n";
if ($load_type eq "all" || $load_type eq "actionable") {
	$dbh->do("truncate table gene_fusion");
	$dbh->do("truncate table gene_fusion_details");
	$dbh->do("truncate table var_actionable_site");
	#insert fusion data
	while (my $file = readdir(ACDIR)) {	
		if ($file =~ /(.*)\.(germline|somatic|rnaseq|fusion)\.actionable\.txt/) {
			my $patient_id = $1;
			my $type = $2;
			if ($type eq "fusion") {
				&insertFusion($patient_id, $ac_dir.$file);
			} else {
				&insertActionable($type, $patient_id, $ac_dir.$file);
			}
		}		
	}
}

if ($load_type eq "all" || $load_type eq "qc") {
	$dbh->do("truncate table qc");
	#insert qc data
	while (my $file = readdir(ACDIR)) {	
		if ($file =~ /(.*)\.(germline|somatic|rnaseq|fusion)\.actionable\.txt/) {
			my $patient_id = $1;
			my $type = $2;
			if ($type eq "fusion") {
				&insertFusion($patient_id, $ac_dir.$file);
			} else {
				&insertActionable($type, $patient_id, $ac_dir.$file);
			}
		}		
	}
}

if ($load_type eq "all" || $load_type eq "mutation") {
	$dbh->do("truncate table var_annotation");
	$dbh->do("truncate table var_annotation_details");
	$dbh->do("truncate table var_patient");
	$dbh->do("truncate table var_sample");	
	$dbh->do("alter index var_annotation_pk INVISIBLE");
	$dbh->do("alter index var_annotation_gene INVISIBLE");
	$dbh->do("alter index var_annotation_details_main INVISIBLE");
	#insert db
	while (my $file = readdir(DBDIR)) {	
		if ($file =~ /(.*)\.(germline|somatic|rnaseq|variants)$/) {
			my $patient_id = $1;
			my $type = $2;
			&insertSample($type, $patient_id, $db_dir.$file);
		}		
	}
	#insert annotation
	while (my $file = readdir(ANODIR)) {	
		if ($file =~ /coding\.rare\.txt/) {
			&insertAnnotation($ano_dir.$file);
		}		
	}

	$dbh->do("alter index var_annotation_pk VISIBLE");
	$dbh->do("alter index var_annotation_gene VISIBLE");
	$dbh->do("alter index var_annotation_details_main VISIBLE");

	$dbh->do("update var_annotation set germline='Y' where exists(select * from stjude where var_annotation.chromosome=stjude.chromosome and var_annotation.start_pos=stjude.start_pos and var_annotation.end_pos=stjude.end_pos and var_annotation.ref=stjude.ref and var_annotation.alt=stjude.alt)");
}
=begin
	$dbh->do("delete var_sample where type='germline' and variant_cov = 0");

	$dbh->do("delete var_sample s1 where type='germline' and tissue_cat='tumor' and not exists (
	select * from var_sample s2 where
	s1.chromosome=s2.chromosome and
	s1.start_pos=s2.start_pos and
	s1.end_pos=s2.end_pos and
	s1.ref=s2.ref and
	s1.alt=s2.alt and
	s1.patient_id=s2.patient_id and
	s1.exp_type=s2.exp_type and
	s2.type='germline' and
	s2.tissue_cat = 'normal')");

	$dbh->do("delete var_patient p where type='germline' and not exists (
	select * from var_sample s where
	p.chromosome=s.chromosome and
	p.start_pos=s.start_pos and
	p.end_pos=s.end_pos and
	p.ref=s.ref and
	p.alt=s.alt and
	p.patient_id=s.patient_id and
	s.type='germline')");	

	$dbh->do("merge into var_sample s1 using (select * from var_sample where type='germline' and tissue_cat='normal' and relation='self') s2 
	on
 	(s1.chromosome=s2.chromosome and
	s1.start_pos=s2.start_pos and
	s1.end_pos=s2.end_pos and
	s1.ref=s2.ref and
	s1.alt=s2.alt and
	s1.patient_id=s2.patient_id and
	s1.exp_type=s2.exp_type) 
	when matched then update set s1.vaf_ratio=s1.vaf_ratio/s2.vaf_ratio");	
=cut
#$dbh->do("create unique index var_annotation_pk on var_annotation(chromosome, start_pos, end_pos, ref, alt)");
#$dbh->do("create index var_annotation_gene on var_annotation(gene)");
#$dbh->do("create index var_annotation_details_main on var_annotation_details(chromosome, start_pos, end_pos, ref, alt)");

$dbh->disconnect();


sub insertFusion {
	my ($patient_id, $file) = @_;	
	return if (!-e $file);
		
	open(INFILE, "$file") or die "Cannot open file $file";
	<INFILE>;
	print "processing fusion file: $file\n";
	my %vars = ();
	my %fusions = ();
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < 8);		
		
		my $tool = $fields[7];
		splice @fields, 7, 1;
		my $key = join("\t", @fields);
		my $current_tools = $fusions{$key};
		if ($current_tools) {
			$fusions{$key} = "$current_tools $tool";
		} else {
			$fusions{$key} = $tool;
		}
		for (my $i=0;$i<=$#fields;$i++) {
			if ($fields[$i] eq "-1" || $fields[$i] eq "." || $fields[$i] eq "-") {
				$fields[$i] = "";
			}
		}
	}

	while (my ($key, $tools) = each %fusions) {
		my @fields = split(/\t/, $key);
		my $left_gene = $fields[0];
		my $right_gene = $fields[1];
		my $left_chr = $fields[2];
		my $left_junction = $fields[3];
		my $right_chr = $fields[4];
		my $right_junction = $fields[5];
		my $sample_id = $fields[6];	
		$sample_id =~ s/Sample_//;	
		my $refDB = $fields[7];
		my $level = $fields[8];

		my $cmd = "php $script_dir/getGeneFusionData.php left_gene=$left_gene right_gene=$right_gene left_junction=$left_junction right_junction=$right_junction sample_id=$sample_id url=$url";
		my @results = readpipe($cmd);

		my $type = "out-of-frame";
		foreach my $line (@results) {
			chomp $line;
			my @dtl_fields = split(/\t/, $line);
			my $left_trans = $dtl_fields[2];
			my $left_exp = $dtl_fields[3];
			my $right_trans = $dtl_fields[5];
			my $right_exp = $dtl_fields[6];
			my $trans_type = $dtl_fields[7];
			my $json = $dtl_fields[8];
			if ($trans_type eq "in-frame") {
				$type = "in-frame";
			}
			$sth_fu_dtl->execute($patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $left_trans, $right_trans, $trans_type, $left_exp, $right_exp, $json);
		}

		$sth_fu->execute($patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $tools, $refDB, $type, $level);		

	}
	$dbh->commit();
}

sub insertActionable {
	my ($type, $patient_id, $file) = @_;	
	return if (!-e $file);
		
	print "processing actionable $type file: $file\n";	
		
	open(INFILE, "$file") or die "Cannot open file $file";
	<INFILE>;
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < 10);

		my $germline_level = $fields[$#fields];
		my $somatic_level = $fields[$#fields - 1];
		my $sample_id = $fields[$#fields - 8];
		
		$sth_act->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $patient_id, $sample_id, $type, $somatic_level, $germline_level);

	}
	$dbh->commit();
}

sub insertSample {
	my ($type, $patient_id, $file) = @_;	
	return if (!-e $file);
	if ($replaced_old) {
		$dbh->do("delete var_sample where exists(select * from var_patient where var_patient.type = '$type' and var_patient.patient_id='$patient_id' and var_patient.patient_id = var_sample.patient_id");
		$dbh->do("delete var_patient where type = '$type' and patient_id='$patient_id'");		
	}	
	print "processing $type file: $file\n";	
	#my $sth_check_exists = $dbh->prepare("select count(*) from var_patient where type=? and patient_id=?");
	#$sth_check_exists->execute($type, $patient_id);
	#if (my @row = $sth_check_exists->fetchrow_array) {
	#	if ($row[0] > 0) {
	#		print "already exists, skipped.";
	#		return;
	#	}
	#}	
	open(INFILE, "$file") or die "Cannot open file $file";
	my %var_smp = ();
	my %var_vafs = ();
	my %var_normal_vafs = ();
	my %var_tumor_vafs = ();
	my %var_total_covs = ();
	my %var_normal_total_covs = ();
	my %var_tumor_total_covs = ();
	my %var_exp_covs = ();	
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < 10);
		for (my $i=0;$i<=$#fields;$i++) {
			if ($fields[$i] eq "-1" || $fields[$i] eq "." || $fields[$i] eq "-" || $fields[$i] eq "NA") {
				$fields[$i] = "";
			}
		}
		my $caller_fn = 6;
		if ($#fields == 12) {
			$caller_fn = 8;
		}
		$fields[3] = "-" if ($fields[3] eq "");
		$fields[4] = "-" if ($fields[4] eq "");
		my $total_cov = $fields[$caller_fn + 3];
		my $var_cov = $fields[$caller_fn + 4];
		if ($total_cov eq "" || $total_cov eq "0" || $var_cov =~ /,/) {
			$total_cov = 0;
			$var_cov = 0;
		}

		my $sample_id = $fields[5];
		my $tissue_cat = "normal";
		
		my $exp_type = "";
		my $relation = "";

		if (exists $sample_type{$sample_id}) {
			$tissue_cat = $sample_type{$sample_id};	
			$exp_type = $sample_exp_type{$sample_id};
			$relation = $sample_relation{$sample_id};
		}
		elsif (exists $sample_alias{$sample_id}) {
				$sample_id = $sample_alias{$sample_id};
				$tissue_cat = $sample_type{$sample_id};	
				$exp_type = $sample_exp_type{$sample_id};
				$relation = $sample_relation{$sample_id};			
		}		

		if ($tissue_cat eq "blood") {
			$tissue_cat = "normal";
		}
		if ($type eq 'germline') {
			next if ($total_cov eq "0");
			next if ($var_cov eq "0");
			#next if ($relation ne "self");
			#next if ($tissue_cat eq "tumor");
		}		

		my $key = join(",",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4]);
		my $smp_key = join(",",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $sample_id);
		my $vaf = ($total_cov eq "0")? 0: $var_cov/$total_cov;
		
		# for normal samples, find maximum total count. for tumor samples, find maximum VAF
		if ($tissue_cat eq "normal") {
			# for normal samples, consider self only
			if (lc($relation) eq "self") {
				$var_vafs{$key}{$exp_type}{$tissue_cat} = $vaf;			
				if (exists($var_normal_total_covs{$key})) {
					my $old_total_cov = $var_normal_total_covs{$key};
					if ($old_total_cov < $total_cov) {
						$var_normal_total_covs{$key} = $total_cov;
					}
				} else {
					$var_normal_total_covs{$key} = $total_cov;
				}
				if (exists($var_normal_vafs{$key})) {
					my $old_vaf = $var_normal_vafs{$key};
					if ($old_vaf < $vaf) {
						$var_normal_vafs{$key} = $vaf;
					}
				} else {
					$var_normal_vafs{$key} = $vaf;
				}				
			}			
		} else {
			if ($tissue_cat eq "tumor" && lc($exp_type) ne "rnaseq" && lc($exp_type) ne "variants") {
				if (exists($var_tumor_total_covs{$key})) {
					my $old_total_cov = $var_tumor_total_covs{$key};
					if ($old_total_cov < $total_cov) {
						$var_tumor_total_covs{$key} = $total_cov;
					}
				} else {
					$var_tumor_total_covs{$key} = $total_cov;
				}
				if (exists($var_tumor_vafs{$key})) {
					my $old_vaf = $var_tumor_vafs{$key};
					if ($old_vaf < $vaf) {
						$var_tumor_vafs{$key} = $vaf;
					}
				} else {
					$var_tumor_vafs{$key} = $vaf;
				}
			}
			if (exists($var_vafs{$key}{$exp_type}{$tissue_cat})) {
				my $old_vaf = $var_vafs{$key}{$exp_type}{$tissue_cat};
				if ($old_vaf < $vaf) {
					$var_vafs{$key}{$exp_type}{$tissue_cat} = $vaf;
				}
			} else {
				$var_vafs{$key}{$exp_type}{$tissue_cat} = $vaf;
			}
			if (lc($exp_type) eq "rnaseq" || lc($exp_type) eq "variants") {
				if (exists($var_exp_covs{$key}{'variant_cov'})) {
					my $old_var_cov = $var_exp_covs{$key}{'variant_cov'};
					if ($old_var_cov < $var_cov) {
						$var_exp_covs{$key}{'variant_cov'} = $var_cov;
						$var_exp_covs{$key}{'total_cov'} = $total_cov;
					}
				} else {
					$var_exp_covs{$key}{'variant_cov'} = $var_cov;
					$var_exp_covs{$key}{'total_cov'} = $total_cov;
				}
			}
			
		}
		if (exists($var_total_covs{$key})) {
			my $old_total_cov = $var_total_covs{$key};
			if ($old_total_cov < $total_cov) {
				$var_total_covs{$key} = $total_cov;
			}
		} else {
			$var_total_covs{$key} = $total_cov;
		}		
		my $caller = $fields[$caller_fn];
		if ($exp_type eq "RNAseq" && $type eq "somatic") {
			$caller = "mpileup";			
		}	
		$var_smp{$smp_key} = join(',', $caller, $fields[$caller_fn + 1], $fields[$caller_fn + 2], $total_cov, $var_cov, $tissue_cat, $exp_type, $vaf, $relation);		
	}

	my %vaf_ratios = ();
	while (my ($smp_key, $smp_value) = each %var_smp) {
		my ($chr, $start, $end, $ref, $alt, $sample_id) = split(/,/, $smp_key);
		my ($caller, $qual, $fisher, $total_cov, $var_cov, $tissue_cat, $exp_type, $vaf, $relation) = split(/,/, $smp_value);
		my $key = join(",",$chr, $start, $end, $ref, $alt);
		my $vaf_ratio = 1;
		#find tumor/normal ratio
		if ($tissue_cat eq "tumor") {
			my $normal_vaf = 0;
			if (exists($var_vafs{$key}{$exp_type}{'normal'})) {
				$normal_vaf = $var_vafs{$key}{$exp_type}{'normal'};				
				$vaf_ratio = ($normal_vaf == 0)? 0 : $vaf / $normal_vaf ;
			} else {				
				next if ($type eq "germline");
			}
			if ($normal_vaf > 0.25) {
				if (exists($vaf_ratios{$key})) {
					my $old_vaf_ratio = $vaf_ratios{$key};
					if ($old_vaf_ratio < $vaf_ratio) {
						$vaf_ratios{$key} = $vaf_ratio;
					}
				} else {
					$vaf_ratios{$key} = $vaf_ratio;
				}
			}
		}
		else {
			$vaf_ratio = 1;
			#if (exists($var_vafs{$key}{$exp_type}{'tumor'})) {
			#	$vaf_ratio = ($vaf == 0)? 0 : $var_vafs{$key}{$exp_type}{'tumor'} / $vaf;
			#}
		}		
		$sth_smp->execute($chr, $start, $end, $ref, $alt, $patient_id, $sample_id, $caller, $qual, $fisher, $total_cov, $var_cov, $type, $tissue_cat, $exp_type, $vaf_ratio, $relation);
	}

	while (my ($key, $total_cov) = each %var_total_covs) {
		my $vaf = $var_normal_vafs{$key};
		if ($type eq "germline") {
			next if (!exists($var_normal_total_covs{$key}));
			$total_cov = $var_normal_total_covs{$key};
		}
		if ($type eq "somatic") {
			next if (!exists($var_tumor_total_covs{$key}));
			$total_cov = $var_tumor_total_covs{$key};
			$vaf = $var_tumor_vafs{$key};;
		}
		my ($chr, $start, $end, $ref, $alt) = split(/,/, $key);		
		my $vaf_ratio = 1;
		if (exists($vaf_ratios{$key})) {
			$vaf_ratio = $vaf_ratios{$key};
		}
				
		my $exp_cov = "0/0";
		if (exists($var_exp_covs{$key}{'variant_cov'})) {
			$exp_cov = $var_exp_covs{$key}{'variant_cov'}."/".$var_exp_covs{$key}{'total_cov'};
		}	
		$sth_pat->execute($chr, $start, $end, $ref, $alt, $patient_id, $type, $vaf, $total_cov, $vaf_ratio, $exp_cov);
	}

	$dbh->commit();
}

sub insertAnnotation {
	my ($file) = @_;	
	return if (!-e $file);
	print "processing annotation file: $file\n";
	open(INFILE, "$file") or die "Cannot open file $file";
	my $line = <INFILE>;
	chomp $line;
	my @header_list = split(/\t/, $line);
	my %headers = ();
	for (my $i=0;$i<=$#header_list;$i++) {
		$headers{$header_list[$i]} = $i;
	}

	#main fields
	my $func_idx = $headers{"Func_refGene"};
	my $gene_idx = $headers{"Gene_refGene"};
	my $exonic_idx = $headers{"ExonicFunc_refGene"};
	my $aachange = $headers{"AAChange_refGene"};
	my $snp_idx = $headers{"snp138"};

	#detail fields
	my $gene_start = $headers{"Func_refGene"};
	my $gene_end = $headers{"cytoBand"};
	my $freq_start = $headers{"1000g2014oct_all"};
	my $freq_end = $headers{"ExAC_SAS"};
	my $clinseq_freq_idx = $headers{"Clinseqfreq_varallele"};
	my $prediction_start = $headers{"CADD"};
	my $prediction_end = $headers{"SIFT Score"};
	my $clinvar_start = $headers{"clinvar_CliSig"};
	my $clinvar_clisig_idx = $headers{"clinvar_CliSig"};
	my $clinvar_end = $headers{"clinvar_VarDiseaseDBID"};
	my $cosmic_idx = $headers{"cosmic76"};
	my $hgmd_start = $headers{"hgmd_AccNo"};
	if (!$hgmd_start) {
		print $file."\n"; 
		return;
	}
	my $hgmd_end = $headers{"hgmd_Phenotype"};	
	my $hgmd_cat_idx = $headers{"hgmd_Category"};		
	my $hgmd_acc_idx = $headers{"hgmd_AccNo"};	
	my $actionable_start = $headers{"MATCH_ArmID"};
	my $actionable_end = $headers{"civic_Diagnosis"};
	my $reported_start = $headers{"ICGC_09202015"};
	my $reported_end = $headers{"GRAND_TOTAL"};
	my $grand_total_idx = $headers{"GRAND_TOTAL"};
	my $acmg_start = $headers{"ACMG_Gene"};	
	my $acmg_end = $headers{"ACMG_LSDB"};
	my $acmg_gene_idx = $headers{"ACMG_Gene"};	


	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < $#header_list);
		next if ($fields[0] eq "Chr");
		for (my $i=0;$i<=$#fields;$i++) {
			if ($fields[$i] eq "-1" || $fields[$i] eq "." || $fields[$i] eq "-") {
				$fields[$i] = "";
			}
		}
		$fields[3] = "-" if ($fields[3] eq "");
		$fields[4] = "-" if ($fields[4] eq "");		
		my $var = join(",",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4]);
		if (!exists($var_anno{$var})) {
			#gene
			for (my $i=$gene_start;$i<=$gene_end;$i++) {
				if ($fields[$i] ne '') {
					my ($attr_name) = $header_list[$i] =~ (/(.*)_refGene/);
					if (!$attr_name) {
						$attr_name = $header_list[$i]; 
					}
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "refgene", $attr_name, $fields[$i]);
				}
			}			

			#ACMG
			my $acmg = ($fields[$acmg_gene_idx] ne '')? "Y" : "";
			if ($acmg eq "Y") {
				for (my $i=$acmg_start;$i<=$acmg_end;$i++) {
					if ($fields[$i] ne '') {
						my ($attr_name) = $header_list[$i] =~ (/ACMG_(.*)/);
						$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "acmg", $attr_name, $fields[$i]);
					}
				}
			}
			
			#actionable
			my $actionable = "";
			for (my $i=$actionable_start;$i<=$actionable_end;$i++) {
				if ($fields[$i] ne '') {
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "actionable", $header_list[$i], $fields[$i]);
					$actionable = 'Y';
				}
			}
			
			#SNP138
			my $snp138 = $fields[$snp_idx];
			
			#frequency
			my $freq = 0;
			my %pub_freqs = (
				"1000g2014oct_all"=> "",
				"1000g2014oct_eur"=>"",
				"1000g2014oct_afr"=>"",
				"1000g2014oct_amr"=>"",
				"1000g2014oct_eas"=>"",
				"1000g2014oct_sas"=>"",
				"esp6500_all"=>"",
				"esp6500_ea"=>"",
				"esp6500_aa"=>"",
				"ExAC_ALL_nonTCGA"=>"",
				"ExAC_AFR_nonTCGA"=>"",
				"ExAC_AMR_nonTCGA"=>"",				
				"ExAC_EAS_nonTCGA"=>"",
				"ExAC_FIN_nonTCGA"=>"",
				"ExAC_NFE_nonTCGA"=>"",
				"ExAC_OTH_nonTCGA"=>"",
				"ExAC_SAS_nonTCGA"=>""
				#"cg69"=>"",
				#"Clinseqfreq_varallele"=>"",
			);
			for (my $i=$freq_start;$i<=$freq_end;$i++) {
				next if ($fields[$i] eq "");
				if ($fields[$i] > 0) {
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "freq", $header_list[$i], $fields[$i]);
					if (exists $pub_freqs{$header_list[$i]}) {
						if ($freq < $fields[$i]) {
							$freq = $fields[$i];
						}
					}
				}
			}			
			#clinseq frequency
			if ($fields[$clinseq_freq_idx] ne "") {
				if ($fields[$clinseq_freq_idx] > 0) {
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "freq", $header_list[$clinseq_freq_idx], $fields[$clinseq_freq_idx]);
					#if ($freq < $fields[$clinseq_freq_idx]) {
					#	$freq = $fields[$clinseq_freq_idx];
					#}
				}
			}
			$freq ="" if ($freq == 0);
			#prediction
			my $prediction = '';
			for (my $i=$prediction_start;$i<=$prediction_end;$i++) {
				if ($fields[$i] ne '') {
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "prediction", $header_list[$i], $fields[$i]);
					$prediction = 'Y';
				}
			}

			#clinvar
			my $clinvar = "";
			for (my $i=$clinvar_start;$i<=$clinvar_end;$i++) {
				if ($fields[$i] ne '') {
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "clinvar", $header_list[$i], $fields[$i]);
					$clinvar = 'Y';
				}
			}

			#clinvar sig
			my $clinsig_str = $fields[$clinvar_clisig_idx];
			my @clinsigs = split(/\|/, $clinsig_str);
			my $ciinsig = "";
			foreach my $sig(@clinsigs) {
				if ($sig =~ /^pathogenic/i || $sig =~ /\|pathogenic/i || $sig =~ /^likely pathogenic/i || $sig =~ /\|likely pathogenic/i) {
					$ciinsig = "Y";
					last;
				}
			}
			#cosmic
			my $cosmic = ($fields[$cosmic_idx] ne "NA")? "Y": "";

			if ($cosmic eq "Y") {
				my @cosmic_cols = split(/;/, $fields[$cosmic_idx]);
				foreach my $col(@cosmic_cols) {
					my ($key, $value) = $col =~ /(.*)=(.*)/;
					$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "cosmic", $key, $value);
				}
			}

			#HGMD
			my $hgmd = ($fields[$hgmd_acc_idx] ne '')? "Y" : "";
			if ($hgmd eq "Y") {
				for (my $i=$hgmd_start;$i<=$hgmd_end;$i++) {
					if ($fields[$i] ne '-1') {
						my ($attr_name) = $header_list[$i] =~ (/hgmd_(.*)/);
						$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "hgmd", $attr_name, $fields[$i]);
					}
				}
			}

			#HGMD Category
			my $hgmd_cat = ($fields[$hgmd_cat_idx] eq 'Disease causing mutation')? "Y" : "";			
			#reported
			my $reported = $fields[$grand_total_idx];
			if ($reported ne "" && $reported > 0) {
				for (my $i=$reported_start;$i<=$reported_end;$i++) {
					if ($fields[$i] > 0) {
						$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "reported", $header_list[$i], $fields[$i]);
					}
				}
			}
			$reported = "" if ($reported eq "0");
			$sth_ano->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[$func_idx], $fields[$gene_idx], $fields[$exonic_idx], $fields[$aachange], $actionable, $snp138, $freq, $prediction, $clinvar, $cosmic, $hgmd, $reported, "", $ciinsig, $hgmd_cat, $acmg);
			$var_anno{$var} = '';
		} #end of if		
	} # end of while
	$dbh->commit();
}

sub formatDir {
    my ($dir) = @_;
    if ($dir !~ /\/$/) {
        $dir = $dir."/";
    }
    return $dir;
}

