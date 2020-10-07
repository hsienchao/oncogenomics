@extends('layouts.default')
@section('content')

{{ HTML::style('packages/igv.js/igv.css') }}
{{ HTML::style('https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css') }}
{{ HTML::script('packages/jquery-ui-1.11.4/jquery-ui.min.js') }}
{{ HTML::script('packages/igv.js/igv-beta.js') }}




<script type="text/javascript">

    var track_infos = {};
    @foreach ($bams as $bam)
		track_info = {};
		track_info.sample_file = '{{$bam->sample_file}}';
		track_info.exp_type = '{{$bam->exp_type}}';
		track_info.tissue_cat = '{{$bam->tissue_cat}}';
		track_infos['{{$bam->sample_name}}'] = track_info;
	@endforeach
	var track_hight = 200;
	var center = {{$center}};

    $(document).ready(function() {
        
        var div = $("#igvDiv")[0],
                options = {
                    showNavigation: true,
                    showKaryo : false,
                    showRuler : true,
                    showCenterGuide : true,
                    showCursorTrackingGuide : true,
                    //genome: "hg19",
                    reference: {fastaURL: "{{url('/ref/hg19.fasta')}}", cytobandURL: "{{url('/ref/cytoBand.txt')}}"},
                    locus: '{{$locus}}',
                    tracks: [ 
                    	{
                            //url: '{{url('/ProcessedResults/')."/".$first_bam->sample_file}}',
                            url: '{{url('/getBAM/')."/".$first_bam->sample_file}}',
                            //url: 'https://data.broadinstitute.org/igvdata/BodyMap/hg19/IlluminaHiSeq2000_BodySites/brain_merged/accepted_hits.bam',
                            //locus: "chr8:128,747,267-128,754,546",
                            name: '{{$first_bam->sample_name}}',
                            removable : true,
                            height : track_hight,
                            colorBy : 'strand',
                            samplingDepth : Number.MAX_VALUE
                        },
					    {
                            name: "Genes",
                            url: "{{url('/ref/gencode.v18.collapsed.bed')}}",
                            order: Number.MAX_VALUE,
                            displayMode: "EXPANDED"
                        },
                        {
                            url: "{{url('/ref/06302016_refseq.gtf.gz')}}",
                            indexURL: "{{url('/ref/06302016_refseq.gtf.gz.tbi')}}",
                            name: 'Transcripts',
                            height : 50,
                            format: 'gtf',
                            displayMode: "EXPANDED",
                            visibilityWindow: 10000000
                        }
                    ]
                };

        igv.createBrowser(div, options);
        sort_center(); 
        //igv.browser.centerGuide.$centerGuideToggle.trigger( "click" );               
		//var bam_track = igv.browser.trackViews[igv.browser.trackViews.length].track;
        //bam_track.altClick(center, null);
        /*
		var tracks = igv.browser.trackViews;
        		for (var i = 0; i < tracks.length; i++) {
        			var track = tracks[i].track;
        			if (track.alignmentTrack != null) {
        				track.altClick(center, null);
        				console.log(i + ' ' + typeof(track));
        			}
        		}
*/
        $('.ckSample').on('change', function() {
        	//var location = 45873433;
        	//console.log(JSON.stringify(igv.browser.trackViews[0].track));
        	//var bam_track = igv.browser.trackViews[2].track;
        	//bam_track.altClick(center, null);
        	//bam_track.alignmentTrack.sortAlignmentRows(location, {sort: "NUCLEOTIDE"});
        	//bam_track.trackView.redrawTile(bam_track.featureSource.alignmentContainer);
        	

        	var sample_name = $(this).val();
        	if ($(this).is(':checked')) {            	
            	track_info = track_infos[sample_name];
            	var url = '{{url('/getBAM/')}}' + '/' + track_info.sample_file;
            	var track = igv.browser.loadTrack({url: url, name: sample_name, height: track_hight, colorBy : 'strand', samplingDepth : Number.MAX_VALUE});
            }
            else {
            	var tracks = igv.browser.trackViews;
        		for (var i = 0; i < tracks.length; i++) {
            		var track = tracks[i].track;
            		if (track.name == sample_name)
            			igv.browser.removeTrack(track);
        		}
        	}
            sort_center(); 
        });

		$('#btnSort').on('click', function() {
			sort_center();
		});        

    });    

    function sort_center() {
        var tracks = igv.browser.trackViews;
        for (var i = 0; i < tracks.length; i++) {
                var track = tracks[i].track;
                if (track.alignmentTrack != null) {                    
                        try {
                            track.altClick(center, null);
                            
                        }
                        catch(e) {
                        }
                    console.log(i + ' ' + typeof(track));
                }
        }
    }

</script>

<span id="igv_header">                
	<h3>The IGV view of patient: <font color="red">{{$patient_id}}</font> case: <font color="red">{{$case_name}}</font> Total <font color="red">{{count($bams)}}</font> sample(s)</h3><hr>
	<h4>Samples: (check sample to load)</h4>
	@foreach ($bams as $bam)
		<input class='ckSample' type='checkbox' {{($bam->sample_file==$first_bam->sample_file)? 'checked ' : ''}} value='{{$bam->sample_name}}'><font color="red">{{$bam->sample_name}}</font> ({{$bam->exp_type}}, {{$bam->tissue_cat}})</input>
	@endforeach
	<button id="btnSort" class="btn btn-info">Sort by center base</button>
</span>
<hr>
<div class="container-fluid" id="igvDiv" style="padding:5px; border:1px solid lightgray"></div>



@stop