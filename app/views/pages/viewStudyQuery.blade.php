{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/canvasXpress/css/canvasXpress.css') }}
{{ HTML::style('css/heatmap.css') }}

{{ HTML::script('packages/smartmenus-1.0.0-beta1/libs/jquery/jquery.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/canvasXpress/js/canvasXpress.js') }}


  
  <script type="text/javascript">

	var cheatmap = null;
	var data = null;

	$(document).ready(function() {
		$("#loadingHeatmap").css("display","block");
		gene_list = '{{$gene_list}}';
		if (gene_list == '') gene_list = 'null';
		
		$.ajax({ url: '{{url("/hasEnsemblData/".$study->id)}}', async: true, dataType: 'text', success: function(data) {
				if (data == 'Y') {
					$('#selDataType').append($('<option>', { value : 'Ensembl' }).text('Ensembl'));
					$('#selDataTypeLocus').append($('<option>', { value : 'Ensembl' }).text('Ensembl'));
					$('#selDataType option[value={{$data_type}}]').prop('selected',true);
					$('#selDataTypeLocus option[value={{$data_type}}]').prop('selected',true);		
				}
			}
		});

		$.ajax({ url: '{{url("/getStudyQueryData/".$study->id)}}' + '/' + gene_list + '/{{$data_type}}', async: true, dataType: 'text', success: function(json_data) {
				$("#loadingHeatmap").css("display","none");				
				if (json_data == '') {
					$("#status").css("display","block");
				}
				data = JSON.parse(json_data);
				showPlot('log2');
				
			}
		});

		$('#showClust').change(
			function() {
				if ($(this).is(":checked")) {
					cheatmap.dendrogramSpace = 1;
				}
				else {
					cheatmap.dendrogramSpace = 0;
				}
				cheatmap.draw();
			}
		);

		$('#showOncoprint').change(
			function() {
				if ($(this).is(":checked")) {
					cheatmap.isOncoprint = "mutation";
				}
				else {
					cheatmap.isOncoprint = "";
				}
				cheatmap.draw();
			}
		);

		$('#selType').on('change', function() {
	              showPlot($("#selType").val());              
		});

		$('#selColor').on('change', function() {
			if ($(this).val() == '0') {
				cheatmap.heatmapType = "green-red";
				cheatmap.indicatorCenter = "black";
			}
			if ($(this).val() == '1') {
				cheatmap.heatmapType = "blue-green";
				cheatmap.indicatorCenter = "rainbow";
			}
		cheatmap.draw();
		});


		$('#range').slider({onComplete : function (value) {
			cheatmap.setMinX = value[0];
			cheatmap.setMaxX = value[1];
        	cheatmap.draw();
		}});

		$('#selGene_set').on('change', function() {
			gene_set = {"0":"TP53 MDM2 MDM4 CDKN2A CDKN2B TP53BP1", "1":"AKR1C3 AR CYB5A CYP11A1 CYP11B1 CYP11B2 CYP17A1 CYP19A1 CYP21A2 HSD17B1 HSD17B10 HSD17B11 HSD17B12 HSD17B13 HSD17B14 HSD17B2 HSD17B3 HSD17B4 HSD17B6 HSD17B7 HSD17B8 HSD3B1 HSD3B2 HSD3B7 RDH5 SHBG SRD5A1 SRD5A2 SRD5A3 STAR", "2":"EGFR ERBB2 PDGFRA MET KRAS NRAS HRAS NF1 SPRY2 FOXO1 FOXO3 AKT1 AKT2 AKT3 PIK3R1 PIK3CA PTEN","3":"TP53 MDM2 MDM4 CDKN2A CDKN2B TP53BP1"};
			$("#gene_list").val(gene_set[$("#selGene_set").val()]);
		});
		
		$('#btnLocus').on('click', function() {
			if (!isInt($('#start_pos').val())) {
				alert('The start position is not a number!');
				$('#start_pos').focus();
				return;
			}
			if (!isInt($('#end_pos').val())) {
				alert('The end position is not a number!');
				$('#end_pos').focus();
				return;
			}
			if ($('#end_pos').val() < $('#start_pos').val()) {
				alert('The end position must be larger than start position!');
				$('#end_pos').focus();
				return;
			}
			window.location.replace("{{url('/viewExpressionHeatmapByLocus')}}" + "/{{$study->id}}/" + $('#selChr').val() + "/" + $('#start_pos').val() + "/" + $('#end_pos').val() + '/' + $('#selDataTypeLocus').val());
        	});

		$("#selChr").val({{$chr}}); 
	});
           
	var showPlot = function(value_type) {
		if (value_type == 'log2')
			data.data.y.data = data.log2;
		if (value_type == 'zscore')
			data.data.y.data = data.zscore;
		if (value_type == 'mcenter')
			data.data.y.data = data.mcenter;
		if (value_type == 'zscore_normal')
			data.data.y.data = data.zscore_normal;
		if (value_type == 'mcenter_normal')
			data.data.y.data = data.mcenter_normal;

		if (cheatmap != null) {
			cheatmap.data = data.data;
			cheatmap.initialize()
			cheatmap.clusterSamples();
			cheatmap.clusterVariables();
			cheatmap.draw();
			$('#range').slider({min:cheatmap.minData, max:cheatmap.maxData, value:[cheatmap.minData,cheatmap.maxData]});
			return;
		}

		cheatmap_width = data.width;
		cheatmap_height = data.height;

		$('#heatmap_canvas').attr("width" , Math.max(cheatmap_width, $('#panel_heatmap').width() - 40));
		$('#heatmap_canvas').attr("height", Math.max(cheatmap_height, $('#panel_heatmap').height() - 40));
		cheatmap = new CanvasXpress("heatmap_canvas", data.data,
						{
							"title"                 : "Expression",
							"varTitle"              : "Samples",
							"smpTitle"              : "Genes",
							"axisTitleFontStyle"    : "italic",
							"axisTitleFontSize"     : 14,
							"smpTitleFontSize"      : 14,
							"varTitleFontSize"      : 12,
							"axisTickFontSize"      : 12,
							"smpLabelFontSize"      : 12, 
							"varLabelFontSize"      : 12, 
							"autoScaleFont"         : false, 
							"graphType"             : "Heatmap",
							"heatmapType"           : "green-red",
							"indicatorsPosition"	: "right",
							"indicatorCenter"       : "black",
							"dendrogramHang"        : false,
							"dendrogramSpace"       : 0, 
							"showDataValues"        : false,
							"zoomSamplesDisable"   : true,
							"zoomVariablesDisable"   : true,
							"adjustAspectRatio "    : true,
							"varOverlays"           : ["Group"],
							"smpOverlays"           : ["surface", "membranous"],   
							"remoteParamOverride"   : true,
							"disableToolbar":true,
							"disableMenu":true, 
							
						},
						{       
							/*mousemove: function(o,e,t){								
								if (o.objectType=='Smp')
									$('#heatmap_canvas').css('cursor','pointer');
								else
									$('#heatmap_canvas').css('cursor','default');
							},*/  
							click: function(o,e,t){
								if(o.objectType=='Smp') { 
									var win = window.open('{{url('/viewGeneDetail')}}/'+ {{$study->id}}+"/"+o.display+"/0/UCSC", '_blank');
									win.focus();
								}
							},
							dblclick: function(o,e,t){
								if(o.objectType=='Smp') { 
									var win = window.open('{{url('/viewGeneDetail')}}/'+ {{$study->id}}+"/"+o.display+"/0/UCSC", '_blank');
									win.focus();
								}
							}
						}
					);
		cheatmap.clusterSamples();
		cheatmap.clusterVariables();
		//cheatmap.highlightSmp = ['P53','RET'];		
		cheatmap.filterSmpBy = ['TP53','RET'];
		cheatmap.draw();
		$('#range').slider({min:cheatmap.minData, max:cheatmap.maxData, value:[cheatmap.minData,cheatmap.maxData]});
	} 

	var showPCAPlot = function() {
		$('#pca_plot').attr("width" , 700);
		$('#pca_plot').attr("height", 700);
		var pcaPlot = new CanvasXpress("pca_plot", data.pca_data, {
							"colorBy" : "Genes",
							"graphType": "Scatter3D",			
							}
        					);
	}

	function isInt(value) {
		var x;
		if (isNaN(value)) {
			return false;
		}
		x = parseFloat(value);
		return (x | 0) === x;
	}

	function savePNG() {
		var i=cheatmap.canvas.toDataURL("image/png");return window.open("about:blank","canvasXpressImage").document.write('<html><body><img src="'+i+'" /></body></html>');
	}


  </script>
<body onload="showPlot('log2');">
<div class="easyui-panel" style="height:100%;padding:0px;overflow:none;">
	<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="overflow:hidden;">
		<div id="panel" class="easyui-panel" data-options="region:'west',split:true,collapsed:false" style="width:340px;padding:10px;overflow:auto;" title="Setting">
			<table><tr class="spaceUnder"><td>
				<fieldset>
				<legend>Gene Set</legend>
				{{Form::open(array('url' => '/viewExpressionHeatmap/'.$study->id, 'method' => 'post', 'id' => 'heatmapSettings') )}} 	
					<table><tr><td>
						<select id="selGene_set" >
							<option value="-1">User defined list</option>
							<option value="0">p53 signaling (6 genes)</option>
							<option value="1">Prostate Cancer (30 genes)</option>
							<option value="2">Glioblastoma (17 genes) </option>
							<option value="3">DNA Damage Response (12 genes)</option>
						</select>
						</td></tr>
						<tr><td>	
							<textarea id="gene_list"  name="gene_list"  rows="6" cols="30" maxrows="500">{{$gene_list}}</textarea>
						</td></tr>
						<tr><td>
							Annotation: <select id='selDataType' name='selDataType' style="width: 150px">
							<option value="UCSC">UCSC</option>							
						</select>
						</td></tr>
						<tr><td>	
							<input id="btnSubmit" type="submit" value="Filter"/>
						</td></tr>
					</table>
					<input id="sid" type="hidden" name="sid" value='{{$study->id}}' />
				{{Form::close()}} 
				</fieldset>
				</td></tr>
				<tr class="spaceUnder"><td>
				<fieldset>
				<legend>Locus</legend>
					<table><tr><td>Chromosome: </td><td>
						<select id="selChr">
							@for ($i = 1; $i <= 22; $i++)
								<option value="{{$i}}">chr{{$i}}</option>
							@endfor
							<option value="X">chrX</option>
						</select>
						</td></tr>
						<tr><td>From:</td><td><input type="text" id="start_pos" value="{{$start_pos}}"/></td><tr><td>To:</td><td><input type="text" id="end_pos" value="{{$end_pos}}"/></td></tr>

						<tr><td>Annotation: </td>
						<td><select id='selDataTypeLocus' name='selDataType' style="width: 150px">
							<option value="UCSC">UCSC</option>
						</select>
						</td></tr>
						<tr><td><button id='btnLocus' >Filter</button></td></tr>
					</table>
				</fieldset>
				</td></tr>
				<tr class="spaceUnder"><td>
				<fieldset>
				<legend>Heatmap</legend>
					<table><tr><td>Dendrogram:</td><td><input id='showClust' type='checkbox'/></td></tr>
						<tr><td>Value type:</td><td>
						<select id='selType' style="width: 150px">
							<option value="log2">Log2</option>
							@if ($study->status > 0)
								<option value="zscore">Z-score</option>
								<option value="mcenter">Median Centered</option>
								@if ($study->getNormalSampleCount() > 0)
									<option value="zscore_normal">Z-score by normal samples </option>
									<option value="mcenter_normal">Median Centered by normal samples</option>
								@endif
							@endif
						</select></td></tr>
						<tr><td>Color:</td><td>
						<select id='selColor' style="width: 150px">
							<option value="0">Green-Red</option>
							<option value="1">Blue-Green</option>
						</select></td></tr>
						<tr height="50"><td>Color range:</td><td><input id="range" class="easyui-slider" style="width:120px" data-options="showTip: true,range: true,value: [0,0]">
						<tr><td>Oncoprint:</td><td><input id='showOncoprint' type='checkbox'/></td></tr>
						<tr><td>Export:</td><td><a href=javascript:savePNG();>PNG</a>
					</table>
				</fieldset>		

				</table>								     
		</div>			
		<div id="panel_heatmap" data-options="region:'center',split:true" style="padding:10px;overflow:none;">
					Highlight samples:<input type="text" id="txtHighlightSmp" value=""/>
					<div id='loadingHeatmap'>
				    		<img src='{{url('/images/ajax-loader.gif')}}'></img>
					</div>
					<div id='status' style="display:none">
				    		<h2>No results!</h2>
					</div>
					<canvas id='heatmap_canvas'></canvas>			
		</div>
	</div>
</div>

