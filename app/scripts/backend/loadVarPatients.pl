#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;
use LWP::Simple;
use LWP::UserAgent;
use Scalar::Util qw(looks_like_number);
use Try::Tiny;
use MIME::Lite; 
use File::Temp qw/ tempfile tempdir /;
use POSIX;
use Cwd 'abs_path';

local $SIG{__WARN__} = sub {
	my $message = shift;
	if ($message =~ /uninitialized/) {
		die "Warning:$message";
	}
};

my $target_patient;
my $target_case;
my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev/public";
my $db_name = "development";
my $canonical_trans_list = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/data/refseq_canonical.txt";
my $fusion_list_file = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/storage/data/sanger_mitelman_pairs.txt";
my $update_list_file;
my $skip_fusion = 0;
my $replaced_old = 0;
my $insert_col = 0;
my $load_type = "all";
my $use_sqlldr = 0;
my $remove_noncoding = 0;
my $refresh_exp = 0;
my $case_name_eq_id = 0;
my $email = "";

my $script_dir = abs_path(dirname(__FILE__));
my $app_path = $script_dir."/../..";
my $dir_example = "$app_path/storage/ProcessedResults/processed_DATA";
my $dir;
my $usage = <<__EOUSAGE__;

Usage:

$0 [options]

Options:

  -i  <string>  Input folder (example: $dir_example) 
  -l  <string>  Update list  
  -u  <string>  Oncogenomic url (default: $url)
  -d  <string>  Database name (default: $db_name)
  -r            Replace old data
  -s            Use SQLLoader
  -p  <string>  Upload specified patient
  -c  <string>  Upload specified case
  -a            Append annotation column table
  -g            Remove noncoding variants (annotated by AVIA)
  -t  <string>  Load type: (all,fusion,variants,tier,annotation,qc,cnv,antigen,exp,burden), default: $load_type
  -n  <string>  Canonical transcript list file (default: $canonical_trans_list)
  -f  <string>  Fusion list file (default: $fusion_list_file)
  -x            Update expression data
  -k            Assign case id using case name.
  -y            Skip loading fusion
  -e  <string>  Email be notified.
  
__EOUSAGE__



GetOptions (
  'i=s' => \$dir,
  'u=s' => \$url,
  'd=s' => \$db_name,
  'l=s' => \$update_list_file,
  'r' => \$replaced_old,
  's' => \$use_sqlldr,
  'g' => \$remove_noncoding,
  'a' => \$insert_col,
  'x' => \$refresh_exp,
  'n=s' => \$canonical_trans_list,
  'f=s' => \$fusion_list_file,
  'p=s' => \$target_patient,
  'c=s' => \$target_case,
  't=s' => \$load_type,
  'k' => \$case_name_eq_id,
  'y' => \$skip_fusion
);

if (!$dir) {
    die "Some parameters are missing\n$usage";
}

if (!$update_list_file) {
	$update_list_file = '';
	#$replaced_old = 1;
}


my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd, $port) = split(/\t/, $db_config[0]);
my $dbh = DBI->connect( "dbi:Oracle:host=$host;port=$port;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,    
}) || die( $DBI::errstr . "\n" );
#$dbh->trace(4);

chdir $dir;

my $var_sample_tbl = "var_samples";
my $var_annotation_tbl = "var_annotation";
my $var_annotation_col_tbl = "var_annotation_col";
my $var_annotation_dtl_tbl = "var_annotation_details";
my $sth_ano_exists = $dbh->prepare("select count(*) from $var_annotation_tbl where chromosome = ? and start_pos = ? and end_pos = ? and ref = ? and alt = ?");
my $sth_exp_exists = $dbh->prepare("select count(*) from sample_values where sample_id=? and target_type=? and target_level=? and rownum=1");
my $sth_exp_exon_exists = $dbh->prepare("select count(*) from exon_expression where sample_id=? and target_type=? and rownum=1");
my $sth_diag = $dbh->prepare("select diagnosis from patients where patient_id=?");
my $sth_emails = $dbh->prepare("select email_address from users where permissions like '%_superadmin%'");
my $sth_fu = $dbh->prepare("insert into /*+ APPEND */ var_fusion values(?,?,?,?,?,?,?,?,?,?,?,?,?)");
my $sth_fu_dtl_old = $dbh->prepare("insert into /*+ APPEND */ var_fusion_details values(?,?,?,?,?,?,?,?,?,?,?)");
my $sth_fu_dtl = $dbh->prepare("insert into /*+ APPEND */ var_fusion_dtl values(?,?,?,?,?,?,?,?,?,?)");
my $sth_smp = $dbh->prepare("insert into  /*+ APPEND */ $var_sample_tbl values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
#my $sth_ano = $dbh->prepare("insert into $annotation_tbl values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
my $sth_ano = $dbh->prepare("insert into $var_annotation_tbl values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
my $sth_ano_dtl = $dbh->prepare("insert into $var_annotation_dtl_tbl values(?,?,?,?,?,?,?,?)");
my $sth_ano_col = $dbh->prepare("insert into $var_annotation_col_tbl values(?,?,?)");
my $sth_act = $dbh->prepare("insert into var_actionable_site values(?,?,?,?,?,?,?,?,?,?)");
my $sth_qc = $dbh->prepare("insert into var_qc values(?,?,?,?,?,?)");
my $sth_exp = $dbh->prepare("insert into /*+ APPEND */ sample_values values(?,?,?,?,?,?,?)");
my $sth_tier = $dbh->prepare("insert into /*+ APPEND */ var_tier values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
my $sth_sample_cases = $dbh->prepare("update sample_cases set case_id=? where patient_id=? and case_id=?");
my $sth_tier_avia = $dbh->prepare("insert into /*+ APPEND */ var_tier_avia values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
# my $sth_exon_exp = $dbh->prepare("insert /*+ APPEND */ into exon_expression values(?,?,?,?,?,?,?)");
my $sth_cnv = $dbh->prepare("insert into var_cnv values(?,?,?,?,?,?,?,?,?,?)");
my $sth_cnvkit = $dbh->prepare("insert into var_cnvkit values(?,?,?,?,?,?,?,?,?,?)");
my $sth_mutation_burden = $dbh->prepare("insert into mutation_burden values(?,?,?,?,?,?)");
my $stn_del_noncoding = $dbh->prepare("delete $var_sample_tbl v where patient_id=? and case_id=? and exists(select * from hg19_annot\@pub_lnk a where SUBSTR(v.chromosome,4) = a.chr and v.start_pos=a.query_start and v.end_pos=a.query_end and v.ref=a.allele1 and v.alt=a.allele2 and (maf > 0.05 or annovar_annot not in ('exonic','splicing','exonic;splicing')))");
my $stn_rnaseqfp_khanlab = $dbh->prepare("delete var_tier t where type='rnaseq' and exists(select * from rnaseq_fp r where t.chromosome=r.chromosome and t.start_pos=r.start_pos and t.end_pos=r.end_pos and t.ref=r.ref and t.alt=r.alt)");
my $stn_rnaseqfp_avia = $dbh->prepare("delete var_tier_avia t where type='rnaseq' and exists(select * from rnaseq_fp r where t.chromosome=r.chromosome and t.start_pos=r.start_pos and t.end_pos=r.end_pos and t.ref=r.ref and t.alt=r.alt)");

my $sth_update_smp_exp_cov = $dbh->prepare("update $var_sample_tbl v1 
	set (matched_var_cov, matched_total_cov) = (select var_cov, total_cov from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='rnaseq' and
	  rownum=1
	  )
	where patient_id=? and case_id=? and type <> 'somatic' and type <> 'rnaseq' and type <> 'hotspot' and exists(select * from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='rnaseq')");

my $sth_update_smp_dna_cov = $dbh->prepare("update $var_sample_tbl v1 
	set (matched_var_cov, matched_total_cov) = (select var_cov, total_cov from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type<>'rnaseq' and
	  rownum=1
	  )
	where patient_id=? and case_id=? and type='rnaseq' and exists(select * from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type<>'rnaseq')");

my $sth_update_smp_hotspot_exp_cov = $dbh->prepare("update $var_sample_tbl v1 
	set (matched_var_cov, matched_total_cov) = (select var_cov, total_cov from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='hotspot' and
	  v2.exp_type='RNAseq' and
	  rownum=1
	  )
	where patient_id=? and case_id=? and type='hotspot' and exp_type <> 'RNAseq' and exists(select * from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='hotspot' and
	  v2.exp_type='RNAseq')");

my $sth_update_smp_hotspot_dna_cov = $dbh->prepare("update $var_sample_tbl v1 
	set (matched_var_cov, matched_total_cov) = (select var_cov, total_cov from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='hotspot' and
	  v2.exp_type<>'RNAseq' and	  
	  rownum=1
	  )
	where patient_id=? and case_id=? and type='hotspot' and exp_type='RNAseq' and exists(select * from $var_sample_tbl v2 where
	  v1.chromosome=v2.chromosome and
	  v1.start_pos=v2.start_pos and
	  v1.end_pos=v2.end_pos and
	  v1.ref=v2.ref and
	  v1.alt=v2.alt and
	  v1.patient_id=v2.patient_id and
	  v1.case_id=v2.case_id and
	  v2.type='hotspot' and
	  v2.exp_type<>'RNAseq'
	  )");

my $sth_delete_smp_rnaseq_splicing = $dbh->prepare("delete $var_sample_tbl v where patient_id=? and case_id=? and type ='rnaseq' and exists(select * from var_annotation a where 
	v.chromosome=a.chromosome and
	v.start_pos=a.start_pos and
	v.end_pos=a.end_pos and
	v.ref=a.ref and 
	v.alt=a.alt and a.func='splicing')");

$dir = &formatDir($dir);

my $project_folder = basename($dir);

my %update_list = ();
my %update_cases = ();

my %var_smp = ();

if ($update_list_file ne '') {
	open(UPDATE_FILE, $update_list_file) or die "Cannot open file $update_list_file";
	while(<UPDATE_FILE>) {
		chomp;
		$update_list{$_} = '';
		if (/(.*?)\/(.*?)\/successful.txt/) {
			my $failed = $dir."$1/$2/failed.txt";
			system("rm -rf $failed");
			$update_cases{$1}{$2} = '';
		}
	}
	close(UPDATE_FILE);
} 

my %fusion_list = ();
my %fusion_pair_list = ();

#read fusion pair file which will be used to determine the fusion tier
if (! $skip_fusion) {
	open(FUSION_FILE, $fusion_list_file) or die "Cannot open file $fusion_list_file";
	while(<FUSION_FILE>) {
			chomp;
			my @pairs = split(/\t/);
			if ($#pairs == 1) {
				$fusion_list{$pairs[0]} = '';
				$fusion_list{$pairs[1]} = '';	
				$fusion_pair_list{$_} = '';
			}
	}
	close(FUSION_FILE); 
}

my %var_anno = ();

# get sample information
my $sth_smp_cat = $dbh->prepare("select sample_id, sample_name, tissue_cat, exp_type, relation, normal_sample, rnaseq_sample, sample_alias from samples");
$sth_smp_cat->execute();
my %sample_type = ();
my %sample_alias = ();
my %sample_names = ();
my %sample_exp_type = ();
my %sample_relation = ();
my %match_normal = ();
my %match_tumor = ();
my %match_rnaseq = ();
my %chr_list = ();

for (my $i=1; $i<=22; $i++) {
	$chr_list{"chr".$i} = '';
}
$chr_list{"chrX"} = '';
$chr_list{"chrY"} = '';

while (my @row = $sth_smp_cat->fetchrow_array) {
	next if (!$row[0]);
	$sample_type{$row[0]} = $row[2];
	$sample_alias{$row[7]} = $row[0] if ($row[7]);
	$sample_names{$row[0]} = $row[1];
	$sample_exp_type{$row[0]} = $row[3];
	$sample_relation{$row[0]} = $row[4];
	if ($row[5]) {
		if ($row[0] ne $row[5] && $row[1] ne $row[5]) {
			$match_normal{$row[0]} = $row[5];
		}
	}
	$match_rnaseq{$row[0]} = $row[6] if ($row[6]);
}
$sth_smp_cat->finish;

while (my ($sample_id, $normal_id) = each %match_normal) {
	if (exists $sample_alias{$normal_id}) {
		$normal_id = $sample_alias{$normal_id};
		$match_normal{$sample_id} = $normal_id;
	}
	push @{$match_tumor{$normal_id}}, $sample_id;
}
while (my ($sample_id, $rnaseq_id) = each %match_rnaseq) {
	if (exists $sample_alias{$rnaseq_id}) {
		$match_rnaseq{$sample_id} = $sample_alias{$rnaseq_id};
	}
}

#get all cases

my $sth_cases = $dbh->prepare("select * from var_type");
$sth_cases->execute();
my %cases = ();
while (my @row = $sth_cases->fetchrow_array) {
	$cases{$row[0].$row[1].$row[2]} = $row[3];
}
$sth_cases->finish;

my %fusion_type = ();
my %fusion_level = ();
my %coding_genes = ();

#get admin emails
my @emails = ('vuonghm@mail.nih.gov','khanjav@mail.nih.gov',  'wenxi@mail.nih.gov', 'chouh@nih.gov', 'NCIBioinformatics_Oncogenomics@mail.nih.gov');
#my @emails = ('chouh@nih.gov');
#$sth_emails->execute();
#while (my @row = $sth_emails->fetchrow_array) {	
#	push (@emails, $row[0]);
#}
#$sth_emails->finish;

my $email_list = join(',', @emails);

#get fusion data

if (($load_type eq "all" || $load_type eq "fusion") && !$skip_fusion) {
#if ($load_type eq "fusion") {
	my $sth_fusion = $dbh->prepare("select distinct left_gene, right_gene, left_chr, right_chr, left_position, right_position, type, var_level from var_fusion");
	$sth_fusion->execute();
	while (my @row = $sth_fusion->fetchrow_array) {
		my $key = join("\t", $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
		$fusion_type{$key} = $row[6];
		my $level = $row[7];
		$fusion_level{$key} = $level;
	}
	$sth_fusion->finish;

	my $sth_coding_genes = $dbh->prepare("select distinct symbol from gene where type='protein-coding'");
	$sth_coding_genes->execute();
	while (my @row = $sth_coding_genes->fetchrow_array) {
		$coding_genes{$row[0]} = '';
	}
	$sth_coding_genes->finish;
}

#get gene/transcripts id mapping
my %symbol_mapping = ();
my %gene_mapping = ();
my %canonical_trans = ();

my $sth_trans = $dbh->prepare("select gene, target_type, symbol, trans, canonical from transcripts");
$sth_trans->execute();
while (my @row = $sth_trans->fetchrow_array) {
	my $gene = $row[0];
	my $target_type = $row[1];
	my $symbol = $row[2];
	my $trans = $row[3];
	my $canonical = $row[4];
	if (!$canonical) {
		$canonical = 'N';
	}
	if ($canonical eq 'Y') {
		$canonical_trans{$trans} = '';
	}
	$gene_mapping{$trans} = $gene;
	$gene_mapping{$gene} = $gene;
	$symbol_mapping{$trans} = $symbol;
	$symbol_mapping{$gene} = $symbol;
}
$sth_trans->finish;

my @patient_dirs = grep { -d } glob $dir."*";
my %new_data = ();
my %failed_data = ();
my @errors = ();
print "use sqlldr? ($use_sqlldr)...load type = $load_type\n";
foreach my $patient_dir (@patient_dirs) {
	my $patient_id = basename($patient_dir);
	my $diagnosis = &getDiagnosis($patient_id);
	$patient_dir = &formatDir($patient_dir);
	if ($target_patient) {
		next if ($patient_id ne $target_patient);
	}	
	print "Processing patient: ($project_folder) $patient_id $sid\n";
	my @case_dirs = grep { -d } glob $patient_dir."*";
	foreach my $case_dir (@case_dirs) {
		my $case_id = basename($case_dir);
		if ($target_case) {
			next if ($case_id ne $target_case);
		}
		
		$case_dir = &formatDir($case_dir);
		my $succss_file = $dir."$patient_id/$case_id/successful.txt";
		my $failed_file = $dir."$patient_id/$case_id/failed.txt";
		my $failed_file_del = $dir."$patient_id/$case_id/failed_delete.txt";
		my $status = ($project_folder eq "clinomics")? 'pending' : 'passed';
# Note to other developers from Hue:  If this script (loadVarPatients.pl) is run on prod first (which it is), it will have already deleted the directory $patient_id/$case_id
#...when it gets to this step in dev, the $patient_id/$case_id/failed.txt or failed_delete.txt will no longer exist and WILL NOT run deleteCase.pl
# This means that the dev script will not delete orphan data in the database because it needs the presence of this directory on server 
# Noticed this because the development database had a lot of legacy data from previous cases
# Hue changed this from "if ( -e $failed_file && $load_type ne 'tier') { "
# to next line on 20190820
		if ( ($db_name=~/dev/ && -e $failed_file) && $load_type ne 'tier') {
			system("$script_dir/deleteCase.pl -p $patient_id -c $case_id") ;
			my $patient_key = "$patient_id\t$case_id\t$diagnosis";
			print "FAILED $failed_file\n";
			$failed_data{$patient_key} = '';
			next;
		}
		if (-e $failed_file_del && $load_type ne 'tier') {
			print "Removing directory from server!!! $patient_id/$case_id\n";
			system("$script_dir/deleteCase.pl -p $patient_id -c $case_id -r");
			my $patient_key = "$patient_id\t$case_id\t$diagnosis";
			$failed_data{$patient_key} = '';
			next;
		}

		if ($update_list_file ne '') {
			next if (!exists $update_cases{$patient_id}{$case_id});
			if ($project_folder eq "clinomics") {
				next unless (-e $succss_file);				
			}
		}

		print "==>Processing case: $case_id\n";
		my $start = time;

		#my $last_mod_time = (stat ($succss_file))[9];
		#print "file: $succss_file\n";
		if ($load_type eq "all" || $load_type eq "version") {
			my $last_mod_time = POSIX::strftime('%Y-%m-%d %H:%M:%S', localtime(( stat "$dir$patient_id/$case_id" )[9]));
			if (-e $succss_file) {
				$last_mod_time = POSIX::strftime('%Y-%m-%d %H:%M:%S', localtime(( stat $succss_file )[9]));
			} else {
				$status = "not_successful";
			}
			#print "last_mod_time: $last_mod_time\n";
			my $sth_case_exists = $dbh->prepare("select count(*) from processed_cases where patient_id='$patient_id' and case_id='$case_id'");
			$sth_case_exists->execute();
			my @case_row = $sth_case_exists->fetchrow_array;
			my $exists = ($case_row[0] > 0);
			$sth_case_exists->finish;
			my $case_name = '';
			if ($case_name_eq_id) {
				$case_name = $case_id;
			}

			my @version_ret = readpipe("$script_dir/getPipelineVersion.sh $case_dir");
			my $version = "NA";
			if ($#version_ret >= 0) {
				$version = $version_ret[0];
			}
			chomp $version;			
			my $sql = "insert into processed_cases values('$patient_id', '$case_id', '$project_folder', '$status', TO_TIMESTAMP('$last_mod_time', 'YYYY-MM-DD HH24:MI:SS'), CURRENT_TIMESTAMP,CURRENT_TIMESTAMP, '$version')";
			if ($exists) {					
				$sql = "update processed_cases set version='$version', status='$status', finished_at=TO_TIMESTAMP('$last_mod_time', 'YYYY-MM-DD HH24:MI:SS'), updated_at=CURRENT_TIMESTAMP where patient_id='$patient_id' and case_id='$case_id'";
			}
			print "$sql\n";
			print "\tinserted into or updated time in CASES table..\n";
			$dbh->do($sql);		
			$dbh->commit();			
		}
		
		my $patient_link = "<a target=_blank href='$url/viewPatient/any/$patient_id'>$patient_id</a>";
		my $patient_key = "$patient_link\t$case_id\t$diagnosis";

		#process fusion		
		my $fusion_file = "$patient_id/$case_id/Actionable/$patient_id.fusion.actionable.txt";
		print("fusion file: $fusion_file\n");
		#if (-e $dir.$fusion_file && ($replaced_old || exists $update_list{$fusion_file})) {
		if (-e $dir.$fusion_file) {
			print("fusion file exists\n");
			if (($load_type eq "all" || $load_type eq "fusion" ) && !$skip_fusion) {
			#if ($load_type eq "fusion") {
				my $fusion_output_file = "$dir$patient_id/$case_id/$patient_id/db/var_fusion.tsv";
				my $fusion_detail_output_file = "$dir$patient_id/$case_id/$patient_id/db/var_fusion_details.tsv";
				if ( !-e $fusion_output_file || !-e $fusion_detail_output_file || $replaced_old ) {
					try {
						print("Processing fusion");
						&insertFusion($case_id, $patient_id, $dir.$fusion_file, $project_folder, $fusion_output_file, $fusion_detail_output_file);					
					} catch {
						push(@errors, "$patient_id\t$case_id\tFusion\t$_");
					};
				}
				$new_data{$patient_key}{"fusion"} = '';
				if ($use_sqlldr) {
					my $ret = &callSqlldr($fusion_output_file, $script_dir."/ctrl_files/var_fusion.ctrl");
					if ($ret ne "ok") {
						push(@errors, "$patient_id\t$case_id\tFusion\tSQLLoader");
					}
					$ret = &callSqlldr($fusion_detail_output_file, $script_dir."/ctrl_files/var_fusion_details.ctrl");
					if ($ret ne "ok") {
						push(@errors, "$patient_id\t$case_id\tFusion Details\tSQLLoader");
					}
				}				
			}
		}		

		#process Annotation		
		my $ano_file = "$patient_id/$case_id/annotation/$patient_id.Annotations.coding.rare.txt";
		if (-e $dir.$ano_file) {
			if ($load_type eq "all" || $load_type eq "annotation" || $load_type eq "variants") {
				try {
					#print("not procesing annotation\n");
					#&insertAnnotation($dir.$ano_file);
				} catch {
					push(@errors, "$patient_id\t$case_id\tAnnotation\t$_"); 
				};
			}
		}

		#process variants
		my @files = grep { -f } glob $case_dir."$patient_id/db/*";

		my $sample_file = "$dir$patient_id/$case_id/$patient_id/db/var_samples.tsv";
		my $tier_file = "$dir$patient_id/$case_id/$patient_id/db/var_tier.tsv";
		my $tier_avia_file = "$dir$patient_id/$case_id/$patient_id/db/var_tier_avia.tsv";
		if ($use_sqlldr) {
			system("rm -f $sample_file");
			system("rm -f $tier_file");
			system("rm -f $tier_avia_file");
		}
		
		if ($load_type eq "all" || $load_type eq "variants") {
			print "deleting from $var_sample_tbl (case_id='$case_id' and patient_id='$patient_id')\n";
			$dbh->do("delete $var_sample_tbl where case_id='$case_id' and patient_id='$patient_id'");
			if (!$replaced_old && $use_sqlldr) {
				print "commiting $var_sample_tbl\n";
				$dbh->commit();
			}
		}

		#if ($load_type eq "all" || $load_type eq "tier") { #testing tiering 2/14/19	
		if ($load_type eq "tier") { 
			print "Deleting from var_tier(_avia) where case_id='$case_id' and patient_id='$patient_id'\n";
			$dbh->do("delete var_tier where case_id='$case_id' and patient_id='$patient_id'");
			$dbh->do("delete var_tier_avia where case_id='$case_id' and patient_id='$patient_id'"); #<-----NEW ON 2/7/2018, why delete from var_tier and not var_tier_avia
			if (!$replaced_old && $use_sqlldr) {
				print "commiting var_tier(_avia)\n";
				$dbh->commit();
			}			
		}

		foreach my $file (@files) {			
			if ($file =~ /.*\.(germline|somatic|rnaseq|variants|hotspot)$/) {
				my $type = $1;
				print "working on $patient_id.$type $sid\n";
				my $db_file = "$patient_id/$case_id/$patient_id/db/$patient_id.$type";
				if (-e $dir.$db_file) {
					next if ($type ne "rnaseq" && ($patient_id eq "ASPS018" || $patient_id eq "ASPS019"));					
					if ($load_type eq "all" || $load_type eq "variants") {
						try {	
							#print "inserting $type, $case_id, $patient_id\n";						
							&insertSample($type, $case_id, $patient_id, $dir.$db_file, $project_folder, $sample_file);
						} catch {
							push(@errors, "$patient_id\t$case_id\tVariants-$type\t$_");
						};						
						$new_data{$patient_key}{$type} = '';
						#system("$script_dir/postProcessVar.pl -p $patient_id -c $case_id -t $type");
					}
					next if ($type eq "hotspot");
					if ($load_type eq "all" || $load_type eq "tier") { #testing tiering 2/14/19
					#if ($load_type eq "tier") {
						print "running $file for $patient_id\n";
						try {
							my $sql = "select distinct sample_id from var_samples where patient_id='$patient_id' and case_id='$case_id' and type= '$type'";
							print "$sql\n";
							my $sth_samples = $dbh->prepare($sql);
							$sth_samples->execute();
							while (my @row = $sth_samples->fetchrow_array) {
								my $sample_id = $row[0];
								&insertTier($type, $case_id, $patient_id, $sample_id, $tier_file, $tier_avia_file);
							}
							$sth_samples->finish;
							
						} catch {
							push(@errors, "$patient_id\t$case_id\tTier-$type\t$_");
						};
						$new_data{$patient_key}{'tier'} = '';
					}
				}				
			}
		}		
		if ($load_type eq "all" || $load_type eq "variants") {
			if ($use_sqlldr) {
				my $ret = &callSqlldr($sample_file, $script_dir."/ctrl_files/var_samples.ctrl");
				if ($ret ne "ok") {
					push(@errors, "$patient_id\t$case_id\tVariants\tSQLLoader");
				}
			}
			if (!-e "$dir$patient_id/$case_id/$patient_id.$case_id.vcf.zip" || $load_type eq "all" || $replaced_old) {
				system("rm -rf ./$patient_id/$case_id/$patient_id.$case_id.vcf.zip");
				my $cmd = "zip -r ./$patient_id/$case_id/$patient_id.$case_id.vcf.zip ./$patient_id/$case_id/*/calls/*.snpEff.vcf";
				if ( -e "./$patient_id/$case_id/*/calls/*.snpEff.vcf" ) {
					system($cmd);
				}
			}
			if ($remove_noncoding) {
				$stn_del_noncoding->execute($patient_id, $case_id);
			}
			
			#update expression coverage			
			#$sth_update_pat_exp_cov->execute($patient_id, $case_id);
			$sth_update_smp_dna_cov->execute($patient_id, $case_id);
			$sth_update_smp_exp_cov->execute($patient_id, $case_id);
			$sth_update_smp_hotspot_dna_cov->execute($patient_id, $case_id);
			$sth_update_smp_hotspot_exp_cov->execute($patient_id, $case_id);
			#$sth_update_pat_dna_cov->execute($patient_id, $case_id);
			$sth_delete_smp_rnaseq_splicing->execute($patient_id, $case_id); #<------------------DELETING SPLICE VARIANTS
			#$sth_delete_pat_rnaseq_splicing->execute($patient_id, $case_id);
			$dbh->commit();
		}

		#if ($load_type eq "all" || $load_type eq "tier") { #testing tiering 2/14/19
		if ($load_type eq "tier") {
			if ($use_sqlldr) {
				my $ret = &callSqlldr($tier_file, $script_dir."/ctrl_files/var_tier.ctrl");
				if ($ret ne "ok") {
					push(@errors, "$patient_id\t$case_id\tTier\tSQLLoader");
				}
				$ret = &callSqlldr($tier_avia_file, $script_dir."/ctrl_files/var_tier_avia.ctrl");
				if ($ret ne "ok") {
					push(@errors, "$patient_id\t$case_id\tTier\tSQLLoader");
				}
			}
		}

		#process QC		
		if ($load_type eq "all" || $load_type eq "qc" || $load_type eq "variants") {
			print ("deleting qc data\n");
			$dbh->do("delete var_qc where case_id = '$case_id' and patient_id = '$patient_id'");	
		}
		
		my $qc_file = "$patient_id/$case_id/qc/$patient_id.consolidated_QC.txt";
		
		if (-e $dir.$qc_file) {
			if ($load_type eq "all" || $load_type eq "qc" || $load_type eq "variants") {
				try {
					&insertQC($case_id, $patient_id, $dir.$qc_file, "dna");
				} catch {
					push(@errors, "$patient_id\t$case_id\tQC-DNA\t$_");
				};
			}
		}
		#process RNA QC		
		$qc_file = "$patient_id/$case_id/qc/$patient_id.RnaSeqQC.txt";
		if (-e $dir.$qc_file) {
			if ($load_type eq "all" || $load_type eq "qc" || $load_type eq "variants") {
				try {
					&insertQC($case_id, $patient_id, $dir.$qc_file, "rna");
				} catch {
					push(@errors, "$patient_id\t$case_id\tQC-RNA\t$_");
				};
			}
		}
		#process RNA QC	V2	
		$qc_file = "qc/rnaseqc/metrics.tsv";
		opendir( my $DIR, $dir."$patient_id/$case_id" );
		while ( my $entry = readdir $DIR ) {
    		next unless -d $dir."$patient_id/$case_id" . '/' . $entry;
    		next if $entry eq '.' or $entry eq '..';
    		if(-e $dir."$patient_id/$case_id" . '/' . $entry.'/'.$qc_file){
    			# print $dir."$patient_id/$case_id" . '/' . $entry.'/'.$qc_file."\n";
    			if ($load_type eq "all" || $load_type eq "qc" || $load_type eq "variants") {
					try {
						&insertQC($case_id, $patient_id, $dir."$patient_id/$case_id" . '/' . $entry.'/'.$qc_file, "rnaV2");				
					} catch {
						push(@errors, "$patient_id\t$case_id\tQC-RNAV2\t$_");
					};
				}

    		}
		}
#		if (-e $dir.$qc_file) {
#			if ($load_type eq "all" || $load_type eq "qc" || $load_type eq "variants") {
#				try {
#					&insertQC($case_id, $patient_id, $dir.$qc_file, "rnaV2");				
#				} catch {
#					push(@errors, "$patient_id\t$case_id\tQC-RNA\t$_");
#				};
#			}
#		}

		#process CNV
		if ($load_type eq "all" || $load_type eq "cnv") {
			my @sample_dirs = grep { -d } glob $case_dir."*";
			my $inserted = 0;
			foreach my $d (@sample_dirs) {
				$d = &formatDir($d);
				my $folder_name = basename($d);				
				try {					
					my $ret = &insertCNV($dir, $folder_name, $patient_id, $case_id);
					my $retkit = &insertCNVKit($dir, $folder_name, $patient_id, $case_id);					
					if ($ret || $retkit) {
						$inserted = 1;
					}
				} catch {
					push(@errors, "$patient_id\t$case_id\tCNV\t$_");
				};				
			}	
			if ($inserted) {
				$new_data{$patient_key}{'CNV'} = '';
			}
		}

		#process neoantigen
		if ($load_type eq "all" || $load_type eq "antigen") {
			my @sample_dirs = grep { -d } glob $case_dir."*";
			my $inserted = 0;
			foreach my $d (@sample_dirs) {
				$d = &formatDir($d);
				my $folder_name = basename($d);				
				try {					
					my $ret = &insertAntigen($dir, $folder_name, $patient_id, $case_id);
					if ($ret) {
						$inserted = 1;
					}
				} catch {
					push(@errors, "$patient_id\t$case_id\tantigen\t$_");
				};				
			}	
			if ($inserted) {
				$new_data{$patient_key}{'antigen'} = '';
			}
		}

		#process mutation burden
		if ($load_type eq "all" || $load_type eq "burden") {
			my @sample_dirs = grep { -d } glob $case_dir."*";
			my $inserted = 0;
			foreach my $d (@sample_dirs) {
				$d = &formatDir($d);
				my $folder_name = basename($d);				
				try {					
					my $ret = &insertBurden($dir, $folder_name, $patient_id, $case_id);
					if ($ret) {
						$inserted = 1;
					}
				} catch {
					push(@errors, "$patient_id\t$case_id\tmutation_burden\t$_");
				};				
			}	
			if ($inserted) {
				$new_data{$patient_key}{'mutation_burden'} = '';
			}
		}

		#next if ($load_type ne "exp" || $refresh_exp);
		if ($load_type eq "exp" || $refresh_exp) {
			my @exp_dirs = grep { -d } glob $case_dir."*";
			my $inserted = 0;
			foreach my $d (@exp_dirs) {
				$d = &formatDir($d);
				my $folder_name = basename($d);
				my $sample_id = $folder_name;
				$sample_id =~ s/Sample_//;
				try {
					my $ret = &insertExp($dir, $folder_name, $patient_id, $case_id, $sample_id);
					if ($ret) {
						$inserted = 1;
					}
				} catch {
					push(@errors, "$patient_id\t$case_id\tExpression\t$_");
				};				
			}
			if ($inserted) {
				$new_data{$patient_key}{'expression'} = '';
			}
		}
		my $duration = time - $start;
		print "Patient $patient_id upload time: $duration seconds\n";
	}
}

$stn_rnaseqfp_khanlab->execute();
$stn_rnaseqfp_avia->execute();

my $subject   = "Oncogenomics $db_name DB upload status ($project_folder)";
my $sender    = 'oncogenomics@mail.nih.gov';
#my $recipient = 'hsien-chao.chou@nih.gov, rajesh.patidar@nih.gov';
my $recipient = 'vuonghm@mail.nih.gov';
if ($email ne "") {
	$recipient .= ",$email";
}
else {
	if ($load_type eq "all" && lc($db_name) eq "production") {
		$recipient = $email_list;	
	}
}
my $log_cotent = "";
my $failed_cotent = "";
my $err_cotent = "";
my @types = ("germline", "somatic", "variants", "rnaseq", "hotspot", "tier", "fusion", "expression", "CNV", "antigen", "mutation_burden");
foreach my $patient_key (sort keys %new_data) {
	$log_cotent .= "<tr>";
	my @fields = split(/\t/, $patient_key);
	foreach my $field (@fields) {
		$log_cotent .= "<td>$field</td>";
	}
	foreach my $type (@types) {
		if (exists $new_data{$patient_key}{$type}) {
			$log_cotent .= "<td>&#10004;</td>";
		} else {
			$log_cotent .= "<td></td>";
		}
	}
	$log_cotent .= "</tr>";
}

foreach my $patient_key (sort keys %failed_data) {
	$failed_cotent .= "<tr>";
	my @fields = split(/\t/, $patient_key);
	foreach my $field (@fields) {
		$failed_cotent .= "<td>$field</td>";
	}
	$failed_cotent .= "</tr>";
}

foreach my $err (@errors) {
	$err_cotent .= "<tr>";
	my @fields = split(/\t/, $err);
	foreach my $field (@fields) {
		$err_cotent .= "<td>$field</td>";
	}
	$err_cotent .= "</tr>";
}

my $project_folder_desc = $project_folder;
if ($project_folder eq "uploads") {
	$project_folder_desc = "";
}
my $data = qq{
	
    <h2>The following <font color=red>$project_folder_desc</font> data has been uploaded to $db_name DB:</h2>

    <table id="log" border=1 cellspacing="2" width="60%">
    	<thead><tr><th>Patient ID</th><th>Case ID</th><th>Diagnosis</th><th>Germline</th><th>Somatic</th><th>DNA Variants</th><th>RNASeq Variants</th><th>Hotspot</th><th>Tier</th><th>Fusion</th><th>Expression</th><th>CNV</th><th>NeoAntigen</th><th>Mutation Burden</th></tr></thead>
    	<tbody>$log_cotent</tbody>
    </table>
    <HR>
    <h2><font color=red>Failed Cases:</font></h2>
	<table id="errlog" border=1 cellspacing="2" width="60%">
    	<thead><tr><th>Patient ID</th><th>Case ID</th><th>Diagnosis</th></tr></thead>
    	<tbody>$failed_cotent</tbody>
    </table>
    <h2><font color=red>Errors:</font></h2>
	<table id="errlog" border=1 cellspacing="2" width="60%">
    	<thead><tr><th>Patient ID</th><th>Case ID</th><th>Type</th><th>Error</th></tr></thead>
    	<tbody>$err_cotent</tbody>
    </table>
};

my $mime = MIME::Lite->new(
    'From'    => $sender,
    'To'      => $recipient,
    'Subject' => $subject,
    'Type'    => 'text/html',
    'Data'    => $data,
);

if ($log_cotent ne "" || $err_cotent ne "") {
	$mime->send();
}

$dbh->disconnect();

sub insertAntigen {
	my ($dir, $folder_name, $patient_id, $case_id) = @_;	
	my $filename = $dir.$patient_id."/$case_id/$folder_name/NeoAntigen/$folder_name.final.txt";
	#print $filename."\n";
	if (!-e $filename) {
		return 0;
	}
	my $sample_id = $folder_name;
	$sample_id =~ s/Sample_//;
	if (exists $sample_alias{$sample_id}) {
		$sample_id = $sample_alias{$sample_id};
	}
	open (INFILE, "$filename") or return;
	print "=====>processing NeoAntigen\n";
	$dbh->do("delete neo_antigen where case_id = '$case_id' and patient_id = '$patient_id' and sample_id = '$sample_id'");
	$dbh->commit();

	my $antigen_file = $dir.$patient_id."/$case_id/$folder_name/NeoAntigen/$folder_name.antigen.tsv";
	open(ANTIGEN_FILE, ">$antigen_file") or die "Cannot open file $antigen_file";
	my $line = <INFILE>;
	while(<INFILE>) {
		print ANTIGEN_FILE "$patient_id\t$case_id\t$sample_id\t$_";		
	}
	close(INFILE);
	close(ANTIGEN_FILE);
	my $ret = &callSqlldr($antigen_file, $script_dir."/ctrl_files/neo_antigen.ctrl");
	if ($ret ne "ok") {
		push(@errors, "$patient_id\t$case_id\tAntigen\tSQLLoader");
		return 0;
	}	
	return 1;
}

sub insertBurden {
	my ($dir, $folder_name, $patient_id, $case_id) = @_;	
	my $file_pattern = $dir.$patient_id."/$case_id/$folder_name/qc/*.mutationburden.txt";
	my @burden_files = glob $file_pattern;
	my $sample_id = $folder_name;
	$sample_id =~ s/Sample_//;
	if (exists $sample_alias{$sample_id}) {
		$sample_id = $sample_alias{$sample_id};
	}
	if ($#burden_files > 0) {
		print "=====>processing Mutationburden: $file_pattern\n";
		$dbh->do("delete mutation_burden where sample_id = '$sample_id' and patient_id = '$patient_id' and case_id='$case_id'");
	} else {
		return 0;
	}
	foreach my $burden_path (@burden_files) {
		my $burden_file = basename($burden_path);
		$burden_file = substr $burden_file, length($folder_name) + 1; 
		print "$burden_file\n";
		if ($burden_file =~ /(.*)\.mutationburden.txt/) {
			open (INFILE, $burden_path);			
			my $first_line = <INFILE>;
			my $second_line = <INFILE>;
			chomp $first_line;
			chomp $second_line;
			my @totalbases = split(/\t/, $first_line);
			my @burdens = split(/\t/, $second_line);
			#print $totalbases[1]."\t".$burdens[1]."\n";
			$sth_mutation_burden->execute($patient_id, $case_id, $sample_id, $1, $burdens[1], $totalbases[1]);
			close(INFILE);
		}
	}
	$dbh->commit();
	return 1;	
}

sub insertCNV {
	my ($dir, $folder_name, $patient_id, $case_id) = @_;	
	my $filename = $dir.$patient_id."/$case_id/$folder_name/sequenza/$folder_name/$folder_name"."_segments.txt";
	#print $filename."\n";
	if (!-e $filename) {
		return 0;
	}
	my $sample_id = $folder_name;
	$sample_id =~ s/Sample_//;
	if (exists $sample_alias{$sample_id}) {
		$sample_id = $sample_alias{$sample_id};
	}
	open (INFILE, "$filename") or return;
	print "=====>processing CNV\n";
	$dbh->do("delete var_cnv where case_id = '$case_id' and patient_id = '$patient_id' and sample_id = '$sample_id'");
	my $line = <INFILE>;
	chomp $line;
	my @header_list = split(/\t/, $line);
	while(<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields != $#header_list);
		my $chr = $fields[0];
		$chr =~ s/\"//g;
		my $start_pos = $fields[1];
		my $end_pos = $fields[2];
		my $cnt = $fields[$#fields - 3];		
		my $a = $fields[$#fields - 2];
		my $b = $fields[$#fields - 1];
		my $lpp = $fields[$#fields];
		$lpp =~ s/Inf/99999999/;
		next if ($cnt eq "NA");
		$sth_cnv->execute($patient_id, $case_id, $sample_id, $chr, $start_pos, $end_pos, $cnt, $a, $b, $lpp);		
	}
	$dbh->commit();
	return 1;
}

sub insertCNVKit {
	my ($dir, $folder_name, $patient_id, $case_id) = @_;	
	my $filename = $dir.$patient_id."/$case_id/$folder_name/cnvkit/$folder_name".".cns";
	#print $filename."\n";
	if (!-e $filename) {
		return 0;
	}
	my $sample_id = $folder_name;
	$sample_id =~ s/Sample_//;
	if (exists $sample_alias{$sample_id}) {
		$sample_id = $sample_alias{$sample_id};
	}
	open (INFILE, "$filename") or return;
	print "=====>processing CNVKit\n";
	$dbh->do("delete var_cnvkit where case_id = '$case_id' and patient_id = '$patient_id' and sample_id = '$sample_id'");
	my $line = <INFILE>;
	chomp $line;
	my @header_list = split(/\t/, $line);
	while(<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields != $#header_list);
		my $chr = $fields[0];
		$chr =~ s/\"//g;
		my $start_pos = $fields[1];
		my $end_pos = $fields[2];
		my $log2 = $fields[4];		
		my $depth = $fields[5];
		my $probes = $fields[6];
		my $weight = $fields[7];
		$sth_cnvkit->execute($patient_id, $case_id, $sample_id, $chr, $start_pos, $end_pos, $log2, $depth, $probes, $weight);		
	}
	$dbh->commit();
	return 1;
}

sub insertExp {
	my ($dir, $folder_name, $patient_id, $case_id) = @_;
	my %target_types = ("ENS" => "ensembl", "UCSC" => "refseq");
	my %target_levels = ("gene" => "gene", "transcript" => "trans");

	my $sample_id = $folder_name;
	$sample_id =~ s/Sample_//;
	if (exists $sample_alias{$sample_id}) {
		$sample_id = $sample_alias{$sample_id};
	}
	my $exp_type = $sample_exp_type{$sample_id};
	if (!$exp_type) {
		return 0;
	}
	if (lc($exp_type) ne "rnaseq"){
		return 0;
	}
	
	my $case_type = "exp";
	my $res = 0;
	while (my ($type, $target_type) = each %target_types) {
		my $path = $patient_id."/$case_id/$folder_name";		
		while (my ($level, $target_level) = each %target_levels) {
			my $tpm_file = "$path/TPM_$type/$folder_name.$level.TPM.txt";
			#my $count_file = "$path/TPM_$type/$folder_name"."_counts.$level.txt";
			my $exp_file = "$path/TPM_$type/exp.$level.tsv";
			if ( -s $tpm_file ) {
				print "=====>processing expression file: $tpm_file\n";
				#if ( -s $count_file && -s $fpkm_file) {
				if ($refresh_exp || !checkExpressionExists($sample_id, $target_type, $target_level)) {
					if (!exists $cases{$case_id.$patient_id.$case_type}) {						
						my $sql = "insert into var_type values('$case_id', '$patient_id', '$case_type', 'active','',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)";
						$cases{$case_id.$patient_id.$case_type} = '';
						$dbh->do($sql);		
						$dbh->commit();
					}
					if ($refresh_exp || !-e $exp_file) {						
						#open COUNT_FILE, $count_file;
						if ($refresh_exp) {
							$dbh->do("delete sample_values where sample_id = '$sample_id' and target_type = '$target_type' and target_level = '$target_level'");
							$dbh->commit();
						}

						open TPM_FILE, $tpm_file;
						open(EXP_FILE, ">$exp_file") or die "Cannot open file $exp_file";
						#my %count_data = ();
						#while (<COUNT_FILE>) {
						#	chomp;
						#	my @fields = split(/\t/);
						#	my $target = $fields[3];
						#	my $value = $fields[5];
						#	$count_data{$target} = $value;
						#}
						#close(COUNT_FILE);					
						<TPM_FILE>;
						my $counter = 0;
						while (<TPM_FILE>) {
							chomp;
							my @fields = split(/\t/);
							my $target = $fields[3];
							my $tpm = $fields[4];
							if ($target_level eq "trans") {
								$target = $fields[4];
								$tpm = $fields[5];
							}
							
							#my $count = $count_data{$target};
							my $gene = $gene_mapping{$target};
							my $symbol = $symbol_mapping{$target};

							if ($gene && $symbol) {
								print EXP_FILE join("\t",$sample_id, $target, $gene, $symbol, $target_type, $target_level, $tpm)."\n";
							}

							#$sth_exp->execute($sample_id, $target, $gene, $symbol, $target_type, $target_level, $fpkm, $count);
							#$counter++;
							#if ($counter % 5000 == 0) {
							#	$dbh->commit();
							#	$counter = 0;
							#}
						}
						close(TPM_FILE);
						close(EXP_FILE);
						#$dbh->commit();
					}
					my $ret = &callSqlldr($exp_file, $script_dir."/ctrl_files/exp.ctrl");
					if ($ret ne "ok") {
						push(@errors, "$patient_id\t$case_id\tExp\tSQLLoader-$exp_file");						
					} else {
						$res = 1;
					}
				}
			}
		}
		my $tpm_file = "$path/TPM_$type/$folder_name.exon.TPM.txt";
		if (-e $tpm_file) {
			#my $count_file = "$path/TPM_$type/$folder_name"."_counts.Exon.txt";
			my $exon_exp_file = "$path/TPM_$type/exp.exon.tsv";
			if ($refresh_exp || !checkExpressionExists($sample_id, $target_type, "exon")) {
				&loadExonExp($patient_id, $case_id, $tpm_file, $exon_exp_file, $target_type, $sample_id);
			}
		}
	}
	return $res;				
}

sub loadExonExp {
	my ($patient_id, $case_id, $tpm_file, $exp_file, $target_type, $sample_id) = @_;

	print "=====>processing expression file: $tpm_file\n";
	if ( -s $tpm_file) {
		if ($refresh_exp || !-e $exp_file) {
			#open COUNT_FILE, $count_file;
			if ($refresh_exp) {
				$dbh->do("delete exon_expression where sample_id = '$sample_id' and target_type = '$target_type'");
				$dbh->commit();
			}

			open TPM_FILE, $tpm_file;
			open(EXP_FILE, ">$exp_file") or die "Cannot open file $exp_file";
		
			#my %count_data = ();
			my %tpm_data = ();

			#while (<COUNT_FILE>) {
			#	chomp;
			#	my @fields = split(/\t/);
			#	my $target = join("\t", $fields[0], $fields[1], $fields[2]);			
			#	$count_data{$target} = $fields[5];
			#}

			#close(COUNT_FILE);
			
			<TPM_FILE>;
			#my $counter = 0;
			while (<TPM_FILE>) {
				chomp;
				my @fields = split(/\t/);		
				my $target = join("\t", $fields[0], $fields[1], $fields[2]);
				if (exists($tpm_data{$target})) {
					next;
				}
				my $chr = "chr".$fields[0];
				my $start_pos = $fields[1];
				my $end_pos = $fields[2];
				my $trans = $fields[4];
				my $tpm = $fields[6];

				#my $trans = "";
				#if ($id =~ /(.*)\..*/) {
				#	$trans = $1;
				#}
				my $symbol = $symbol_mapping{$trans};
				if (!$symbol) {
					$symbol = "";
				}

				#my $count = $count_data{$target};
				#if (!$count) {
				#	next;
				#}

				if ($tpm == 0) {
					next;
				}

				print EXP_FILE join("\t",$sample_id, $chr, $start_pos, $end_pos, $symbol, $target_type, $tpm)."\n";
				#$sth_exon_exp->execute($sample_id, $chr, $start_pos, $end_pos, $symbol, $target_type, $fpkm, $count);
				#$counter++;
				#if ($counter % 5000 == 0) {
				#	$dbh->commit();
				#	$counter = 0;
				#}
				$tpm_data{$target} = '';
			}
			close(TPM_FILE);
			close(EXP_FILE);
		}
		my $ret = &callSqlldr($exp_file, $script_dir."/ctrl_files/exp_exon.ctrl");
		if ($ret ne "ok") {
			push(@errors, "$patient_id\t$case_id\tExp-Exon\tSQLLoader-$exp_file");						
		}
		#$dbh->commit();
	}	
	return 1;
}

sub loadCufflinks {
	my ($file, $level, $type, $sample_id) = @_;
	open (INFILE, "$file") or return;
	my $line = <INFILE>;
	chomp $line;
	my @header_list = split(/\t/, $line);
	my %headers = ();
	for (my $i=0;$i<=$#header_list;$i++) {
		$headers{$header_list[$i]} = $i;		
	}
	my $counter = 0;
	while(<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		my $target = $fields[$headers{'tracking_id'}];
		my $symbol = $fields[$headers{'gene_short_name'}];
		my $gene = $fields[$headers{'gene_id'}];
		my $fpkm = $fields[$headers{'FPKM'}];
		#if ($fpkm ne "0") {
			$sth_exp->execute($sample_id, $target, $gene, $symbol, $type, $level, $fpkm);
			$counter++;
			if ($counter % 5000 == 0) {
				$dbh->commit();
				$counter = 0;
			}
		#}
	}
	$dbh->commit();
}




sub insertFusion {
	my ($case_id, $patient_id, $file, $path, $fusion_file, $fusion_detail_file) = @_;
	return if (!-e $file);
	my $type = 'fusion';
	if (exists $cases{$case_id.$patient_id.$type}) {
		my $status = $cases{$case_id.$patient_id.$type};
		print "status: $status\n";
		return if ($status eq "closed");
	} else {
		my $sql = "insert into var_type values('$case_id', '$patient_id', '$type', 'active', '', 1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)";
		#print "$sql\n";
		$dbh->do($sql);		
		$dbh->commit();
	}
	print "=====>processing fusion file: $file\n";
	open(INFILE, "$file") or die "Cannot open file $file";
	<INFILE>;
	my $del_sql = "delete var_fusion where case_id = '$case_id' and patient_id = '$patient_id'";
	print "$del_sql\n";
	$dbh->do($del_sql);
	if ($use_sqlldr) {
		$dbh->commit();
		print "=========>output file: $fusion_file\n";
		print "=========>output file: $fusion_detail_file\n";
		open(FUSION_FILE, ">$fusion_file") or die "Cannot open file $fusion_file";
		open(FUSION_DETAIL_FILE, ">$fusion_detail_file") or die "Cannot open file $fusion_detail_file";
	}	
	my %vars = ();
	my %fusions = ();
	my %fusion_read_count = ();
	my $num_column = 0;
	my $min_column = 8;
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);		
		
		next if ($#fields < ($min_column - 1));
		$num_column = $#fields + 1;
		my $tool = $fields[7];
		my $read_count = 0;
		if ($#fields >= 8) {
			$read_count = $fields[8];
		}
		splice @fields, 7, 2;
		my $key = join("\t", @fields);
		my $current_tools = $fusions{$key};
		my $current_read_count = $fusion_read_count{$key};		
		if ($current_tools) {
			$fusions{$key} = "$current_tools $tool";
			$fusion_read_count{$key} = "$current_read_count $read_count";
		} else {
			$fusions{$key} = $tool;
			$fusion_read_count{$key} = $read_count;
		}
		for (my $i=0;$i<=$#fields;$i++) {
			if ($fields[$i] eq "-1" || $fields[$i] eq "." || $fields[$i] eq "-") {
				$fields[$i] = "";
			}
		}
	}
	my $size = keys %fusions;
	my %fusion_domains = ();
	print "length ".$size."\n";
	while (my ($key, $tools) = each %fusions) {
		my @fields = split(/\t/, $key);
		my $left_gene = $fields[0];
		my $right_gene = $fields[1];
		my $left_chr = $fields[2];
		my $left_junction = $fields[3];
		my $right_chr = $fields[4];
		my $right_junction = $fields[5];
		my $sample_id = $fields[6];
		my $read_count = $fusion_read_count{$key};
		#if ($num_column == 9) {
		#	$read_count = $fields[7];
		#}
		$sample_id =~ s/Sample_//;

		if (!looks_like_number($left_junction) || !looks_like_number($right_junction)) {
			next;
		}
		if (exists $sample_alias{$sample_id}) {
			$sample_id = $sample_alias{$sample_id};
		}		

		my $level = "";
		my $type = "";
		my $key = join("\t", $left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction);		
		if (!exists $coding_genes{$left_gene} && !exists $coding_genes{$right_gene}) {
			next;
		}
		#print("$left_gene\t$right_gene\n");
		if (exists $fusion_type{$key} && exists $fusion_level{$key}) {
			#print("fusion exists!\n");
			$type = $fusion_type{$key};
			$level = $fusion_level{$key};
			if (!$level) {
				$level = "No Tier";
			}
			if ($use_sqlldr) {
				print FUSION_FILE join("\t", $case_id, $patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $tools, $read_count, $type, $level)."\n";
			} else {
				$sth_fu->execute($case_id, $patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $tools, $read_count, $type, $level);
			}
			next;
		}		
		my $cmd = "php $script_dir/getGeneFusionData.php left_gene=$left_gene right_gene=$right_gene left_chr=$left_chr right_chr=$right_chr left_junction=$left_junction right_junction=$right_junction url=$url";
		#print "$cmd\n";
		my @lines = readpipe($cmd);

		#my $curl = "$url/calculateGeneFusionData/$left_gene/$right_gene/$left_chr/$right_chr/$left_junction/$right_junction";
		#print($curl."\n");
		#my $results;		
		#my $results = get $curl;
		#if (!$results) {
		#	push(@errors, "$patient_id\t$case_id\tFusion\tURL error: $curl");
		#	next;
		#}
		#my @lines = split(/\n/, $results);
		if ($#lines == 0) {			
			next;
		}
		my $header = $lines[0];
		#print "$left_gene\t$right_gene\n";
		chomp $header;
		my ($left_cancer_gene, $right_cancer_gene) = split(/\t/, $header);
		my $type_in_frame = 0;
		my $type_right_in_tact = 0;
		my $type_left_in_tact = 0;
		my $type_out_of_frame = 0;
		my $type_truncated_orf = 0;
		my $type_alternative_start_codon = 0;
		my $have_details = 0;
		for (my $i=1; $i<=$#lines; $i++) {
			my $line = $lines[$i];
			chomp $line;
			#print $line."\n";
			my @dtl_fields = split(/\t/, $line);
			next if ($#dtl_fields < 3);
			my $trans_type = $dtl_fields[0];
			my $fused_pep_length = $dtl_fields[1];
			my $trans_list = $dtl_fields[2];
			#my $domain = "";
			my $domain = $dtl_fields[3];
			if (lc($trans_type) eq "in-frame") {
				$type_in_frame = 1;
			}
			if (lc($trans_type) eq "right gene intact") {
				$type_right_in_tact = 1;
			}
			if (lc($trans_type) eq "left gene intact") {
				$type_left_in_tact = 1;
			}
			if (lc($trans_type) eq "out-of-frame") {
				$type_out_of_frame = 1;
			}	
			if (lc($trans_type) eq "truncated orf") {
				$type_truncated_orf = 1;	
			}
			if (lc($trans_type) eq "alternative start codon") {
				$type_alternative_start_codon = 1;	
			}			
    		$have_details = 1;
    		if ($use_sqlldr) {
    			#print FUSION_DETAIL_FILE join("\t", $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $left_trans, $right_trans, $trans_type, $fused_pep, $domain)."\n";
    			print FUSION_DETAIL_FILE join("\t", $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $trans_list, $fused_pep_length, $trans_type, $domain)."\n";
			}
			else {
				$sth_fu_dtl->execute($left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $trans_list, $fused_pep_length, $trans_type, $domain);
			}
		}
		
		$type = "Truncated ORF";
		if ($type_out_of_frame) {
			$type = "Out-of-frame";	
		}
		if ($type_alternative_start_codon) {
			$type = "Alternative start codon";
		}		
		if ($type_left_in_tact) {
			$type = "Left gene intact";
		}
		if ($type_right_in_tact) {
			$type = "Right gene intact";
		}
		if ($type_in_frame) {
			$type = "In-frame";
		}
		# get tier

		$level = &getFusionTier($type, $left_gene, $right_gene, $left_cancer_gene, $right_cancer_gene);
		if ($have_details) {
			if ($use_sqlldr) {
				print FUSION_FILE join("\t", $case_id, $patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $tools, $read_count, $type, $level)."\n";
			} else {
				$sth_fu->execute($case_id, $patient_id, $left_gene, $right_gene, $left_chr, $left_junction, $right_chr, $right_junction, $sample_id, $tools, $read_count, $type, $level);
			}
		}
		$fusion_type{$key} = $type;
		
		$fusion_level{$key} = $level;

	}
	if ($use_sqlldr) {
		close(FUSION_FILE);
		close(FUSION_DETAIL_FILE);
	} else {
		$dbh->commit();
	}
}

sub getFusionTier {
	my ($type, $left_gene, $right_gene, $is_left_cancer_gene, $is_right_cancer_gene) = @_;
	my $left_sanger = (exists $fusion_list{$left_gene});
	my $right_sanger = (exists $fusion_list{$right_gene});
	my $inframe_right_intact = (lc($type) eq "in-frame" || lc($type) eq "right gene intact");
	if ((exists $fusion_pair_list{"$left_gene\t$right_gene"}) && $inframe_right_intact) {
		return "Tier 1.1";
	}
	if (($left_sanger || $right_sanger) && $inframe_right_intact) {
		return "Tier 1.2";
	}
	if ($is_left_cancer_gene eq "Y" || $is_right_cancer_gene  eq "Y") {
		if ($inframe_right_intact) {
			if ($is_left_cancer_gene eq "Y" && $is_right_cancer_gene  eq "Y") {
				return "Tier 1.3";
			} else {
				return "Tier 2.1";
			}
		}
		if (lc($type) eq "left gene intact") {
			return "Tier 2.2";
		}
		return "Tier 3";
	}
	if ($inframe_right_intact) {
		return "Tier 4";
	}
	return "No Tier";

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

sub insertQC {
	my ($case_id, $patient_id, $file, $type) = @_;
	#$dbh->do("delete var_qc where case_id = '$case_id' and patient_id = '$patient_id' and type = '$type'");
	print "=====>processing QC file: $file\n";
		
	open(INFILE, "$file") or die "Cannot open file $file";
	
	my $line = <INFILE>;
	chomp $line;
	if ($line eq "") {
		$line = <INFILE>;
	}
	my @header_list = split(/\t/, $line);
	my %headers = ();
	my @rnaV2headers=("Sample","Total Purity Filtered Reads Sequenced", "Mapped","Mapped Unique", "Unique Rate of Mapped","Duplication Rate of Mapped","rRNA rate","Exonic Rate","Intronic Rate");
		for (my $i=0;$i<=$#header_list;$i++) {
			$header_list[$i] =~ s/^#//;
			$headers{$header_list[$i]} = $i;
		}
	


	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < $#header_list);
		my $sample_id = $fields[1];
		$sample_id =~ s/Sample_//;
		
		if (exists $sample_alias{$sample_id}) {
			$sample_id = $sample_alias{$sample_id};			
		}

		next if ($fields[0] =~ /patient/i);
		for (my $i=2;$i<=$#fields;$i++) {
			if($type ne "rnaV2"){
				my $attr_name = $header_list[$i];
				$sth_qc->execute($case_id, $patient_id, $sample_id, $attr_name, $fields[$i], $type);
			}
			elsif($type eq "rnaV2"){
				foreach (@rnaV2headers) {
  					if ($header_list[$i] eq $_) {
  						print $_."\n";
    					my $attr_name = $header_list[$i];
						$sth_qc->execute($case_id, $patient_id, $sample_id, $attr_name, $fields[$i], $type);
 					}
				}
			}
		}		

	}
	$dbh->commit();
}

sub getSampleCov {
	#my ($chr, $start, $end, $ref, $alt, $sample_id, $var_smp_ref) = @_;
	my ($chr, $start, $end, $ref, $alt, $sample_id) = @_;
	#my %var_smp = %{$var_smp_ref};
	my $smp_key = join("\t",$chr, $start, $end, $ref, $alt, $sample_id);
	my $var_cov = 0;
	my $total_cov = 0;
	
	if (exists $var_smp{$smp_key}) {
		my $smp_values = $var_smp{$smp_key};
		my @fields = split(/\t/, $smp_values);
		$total_cov = $fields[3];
		$var_cov = $fields[4];		
	}
	return ($var_cov, $total_cov);
}

sub getMatchTumorSample {
	my ($chr, $start, $end, $ref, $alt, $sample_id) = @_;
	#my %var_smp = %{$var_smp_ref};
	my $var_cov = 0;
	my $total_cov = 0;
	my $tumor_sample_id = "";
	if (exists $match_tumor{$sample_id}) {
		my @tumor_samples = @{$match_tumor{$sample_id}};
		foreach my $tumor_sample (@tumor_samples) {
			my $smp_key = join("\t",$chr, $start, $end, $ref, $alt, $tumor_sample);
			if (exists $var_smp{$smp_key}) {
				my $smp_values = $var_smp{$smp_key};
				my @fields = split(/\t/, $smp_values);
				my $tumor_total_cov = $fields[3];
				my $tumor_var_cov = $fields[4];
				if ($tumor_total_cov > $total_cov) {
					$tumor_sample_id = $tumor_sample;
					$total_cov = $tumor_total_cov;
					$var_cov = $tumor_var_cov;
				}
			}
		}
	}
	return ($tumor_sample_id, $var_cov, $total_cov);
}

sub insertTier {	
	my ($type, $case_id, $patient_id, $sample_id, $tier_file, $tier_avia_file) = @_;	
	if ($use_sqlldr) {		
		print "=========>output file ($type): $tier_file\n";
		open(TIER_FILE, ">>$tier_file") or die "Cannot open file $tier_file";
		open(TIER_FILE_AVIA, ">>$tier_avia_file") or die "Cannot open file $tier_avia_file";
	}
	my $curl = "$url/getVarTier/$patient_id/$case_id/$type/$sample_id/avia";
	print "$curl\n";#hv
	my @lines = readpipe("php $script_dir/httpClient.php url=$curl");
	
	#my( $status, $results ) = getWithStatus( $curl );
	#print "$status\n";#hv
	#print "$results\n";#hv
	#if( $status ne "200 OK" ) {
    # 	push(@errors, "$patient_id\t$case_id\tTier\tURL error: $curl, status: $status");
    # 	return;	
	#}
	
	#if (!$results) {
	#	$results = "";
	#}
	#my @lines = split(/\n/, $results);

	#my @lines = readpipe($cmd);
	my $total_lines = $#lines + 1;
	print("inserting $total_lines tier(_avia) data...\n");
	foreach my $line (@lines) {
		chomp $line;
		next if ($line eq "");
		#print("inserting $line\n");
		my @fields = split(/\t/, $line);
		my $gene_idx = 6;
		my $gene = $fields[$gene_idx];
		my $somatic_tier = $fields[$gene_idx+1];
		my $germline_tier = $fields[$gene_idx+2];
		my $maf = $fields[$gene_idx+3];
		my $total_cov = $fields[$gene_idx+4];
		my $vaf = $fields[$gene_idx+5];
		my $annotation = $fields[$gene_idx+6];		
		if ($use_sqlldr) {
			if ($annotation eq "AVIA") {
				print TIER_FILE_AVIA join("|", $fields[0],$fields[1],$fields[2],$fields[3],$fields[4],$case_id, $patient_id, $sample_id, $type, $somatic_tier, $germline_tier,$gene,$maf,$total_cov,$vaf)."\n";
			} else {
				print TIER_FILE join("|", $fields[0],$fields[1],$fields[2],$fields[3],$fields[4],$case_id, $patient_id, $sample_id, $type, $somatic_tier, $germline_tier,$gene, $maf,$total_cov,$vaf)."\n";
			}
		} else {
			if ($annotation eq "AVIA") {
				#print("inserting avia\n");
				$sth_tier_avia->execute($fields[0],$fields[1],$fields[2],$fields[3],$fields[4],$case_id, $patient_id, $sample_id, $type, $somatic_tier, $germline_tier, $gene,$maf,$total_cov,$vaf);
			} else {
				#print("inserting khanlab\n");
				$sth_tier->execute($fields[0],$fields[1],$fields[2],$fields[3],$fields[4],$case_id, $patient_id, $sample_id, $type, $somatic_tier, $germline_tier, $gene,$maf,$total_cov,$vaf);
			}
		}
	}
	$dbh->commit();
}

sub insertSample {	
	my ($type, $case_id, $patient_id, $file, $path, $sample_file) = @_;
	
	#$sample_file = "";

	if (exists $cases{$case_id.$patient_id.$type}) {
		my $status = $cases{$case_id.$patient_id.$type};
		#return if ($status eq "closed");
	} else {
		my $sql = "insert into var_type values('$case_id', '$patient_id', '$type', 'active', '', 1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)";
		#print "$sql\n";
		$dbh->do($sql);		
		$dbh->commit();
	}
	return if (!-e $file);
	#$dbh->do("delete $var_sample_tbl where case_id='$case_id' and patient_id='$patient_id' and type='$type'");
	#$dbh->do("delete $var_patient_tbl where case_id='$case_id' and patient_id='$patient_id' and type='$type'");
	print "=====>processing $type (reading $file)\n";

	#if (-e $sample_file && !$replaced_old && $use_sqlldr) {
	#	$dbh->commit();
	#	return;
	#}

	open(INFILE, "$file") or die "Cannot open file $file";
	%var_smp = ();
	
	if ($use_sqlldr) {
		$dbh->commit();		
		print "=========>output file: $sample_file\n";
		open(SAMPLE_FILE, ">>$sample_file") or die "Cannot open file $sample_file";		
	}
	#first pass, read db files
	while (<INFILE>) {
		chomp;
		my @fields = split(/\t/);
		next if ($#fields < 10);
		for (my $i=0;$i<=$#fields;$i++) {
			if ($fields[$i] eq "-1" || $fields[$i] eq "." || $fields[$i] eq "-" || $fields[$i] eq "NA") {
				$fields[$i] = "";
			}
		}
		if (!exists $chr_list{$fields[0]}) {
			next;
		}
		my $caller_fn = 6;
		if ($#fields == 12) {
			$caller_fn = 8;
		}
		$fields[3] = "-" if ($fields[3] eq "");
		$fields[4] = "-" if ($fields[4] eq "");
		my $total_cov = $fields[$caller_fn + 3];
		my $var_cov = $fields[$caller_fn + 4];
		next if ($var_cov =~ /,/);
		if ($total_cov eq "" || $total_cov eq "0") {
			$total_cov = 0;
			$var_cov = 0;
		}

		my $sample_id = $fields[5];
		$sample_id =~ s/Sample_//;
		my $tissue_cat = "normal";
		
		my $exp_type = "";
		my $relation = "";

		#print $_ if ($fields[1] eq "48305819");

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

		if ($tissue_cat eq "cell line") {
			$tissue_cat = "tumor";
		}

		if ($tissue_cat eq "xeno") {
			$tissue_cat = "tumor";
		}

		if ($tissue_cat eq "blood") {
			$tissue_cat = "normal";
		}
		if ($type eq 'germline') {
			next if ($total_cov eq "0");
			next if ($var_cov eq "0");			
		}		

		if (length($fields[3]) > 255 || length($fields[4]) > 255) {
			next;
		}
		my $key = join("\t",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4]);
		my $smp_key = join("\t",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $sample_id);
		my $caller = $fields[$caller_fn];

		if ($caller eq "bam2mpg") {
			next;
		}
		if ($exp_type eq "RNAseq" && $type eq "somatic") {
			$caller = "mpileup";			
		}
		#print "$smp_key\n" if ($exp_type eq "RNAseq");
		#print "$smp_key\n" if ($fields[1] eq "48305819");
		if ($type eq "somatic" && $fields[1] eq "50747071") {
				print "key==>$smp_key\n";
		}
		$var_smp{$smp_key} = join("\t", $caller, $fields[$caller_fn + 1], $fields[$caller_fn + 2], $total_cov, $var_cov, $tissue_cat, $exp_type, $relation);
	}

	my %vaf_ratios = ();
	my %vafs = ();
	my %var_covs = ();
	my %total_covs = ();
	my %exp_var_covs = ();
	my %exp_total_covs = ();
	my %pat_vars = ();
	my $varCount=0;
	#second pass find match normal & rnaseq
	#while (my ($smp_key, $smp_value) = each %var_smp) {
	foreach my $smp_key (keys %var_smp) {
		my $smp_value = $var_smp{$smp_key};
		my ($chr, $start, $end, $ref, $alt, $sample_id) = split(/\t/, $smp_key);
		my ($caller, $qual, $fisher, $total_cov, $var_cov, $tissue_cat, $exp_type, $relation) = split(/\t/, $smp_value);
		my $key = join("\t",$chr, $start, $end, $ref, $alt);
		my $vaf = ($total_cov == 0)? 0: ($var_cov/$total_cov);
		my $vaf_ratio = 1;
		my $tumor_var = 0;
		my $tumor_total = 0;
		my $normal_var = 0;
		my $normal_total = 0;
		my $rnaseq_var = 0;
		my $rnaseq_total = 0;	
		my $sample_name = "";
		my $normal_sample_id = "";
		my $rnaseq_sample_id = "";
		my $tumor_sample_id = "";
		#get tumor, normal and rnaseq read count
		if($sample_id eq "CL0138_T1D_PS_HKWYFBGX3"){
					print $sample_id." SAMPLE ID"."\n";
					print $match_normal{"CL0138_T1D_E_HKWYFBGX3"}."MATCHED NORMAL"."\n";
				}
		if ($tissue_cat eq "normal") {
			$normal_var = $var_cov;
			$normal_total = $total_cov;			
			($tumor_sample_id, $tumor_var, $tumor_total) = getMatchTumorSample($chr, $start, $end, $ref, $alt, $sample_id);
			
		} else {
			$tumor_var = $var_cov;
			$tumor_total = $total_cov;
			if (exists $match_normal{$sample_id}) {

				my $normal_sample_id = $match_normal{$sample_id};
				if($sample_id eq "CL0138_T1D_PS_HKWYFBGX3"){
					print $normal_sample_id." NORMAL TOTAL ".$normal_total."\n";
				}

				my $normal_sample_key = join("\t",$chr, $start, $end, $ref, $alt, $normal_sample_id);				
				if ($type eq "somatic" && $start eq "50747071") {
						print "$normal_sample_key\n";
				}
				if (exists $var_smp{$normal_sample_key}) {
					my $smp_values = $var_smp{$normal_sample_key};					
					my @fields = split(/\t/, $smp_values);
					$normal_total = $fields[3];
					
					$normal_var = $fields[4];
					#($normal_var, $normal_total) = getSampleCov($chr, $start, $end, $ref, $alt, $normal_sample_id);
				} else {
					next if ($type eq "germline");
				}
			}
		}
		if ($exp_type ne "RNAseq") {			
			if (exists $match_rnaseq{$sample_id}) {
				my $rnaseq_sample_id = $match_rnaseq{$sample_id};
				($rnaseq_var, $rnaseq_total) = getSampleCov($chr, $start, $end, $ref, $alt, $rnaseq_sample_id);				
			}
		}
		my $normal_vaf = ($normal_total == 0)? 0: ($normal_var/$normal_total);
		my $tumor_vaf = ($tumor_total == 0)? 0: ($tumor_var/$tumor_total);
		if ($tumor_total != 0 && $normal_total != 0) {
			$vaf_ratio = ($normal_vaf == 0)? 0 : $tumor_vaf / $normal_vaf ;
		}
		# if multiple VAF ratio, find max one (multiple tumors)
		if (exists($vaf_ratios{$key})) {
			my $old_vaf_ratio = $vaf_ratios{$key};
			if ($old_vaf_ratio < $vaf_ratio) {
				$vaf_ratios{$key} = $vaf_ratio;
			}
		} else {
			$vaf_ratios{$key} = $vaf_ratio;
		}	

		if (exists $sample_names{$sample_id}) {
			$sample_name = $sample_names{$sample_id};
		}
		#if germline, then we focus on normal sample
		if ($type eq "germline") {
			if ($tissue_cat eq "normal") {
				$pat_vars{$key} = '';
				# if multiple normal samples, find largest total cov
				if (exists($total_covs{$key})) {
					my $old_total_cov = $total_covs{$key};
					if ($old_total_cov < $total_cov) {
						$var_covs{$key} = $var_cov;
						$total_covs{$key} = $total_cov;
					}
				} else {
					$var_covs{$key} = $var_cov;
					$total_covs{$key} = $total_cov;
				}	
				# if multiple normal samples, find largest VAF
				if (exists($vafs{$key})) {
					my $old_vaf = $vafs{$key};
					if ($old_vaf < $vaf) {
						$vafs{$key} = $vaf;
					}
				} else {
					$vafs{$key} = $vaf;
				}				
			} # end of if ($tissue_cat eq "normal")
			else {
				next if (!exists $match_normal{$sample_id});
				my $normal_sample_id = $match_normal{$sample_id};								
				my $normal_smp_key = join("\t",$chr, $start, $end, $ref, $alt, $normal_sample_id);
				#if tumor in germline but no normal found, skip this sample
				next if (!exists $var_smp{$normal_smp_key});
			}
		}
		else { # if somatic, variant, RNAseq, we focus on tumor sample (except for RNAseq)
			if ($tissue_cat eq "tumor" || $type eq "rnaseq") {
				$pat_vars{$key} = '';
				# if somatic, then find paried normal and RNAseq
				if ($type eq "somatic") {					
					if ($exp_type ne "RNAseq") {						
						my $normal_sample_id = $match_normal{$sample_id};
						#if (!$normal_sample_id) {
						#	print "$sample_id\n";
						#}
						my $normal_smp_value = "";
						my $rnaseq_sample_id = $match_rnaseq{$sample_id};
						if (exists $match_normal{$sample_id}) {
							my $normal_sample_id = $match_normal{$sample_id};
							my $normal_smp_key = join("\t",$chr, $start, $end, $ref, $alt, $normal_sample_id);
							if (exists $var_smp{$normal_smp_key}) {
								$normal_smp_value = $var_smp{$normal_smp_key};				
							}
						}
						 my $rnaseq_smp_value = "";
						 if (exists $match_rnaseq{$sample_id} ) {					 	
							my $rnaseq_sample_id = $match_rnaseq{$sample_id};																	
							my $rnaseq_smp_key = join("\t",$chr, $start, $end, $ref, $alt, $rnaseq_sample_id);
							if (exists $var_smp{$rnaseq_smp_key}) {
								$rnaseq_smp_value = $var_smp{$rnaseq_smp_key};				
							}
						}
						
						# if paired tumor exists, calculate VAF ratio (this step is not required for somatic variants)
						if ($normal_smp_value ne "") {
							my @normal_fields = split(/\t/, $normal_smp_value);
							my $normal_total = $normal_fields[3];
							my $normal_var = $normal_fields[4];
							my $normal_vaf = ($normal_total == 0)? 0: ($normal_var/$normal_total);
							$vaf_ratio = ($normal_vaf == 0)? 0 : $vaf / $normal_vaf ;
							# if multiple VAF ratio, find max one (multiple tumors)
							if (exists($vaf_ratios{$key})) {
								my $old_vaf_ratio = $vaf_ratios{$key};
								if ($old_vaf_ratio < $vaf_ratio) {
									$vaf_ratios{$key} = $vaf_ratio;
								}
							} else {
								$vaf_ratios{$key} = $vaf_ratio;
							}
						}
						
						# if paired RNAseq exists, calculate RNAseq coverage
						if ($rnaseq_smp_value ne "") {						
							my @rnaseq_fields = split(/\t/, $rnaseq_smp_value);
							$rnaseq_total = $rnaseq_fields[3];
							$rnaseq_var = $rnaseq_fields[4];
							# if multiple RNAseq coverage, find max variant coverage (multiple RNAseq)
							if (exists($exp_var_covs{$key})) {
								my $old_var_cov = $exp_var_covs{$key};
								if ($old_var_cov < $rnaseq_var) {
									$exp_var_covs{$key} = $rnaseq_var;
									$exp_total_covs{$key} = $rnaseq_total;
								}
							} else {
								$exp_var_covs{$key} = $rnaseq_var;
								$exp_total_covs{$key} = $rnaseq_total;							
							}
						}					
						# if multiple tumor samples, find largest total cov
						if (exists($total_covs{$key})) {
							my $old_total_cov = $total_covs{$key};
							if ($old_total_cov < $total_cov) {
								$var_covs{$key} = $var_cov;
								$total_covs{$key} = $total_cov;
							}
						} else {
							$var_covs{$key} = $var_cov;
							$total_covs{$key} = $total_cov;
						}

						# if multiple tumor samples, find largest VAF
						if (exists($vafs{$key})) {
							my $old_vaf = $vafs{$key};
							if ($old_vaf < $vaf) {
								$vafs{$key} = $vaf;
							}
						} else {
							$vafs{$key} = $vaf;
						}
					}
				} # end of somatic
				#RNAseq and Variants
				else {
					# if multiple samples, find largest total cov
					if (exists($total_covs{$key})) {
						my $old_total_cov = $total_covs{$key};
						if ($old_total_cov < $total_cov) {
							$var_covs{$key} = $var_cov;
							$total_covs{$key} = $total_cov;
						}
					} else {
						$var_covs{$key} = $var_cov;
						$total_covs{$key} = $total_cov;
					}

					# if multiple samples, find largest VAF
					if (exists($vafs{$key})) {
						my $old_vaf = $vafs{$key};
						if ($old_vaf < $vaf) {
							$vafs{$key} = $vaf;
						}
					} else {
						$vafs{$key} = $vaf;
					}
				}
			} # end of if ($tissue_cat eq "tumor")			
		}
		
		if ($use_sqlldr) {
			print SAMPLE_FILE join("\t", $chr, $start, $end, $ref, $alt, $case_id, $patient_id, $sample_id, $sample_name, $caller, $qual, $fisher, $type, $tissue_cat, $exp_type, $relation, $var_cov, $total_cov, $vaf, $normal_var, $normal_total, $normal_vaf, $rnaseq_var, $rnaseq_total, $vaf_ratio)."\n";			
		} else {
			if ($start == "29474135") {
				print join(",", $chr, $start, $end, $ref, $alt, $case_id, $patient_id, $sample_id, $sample_name, $caller, $qual, $fisher, $type, $tissue_cat, $exp_type, $relation, $var_cov, $total_cov, $normal_var, $normal_total, $rnaseq_var, $rnaseq_total, $vaf_ratio, $vaf, $normal_vaf)."\n";
			}
			$varCount++;
			$sth_smp->execute($chr, $start, $end, $ref, $alt, $case_id, $patient_id, $sample_id, $sample_name, $caller, $qual, $fisher, $type, $tissue_cat, $exp_type, $relation, $var_cov, $total_cov, $normal_var, $normal_total, $rnaseq_var, $rnaseq_total, $vaf_ratio, $vaf, $normal_vaf);
		}

	} # end of while
	if (!$use_sqlldr){
		print "=====> There were $varCount variants inserted by sql execute\n";
	}

	foreach my $key (keys %pat_vars) {
		my ($chr, $start, $end, $ref, $alt) = split(/\t/, $key);
		my $vaf_ratio = ($type eq "germline")? 0 : 1;
		my $var_cov = 0;
		my $total_cov = 0;
		my $vaf = 0;
		my $exp_var_cov = 0;
		my $exp_total_cov = 0;
		
		$vaf = $vafs{$key} if (exists($vafs{$key}));
		$var_cov = $var_covs{$key} if (exists($var_covs{$key}));
		$total_cov = $total_covs{$key} if (exists($total_covs{$key}));
		$vaf_ratio = $vaf_ratios{$key} if (exists($vaf_ratios{$key}));
		$exp_var_cov = $exp_var_covs{$key} if (exists($exp_var_covs{$key}));
		$exp_total_cov = $exp_total_covs{$key} if (exists($exp_total_covs{$key}));
		
		if ($use_sqlldr) {
			print PATIENT_FILE join("\t", $chr, $start, $end, $ref, $alt, $case_id, $patient_id, $type, $var_cov, $total_cov, $vaf, $vaf_ratio, $exp_var_cov, $exp_total_cov, '', '')."\n";			
		} else {
			#$sth_pat->execute($chr, $start, $end, $ref, $alt, $case_id, $patient_id, $type, $var_cov, $total_cov, $vaf, $vaf_ratio, $exp_var_cov, $exp_total_cov, '', '');
		}
	}

	if ($use_sqlldr) {
		close(SAMPLE_FILE);
		close(PATIENT_FILE);		
	}
	else {
		$dbh->commit();
	}
	
}

sub insertAnnotation {
	my ($file) = @_;	
	return if (!-e $file);
	print "=====>processing annotation\n";
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
	my $aachange_idx = $headers{"AAChange_refGene"};
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
	my $cosmic_idx = $headers{"cosmic78"};
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

	if ($insert_col) {
		my $col_idx = 1;
		for (my $i=$gene_start;$i<=$gene_end;$i++) {
			my ($attr_name) = $header_list[$i] =~ (/(.*)_refGene/);
			if (!$attr_name) {
				$attr_name = $header_list[$i]; 
			}
			$sth_ano_col->execute($col_idx, $attr_name, 'refgene');
			$col_idx++;
		}
		$sth_ano_col->execute($col_idx, $header_list[$snp_idx], 'snp138');
		$col_idx++;		
		for (my $i=$actionable_start;$i<=$actionable_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'actionable');
			$col_idx++;
		}		
		for (my $i=$freq_start;$i<=$freq_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'freq');
			$col_idx++;
		}		
		$sth_ano_col->execute($col_idx, $header_list[$clinseq_freq_idx], 'freq');
		$col_idx++;
		for (my $i=$prediction_start;$i<=$prediction_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'prediction');
			$col_idx++;
		}
		for (my $i=$clinvar_start;$i<=$clinvar_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'clinvar');
			$col_idx++;
		}
		$sth_ano_col->execute($col_idx, $header_list[$cosmic_idx], 'cosmic');
		$col_idx++;
		for (my $i=$hgmd_start;$i<=$hgmd_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'hgmd');
			$col_idx++;
		}
		for (my $i=$reported_start;$i<=$reported_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'reported');
			$col_idx++;
		}
		for (my $i=$acmg_start;$i<=$acmg_end;$i++) {
			$sth_ano_col->execute($col_idx, $header_list[$i], 'acmg');
			$col_idx++;
		}
		$dbh->commit();
		$insert_col = 0;
	}

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
		if (length($fields[3]) > 255 || length($fields[4]) > 255) {
			next;
		}	
		my $var = join(",",$fields[0], $fields[1], $fields[2], $fields[3], $fields[4]);
		if (!exists($var_anno{$var})) {
			next if (&checkAnnotationExists($fields[0], $fields[1], $fields[2], $fields[3], $fields[4]));
			#print "new annotation!\n";
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
			#aachange
			my $aachange_all_string = $fields[$aachange_idx];
			my @aachange_list = split(/\,/, $aachange_all_string);
			my $canonical_trans_name = "";
			my $exon_num = "";
			my $aaref = "";
			my $aaalt = "";
			my $aapos = "";
			my $aachange_string = "";
			for (my $i=0; $i<=$#aachange_list;$i++) {
				my @aachange_fields = split(/\:/, $aachange_list[$i]);
				if ($#aachange_fields >= 3) {
					my $trans = $aachange_fields[1];
					my $en = $aachange_fields[2];
					#if the transcript is canonical transcript or its the last transcript
					if (exists $canonical_trans{$trans} || $i==$#aachange_list) {
						$canonical_trans_name = $trans;
						$exon_num = $en;
						if ($#aachange_fields >= 4) {
							$aachange_string = $aachange_fields[4];
							chomp $aachange_string;
							if ($aachange_string =~ /^p\.([A-Z]*)(\d+)(.+)$/) {
								$aaref = $1;
								$aaalt = $3;
								$aapos = $2;
								$aachange_string = "$1$2$3";
							}
						} else {
							$aachange_string = $aachange_fields[3];
						}
						last;					
					}					
				}							
			}			
			#ACMG
			my $acmg = ($fields[$acmg_gene_idx] ne '')? "Y" : "";
			if ($acmg eq "Y") {
				for (my $i=$acmg_start;$i<=$acmg_end;$i++) {
					if ($fields[$i] ne '') {
						$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "acmg", $header_list[$i], $fields[$i]);
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
						$sth_ano_dtl->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], "hgmd", $header_list[$i], $fields[$i]);
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
			$sth_ano->execute($fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[$func_idx], $fields[$gene_idx], $fields[$exonic_idx], $aachange_string, $actionable, $snp138, $freq, $prediction, $clinvar, $cosmic, $hgmd, $reported, "", $ciinsig, $hgmd_cat, $acmg, $canonical_trans_name, $exon_num, $aaref, $aaalt, $aapos);
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

sub checkAnnotationExists {
	my ($chr, $start_pos, $end_pos, $ref, $alt) = @_;
	$sth_ano_exists->execute($chr, $start_pos, $end_pos, $ref, $alt);
	my @row = $sth_ano_exists->fetchrow_array;
	my $exists = ($row[0] > 0);
	$sth_ano_exists->finish;
	return $exists;

}

sub getDiagnosis {
	my ($patient_id) = @_;
	$sth_diag->execute($patient_id);
	my $diagnosis = "";
	my @row = $sth_diag->fetchrow_array;
	if (@row) {
		$diagnosis = $row[0];
	}
	$sth_diag->finish;
	return $diagnosis;
}

sub checkExpressionExists {
	my ($sample_id, $target_type, $target_level) = @_;
	my @row;
	if ($target_level ne "exon") {
		$sth_exp_exists->execute($sample_id, $target_type, $target_level);
		@row = $sth_exp_exists->fetchrow_array;
		$sth_exp_exists->finish;
	} else {
		$sth_exp_exon_exists->execute($sample_id, $target_type);
		@row = $sth_exp_exon_exists->fetchrow_array;
		$sth_exp_exon_exists->finish;
	}
	my $exists = ($row[0] > 0);	
	return $exists;

}

sub getWithStatus {
    my $url = shift;
    my $content;
    my $ua = LWP::UserAgent->new;
 	$ua->timeout(1000);
 	$ua->env_proxy;
 	my $response = $ua->get($url);
 	return $response->status_line, $response->decoded_content;
}

sub callSqlldr {
	my ($data_file, $ctrl_file) = @_;

	my $bad_file = $data_file.".bad";
	my $log_file = $data_file.".log";
	system("rm -f $bad_file $log_file");
	my $user = "$username/$passwd@//$host:1521/$sid.ncifcrf.gov";
	my $cmd = "$script_dir/runSqlldr.sh $user $data_file $ctrl_file $bad_file $log_file";
	print "$cmd\n";
	system($cmd);
	if (-s $bad_file) {
		return "SQLLoader error";
	} else {
		return "ok";
	}

}
