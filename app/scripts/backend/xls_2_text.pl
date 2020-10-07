#!/usr/bin/perl
use strict;
`export PERL5LIB=/data/khanlab/apps/Bam2mpg/lib`;
use Spreadsheet::ParseExcel;

my $sourcename = shift @ARGV or die "invocation: $0 <source file>\n";
my $source_excel = new Spreadsheet::ParseExcel;
my $source_book = $source_excel->Parse($sourcename) or die "Could not open source Excel file $sourcename: $!";
my $storage_book;

foreach my $source_sheet_number (0 .. 0){
   my $source_sheet = $source_book->{Worksheet}[$source_sheet_number];
   #print "--------- SHEET:", $source_sheet->{Name}, "\n";
   next unless defined $source_sheet->{MaxRow};
   next unless $source_sheet->{MinRow} <= $source_sheet->{MaxRow};
   next unless defined $source_sheet->{MaxCol};
   next unless $source_sheet->{MinCol} <= $source_sheet->{MaxCol};
   foreach my $row_index ($source_sheet->{MinRow} .. $source_sheet->{MaxRow}){
      foreach my $col_index ($source_sheet->{MinCol} .. $source_sheet->{MaxCol}){
         my $source_cell = $source_sheet->{Cells}[$row_index][$col_index];
         if ($source_cell){
            #print "( $row_index , $col_index ) =>", $source_cell->Value, "\t";
            my $cell_value = $source_cell->Value;
            $cell_value  =~ s/\n/\|\|/g;
            print $cell_value, "\t";
         }
      }
      print "\n";
   }
}
#print "done!\n";
