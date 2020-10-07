@extends(($with_header)? 'layouts.default' : 'layouts.noheader')
@section('content')

@section('title')
    {{$patient_id}}
@stop
{{ HTML::style('css/style.css') }}
{{ HTML::style('css/light-bootstrap-dashboard.css') }}

{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('packages/smartmenus-1.0.0-beta1/libs/jquery/jquery.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/d3/d3.css') }}
{{ HTML::style('packages/gene_fusion/gene-fusion.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('css/filter.css') }}

{{ HTML::script('packages/d3/d3.min.js') }}
{{ HTML::script('packages/d3/d3.tip.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.new.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}

<style>
div.toolbar {
	display:inline;
}

.btn-default:focus,
.btn-default:active,
.btn-default.active {
    background-color: DarkCyan;
    border-color: #000000;
    color: #fff;
}

.block_details {
    display:none;
    width:90%;
    height:130px;    
	border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto; 
}

.comment {
    width:90%;    
	border-radius: 20px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px;
	overflow: auto; 
}

td.details-control {
	text-align: center;
    cursor: pointer;
}

tr.details td.details-control {
    background: '{{url('/images/details_close.png')}}' no-repeat center center;
}

a.boxclose{
    float:right;
    margin-top:-12px;
    margin-right:-12px;
    cursor:pointer;
    color: #fff;
    border-radius: 10px;
    font-weight: bold;
    display: inline-block;
    line-height: 0px;
    padding: 8px 3px; 
    width:25px;
    height:25px;    
    background:url('{{url('/images/close-button.png')}}') no-repeat center center;  
}

/* end only demo styles */

.checkbox-custom, .radio-custom {
    opacity: 0;
    position: absolute;     
}

.checkbox-custom, .checkbox-custom-label, .radio-custom, .radio-custom-label {
    display: inline-block;
    vertical-align: middle;
    margin: 5px;
    cursor: pointer;
}

.checkbox-custom-label, .radio-custom-label {
    position: relative;
}

.checkbox-custom + .checkbox-custom-label:before, .radio-custom + .radio-custom-label:before {
    content: '';
    background: #fff;
    border: 2px solid #ddd;
    display: inline-block;
    vertical-align: middle;
    width: 30px;
    height: 30px;
    padding: 2px;
    margin-right: 10px;
    text-align: center;
}

.checkbox-custom:checked + .checkbox-custom-label:before {
    content: "\f00c";
    font-family: 'FontAwesome';
    background: rebeccapurple;
    font-size: 16;
    color: #fff;
}

.radio-custom + .radio-custom-label:before {
    border-radius: 50%;
}

.radio-custom:checked + .radio-custom-label:before {
    content: "\f00c";
    font-family: 'FontAwesome';
    font-size: 16;
    width : 30px;
    height : 30px;
    color: red;
}

.checkbox-custom:focus + .checkbox-custom-label, .radio-custom:focus + .radio-custom-label {
  outline: 1px solid #ddd; /* focus style */
}

.left_pep {
	background-color: rbga(23,34,56,1);
}

.right_pep {
	background-color: "pink";
}

.in_domain {
	opacity: 1;
}

.out_domain {
	opacity: 0.6;
}


</style>

<script type="text/javascript">
	
	var options = [];
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var patient_state = 'collapse';
	
	//current project, diagnosis and patient data
	var current_patient = "{{$patient_id}}";
	var default_project = "{{$project_id}}";	
		
	var tab_urls = [];
	var sub_tabs = [];
	var loaded_list = [];
	var tab_shown = true;
	var sample_list=[];
	var title_id_mapping=[];

	$(document).ready(function() {		
		$("a.img_group").fancybox();
		$("#lblStatus").text('{{$case->status}}');
		var first_tab = "Summary";
		$('#case_summary').height(100);
		getSummary( 'case_summary','variant_summary');		
		
		@foreach ($sample_types as $type => $samples)
			@if (count($samples) > 0)
				sub_tabs['{{Lang::get("messages.$type")}}'] = 'tabVar{{$type}}'
				@if (($type == 'somatic' &&  count($samples) > 2) || ($type != 'somatic' &&  count($samples) > 1))
					console.log('{{url("/viewVarAnnotation/$project_id/$patient_id/null/$case->case_id/$type")}}');
					tab_urls['{{Lang::get("messages.$type")}}-All'] = '{{url("/viewVarAnnotation/$project_id/$patient_id/null/$case->case_id/$type")}}';
					if (first_tab == null)
						first_tab = '{{Lang::get("messages.$type")}}-All';				
				@else
					tab_urls['{{Lang::get("messages.$type")}}'] = '{{url("/viewVarAnnotation/$project_id/$patient_id/null/$case->case_id/$type")}}';
					if (first_tab == null)
						first_tab = '{{Lang::get("messages.$type")}}';
				@endif
			@endif
			@foreach ($samples as $sample)
				@if (!($type == 'rnaseq' && $sample->exp_type != 'RNAseq') && !($type == 'somatic' && $sample->exp_type == 'RNAseq'))
					var title = '{{$sample->sample_alias."-".str_replace(" ", "_", $sample->exp_type)}}';
					title_id_mapping[title] = '{{$type}}_{{$sample->sample_id}}';
					tab_urls['{{$type}}_{{$sample->sample_id}}'] = '{{url("/viewVarAnnotation/$project_id/$patient_id/$sample->sample_id/$sample->case_id/$type")}}';
				@endif
				
			@endforeach

			@if ($type == 'somatic' && $has_burden)
				tab_urls['Mutation_Burden'] = '{{url("/viewMutationBurden/null/$patient_id/$case->case_id")}}';
			@endif

		@endforeach
		
		@if ($fusion_cnt > 0 )
			tab_urls['Fusion'] = '{{url("/viewFusion/$patient_id/$case->case_name")}}';
			@if ($merged)
				tab_urls['Fusion'] = '{{url("/viewFusion/$patient_id/any")}}';
			@endif
			if (first_tab == null)
				first_tab = 'Fusion';
		@endif

		@foreach ($exp_samples as $sample_name => $sample_id)
			tab_urls["Exp-{{$sample_id}}"] = '{{url("/viewExpressionByCase/$project_id/$patient_id/$case->case_id/$sample_id")}}';
			if (first_tab == null)
				first_tab = 'Exp-{{$sample_id}}';				
		@endforeach
		sub_tabs['Expression'] = 'tabExp';
		sub_tabs['Mixcr'] = 'tabMix';
		tab_urls['Circos'] = '{{url("/viewCircos/$patient_id/$case->case_name")}}';
		console.log('{{url("/viewCircos/$patient_id/$case->case_name")}}');
		tab_urls['QC'] = '{{url("/viewVarQC/$patient_id/$case->case_name")}}';
		tab_urls['GSEA'] = '{{url("/viewGSEA/$project_id/$patient_id/$case->case_id/".rand())}}';
		tab_urls['MethylSeq']='{{url("/viewMethylSeq/$patient_id/$case->case_id")}}';
		@foreach ($cnv_samples as $sample_name => $case_id)

			tab_urls["{{$sample_name}}-Table-Sequenza"] = '{{url("/viewCNV/$project_id/$patient_id/$case->case_id/$sample_name/sequenza")}}';
			if (first_tab == null)
				first_tab = "{{$sample_name}}-Table-Sequenza";
		@endforeach

		@foreach ($cnvkit_samples as $sample_name => $case_id)
			tab_urls["{{$sample_name}}-Table-cnvkit"] = '{{url("/viewCNV/$project_id/$patient_id/$case->case_id/$sample_name/cnvkit")}}';
			if (first_tab == null)
				first_tab = "{{$sample_name}}-Table-cnvkit)";
		@endforeach
		@if (count($mix_samples) > 0)
			@foreach ($mix_samples as $sample_name => $case_id)
				console.log("/getmixcrTable/$patient_id/$sample_name/$case_id/clones");
				$.ajax({ url: '{{url("/getmixcrTable/$patient_id/$sample_name/$case_id/clones")}}', async: true, dataType: 'text', success: function(json_data) {			json_data = parseJSON(json_data);
					console.log(json_data);
					showTable(json_data, '{{$sample_name}}-clones_table');	
						}
					});
				if (first_tab == null)
					first_tab = "{{$sample_name}}-clones";

				$.ajax({ url: '{{url("/getmixcrTable/$patient_id/$sample_name/$case_id/summary")}}', async: true, dataType: 'text', success: function(json_data) {			json_data = parseJSON(json_data);
					showTable(json_data, '{{$sample_name}}-summary_table');	
						}
					});
			@endforeach
		@endif
		
		@if (count($cnv_samples) > 0 && $merged)
			tab_urls["CNV-Merged"] = '{{url("/viewCNV/$project_id/$patient_id/any/any")}}';
			tab_urls["CNV-Merged-Table"] = '{{url("/viewCNV/$project_id/$patient_id/any/any")}}';
		@endif

		sub_tabs['Neoantigen'] = 'tabAntigen';
		@foreach ($antigen_samples as $sample_name => $case_id)
			url = '{{url("/viewAntigen/$project_id/$patient_id/$case->case_id/$sample_name")}}';
			tab_urls["{{$sample_name}}-Neoantigen"] = url;
			if (first_tab == null)
				first_tab = "{{$sample_name}}-Neoantigen";
		@endforeach

		showFrameHtml(first_tab);
		
		@if (count($hla_samples) > 0)	
			@foreach ($hla_samples as $sample_name => $sample_id)
				$.ajax({ url: '{{url("/getHLAData/$patient_id/$case->case_id/$sample_name")}}', async: true, dataType: 'text', success: function(json_data) {
							json_data = parseJSON(json_data);				
							showTable(json_data, 'tblHLA{{$sample_name}}');
							doFilter('tblHLA{{$sample_name}}');
						}
					});
			@endforeach								
		@endif
		{{url("/getQCPlot/$patient_id/$case->case_id/coveragePlot")}}
		$.extend($.fn.validatebox.defaults.rules,{
		    exists:{
		        validator:function(value,param){
		            var cc = $(param[0]);
		            var v = cc.combobox('getValue');
		            var rows = cc.combobox('getData');
		            for(var i=0; i<rows.length; i++){
		                if (rows[i].id == v){return true}
		            }
		            return false;
		        },
		        message:'The entered patient does not exists.'
		    }
		});
		
		$.fn.dataTableExt.afnFiltering.push( function( oSettings, aData, iDataIndex ) {
			if (oSettings.nTable == document.getElementById('tblCNV')) {
				console.log(oSettings);
				if ($('#ckCNVCancerGene').is(":checked"))
					return (cancer_genes.indexOf(aData[6]) != -1);
				else
					return true;
			}
			if (oSettings.nTable.id.substring(0, 6) == "tblHLA") {
				if ($('#ck' + oSettings.nTable.id).is(":checked"))
					return (aData[1] != "NotCalled" && aData[2] != "NotCalled");				
			}			
			return true;

		});	


		$('.easyui-tabs').tabs({
			onSelect:function(title, idx) {
				var tab = null;
				var url = null;				
				var sub_tab = sub_tabs[title];
				if (sub_tab != undefined)
					tab = $('#' + sub_tab).tabs('getSelected');
				else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;
				console.log("ID: " + id);
				showFrameHtml(id);				
		   }
		});		

		$('#btnDownloadVCF').on('click', function() {
			var url = '{{url('/getVCF')}}' + '/' + '{{$patient_id}}' + '/' + '{{$case->case_id}}';
			window.location.replace(url);	
		});
		
		$('#btnPublish').on('click', function() {
			w2confirm('<H4>Are you sure you want to publish case {{$case->case_name}}?</H4>')
	   			.yes(function () {
	   				var url = '{{url("/publishCase/$patient_id/$case->case_id")}}';
					$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
						if (data = "ok") {
							$("#lblStatus").text('passed');
							$('#btnPublish').css("display","none");
							w2alert("<H4>Publish successful!</H4>");
						}
						else
							w2alert("<H4>Publish error! " + data + "</H4>");
					}, error: function(xhr, textStatus, errorThrown){					
						w2alert("<H4>Error:</H4>" + JSON.stringify(xhr) + ' ' + errorThrown);
					}
				});		
			});
		});	

		$('#ckCNVCancerGene').change(function() {
			tbls['tblCNV'].draw();
		}); 

		$('.mytooltip').tooltipster();				
		
	});

function drawBarChart(div_id, title, count_data, variants ){

	var chart=$('#' + div_id).highcharts({

			credits: false,
	        chart: {
	            type: 'column',
	            zoomType: 'xy'
	        },

	        title: {
	            text: title+" variant summary"
	        },

	        legend: {
	            enabled: true
	        },
	        xAxis: {
			categories:variants,
	        	title: {
	                text: 'Type',
	                style: {
	                    fontSize:'15px'
	                }
	            },

	            labels: {
	                style: {
	                    fontSize:'12px'
	                }
	            },
        	
	        },
  			

	        yAxis: {
  				type: 'logarithmic',
	            title: {
	                text: 'Count',
	                style: {
	                    fontSize:'12px'
	                }
	            },
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
	                
	        },

	        plotOptions: {
	        	column: {
					dataLabels: {
	                	enabled: true,
	                	useHTML: true,
	                	rotation : -50,
	                	overflow: 'none',
	                	align: 'left',
	                	crop: false,
	                	formatter: function() {
	                		return this.point.y;
	                	}
	                }
	            }
		    }
	        

	        

	    });

    	var chart = $('#' + div_id).highcharts();
    	for (i = 0; i < count_data.length; i++) {
    		name="Tier "+(i+1);
    		chart.addSeries({
        		name: name,
	    		data: count_data[i],
	    		type:'column'
   			 });
    	} 
}

function drawLinePlot(div_id, title, sample_list, coverage_data ) {	
		
		var chart=$('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'line',
	            zoomType: 'xy'
	        },

	        title: {
	            text: title+" Target Region Coverage"
	        },

	        legend: {
	            enabled: true
	        },
	        xAxis: {
	        	type: 'logarithmic',
	        	title: {
	                text: 'Coverage',
	                style: {
	                    fontSize:'18px'
	                }
	            },

	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
        	
	        },

	        yAxis: {

	            title: {
	                text: 'Fraction of Capture target bases >= depth',
	                style: {
	                    fontSize:'12px'
	                }
	            },
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            },
	            floor : 0,
	            tickInterval: .1,
    			min: 0,
    			max: 1,	            
	        },

	        

	    });

    	var chart = $('#' + div_id).highcharts();
    	for (i = 0; i < sample_list.length; i++) {

    		chart.addSeries({
        		name: sample_list[i],
	    		data: coverage_data[i],
   			 });
    	} 



	}

	function showAll(tblName) {
		var tbl = tbls[tblName];
		tbl.search('');
		$('#lbl' + tblName).removeClass('active');
		$('#ck' + tblName).prop('checked', false);
		$('#fc_cutoff' + tblName).numberbox("setValue", 0);
		doFilter(tblName);
	}

	function doFilter(tblName) {
		var tbl = tbls[tblName];
		tbl.draw();
		$('#lblCountDisplay' + tblName).text(tbl.page.info().recordsDisplay);
    	$('#lblCountTotal' + tblName).text(tbl.page.info().recordsTotal);		
	}

	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url == undefined)
				url = tab_urls[title_id_mapping[id]];
			// console.log(url);
			if (url != undefined) {
				console.log(url);
				var html = '<iframe scrolling="auto" frameborder="0" frameborder="0" scrolling="no" onload="resizeIframe(this)" src="' + url + '" style="width:100%;height:100%;min-height:800px;border-width:0px"></iframe>';
				$('#' + id).html(html);
				loaded_list.push(id);
			}
		}
	}


	function generateList(project, diagnosis, patient) {
		case_list = [];
		patient_list = [];
		diagnosis_list = [];		

		var selected_diags = projects[project].sort();
		selected_diags.forEach(function(d) {
			diagnosis_list.push({value: d, text: d});
		})

		var selected_patients = diagnoses[diagnosis].sort();
		selected_patients.forEach(function(d) {
			patient_list.push({value: d, text: d});
		})

		var selected_cases = patients[patient].sort();

		selected_cases.forEach(function(d) {
			case_list.push({value: d, text: d});
		})
		
	}

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
	}	
		

	function getSummary ( table, bar_chart ) {
		//type = "sample";		
		var patient_link = document.createElement("div");
//		patient_id = getInnerText(d[2]);
		type="samples";
		//var url = (type == 'samples')? '{{url("/getSampleByPatientID/$project_id")}}' + '/' + '{{$patient_id}}'+'/{{$case->case_id}}' : '{{url("/getCasesByPatientID/$project_id")}}' + '/' + '{{$patient_id}}';
		var url = (type == 'samples')? '{{url("/getSamplesByCaseName/$patient_id/$case->case_name")}}' : '{{url("/getCasesByPatientID/$project_id")}}' + '/' + '{{$patient_id}}';
		console.log(url);
		var version_url = '{{url("/getpipeline_summary")}}' + '/' + '{{$patient_id}}'+'/{{$case->case_id}}';
		tbl_id = table;
		bar_crt=bar_chart;
		loading_id = "loading" + '{{$case->case_id}}';
		console.log(url);
		lbl_id = "lbl" + '{{$case->case_id}}';
		var num_samples = 0;
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
			var data = JSON.parse(data);
			var num_samples = data.data.length;
			if (num_samples > 0) {		
				showTable(data, tbl_id);
			}
			$('#' + loading_id).css("display","none");
			$('#' + lbl_id).text(num_samples);
			}
		});
		if('{{$case->case_id}}' != "any"){
			$.ajax({ url: version_url, async: true, dataType: 'text', success: function(vdata) {
				var vdata = JSON.parse(vdata);
				
				$("#pipline_version").text(" "+vdata.version_data['pipeline_version']);
				}
			});
		}
		else $("#pipline_version").text(" NA");

		$("#summary_text").html( '<div style="text-align:left; padding: 20px;margin: 0px 0px 0px;font-size: 13px;line-height:1;"><div id="' + loading_id + '"><img src="{{url('/images/ajax-loader.gif')}}""></img></div>Case ' + '{{$case->case_name}}' + ' has <label ID="' + lbl_id + '"></label> ' + type);
		
		type="cases";
		var url ='{{url("/getTierCount/$project_id")}}' + '/' + '{{$patient_id}}'+'/{{$case->case_name}}';
		console.log(url);
		//var url = (type == 'samples')? '{{url("/getSampleByPatientID/$project_id")}}' + '/' + '{{$patient_id}}'+'/{{$case->case_id}}' : '{{url("/getCasesByPatientID/$project_id")}}' + '/' + '{{$patient_id}}';
		$.ajax({ url: url, async: true, dataType: 'text', success: function(case_data) {
			var case_data = JSON.parse(case_data);
			if (case_data.data.length > 0)
				drawBarChart("sum_barchart", "{{$case->case_name}}", case_data.data,case_data.variants )
			else {
				$('#variants_card').css("display","none");
				$('#coverage_card').removeClass('col-md-5');
				$('#coverage_card').addClass('col-md-12');
			}
			var url= '{{url("/getCoveragePlotData/$patient_id/$case->case_name/all")}}';
			console.log(url);
			$.ajax({ url: url, async: true, dataType: 'text', success: function(json_data) {
				json_data = parseJSON(json_data);
				console.log("in viewCase.blade.php:");
				console.log(json_data);
				for (i = 0; i < json_data.samples.length; i++) {
					sample_list.push(json_data.samples[i]);
				}
				if (json_data.coverage_data.length > 0)
					drawLinePlot("cov_lineplot", "{{$case->case_name}}", sample_list, json_data.coverage_data )
				else {
					$('#coverage_card').css("display","none");
					$('#variants_card').removeClass('col-md-7');
					$('#variants_card').addClass('col-md-12');
				}
				$(".hotspot_sample").on('change', function() {
					drawLinePlot("cov_lineplot", "{{$case->case_name}}", sample_list, json_data.coverage_data);
				});
			}
		});


		}
	});
	  	
	}
	function showTable(data, tblId) {		
		var tbl = $('#' + tblId).DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"deferRender": true,
			"lengthMenu": [[15, 25, 50], [15, 25, 50]],
			"pageLength":  15,
			"pagingType":  "simple_numbers",			
			"dom": '<"toolbar">lfrtip',
			"columnDefs": [{
                			"render": function ( data, type, row ) {
                						if (tblId != 'tblGenoTyping')
                							return data;
                						if (isNaN(data))
                							return data;
                						else {
                							color_value = getColor(data);
                    						return $("<div></div>", {"class": "bar-chart-cell"}).append(function () {
                    													var bars = [];
                    													bars.push($("<div></div>",{"class": "bar"}).text(Math.round((data * 100)) + '%').css({"width": (data * 100) + '%', "background-color" : color_value}));
                    													return bars;
                    											}).prop("outerHTML");
                    								}

                						},
                			"targets": '_all'
            				}]					
		} );		
		tbls[tblId] = tbl;
		var columns =[];
		col_html[tblId] = '';
		
		//$("div.toolbar").html('<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		
		$("#" + tblId + "_wrapper").children("div.toolbar").html('<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		//$("#" + tblId + "_wrapper").children("div.toolbar").html(tblId);
		tbl.columns().iterator('column', function ( context, index ) {
			if(data.type!="summary")
				var show = (data.hide_cols.indexOf(index) == -1);
			else{
				var show = (data.show_cols.indexOf(tbl.column(index).header().innerHTML) != -1);
				//console.log(tbl.column(index).header().innerHTML+ " "+ (data.show_cols.indexOf(tbl.column(index).header().innerHTML) != -1))
			}
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	        
		$("#" + tblId + "_popover").popover({				
				title: 'Select column <a href="#inline" class="close" data-dismiss="alert">×</a>',
				placement : 'bottom',  
				html : true,
				content : function() {
					var tblId= $(this).attr("id").substring(0, $(this).attr("id").indexOf('_popover'));
					return col_html[tblId];
				}
		});

		$(document).on("click", ".popover .close" , function(){
				$(this).parents(".popover").popover('hide');
		});
		
		$('body').on('change', 'input.data_column', function() {             				
				var tblId = $(this).attr("id").substring($(this).attr("id").indexOf('data_column_') + 12);
				var tbl = tbls[tblId];
				var columns = column_tbls[tblId];
				col_html[tblId] = '';
				for (i = 0; i < columns.length; i++) { 
					if (i == $(this).attr("value"))
						checked = ($(this).is(":checked"))?'checked' : '';
					else
						checked = (tbl.column(i).visible())?'checked' : '';
					col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + i + '><font size=3>&nbsp;' + columns[i] + '</font></input><BR>';
				}
				tbl.column($(this).attr("value")).visible($(this).is(":checked"));
				
		});

	}
	
	
	function switch_details(state, block_class, image_id) {
		if ( state === 'collapse' ) {
			state = 'expand';
			$("#" + block_class).css("display","block");
			$("#" + image_id).attr("src",'{{url('/images/details_close.png')}}');
		}
		else {
			state = 'collapse';
			$("#"  + block_class).css("display","none");
			$("#"  + image_id).attr("src",'{{url('/images/details_open.png')}}');
    	}
    	return state;
	}

	function do_switch(type) {
		if (type == "patient")
			patient_state = switch_details(patient_state, 'patient_details', 'imgPatientDetails');		
	}

	function toggle_tabs() {
		if (tab_shown) {
			$('#tabMain').tabs('hideHeader');
			$('#tabVar').tabs('hideHeader');
			$('#tabQC').tabs('hideHeader');
			$('#btnToggleTabs').text('Show tabs');
			tab_shown = false;
		} else {
			$('#tabMain').tabs('showHeader');
			$('#tabVar').tabs('showHeader');
			$('#tabQC').tabs('showHeader');
			$('#btnToggleTabs').text('Hide tabs');
			tab_shown = true;
		}
	}
	function show_case_details() {
		$.ajax({ url: '{{url("/getCaseDetails/null/$patient_id/$case->case_id/true")}}', async: true, dataType: 'text', success: function(json_data) {
				json_data = JSON.parse(json_data);				
				showCaseTable(json_data);
			}
		});
	}

	function resizeIframe(obj) {
		obj.style.height = 0;
    	obj.style.height = obj.contentWindow.document.documentElement.scrollHeight + 'px';
  	}	

</script>

<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;height:100%;padding:0px;border-width:0px">	
	<div id="tabVar" class="easyui-tabs" data-options="tabPosition:'left',fit:true,plain:true,pill:true,border:false,headerWidth:100" style="width:100%;height:100%;padding:0px;overflow:visible;border-width:0px">
				<div id="Summary" title="Summary" style="width:100%;padding:10px;background: #E0E0E0 !important">
					@if ($case->status == "pending" && $case->case_id != "any")
						<div id="publish" class="card" style="padding-bottom: 40px;">
							<span style="font-family: monospace; font-size: 20;float:left;">	
								Status: <font color="red"><span id="lblStatus" style="text-align:left;color:red;" text=""></span></font>	
								&nbsp;&nbsp;<button id="btnPublish" class="btn btn-info">Publish case</button>
							</span>
							<span style="font-family: monospace; font-size: 20;float:right;"> Pipeline finished: <font color="red">{{$case->finished_at}}</font>&nbsp;&nbsp;Uploaded: <font color="red">{{$case->updated_at}}</font></span>
						</div>
					<br>
					<br>
					@endif

					<div id="Libraries" class="card">
						<text x="399" text-anchor="middle" class="highcharts-title" style="color:#333333;font-size:18px;fill:#333333;width:734px;" y="24"><tspan>Libraries:</tspan>
							@if (!Config::get('site.isPublicSite'))
							<tspan> pipeline version</tspan><tspan id="pipline_version"> </tspan></text>
							@endif
						<hr>
						<div id ="summary_text"></div>
							<table id="case_summary" style="width:100%;border:1px" class="pretty dataTable no-footer"></table>
					</div>
					<br>
					<div class="container-fluid">
						<div class="row">
							<div id="coverage_card" class="col-md-5">
								<div class="card">

								<text x="399" text-anchor="middle" class="highcharts-title" style="color:#333333;font-size:18px;fill:#333333;width:734px;padding: 10px" y="24"><tspan>Coverage</tspan></text>
								<hr>
								<br>
								<div id='cov_lineplot' style="height:320px;"></div>
								</div>
							</div>
							<div id="variants_card" class="col-md-7">
								<div class="card">

								<text x="399" text-anchor="middle" class="highcharts-title" style="color:#333333;font-size:18px;fill:#333333;width:734px;padding: 10px" y="24"><tspan>Variants</tspan></text>
								<hr>
								<br>
								<div id='sum_barchart' style="height:320px;"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
				@foreach ($var_types as $type)
				  @if ($project->showFeature($type))
					<div id="{{Lang::get("messages.$type")}}" title="{{Lang::get("messages.$type")}}" data-options="tools:'#{{$type}}_mutation_help'" style="width:98%;padding:0px;">
						@if (array_key_exists($type, $sample_types))
							@if (count($sample_types[$type]) > 0)
							<div id="tabVar{{$type}}" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:100%;padding:0px;overflow:visible;border-width:0px">
								@if (($type == 'somatic' && count($sample_types[$type]) > 2) || ($type != 'somatic' && count($sample_types[$type]) > 1))
								<div id="{{Lang::get("messages.$type")}}-All" title="{{Lang::get("messages.$type")}}-All" style="width:100%;height:95%;padding:0px;">
								</div>
								@endif
							@endif
							@foreach ($sample_types[$type] as $sample)
								@if (!($type == 'rnaseq' && $sample->exp_type != 'RNAseq') && !($type == 'somatic' && $sample->exp_type == 'RNAseq'))
									<div id='{{$type}}_{{$sample->sample_id}}' title='{{$sample->sample_alias."-".str_replace(" ", "_", $sample->exp_type)}}' style="width:98%;height:95%;padding:0px;">
									</div>
								@endif								
							@endforeach
							@if ($type == 'somatic' && $has_burden)
								<div id="Mutation_Burden" title="Mutation_Burden" style="width:98%;height:95%;padding:0px;">								
								</div>
							@endif
							@if (count($samples) > 0)	
								</div>
							@endif
						@else
				  			<H3>No variants called.</H3>
						@endif
					</div>
				  @endif
				@endforeach					
				
				@if (count($mix_samples) > 0)
					<div id="Mixcr" title="Mixcr" style="width:98%;padding:0px;">
						<div id="tabMix" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
							@foreach ($mix_samples as $sample_name => $case_id)
									<div id="Mixcr-{{$sample_name}}" title="Mixcr-{{$sample_name}}">
										<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
											@if (array_key_exists($sample_name, $mixRNA_samples))
												<div id="{{$sample_name}}-RNAseq" title="{{$sample_name}}-RNAseq">
													<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
														<div id="{{$sample_name}}-summary" title="{{$sample_name}}-summary">
															<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="{{$sample_name}}-summary_table" style='width:95%;'></table> 			
														</div>
														<div id="{{$sample_name}}-clones" title="{{$sample_name}}-clones">
															<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="{{$sample_name}}-clones_table" style='width:95%;'></table> 			
														</div>
														<div id="{{$sample_name}}-TRA" title="{{$sample_name}}-TRA">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/TRA")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
														<div id="{{$sample_name}}-TRB" title="{{$sample_name}}-TRB">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/TRB")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
														<div id="{{$sample_name}}-IGH" title="{{$sample_name}}-IGH">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/IGH")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
														<div id="{{$sample_name}}-IGK" title="{{$sample_name}}-IGK">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/IGK")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
														<div id="{{$sample_name}}-IGL" title="{{$sample_name}}-IGL">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/IGL")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
													</div>
												</div>
											@endif
											@if (array_key_exists($sample_name, $mixTCR_samples))
												<div id="{{$sample_name}}-TCRSeq" title="{{$sample_name}}-TCRSeq">
													<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
														<div id="{{$sample_name}}-clones" title="{{$sample_name}}-clones"></div>
														<div id="{{$sample_name}}-summary" title="{{$sample_name}}-summary"></div>
														<div id="{{$sample_name}}-TRA" title="{{$sample_name}}-TRA">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/TRA")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
														<div id="{{$sample_name}}-TRB" title="{{$sample_name}}-TRB">
															<object data="{{url("/getmixcrPlot/$patient_id/$sample_name/$case_id/TRB")}}" type="application/pdf" width="100%" height="100%"></object>
														</div>
													</div>
												</div>
											@endif
										</div>
									
									</div>
							@endforeach						
						</div>
					</div>
				@endif					
			@if ($fusion_cnt > 0)
			  @if ($project->showFeature('fusion'))
				<div id="Fusion" title="Fusion" style="width:100%;padding:10px;">
				</div>
			  @endif
			@endif
			@if ($project->showFeature('expression'))
			  @if (count($exp_samples) > 0)	
				<div id="Expression" title="Expression" style="width:98%;padding:0px;">
					<div id="tabExp" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
						@foreach ($exp_samples as $sample_name => $sample_id)
							<div id="Exp-{{$sample_id}}" title="Exp-{{$sample_name}}">								
							</div>
						@endforeach						
					</div>	
				</div>
			  @endif
			@endif
			@if ($project->showFeature("cnv"))
			  @if (count($cnv_samples) > 0 || count($cnvkit_samples) > 0)
				<div id="CNV" title="CNV" style="width:98%;padding:0px;">				
					<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:100%;padding:10px;overflow:visible;">
						@if (count($cnv_samples) > 0)
							@foreach ($cnv_samples as $sample_name => $case_id)
								<div id="{{$sample_name}}" title="{{$sample_name}}">
									<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:100%;padding:10px;overflow:visible;">
										<div title="Genome View-Sequenza">
											<!--object data="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/genome_view")}}" type="application/pdf" width="100%" height="700"></object-->
											<embed type="application/pdf" src="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/genome_view")}}" style="width:98%;height:700;overflow:none"></embed>
										</div>									
										<div title="Chromosome View-Sequenza">
											<!--object data="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/chromosome_view")}}" type="application/pdf" width="100%" height="700"></object-->
											<embed type="application/pdf" src="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/chromosome_view")}}" style="width:98%;height:700;overflow:none"></embed>
										</div>

										<div id="{{$sample_name}}-Table-Sequenza" title="{{$sample_name}}-Table-Sequenza">
										</div>
										@if (array_key_exists($sample_name, $cnvkit_samples))
											<div title="Genome View - cnvkit">
												<object data="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/cnvkit")}}" width="60%" height="700"></object>
											</div>
											<div id="{{$sample_name}}-Table-cnvkit" title="{{$sample_name}}-Table-cnvkit">
											</div>
										@endif
										
									</div>								
								</div>
							@endforeach
						@else
							@foreach ($cnvkit_samples as $sample_name => $case_id)
								<div id="{{$sample_name}}" title="{{$sample_name}}">
									<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:100%;padding:10px;overflow:visible;">
										<div title="Genome View - cnvkit">
											<object data="{{url("/getCNVPlot/$patient_id/$sample_name/$case_id/cnvkit")}}" width="100%" height="700"></object>
										</div>
										<div id="{{$sample_name}}-Table-cnvkit" title="{{$sample_name}}-Table-cnvkit">
										</div>
									</div>
								</div>
							@endforeach
						@endif
						@if (count($cnv_samples) > 0 && $merged)
							<div id="CNV-Merged" title="CNV-Merged">
								<div class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:100%;padding:10px;overflow:visible;">
									<div id="CNV-Merged-Table" title="CNV-Merged-Table">										
									</div>
									
								</div>								
							</div>
						@endif
					</div>	
				</div>
			  @endif
			@endif
			@if ($project->showFeature("GSEA") && $has_expression)
			<div id="GSEA" title="GSEA" style="width:100%;" class="list-group-item active text-center " >
					<div id="{{$patient_id}}" title="{{$patient_id}}">
						<object data="{{url("/viewGSEA/$project_id/$patient_id/$case->case_id/".rand())}}" type="application/pdf" width="100%" height="100%"></object>
					</div>
			</div>
			@endif
			@if (count($sig_samples) > 0)	
				<div id="signature" title="Signature" style="width:98%;padding:0px;">
					<div id="tabCNV" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
						@foreach ($sig_samples as $sample_name => $case_id)
							<div id="{{$sample_name}}" title="{{$sample_name}}">
								<object data="{{url("/getSignaturePlot/$patient_id/$sample_name/$case_id")}}" type="application/pdf" width="100%" height="700"></object>								
							</div>
						@endforeach
						<div id="mut_sig_def" title="Definition">
							<H5>reference: &nbsp;&#183;<a target=_blank href='http://cancer.sanger.ac.uk/cosmic/signatures'>COSMIC</a>&nbsp;&#183;<a target=_blank href='https://www.ncbi.nlm.nih.gov/pubmed/23945592'>Pubmed</a></h5>
							<object data="{{url("/images/Signature.pdf")}}" type="application/pdf" width="100%" height="700"></object>								
						</div>
					</div>	
				</div>
			@endif
			@if (count($hla_samples) > 0 && !$merged)	
				<div id="HLA" title="HLA" style="width:98%;padding:0px;">				
					<div id="tabHLA" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:10px;overflow:visible;">
						@foreach ($hla_samples as $sample_name => $case_id)
							<div id="{{$sample_name}}" title="{{$sample_name}}" style="padding:10px;">
								<H4>
									<span class="btn-group" id="HLAHighConf" data-toggle="buttons">
			  							<label class="mut btn btn-default">
											<input class="ck" id="cktblHLA{{$sample_name}}" type="checkbox" autocomplete="off" onchange="doFilter('tblHLA{{$sample_name}}')">High conf
										</label>
									</span>
									<a target=_blank class="btn btn-info" href='{{url("/downloadHLAData/$patient_id/$case_id/$sample_name")}}'><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Download</a>
									<span style="font-family: monospace; font-size: 20;float:right;">
										Count:&nbsp;<span id="lblCountDisplaytblHLA{{$sample_name}}" style="text-align:left;color:red;" text=""></span>/
													<span id="lblCountTotaltblHLA{{$sample_name}}" style="text-align:left;" text="">
									</span>
								</H4>
								<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblHLA{{$sample_name}}" style='width:100%'>
								</table>
							</div>
						@endforeach						
					</div>	
				</div>
			@endif
			@if (count($antigen_samples) > 0 && !$merged)	
				<div id="Neoantigen" title="Neoantigen" style="width:98%;padding:0px;">				
					<div id="tabAntigen" class="easyui-tabs" data-options="tabPosition:top,fit:true,plain:true,pill:true" style="width:98%;padding:0px;overflow:visible;">
						@foreach ($antigen_samples as $sample_name => $case_id)
							<div id="{{$sample_name}}-Neoantigen" title="{{$sample_name}}-Neoantigen">
							</div>							
						@endforeach						
					</div>	
				</div>
			@endif
			@if ($show_circos)
				<div id="Circos" title="Circos" style="width:98%;">
				</div>
			@endif
			@if (!$merged && count($sample_types) > 0)
			  @if ($project->showFeature("download") && $has_vcf)
				<div id="Download" title="Download" style="width:98%;">
					<div style="padding:30px">
						<H4>VCF file:&nbsp;<button id="btnDownloadVCF" class="btn btn-info"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;VCF</button></H4>
					</div>
				</div>
			  @endif
			@endif	
			
			@if ($has_qc)
			@if ($project->showFeature("QC"))
			<div id="QC" title="QC" style="width:98%;">
			</div>	
			@endif
			@endif	
    </div>
</div>

@foreach ($sample_types as $type => $samples)
<div id="{{$type}}_mutation_help">
    <img class="mytooltip" title="{{Lang::get("messages.$type"."_message")}}" width=12 height=12 src={{url("images/help.png")}}></img>
</div>
@endforeach

@stop
