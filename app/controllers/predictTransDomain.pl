#!/usr/bin/perl -w

use strict;
use warnings;
use DBI;
use Getopt::Long qw(GetOptions);
use File::Basename;

my $script_dir = dirname(__FILE__);

my $url = "https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev/public"
my $cmd = "php $script_dir/getDBConfig.php";
my @db_config = readpipe($cmd);
my ($host, $sid, $username, $passwd) = split(/\t/, $db_config[0]);

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1, 
    LongReadLen  => 66000
}) || die( $DBI::errstr . "\n" );

my(%genetic_code) = (    
    'TCA' => 'S',    # Serine
    'TCC' => 'S',    # Serine
    'TCG' => 'S',    # Serine
    'TCT' => 'S',    # Serine
    'TTC' => 'F',    # Phenylalanine
    'TTT' => 'F',    # Phenylalanine
    'TTA' => 'L',    # Leucine
    'TTG' => 'L',    # Leucine
    'TAC' => 'Y',    # Tyrosine
    'TAT' => 'Y',    # Tyrosine
    'TAA' => '*',    # Stop
    'TAG' => '*',    # Stop
    'TGC' => 'C',    # Cysteine
    'TGT' => 'C',    # Cysteine
    'TGA' => '*',    # Stop
    'TGG' => 'W',    # Tryptophan
    'CTA' => 'L',    # Leucine
    'CTC' => 'L',    # Leucine
    'CTG' => 'L',    # Leucine
    'CTT' => 'L',    # Leucine
    'CCA' => 'P',    # Proline
    'CCC' => 'P',    # Proline
    'CCG' => 'P',    # Proline
    'CCT' => 'P',    # Proline
    'CAC' => 'H',    # Histidine
    'CAT' => 'H',    # Histidine
    'CAA' => 'Q',    # Glutamine
    'CAG' => 'Q',    # Glutamine
    'CGA' => 'R',    # Arginine
    'CGC' => 'R',    # Arginine
    'CGG' => 'R',    # Arginine
    'CGT' => 'R',    # Arginine
    'ATA' => 'I',    # Isoleucine
    'ATC' => 'I',    # Isoleucine
    'ATT' => 'I',    # Isoleucine
    'ATG' => 'M',    # Methionine
    'ACA' => 'T',    # Threonine
    'ACC' => 'T',    # Threonine
    'ACG' => 'T',    # Threonine
    'ACT' => 'T',    # Threonine
    'AAC' => 'N',    # Asparagine
    'AAT' => 'N',    # Asparagine
    'AAA' => 'K',    # Lysine
    'AAG' => 'K',    # Lysine
    'AGC' => 'S',    # Serine
    'AGT' => 'S',    # Serine
    'AGA' => 'R',    # Arginine
    'AGG' => 'R',    # Arginine
    'GTA' => 'V',    # Valine
    'GTC' => 'V',    # Valine
    'GTG' => 'V',    # Valine
    'GTT' => 'V',    # Valine
    'GCA' => 'A',    # Alanine
    'GCC' => 'A',    # Alanine
    'GCG' => 'A',    # Alanine
    'GCT' => 'A',    # Alanine
    'GAC' => 'D',    # Aspartic Acid
    'GAT' => 'D',    # Aspartic Acid
    'GAA' => 'E',    # Glutamic Acid
    'GAG' => 'E',    # Glutamic Acid
    'GGA' => 'G',    # Glycine
    'GGC' => 'G',    # Glycine
    'GGG' => 'G',    # Glycine
    'GGT' => 'G',    # Glycine
    );

my $sth_trans = $dbh->prepare("select * from trans_coordinate");
my $sth_new_trans = $dbh->prepare("insert into transcript values(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$sth_trans->execute();
my $count = 0;
while (my ($chr, $start_pos, $end_pos, $coding_start, $coding_end, $data_type, $strand, $symbol, $symbol, $key, $seq, $aa_seq) = $sth_trans->fetchrow_array) {
	my ($offset, $prot) = &dna2protein($seq);
	my $curl = "$url/predictPfamDomain/$key/$prot";
	my $domain = get $curl;
	$sth_trans->execute($chr, $start_pos, $end_pos, $coding_start, $coding_end, $data_type, $strand, $symbol, $symbol, $key, $seq, $aa_seq, $domain, $offset);
	$count++;
	if ($count == 500) {
		$dbh->commit();
		$count = 0;
	}
	
}
$sth_smp_cat->finish;
$dbh->commit();
$dbh->disconnect();

sub dna2protein {
	my ($dna) = @_;	
	my @offsets = (0);
	if (substr($dna, 0, 3) ne "ATG") {
		push @offsets 1;
		push @offsets 2;		
	}
	
	my $protein = "";
	my $max_orf = 0;
	my $max_offset = 0;
	foreach my $offset (@offsets) {
		my $prot = "";
		for(my $i=$offset;$i<(length($dna)-2);$i+=3) {
			my $codon = substr($dna,$i,3);
			$prot .= &codon2aa($codon);
		}
		my $orf_length = index($prot, '*');
		if ($orf_length == -1) {
			$orf_length = length($prot);
		}
		if ($orf_length > $max_orf) {
			$protein = $prot;
			$max_orf = $orf_length;
			$max_offset = $offset;
		}
	}
	return ($max_offset, $protein);
}

sub codon2aa {
    my($codon) = @_;
	$codon = uc $codon;
	if(exists $genetic_code{$codon}) {
		return $genetic_code{$codon};
    }else{
		return "X";
    }
}

sub checkTransExists {
	my ($trans) = @_;
	$sth_trans_exists->execute($trans);
	my @row = $sth_trans_exists->fetchrow_array;
	my $exists = ($row[0] > 0);
	$sth_trans_exists->finish;
	return $exists;

}