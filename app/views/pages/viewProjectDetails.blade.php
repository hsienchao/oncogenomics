@extends('layouts.default')
@section('content')
{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery/jquery.dataTables.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/light-bootstrap-dashboard.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-3d.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highcharts-regression/highcharts-regression.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}

{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}

<style>
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
</style>

<script type="text/javascript">
	var tbl;	
	var pca_data;
	var pca_plot = null;
	var pcaPLoadingPlot;
	var pcaNLoadingPlot;
	var col_html = '';
	var columns = [];
	var tab_urls = [];
	var loaded_list = [];
	var tbl;
	var has_survival = {{$has_survival}};	
	var survival_meta_list = {{$survival_meta_list}};
	var survival_diags = {{$survival_diags}};
	var attr_values = {};

	$(document).ready(function() {
		$("#loadingSummary").css("display","block");
		var url='{{url("/getProjectSummary/".$project->id)}}';
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {			
				summary_json = parseJSON(data);
				$("#loadingSummary").css("display","none");
				var table = document.getElementById("project_summary");
				var row = null;
				var num_cols = 3;
				var width = $('#tabDetails').width() * 0.95;

				var offset = 0;
				if (summary_json.fusion.length > 0) {
					row = table.insertRow(0);
					var cell = row.insertCell(0);
					var div_id = 'tier1fusion';
					var chart_html = '<div class="card" style="height: 350px; width:' + parseInt(width/num_cols) + 'px; margin: 5 auto" id="' + div_id + '"></div>';
					cell.innerHTML = chart_html;
					//console.log(JSON.stringify(summary_json.fusion));
					var fusion_data = [];
					summary_json.fusion.forEach(function (d) {
						fusion_data.push([d.genes, d.count]);
					});
					//console.log(JSON.stringify(fusion_data));
					showColumnPlot(div_id, 'Fusion - Tier 1.1', 'Genes', fusion_data, '.0f', function(p) {
							var cols = [{title:'Patient ID'}];
							summary_json.patient_meta.attr_list.forEach(function(attr){
								cols.push({title:attr});
							});
							var data = [];
							summary_json.fusion.forEach(function(d){
								if (d.genes == p.name) {									
									for (var patient in summary_json.patient_meta.data) {
										if (d.patient_list.indexOf(patient) >= 0) {
											var patient_url = '<a target=_blank href="{{url("/viewPatient/$project->id")}}' + '/' + patient + '">' + patient + '</a>';
											var row_data = [patient_url];
											summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
												row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
											});
											data.push(row_data);
										}
									}
								}								
							});
							$('#selected_patients').w2popup();
							showTable('tblSelPatients', {cols: cols, data: data});
							$('#w2ui-popup #lblTotalPatients').text('Fusion : ' + p.name + ' (' + data.length + ' patients)');									
					});												
					offset++;
				}

				var cell_idx = offset;
				summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx){
					var width_scale = (attr == "Diagnosis") ? 1 : 0;
					var values = [];
					for (var patient in summary_json.patient_meta.data)
						values.push(summary_json.patient_meta.data[patient][attr_idx]);
					var data = [];
					idx = attr_idx + offset;
					if (cell_idx % num_cols == 0)
						row = table.insertRow(cell_idx/num_cols);
					if (row != null) {
						//var cell = row.insertCell(cell_idx % num_cols);
						var cell = row.insertCell(-1);
						var chart_width = parseInt(width/num_cols);
						cell_idx++;
						if (width_scale == 1) {
							cell.colSpan = 2;
							cell_idx++;
							chart_width = parseInt(width/num_cols)*(width_scale+1) + 25;
						}

						var div_id = attr.replace(/[\s\(\)]/g, '');						
						var chart_html = '<div class="card" style="height: 350px; width:' + chart_width + 'px; margin: 5 auto" id="' + div_id + '"></div>';
						cell.innerHTML = chart_html;
						//console.log(attr);
						var plotHist = isNumberArray(values);
						var minHist = 7;
						if (plotHist) {
							//console.log(JSON.stringify(values));
							values = getNumberArray(values);							
							if (unique_array(values).length > minHist) {
								//console.log(JSON.stringify(values));
								var bin_data = getHistData(values);
								//console.log(JSON.stringify(bin_data));
								//how about negative values?
								showHistChart(div_id, attr, bin_data, function(p) {
										var cols = [{title:'Patient ID'}];
										summary_json.patient_meta.attr_list.forEach(function(attr){
											cols.push({title:attr});
										});
										var bin_bottom = 0;
										var bin_top = 0;
										bin_data.forEach(function(bin) {
											if (p.x > bin[2] && p.x <= bin[3]) {
												bin_bottom = bin[2];
												bin_top = bin[3];
											}
										});
										var data = [];
										for (var patient in summary_json.patient_meta.data) {										
											var patient_value = parseFloat(summary_json.patient_meta.data[patient][attr_idx]);

											var bin_bottom_cutoff  = (bin_bottom == 0)? bin_bottom - 1 : bin_bottom;
											if ( patient_value > bin_bottom_cutoff && patient_value <= bin_top) {
												var patient_url = '<a target=_blank href="{{url("/viewPatient/$project->id")}}' + '/' + patient + '">' + patient + '</a>';
												var row_data = [patient_url];
												summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
													row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
												});
												data.push(row_data);
												//console.log(patient);
											}
										}
										$('#selected_patients').w2popup();
										showTable('tblSelPatients', {cols: cols, data: data});
										bin_bottom = Math.round(bin_bottom*100) / 100;
										bin_top = Math.round(bin_top*100) / 100;
										$('#w2ui-popup #lblTotalPatients').text(attr + ' : ' + bin_bottom + ' ~ ' + bin_top + ' (' + data.length + ' patients)');
									});
							} else {
								plotHist = false;
							}
						}
						if (!plotHist) {		
							//console.log(JSON.stringify(values));					
							data = getPieChartData(values);
							if (has_survival) {
								var attr_value = [];
								for (var i in data){
									var a = data[i];
									attr_value.push(a.name);
								};
								attr_values[attr] = attr_value;
								if (survival_meta_list == null) {
									$('#selSurvFilterType1').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvFilterType2').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvGroupBy1').append('<option value="' + attr + '">' + attr + ' </option>');
									$('#selSurvGroupBy2').append('<option value="' + attr + '">' + attr + ' </option>');
								}
							}
							showPieChart(div_id, attr, data, function(p) {
								var cols = [{title:'Patient ID'}];
								summary_json.patient_meta.attr_list.forEach(function(attr){
									cols.push({title:attr});
								});
								var data = [];
								for (var patient in summary_json.patient_meta.data) {
									console.log(summary_json.patient_meta.data[patient][attr_idx]);
									//console.log(p.name);
									if (summary_json.patient_meta.data[patient][attr_idx] == p.name) {
										var patient_url = '<a target=_blank href="{{url("/viewPatient/$project->id")}}' + '/' + patient + '">' + patient + '</a>';
										var row_data = [patient_url];
										summary_json.patient_meta.attr_list.forEach(function(attr, attr_idx) {
											row_data.push(summary_json.patient_meta.data[patient][attr_idx]);
										});
										data.push(row_data);
										//console.log(patient);
									}
								}
								//console.log(JSON.stringify(cols));
								//console.log(JSON.stringify(data));
								//$('#' + div_id).w2overlay('HAHA');								
								$('#selected_patients').w2popup();
								showTable('tblSelPatients', {cols: cols, data: data});
								$('#w2ui-popup #lblTotalPatients').text(attr + ' : ' + p.name + ' (' + data.length + ' patients)');
								//alert(p.name);
							});
						}						
					}
				})
				if (has_survival) {
					//$('#selSurvGroupBy').append('<option value="mutation">Tier1 Mutation Genes</option>');
					if (survival_meta_list != null) {
						survival_meta_list.forEach(function(attr) {
							$('#selSurvFilterType1').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvFilterType2').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvGroupBy1').append('<option value="' + attr + '">' + attr + ' </option>');
							$('#selSurvGroupBy2').append('<option value="' + attr + '">' + attr + ' </option>');
						})
					}
					getSurvivalData();
				}
			}

		});
		
		$('.box').fancybox({
    		width  : '90%',
    		height : '90%',
    		type   :'iframe',
    		autoSize: false
		});

		@if ($project->getExpressionCount() > 0)
			showPCA();
		@endif
		
		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        		$('#btnGene').trigger("click");
    		}
		});	

		$('#btnPlotSurvival').on('click', function() {
			getSurvivalData();
		});


		$('#btnGene').on('click', function() {
			if ($('#gene_id').val().trim() != "")
				window.open("{{url('/viewProjectGeneDetail')}}" + "/{{$project->id}}/" + $('#gene_id').val() + '/0');
        });

        $('.pca-control').on('change', function() {
			showPCA();
        });

        $('#selSurvFilterType1').on('change', function() {
			$('#selSurvFilterValues1').empty();
			var value = $('#selSurvFilterType1').val();
			if (value == "any") {
				$('#selSurvFilterValues1').css("display","none");
				$('#selSurvFilterType2').val("any");
				$('#selSurvFilterType2').css("display","none");
				$('#selSurvFilterValues2').css("display","none");
				$('#lblFilter2').css("display","none");
				return;
			}
			else {
				$('#selSurvFilterValues1').css("display","inline");
				$('#selSurvFilterType2').css("display","inline");
				//$('#selSurvFilterValues2').css("display","inline");
				$('#lblFilter2').css("display","inline");
			}
			attr_value = attr_values[value];
			if (value.toLowerCase() == "diagnosis")
				survival_diags.sort().forEach(function(attr) {
					$('#selSurvFilterValues1').append('<option value="' + attr + '">' + attr + '</option>');
				});
			else
				attr_value.sort().forEach(function(attr){
					$('#selSurvFilterValues1').append('<option value="' + attr + '">' + attr + '</option>');
				});

        });

        $('#selSurvFilterType2').on('change', function() {
			$('#selSurvFilterValues2').empty();
			var value = $('#selSurvFilterType2').val();
			if (value == "any") {
				$('#selSurvFilterValues2').css("display","none");				
				return;
			}
			else {
				$('#selSurvFilterValues2').css("display","inline");				
			}
			attr_value = attr_values[value];
			if (value.toLowerCase() == "diagnosis")
				survival_diags.sort().forEach(function(attr) {
					$('#selSurvFilterValues2').append('<option value="' + attr + '">' + attr + '</option>');
				});
			else
				attr_value.sort().forEach(function(attr){
					$('#selSurvFilterValues2').append('<option value="' + attr + '">' + attr + '</option>');
				});

        });
		
		$('#selSurvGroupBy1').on('change', function() {			
			/*
			if ($('#selSurvGroupBy1').val() == "mutation")
				$('#mutationGenes').css("display","inline");
			else 
				$('#mutationGenes').css("display","none");
			*/
		});		

		$('#radioMeta').click(function () {
        	setMetaVisible($(this).is(':checked'));
    	});

    	$('#radioMut').click(function () {
        	setMetaVisible(!$(this).is(':checked'));
    	});
		
		$('#btnDownloadMatrix').on('click', function() {
			var url = '{{url('/getExpMatrixFile')}}' + '/' + '{{$project->id}}' + '/' + $('#selDownloadTargetType').val() + '/' + $('#selDownloadDataType').val();
			console.log(url);
			window.location.replace(url);	
		});

		$('.easyui-tabs').tabs({
			onSelect:function(title) {				
				if (title == "Mutations" || title == "Expression")
					tab = $('#tab' + title).tabs('getSelected');
				else
					tab = $(this).tabs('getSelected');				
				var id = tab.panel('options').id;				
				showFrameHtml(id);	
		   }
		});		
		

		patient_url = '{{url('/viewPatients/'.$project->id.'/any/0/project_details')}}'
		//alert(url);
		//addTab('Patient data', url);
		tab_urls['Patients'] = patient_url;
		@if ($project->getSampleSummary("RNAseq") > 0)
			//addTab('Expression', '{{url('/viewExpression/'.$project->id)}}' + '/null/null/all/null');
		@endif
		
		@if ($project->getSampleSummary("RNAseq") > 0)
			//addTab('Fusion genes', '{{url("/viewFusionProjectDetail/$project->id")}}');
		@endif
		@foreach ( $project->getVarCount() as $type => $cnt)
			@if ($cnt > 0)
				url = '{{url("/viewVarProjectDetail/$project->id/$type")}}';
				tab_urls['{{Lang::get("messages.$type")}}'] = url;
				//addTab('{{$type}} mutation', url);
			@endif
		@endforeach
		@if ($project->hasBurden())
			tab_urls['Mutation_Burden'] = '{{url("/viewMutationBurden/$project->id/null/null")}}';;
		@endif
		tab_urls['fusion_summary'] = '{{url("/viewFusionProjectDetail/$project->id")}}';
		tab_urls['Heatmap'] = '{{url('/viewExpression/'.$project->id)}}';
		tab_urls['QC'] = '{{url('/viewProjectQC/'.$project->id)}}';
		tab_urls['GSEA'] = '{{url("/viewGSEA/$project->id/any/any/".rand())}}';		

		//addTab('GSEA', '{{url('/viewGSEA/'.$project->id)}}');	
		$('#tabDetails').tabs('select', 'Summary');
		$('.mytooltip').tooltipster();	
				
	});

	function setMetaVisible(visible=true) {
			var meta_display = (visible)? "inline" : "none";
			var mutation_display = (visible)? "none" : "inline";
			$('#selSurvGroupBy1').css("display", meta_display);
        	$('#selSurvGroupBy2').css("display", meta_display);
        	$('#mutationGenes').css("display", mutation_display);

		}
	function showFrameHtml(id) {
		if (loaded_list.indexOf(id) == -1) {
			var url = tab_urls[id];
			if (url != undefined) {
				var html = '<iframe scrolling="auto" frameborder="0"  src="' + url + '" style="width:100%;height:85%;overflow:auto;border-width:0px"></iframe>';
				$('#' + id).html(html);
				console.log('#' + id);
				console.log(html);
				loaded_list.push(id);
			}
		}
	}

	function getSurvivalData() {
		var filter_attr_name1 = $('#selSurvFilterType1').val();
		var filter_attr_value1 = $('#selSurvFilterValues1').val();
		var filter_attr_name2 = $('#selSurvFilterType2').val();
		var filter_attr_value2 = $('#selSurvFilterValues2').val();
		var group_by_attr_name1 = $('#selSurvGroupBy1').val();
		var group_by_attr_name2 = $('#selSurvGroupBy2').val();
		if (group_by_attr_name1 == group_by_attr_name2)
			group_by_attr_name2 = "no_used";
		if (filter_attr_name1 == "any")
			filter_attr_value1 = "any";
		if (filter_attr_name2 == "any")
			filter_attr_value2 = "any";
		var mutation_values = "null";
		if ($('#radioMut').is(':checked')) {
			var tier = $('#selTier').val();
			var tier_type = $('#selTierType').val();
			var gene1 = $('#selGene1').val();
			var gene2 = $('#selGene2').val();
			var relation = $('#selMutationRelation').val();
			/*
			if (gene1.trim() == "") {
				alert("Please input gene name!");
				return;
			}
			*/
			group_by_attr_name1 = "mutation";
			group_by_attr_name2 = "not_used";
			mutation_values = tier + ':' + tier_type + ':' + gene1 + ':' + relation + ':' + gene2;
		}
		$("#loadingAllSurvival").css("display","block");
		$("#survival_status").css("display","none");
		$("#survival_panel").css("visibility","hidden");
		var url = '{{url("/getSurvivalData/$project->id")}}' + '/' + encodeURIComponent(encodeURIComponent(filter_attr_name1)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_value1)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_name2)) + '/' + encodeURIComponent(encodeURIComponent(filter_attr_value2)) + '/' + encodeURIComponent(encodeURIComponent(group_by_attr_name1)) + '/' + encodeURIComponent(encodeURIComponent(group_by_attr_name2)) + '/' + mutation_values;
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {	
				$("#loadingAllSurvival").css("display","none");
				$("#survival_panel").css("visibility","visible");
				survival_data = parseJSON(data);
				if (data == "no data" || survival_data.length == 0) {
					$("#message_row").css("display","block");
					$("#plot_row").css("display","none");
					return;
				} else {					
					$("#message_row").css("display","none");
					$("#plot_row").css("display","block");
				}
				if (survival_data.hasOwnProperty('overall')) {
					$("#overall_survival_plot").css("display","block");
					showSurvivalPlot("overall_survival_plot", "Overall Survival" , survival_data.overall);
				}
				else
					$("#overall_survival_plot").css("display","none");
				if (survival_data.hasOwnProperty('event_free')) {
					$("#event_free_survival_plot").css("display","block");
					showSurvivalPlot("event_free_survival_plot", "Event Free Survival" , survival_data.event_free);
				}
				else
					$("#event_free_survival_plot").css("display","none");
				//showSurvivalCutoffPlot(user_plot, "User Defined Survival", "Exp cutoff: " + survival_data.user_data.cutoff + ", P-value :" + selected_pvalue, survival_data.user_data.high_num, survival_data.user_data.low_num, survival_data.user_data.data);
			}
		});
	}

	Highcharts.Renderer.prototype.symbols.cross = function (x, y, w, h) {
		return [
        'M', x + w/2, y, // move to position
        'L', x + w/2, y + h, // line to position
        'M', x, y + h/2, // move to position
        'L', x + w, y + h/2, // line to position
        'z']; // close the shape, but there's nothing to close!!
	}

	function showSurvivalPlot(div, title, surv_data) {
		//console.log(JSON.stringify(data));
		//var sample_num = {"Low" : low_num, "High" : high_num};		
		var patient_count = surv_data.patient_count;
		var plot_data = surv_data.plot_data;
		var data = surv_data.data
		var subtitle = "P-value: " + surv_data.pvalue;
		data.forEach(function(d){
			var s = 5;
			var cencored = (d[3] == 0);
			if (plot_data[d[2]] == null) {
				console.log(d[2]);
				return;
			}
			plot_data[d[2]].push({name: d[4][0][0], cencored: cencored, x:parseFloat(d[0]), y:parseFloat(d[1]), 
					marker: {
                		radius: s, 
                		lineWidth:1,                		
                		states: { hover: { radius: s+2}},
                		enabled : cencored,
                		symbol : 'cross',                		
                	},                	
            });
		});		
		var series = [];
		var series_size = Object.keys(plot_data).length;
		for (var cat in plot_data) {
			console.log("cat " + cat);
			series.push(
				{
					data: plot_data[cat], 
					step: 'left', 					
			 		name: cat + '(' + patient_count[cat] + ')',
			 		marker : {lineColor: null},
			 		cursor: 'pointer',
			        point: {
		               events: {
			                    click: function () {
			                    	if (this.name == undefined)
			                    		return;
			                    	var url = '{{url("/viewPatient/")}}' + '/' +  '{{$project->id}}' + '/' + this.name;
									window.open(url, '_blank');			                    	
								}
							}                    
					},
			 	});
			if (series_size <= 2 && cat == "Y") series[series.length-1].color = "blue";
			if (series_size <= 2 && cat == "NoValue") series[series.length-1].color = "black";
		}		
		
		Highcharts.chart(div, {
			credits: false,
		    title: {
		        text: title
		    },
		    subtitle: {
		    	text: subtitle
		    },		    
            tooltip: {
            	crosshairs: [true, true],
		        formatter: function(chart) {
		            var p = this.point;
		            var status = (p.cencored)? "Alive" : "Dead";
		            if (p.y == 1) return;
		            return '<b>Patient ID: </b>' + p.name + '<br><b>Survival Rate: </b>' + p.y + ' <br><b>Days: </b>' + p.x  + ' <br><b>Status: </b>' + status;
		        }
		    },
		    yAxis: {
		    	min: 0,
        		max: 1
    		},
            series: series
		});
	}

	function addTab(title, url){
		console.log(url);
		var type='add';		
		var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;overflow:none"></iframe>';
			$('#tabDetails').tabs(type, {
					title:title,
					content:content,
					closable:false,
			});
	}

	function showPCA() {
		$("#loadingPCA").css("display","block");
		$("#no_pca_data").css("display","none");
		var url = '{{url("/getPCAData/$project->id")}}' + '/' + $('#selTargetType').val() + '/' + $('#selValueType').val();
		console.log(url);
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
					pca_data = JSON.parse(data);					
					$("#loadingPCA").css("display","none");					
					$("#pca_panel").css("display","block");
					if (pca_data.status == "no data") {						
						$("#no_pca_data").css("display","block");
						return;	
					}
					
					var width = $('#tabDetails').width() * 0.95;
					var pca_height = (width * 0.6 - 100 > 750)? 750: width * 0.7;
					$("#pca_plot").css("width",width * 0.6 + 'px');		
					//$("#pca_plot").css("height", + pca_height + 'px');
					$("#pca_plot").css("height", + width * 0.6 + 'px');
					console.log(JSON.stringify(pca_data.patients));

					if ($('#selSampleAttr').children('option').length == 0) {
						pca_data.sample_meta.attr_list.forEach(function(d, idx){
							$('#selSampleAttr').append($('<option>', { value : idx }).text(d));
						})

						pca_data.pca_variance.forEach(function(d, idx){
						var pc_idx = idx + 1;
							$('#selPC').append($('<option>', { value : pc_idx }).text('PC' + pc_idx));
						})
					}
					
					showPCAPlot();
					showLoading();
					showVariance();
					$('#selSampleAttr').on('change', function() {
						showPCAPlot();

					});

					$('#selPC').on('change', function() {
						showLoading();

					});

				}
		});
	}
	function showPCAPlot() {
		var sample_meta_idx = $('#selSampleAttr').val();
		var groups = {};
		for (var sample in pca_data.sample_meta.data) {
			if (groups[pca_data.sample_meta.data[sample][sample_meta_idx]] == null)
				groups[pca_data.sample_meta.data[sample][sample_meta_idx]] = [];
			if (pca_data.data[sample] != null) {
				var coord = getNumberArray(pca_data.data[sample]);
				if (coord.length == 3)
					groups[pca_data.sample_meta.data[sample][sample_meta_idx]].push({name: sample, x : coord[0], y:coord[1], z:coord[2]});
			}
		}
		var series = [];
		//console.log(JSON.stringify(pca_data.sample_meta.data));
		for (var sample_attr in groups) {
			if (groups[sample_attr].length > 0)
				series.push(
					{
						name: sample_attr, 
						colorByPoint: false, 
						data: groups[sample_attr],
						cursor: 'pointer',
						point: {
                    		events: {
	                    		click: function (p) {
	                    			var patient_id = pca_data.patients[p.point.name];
	                    			var url = '{{url("/viewPatient/")}}' + '/' +  '{{$project->id}}' + '/' + patient_id;
									window.open(url, '_blank');	                    			
								}
							}
						}					
					});
		}
		//console.log(JSON.stringify(series));
		show3DScatter('pca_plot', 'Principle component Analysis', 'PC1(' + pca_data.variance_prop[0] + '%)', 'PC2(' + pca_data.variance_prop[1] + '%)', 'PC3(' + pca_data.variance_prop[2] + '%)', series);
	}

	function showLoading() {
		var pc_idx = $('#selPC').val();
		var p_genes = pca_data.pca_loading.p["PC" + pc_idx][0];
		var p_loading = pca_data.pca_loading.p["PC" + pc_idx][1];
		var p_data = [];
		p_genes.forEach(function(d, idx){
			p_data.push([d, p_loading[idx]]);
		});
		showColumnPlot('pca_p_loading_plot', 'Loading - positive', 'Loading', p_data);
		var n_genes = pca_data.pca_loading.n["PC" + pc_idx][0];
		var n_loading = pca_data.pca_loading.n["PC" + pc_idx][1];
		var n_data = [];
		n_genes.forEach(function(d, idx){
			n_data.push([d, n_loading[idx]]);
		});		
		showColumnPlot('pca_n_loading_plot', 'Loading - negative', 'Loading', n_data);
	}

	function showVariance() {
		var x_labels = [];
		for (var i=1; i<=pca_data.pca_variance.length; i++)
			x_labels.push('PC' + i);
		showLinePlot('pca_var_plot', 'Variance', x_labels, pca_data.pca_variance);
	}

	function showTable(tbl_id, data) {
		if (tbl != null) {
			tbl.destroy();
			$('#w2ui-popup #' + tbl_id).empty();
		}

		tbl = $('#w2ui-popup #' + tbl_id).DataTable( 
				{				
					"paging":   true,
					"ordering": true,
					"info":     true,
					"dom": 'lfrtip',
					"data": data.data,
					"columns": data.cols,
					"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
					"pageLength":  15,
					"pagingType":  "simple_numbers",									
				} );		
	}



</script>

<div id="selected_patients" style="display: none; width: 80%; height: 80%; overflow: auto; background-color=white;">	
	<div rel="body" style="text-align:left;padding: 20px;">
		<a href="javascript:w2popup.close();" class="boxclose"></a>
    	<H4><lable id="lblTotalPatients"></lable></H4><HR>    	
    	<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblSelPatients" style='width:100%'>
		</table>		
	</div>
</div>

<div id="out_container" class="easyui-panel" data-options="border:false" style="width:100%;padding:0px;border-width:0px">
	<font size=2>
		<div style="padding-top:5px;padding-left:15px">
			<ol class="breadcrumb" style="margin-bottom:0px;padding:4px 20px 0px 0px;background-color:#ffffff">
				<li class="breadcrumb-item active"><a href="{{url('/')}}">Home</a></li>
				<li class="breadcrumb-item active"><a href="{{url('/viewProjects/')}}">Projects</a></li>
				<li class="breadcrumb-item active"><a href="{{url('/viewProjectDetails/'.$project->id)}}">{{$project->name}}</a>
				</li>
				<span style="float:right;">
					<img width="20" height="20" src="{{url('images/search-icon.png')}}"></img> Gene: <input id='gene_id' type='text' value=''/>&nbsp;&nbsp;<button id='btnGene' class="btn btn-info">GO</button>
				</span>
			</ol>
		</div>
	</font>
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:true" style="width:100%;padding:10px;overflow:auto;">
	<!--div id="tabMain" class="easyui-tabs" data-options="tabPosition:'top',plain:true, pill:true,border:false" style="width:95%;padding:10px;overflow:auto;border-width:0px"-->		
		<div title="Summary" style="width:98%;padding:5px;background-color:#f2f2f2">
			<div id='loadingSummary' class='loading_img'>
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>			
			<table id="project_summary" style="width:100%;border:1px;"></table>
		</div>	
		<div id="Patients" title="Patients" style="width:100%;border:1px">
		</div>
		@if ($project->hasMutation())
		<div id="Mutations" title="Mutations" style="height:90%;width:100%;padding:10px;">
			<div id="tabMutations" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:true" style="width:98%;padding:0px;overflow:visible;border-width:0px">
				@foreach ( $project->getVarCount() as $type => $cnt)
					@if ($project->showFeature($type))
						@if ($cnt > 0 && $type != "hotspot")
							<div id="{{Lang::get("messages.$type")}}" title="{{$type}}-{{Lang::get("messages.$type")}}" data-options="tools:'#{{$type}}_mutation_help'" style="width:98%;padding:0px;">
							</div>
						@endif
					@endif
				@endforeach
				@if ($project->showFeature('mutation_burden'))
					@if ($project->hasBurden())				
					<div id="Mutation_Burden" title="Mutation_Burden" style="width:98%;height:95%;padding:0px;">								
					</div>
					@endif
				@endif
			</div>
		</div>
		@endif
	@if ($project->showFeature('fusion'))	
	@if ($project->getSampleSummary("RNAseq") > 0)
		<div id="fusion_summary" title="Gene Fusion" style="padding:0px;">			
		</div>
	@endif
	@endif
	@if ($project->showFeature('expression'))
	  @if ($project->getExpressionCount() > 0)
		<div id="Expression" title="Expression" style="width:100%;padding:5;">
			<div id="tabExpression" class="easyui-tabs" data-options="tabPosition:'top',plain:true,pill:true,border:false,headerWidth:100" style="width:100%;padding:0px;overflow:visible;border-width:0px">
				<div title="PCA" style="width:100%;padding:5px;background-color:#f2f2f2">
					<div id='loadingPCA' class='loading_img'>
						<img src='{{url('/images/ajax-loader.gif')}}'></img>						
					</div>
					<div class="container-fluid" id="pca_panel" style="display:none;">
						<div class="row">
							<div class="col-md-8">
								<div class="card" style="margin: 5 auto">
									<div class="row">
										<div class="col-md-3">
											<label for="selSampleAttr">Group by:</label><select id="selSampleAttr" class="form-control"></select>
										</div>										
										<div class="col-md-3">
											<label for="selTargetType">Annotation:</label>
											<select id="selTargetType" class="form-control pca-control">
												@foreach ($project->getTargetTypes() as $target_type)
													<option value="{{$target_type}}">{{strtoupper($target_type)}}</option>
												@endforeach
											</select>
										</div>
										<!--div class="col-md-2">
											<label for="selNorm">Normalized by:</label>
											<select id="selNorm" class="form-control pca-control">
												<option value="tmm-rpkm">TMM-FPKM</option>
												<option value="tpm">TPM</option>
											</select>
										</div>
										<div class="col-md-2">
											<label for="selLibType">Library type:</label>
											<select id="selLibType" class="form-control pca-control">
												<option value="all">All</option>
												<option value="polya">PolyA</option>
												<option value="nonpolya">Non-PolyA</option>
											</select>
										</div-->
										<div class="col-md-2">
											<label for="selValueType">Value type:</label>
											<select id="selValueType" class="form-control pca-control">
												<option value="zscore">Zscore</option>
												<option value="log2">Log2</option>												
											</select>
										</div>
									</div>
									<div id="no_pca_data" style="height: 720px; margin: 0 auto display:none"><H4>No data!</H4></div>
									<div id="pca_plot" style="height: 720px; margin: 0 auto" ></div>
								</div>
							</div>
							<div class="col-md-4">
								<div class="row card">
									<div class="col-md-6">
										<label for="selPC">Component:</label>
										<select id="selPC" class="form-control"></select>
									</div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_p_loading_plot"></div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_n_loading_plot"></div>
								</div>
								<div class="row">
									<div class="card" style="height: 240px; margin: 5 auto; padding: 2px 2px 2px 2px;" id="pca_var_plot"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div id="Heatmap" title="Heatmap" style="width:100%;padding:5px;">
				</div>
				<div title="Download" style="width:100%;padding:5px;">
					<div class="container-fluid" id="pca_panel">
						<div class="row">
							<div class="col-md-2">
								<label for="selDownloadTargetType">Annotation:</label>
								<select id="selDownloadTargetType" class="form-control pca-control">
								@foreach ($project->getTargetTypes() as $target_type)
									<option value="{{$target_type}}">{{strtoupper($target_type)}}</option>
								@endforeach
								</select>
							</div>
							<div class="col-md-3">
								<label for="selDownalodDataType">Data Type:</label>
								<select id="selDownloadDataType" class="form-control pca-control">
									<option value="all.count">Raw count for all genes</option>
									<option value="coding.count">Raw count for coding genes</option>
									<option value="all.tpm">TPM for all genes</option>
									<option value="coding.tpm">TPM for coding genes</option>
									<option value="coding.tmm-rpkm">TMM-RPKM for coding genes</option>								
								</select>
							</div>
							<div class="col-md-3">
								<label for="btnDownloadMatrix">Download:</label><br><button id="btnDownloadMatrix" class="btn btn-info"><img width=15 height=15 src={{url("images/download.svg")}}></img>&nbsp;Expression file</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>		
		@if ($project->showFeature("GSEA"))
		<div id="GSEA" title="GSEA" style="width:100%;padding:10px;">
			<object data="{{url("/viewGSEA/$project->id/any/any/".rand())}}" type="application/pdf" width="100%" height="100%"></object>
		</div>
		@endif
	  @endif
	@endif
	@if ($has_survival)
		<div title="Survival" style="background:rgba(203, 203, 210, 0.15);">
			<div id='loadingAllSurvival' style="display: none">
			    <img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div class="container-fluid" id="survival_panel" style="visibility: false">
						<div class="row">
							<br>
							<div class="col-md-12">
								<div class="card" style="display:inline;padding:18px 4px 18px 4px;margin:6px;">
									<H5  style="display:inline;font-size: 12px">&nbsp;&nbsp;Filter:</H5>
									<select id="selSurvFilterType1" class="form-control surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
										<option value="any">All Data</option>
									</select>
									<select id="selSurvFilterValues1" class="form-control surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
									</select>
									<H5 id="lblFilter2" style="display:none;font-size: 12px">&nbsp;And&nbsp;</H5>
									<select id="selSurvFilterType2" class="form-control surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
										<option value="any">All Data</option>
									</select>
									<select id="selSurvFilterValues2" class="form-control surv" style="display:none;width:130px;font-size: 12px;padding:2px 2px;">
									</select>
								</div>
								<div class="card" style="display:inline;padding:18px 4px 18px 4px;margin:8px;">
									<H5  style="display:inline;font-size: 12px">Group by:&nbsp;</H5>
									<input id="radioMeta" name="radioGroupBy" type="radio" checked><H5 style="display:inline;font-size: 12px;">&nbsp;Metadata&nbsp;</H5>
									<select id="selSurvGroupBy1" class="form-control surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
									</select>
									<H5  style="display:inline;font-size: 12px">And:&nbsp;</H5>
									<select id="selSurvGroupBy2" class="form-control surv" style="display:inline;width:130px;font-size: 12px;padding:2px 2px;">
										<option value="not_used" >Not used</option>
									</select>
									&nbsp;&nbsp;&nbsp;
									<input id="radioMut" name="radioGroupBy" type="radio" ><H5 style="display:inline;font-size: 12px;">&nbsp;Mutation Genes&nbsp;</H5>	<span id="mutationGenes" style="display:none;">									
									<!--input id="gene1" class="fomr-control" style="width:80;font-size: medium;"></input-->
									<select id="selTier" class="form-control surv" style="display:inline;font-size: 12px;width:90px;padding:2px 2px;">
										<option value="tier1" >Tier 1</option>
										<option value="other_tier" >2-4 Tiers</option>
										<!--option value="all_tier" >All Tiers</option-->
									</select>									
									<select id="selTierType" class="form-control surv" style="display:inline;font-size: 12px;width:140px;padding:2px 2px;">
										<option value="somatic" >Somatic Tiering</option>
										<option value="germline" >Germline Tiering</option>
										<option value="germline_somatic" >Germline or Somatic Tiering</option>
									</select>
									<a href='{{url("data/".Config::get('onco.classification_germline_file'))}}' title="Germline tier definitions" class="fancybox mytooltip box"><img src={{url("images/help.png")}}></img></a>
									<a href='{{url("data/".Config::get('onco.classification_somatic_file'))}}' title="Somatic tier definitions" class="fancybox mytooltip box"><img src={{url("images/help.png")}}></img></a>
									<H5  style="display:inline;font-size: 12px;">&nbsp;&nbsp;&nbsp;Gene1:</H5>
									<select id="selGene1" class="form-control surv" style="display:inline;font-size: 12px;width:100px;padding:2px 2px;">
										@foreach ($tier1_genes as $tier1_gene)
											<option value="{{$tier1_gene}}"" >{{$tier1_gene}}</option>
										@endforeach
									</select>
									<select id="selMutationRelation" class="form-control surv" style="display:inline;font-size: 11px;width:70px;padding:2px 2px;">
										<option value="and">And</option>
										<option value="or">Or</option>
										<option value="andNot">And Not</option>
									</select><H5  style="display:inline;font-size: 12px;">&nbsp;&nbsp;Gene2: </H5>
									<select id="selGene2" class="form-control surv" style="display:inline;width:100px;font-size: 12px;padding:2px 2px;">
										<option value="any">Any</option>
										@foreach ($tier1_genes as $tier1_gene)
											<option value="{{$tier1_gene}}">{{$tier1_gene}}</option>
										@endforeach
									</select>
									<!--input id="gene2" class="fomr-control" style="width:80;font-size: medium;"></input-->
									</span>
									&nbsp;&nbsp;
									<button id='btnPlotSurvival' class="btn btn-info">Submit</button>
								</div>
							</div>
						</div>
						<br>
						<div id="message_row" class="row" style="display:none">
							<div class="col-md-12">
								<H3>No data!</H3>
							</div>
						</div>						
						<div id="plot_row" class="row">
							<div class="col-md-6">
								<div class="card">
									<div id='overall_survival_plot' style="height:450;width=100%"></div>
								</div>								
							</div>
							<div class="col-md-6">
								<div class="card">							
									<div id='event_free_survival_plot' style="height:450;width=100%"></div>
								</div>
							</div>							
						</div>
					</div>
				</div>
		</div>
	@endif
	@if ($project->showFeature("qc"))
	@if ($project->hasMutation())
	<div id="QC" title="QC" style="width:100%;border:1px">
	</div>
	@endif
	@endif
</div>

@foreach ( $project->getVarCount() as $type => $cnt)
<div id="{{$type}}_mutation_help" style="display:none">
    <img class="mytooltip" title="{{Lang::get("messages.$type"."_message")}}" width=12 height=12 src={{url("images/help.png")}}></img>
</div>
@endforeach

@stop
