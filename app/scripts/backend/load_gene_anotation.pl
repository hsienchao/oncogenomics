#!/usr/bin/perl -w

use strict;
use warnings;
use JSON qw( decode_json );
use LWP::Simple;
use DBI;
use Try::Tiny;

my $host = 'fr-s-oracle-d.ncifcrf.gov';
my $sid = 'oncosnp11d';
my $username = 'os_admin';
my $passwd = 'osa0520';

my $outfile = "gene_annotation.tsv";
open(OUTFILE, ">$outfile") or die "Cannot open file $outfile";

my $dbh = DBI->connect( "dbi:Oracle:host=$host;sid=$sid", $username, $passwd, {
    AutoCommit => 0,
    RaiseError => 1,
}) || die( $DBI::errstr . "\n" );
my $sql = 'select distinct symbol from rnaseq_Gene_exprs';
my $sql_insert = "insert into GENE_ANNOTATION values(?,?,?)";
my $sth = $dbh->prepare($sql);
my $sth_insert = $dbh->prepare($sql_insert);
$dbh->do("delete from GENE_ANNOTATION");
$sth->execute();
while (my @row = $sth->fetchrow_array) {
   my $url = 'http://biodbdev.abcc.ncifcrf.gov/webServices/rest.php/biodbnetRestApi.json?method=dbannot&inputValues='.$row[0].'&taxonId=9606&annotations=Genes,Pathways,Drugs,Protein%20interactors,Diseases,GO%20Terms&format=row';
   my $json = get $url;

   my $decoded;
   try {
        $decoded = decode_json($json);
   } catch {
        print "error on gene: $row[0]. reason: $_";
        next;
   };
   

   foreach my $gene (@{$decoded}) {
       while ( my ($key, $value) = each %{$gene}) {
          if ($key eq "Gene Info") {
              my @tokens = ();            
              while ($value =~ /\[(.*?)\]/ig) {
                     push @tokens, $1;
              } 
              foreach my $token (@tokens) {
                     if ($token =~ /(.*)\:\s(.*)/) {
                         #$sth_insert->execute($row[0], $1, $2);
                         print OUTFILE $row[0]."\t$1\t$2\n";
                     }
              }             
          }
          else {
              my @tokens = split(/\/\//, $value);
              foreach my $token (@tokens) {
                 if ($key eq "DrugBank Drug Info") {
                     if ($token =~ /(.*?)\s\[(.*?)\]/ig) {
                         $token = $1."//".$2;
                     }
                 }
                 #$sth_insert->execute($row[0], $key, $token);
                 print OUTFILE $row[0]."\t$key\t$token\n";
              }
          }
       }  
   }
}
close(OUTFILE);
$dbh->commit();
$sth->close();
$dbh->disconnect();


