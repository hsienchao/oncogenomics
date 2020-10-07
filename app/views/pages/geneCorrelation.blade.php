
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/canvasXpress/css/canvasXpress.css') }}
{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}

{{ HTML::script('packages/smartmenus-1.0.0-beta1/libs/jquery/jquery.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/canvasXpress/js/canvasXpress.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}
<script type="text/javascript">
	var heatmap = null;
	$(document).ready(function() {		
		$.ajax({ url: '{{url("/getCorrelationHeatmapData/$sid/$gid/0.3/30")}}', async: true, dataType: 'text', success: function(data) {			
				data = JSON.parse(data);
				showTable(data.table_data);
				showPlot('corrheatmap_canvas_p', data.p);
				showPlot('corrheatmap_canvas_n', data.n);
				showTwoGeneScaterPlot('{{$gid}}', data.best_gene)
			}
		});

		
	});

	$(document).ajaxStart(function(){		
		$("#loadingDiv").css("display","block");
        });

        $(document).ajaxComplete(function(){
		$("#loadingDiv").css("display","none");

        });
        

	var showTable = function(data) {
		tbl = $('#tblOnco').DataTable( 
		{
			"data": data.data,
			"columns": data.cols,
			"ordering":    true,
			"order": [[ 2, "desc" ]],
			"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
			"pageLength":  15,			
			"pagingType":  "simple_numbers",			
			
		} );

		yadcf.init(tbl, [
				{column_number : 2},
		]);
        

		

	}

	var showPlot = function (plot_id, data) {
		corrheatmap_width = data.width;
		corrheatmap_height = data.height;

		$('#' + plot_id).attr("width" , Math.max(corrheatmap_width, $('#tabDetails').width() - 40));
		$('#' + plot_id).attr("height", Math.max(corrheatmap_height, $('#tabDetails').height() - 40));
		corrheatmap = new CanvasXpress(plot_id, data.data,
			{
				"axisTitleFontStyle": "italic",
				"axisTitleFontSize": 18,
				"smpTitleFontSize": 18,
				"varTitleFontSize": 18,
				"axisTickFontSize": 18,
				"smpLabelFontSize": 18, 
				"varLabelFontSize": 18,        
				"autoScaleFont": false, 
				"dendrogramHang": true,
				"graphType": "Heatmap",
				"heatmapType": "green-red",
				"indicatorCenter": "rainbow",
				"showDataValues": false,
				"varDendrogramPosition": "top",
				"adjustAspectRatio " : true,
				"dendrogramSpace" : 0,
				"title": "Correlation - {{$gid}}.",
				"varTitle": "Sample",
				"smpTitle": "Gene Correlation",
				"varOverlays": ["Group"],
				"smpOverlays": ["Correlation"],
			},
			{
				click: function(o,e,t){          
				},
				dblclick: function(o,e,t){
					if(o.objectType=='Smp') { 
						window.location.replace("{{url()}}/geneDetailUCSC/"+{{$sid}}+"/"+o.display);
					}
				}
			}
		);
		//corrheatmap.clusterVariables();		 
	 }

	var showScatterPlot = function (data) {
	$('#scatterPlot').attr("width" , 800);
	$('#scatterPlot').attr("height", 600);
        var scatterPlot = new CanvasXpress("scatterPlot", data,           
			{	"graphType": "Scatter2D",
           			"title": "Expression Scatter Plot",
				"colorBy": "Tissue",
				"xAxis": [data.y.smps[0]],
				"yAxis": [data.y.smps[1]]
			}
		);
	scatterPlot.addRegressionLine();
	}

	function showTwoGeneScaterPlot(gene1, gene2) {
		$.ajax({ url: '{{url("/getTwoGenesDotplotData/$sid")}}' + '/' + gene1 + '/' + gene2, async: true, dataType: 'text', success: function(data) {			
			data = JSON.parse(data);
			showScatterPlot(data);
			}
		});

	}
</script>

<div id="tabDetails" class="easyui-tabs" data-options="tabPosition:top" style="width:98%;padding:10px;overflow:none;">               
	<div title="Table">
        	<div class="easyui-panel" style="height:800px;padding:0px;overflow:none;">
			<div id="onco_layout" class="easyui-layout" data-options="fit:true" style="height:500px;overflow:none;">
				<div id="panelDetail" class="easyui-panel" data-options="region:'west',split:true,collapsed:false" style="width:30%;height:90%;padding:10px;overflow:auto;" title="Data">
					<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%;'>
					</table>
				</div>
				<div id="panelDetail" class="easyui-panel" data-options="region:'center',split:true,collapsed:false" style="width:70%;height:90%;padding:10px;overflow:auto;" title="Data">
<div id='loadingDiv'>
    <img src='{{url('/images/ajax-loader.gif')}}'></img>
</div>
					<canvas id='scatterPlot'></canvas>
				</div>
			</div>
		</div>
	</div>
	<div title="Positive co-expression">
             
		<canvas id='corrheatmap_canvas_p'></canvas>
	</div>

	<div title="Negative co-expression">
		<canvas id='corrheatmap_canvas_n'></canvas>
	</div>
</div>


