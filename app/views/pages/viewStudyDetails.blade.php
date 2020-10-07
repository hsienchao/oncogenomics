@extends('layouts.default')
@section('content')
{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/jquery/jquery.dataTables.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/canvasXpress/css/canvasXpress.css') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/canvasXpress/js/canvasXpress.js') }}


<script type="text/javascript">
	var tbl;
	
	var cols = {{json_encode($cols)}};
	var hide_cols = {{json_encode($col_hide)}};
	var pcaData;
	var pcaPlot;
	var pcaPLoadingPlot;
	var pcaNLoadingPlot;
	var col_html = '';
	var columns = [];
	$(document).ready(function() {
		$("#loadingSummary").css("display","block");
		$.ajax({ url: '{{url("/getStudySummaryJson/".$study->id)}}', async: true, dataType: 'text', success: function(data) {
				jsonData = JSON.parse(data);
				$("#loadingSummary").css("display","none");
				showPiePlot(jsonData.tissue, 'tissue_plot');
				showPiePlot(jsonData.tissue_cat, 'tissue_cat_plot');
				showPiePlot(jsonData.sex, 'sex_plot');
				showPiePlot(jsonData.mortality, 'mortality_plot');
				@if ($study->hasSurvivalSample())
				showPiePlot(jsonData.stage, 'stage_plot');
				showHistPlot(jsonData.age, 'age_plot');
				showPiePlot(jsonData.mycn, 'mycn_plot');
				showPiePlot(jsonData.risk, 'risk_plot');
				@endif

			}
		});


		$("#loadingMutation").css("display","block");
		$.ajax({ url: '{{url("/getMutationGenes/".$study->id)}}', async: true, dataType: 'text', success: function(data) {
				$("#loadingMutation").css("display","none");
				jsonData = JSON.parse(data);
				tbl = $('#tblMutations').DataTable( 
					{
						"data": jsonData.data,
						"columns": jsonData.cols,
						"ordering":    true,
						"order":[[1, "Desc"]],
						"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
						"pageLength":  15,
						"pagingType":  "simple_numbers",			
						"dom": 'B<"toolbar">lfrtip',
						"buttons": ['csv', 'excel']
					} 
				);
			}
		});
		
		@if ($study->status > 0)
			$("#loadingPCA").css("display","block");
			$.ajax({ url: '{{url("/getPCAPlatData/".$study->id)}}', async: true, dataType: 'text', success: function(data) {
					pcaData = JSON.parse(data);
					$("#loadingPCA").css("display","none");
					showPCAPlot();
				}
			});
		@endif
		
		$('#gene_id').keyup(function(e){
			if(e.keyCode == 13) {
        		$('#btnGene').trigger("click");
    		}
		});	

		$('#btnGene').on('click', function() {
			window.open("{{url('/viewGeneDetail')}}" + "/{{$study->id}}/" + $('#gene_id').val() + '/0/UCSC');
        });
		


		$('#selType').on('change', function() {
			pcaPlot.colorBy = ($("#selType").val());
			pcaPlot.draw();

		});

		$('#selPC').on('change', function() {
			//pcaPLoadingPlot.data = pcaData.pca_loading.p[)];
			var pc_number = "PC" + $("#selPC").val();
			pcaPLoadingPlot.data.y.data = pcaData.pca_loading.p[pc_number].y.data;
			pcaPLoadingPlot.data.y.smps = pcaData.pca_loading.p[pc_number].y.smps;
			pcaPLoadingPlot.draw();
			pcaNLoadingPlot.data.y.data = pcaData.pca_loading.n[pc_number].y.data;
			pcaNLoadingPlot.data.y.smps = pcaData.pca_loading.n[pc_number].y.smps;
			pcaNLoadingPlot.draw();

		});

		url = '{{url('/viewPatients/'.$study->id.'/all/0')}}'
		//alert(url);
		addTab('Patient data', url);
		addTab('Query', '{{url('/viewStudyQuery/'.$study->id)}}');
		@if ($study->getAnalysisCount() > 0)
			addTab('Analysis', '{{url('/viewAnalysis/'.$study->id.'/0')}}');
		@endif	

		addTab('GSEA', '{{url('/viewGSEA/'.$study->id)}}');	
		$('#tabDetails').tabs('select', 'Summary');
				
	});


	function addTab(title, url){
		var type='add';		
		var content = '<iframe scrolling="auto" frameborder="0"  src="'+url+'" style="width:100%;height:90%;overflow:hidden"></iframe>';
			$('#tabDetails').tabs(type, {
					title:title,
					content:content,
					closable:false,
			});
	}

	var showHistPlot = function(data, plot_id) {
		$('#' + plot_id).attr("width" , 350);
		$('#' + plot_id).attr("height", 350);
		histPlot = new CanvasXpress(plot_id, data, {
							"graphType": "Scatter2D",
           					"histogramBins": 10,
							"title": data.m.Name,
							"showLegend":false,
							"showShadow": true,
           						"xAxisTitle": "Age (days)",
           						"yAxisTitle": "Number of Subjects"							
						}
        				);
		histPlot.createHistogram();

	}

	var showPiePlot = function(data, plot_id) {
		$('#' + plot_id).attr("width" , 350);
		$('#' + plot_id).attr("height", 350);
		piePlot = new CanvasXpress(plot_id, data, {
							"graphType": "Pie",
							"pieSegmentLabels": "outside",
           					"pieSegmentPrecision": 1,
							"pieSegmentSeparation": 2,
							"showAnimation":true,
							"title": data.m.Name,
							"pieType": "separated"
						}
        				);

	}

	var showPCAPlot = function() {
		$('#pca_plot').attr("width" , 500);
		$('#pca_plot').attr("height", 500);
		$('#pca_var_plot').attr("width" , 820);
		$('#pca_var_plot').attr("height", 280);
		$('#pca_p_loading_plot').attr("width" , 400);
		$('#pca_p_loading_plot').attr("height", 300);
		$('#pca_n_loading_plot').attr("width" , 400);
		$('#pca_n_loading_plot').attr("height", 300);
		pcaPlot = new CanvasXpress("pca_plot", pcaData.pca_scatter, {
							"colorBy" : "Tissue type",
							"graphType": "Scatter3D",
							"legendPosition":"right"
							}
        					);
		var pcaVariancePlot = new CanvasXpress("pca_var_plot", pcaData.pca_variance, {
							"axisTitleFontStyle": "italic",
							"axisTitleFontSize": 12,
							"smpTitleFontSize": 12,
							"axisTickFontSize": 12,
							"smpLabelFontSize": 12,        
							"lineThickness":3,
							"autoScaleFont": false,
							"graphOrientation": "vertical",
							"graphType": "Line",
							"smpTitle": "Principle component",
							"smpTitleFontStyle": "bold",
							"smpTitleScaleFontFactor": 2,
							"showLegend" : false,
							"titleHeight": 40,
							"xAxis2Show": true, 
							"title": "Variance",
							"xAxisTitle": "Variance",
							}
        					);
		pca_loading = JSON.parse(JSON.stringify(pcaData.pca_loading));
		pcaPLoadingPlot = new CanvasXpress("pca_p_loading_plot", pca_loading.p["PC1"], {
							"axisTitleFontStyle": "italic",
							"axisTitleFontSize": 12,
							"smpTitleFontSize": 12,
							"axisTickFontSize": 12,
							"smpLabelFontSize": 12,        
							"autoScaleFont": false,
							"graphOrientation": "vertical",
							"showLegend" : false,
							"graphType": "Bar",
							"smpTitle": "Gene",
							"smpTitleFontStyle": "bold",
							"smpTitleScaleFontFactor": 2,
							"titleHeight": 40,
							"xAxis2Show": false, 
							"title": "Loading - Positive",
							"xAxisTitle": "Loading",
							}
        					);
		pcaNLoadingPlot = new CanvasXpress("pca_n_loading_plot", pca_loading.n["PC1"], {
							"axisTitleFontStyle": "italic",
							"axisTitleFontSize": 12,
							"smpTitleFontSize": 12,
							"axisTickFontSize": 12,
							"smpLabelFontSize": 12,        
							"autoScaleFont": false,
							"graphOrientation": "vertical",
							"showLegend" : false,
							"graphType": "Bar",
							"smpTitle": "Gene",
							"smpTitleFontStyle": "bold",
							"smpTitleScaleFontFactor": 2,
							"titleHeight": 40,
							"xAxis2Show": false, 
							"title": "Loading - Negative",
							"xAxisTitle": "Loading",
							}
        					);

	}

</script>

<div id="out_container" class="easyui-panel" style="width:100%;height:100%;padding:10px;overflow:none;">
	<table width=95%>
		<tr>
			<td style='padding-left:25px'>
				<a href="{{url('/')}}">Home</a>-><a href="{{url('/viewStudies/')}}">Studies</a>-><a href="{{url('/viewStudyDetails/'.$study->id)}}">{{$study->study_name}}</a>
			</td>
			<td align="right">
				<img width="25" height="25" src="{{url('images/search-icon.png')}}"></img> Gene: <input id='gene_id' type='text' value=''/>&nbsp;&nbsp;<button id='btnGene' >GO</button>
			</td>
		</tr>
	</table>
	<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top,plain:true,pill:true" style="width:100%;padding:10px;overflow:auto;">
		<div title="Summary">
			<div id='loadingSummary' class='loading_img'>
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<table><tr><td>
					<canvas id='tissue_plot'></canvas>
				</td><td>
					<canvas id='tissue_cat_plot'></canvas>
				</td><td>
					<canvas id='sex_plot'></canvas>
				</td></tr>
				<tr><td>
					<canvas id='mortality_plot'></canvas>
				</td>
				<td>
					<canvas id='stage_plot'></canvas>
				</td><td>
					<canvas id='age_plot'></canvas>
				</td></tr>
				<tr><td>
					<canvas id='mycn_plot'></canvas>
				</td>
				<td>
					<canvas id='risk_plot'></canvas>
				</td>
				</tr>
			</table>
		</div>	
		<div title="Mutation genes">
			<div id='loadingMutation' class='loading_img'>
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div style='padding:10px;width:100%;height:90%;overflow:auto;'>
				<table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblMutations" style='width:100%;overflow:auto;'>
				</table> 
			</div>
		</div>
	@if ($study->status > 0)
		<div title="PCA">
			<div id='loadingPCA' class='loading_img'>
				<img src='{{url('/images/ajax-loader.gif')}}'></img>
			</div>
			<div class="easyui-panel" style="padding:0px;overflow:none;">
				<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="height:90%;overflow:none;">
					<div id="panelDetail" class="easyui-panel" data-options="region:'west',split:true,collapsed:false" style="width:520px;height:98%;padding:10px;overflow:auto;" title="PCA Plot">
						<table><tr><td>
						Color by:<select id='selType' style="width: 150px">
								<option value="Tissue type">Tissue type</option>
								<option value="Tissue category">Tissue category</option>
								@if ($study->hasSurvivalSample())
									<option value="Age">Age</option>
									<option value="Stage">Stage</option>
									<option value="Gender">Gender</option>
									<option value="Mortality">Mortality</option>
									<option value="MYCN">MYCN</option>
									<option value="Risk">Risk</option>
								@endif
							</select>
						</td></tr><tr><td>
						<canvas id='pca_plot'></canvas></td></tr></table>
					</div>
					<div id="panelDetail" class="easyui-panel" data-options="region:'center',split:true,collapsed:false" style="width:70%;height:90%;padding:10px;overflow:auto;" title="Variance and loading">				
						<table><tr><td colspan=2>	
							Principle component:<select id='selPC' style="width: 150px">
								@for ($i = 1; $i <= 20; $i++)
									<option value="{{$i}}">{{$i}}</option>
								@endfor
							</select></td></tr><tr><td>
						<canvas id='pca_p_loading_plot'></canvas></td><td>
						<canvas id='pca_n_loading_plot'></canvas></td></tr>
						<tr><td colspan=2>
						<canvas id='pca_var_plot'></canvas></td></tr></table>
				</div>
			</div>	
	     </div>  
		</div>   
	@endif

	

</div>

</div>


@stop
