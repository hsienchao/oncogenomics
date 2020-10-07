{{ HTML::style('packages/w2ui/w2ui-1.4.min.css') }}
{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-core-css.css') }}
{{ HTML::style('packages/smartmenus-1.0.0-beta1/css/sm-blue/sm-blue.css') }}    
{{ HTML::script('packages/smartmenus-1.0.0-beta1/libs/jquery/jquery.js') }}
{{ HTML::script('packages/smartmenus-1.0.0-beta1/jquery.smartmenus.min.js') }}


{{ HTML::style('css/style.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}
{{ HTML::style('css/bootstrap.min.css') }}
{{ HTML::style('packages/fancyBox/source/jquery.fancybox.css') }}
{{ HTML::style('packages/bootstrap-switch-master/dist/css/bootstrap3/bootstrap-switch.css') }}
{{ HTML::style('css/filter.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('packages/tooltipster-master/dist/css/tooltipster.bundle.min.css') }}
{{ HTML::style('css/font-awesome.min.css') }}



{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('js/bootstrap.min.js') }}
{{ HTML::script('js/togglebutton.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::script('packages/fancyBox/source/jquery.fancybox.pack.js') }}
{{ HTML::script('packages/tooltipster-master/dist/js/tooltipster.bundle.min.js') }}
{{ HTML::script('packages/bootstrap-switch-master/dist/js/bootstrap-switch.js') }}
{{ HTML::script('packages/w2ui/w2ui-1.4.min.js')}}
{{ HTML::script('js/filter.js') }}
{{ HTML::script('js/onco.js') }}
{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}


{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}

{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.colVis.js') }}

{{ HTML::script('packages/DataTables-1.10.8/extensions/ColReorder/js/dataTables.colReorder.min.js') }}
{{ HTML::script('packages/DataTables-1.10.8/extensions/FixedColumns/js/dataTables.fixedColumns.min.js') }}
{{ HTML::script('packages/yadcf-0.8.8/jquery.dataTables.yadcf.js')}}

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('packages/DataTables-1.10.8/media/css/jquery.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}

<style>

.block_details {
    display:none;
    width:90%;
    border-radius: 10px;
	border: 2px solid #73AD21;
	padding: 10px; 
	margin: 10px; 
	overflow: auto;
}

#list-circos td {
    border: 1px solid black;
    padding: 10px;

}

th, td { white-space: nowrap; padding: 0px;}
	div.dataTables_wrapper {
		margin: 0 auto;
	}

</style>

<script type="text/javascript">
	
	var hide_cols = {"tblGenoTyping" : [], "tblDNAQC" : [0,2,12,13,14,17,18,19,26], "tblRNAQC" : [2]};
	var options = [];
	var tbls = [];
	var column_tbls = [];
	var col_html = [];
	var patient_state = 'collapse';
	var genotyping_state = 'collapse';
	var qc_cutoff_state = 'collapse';
	var tblGenotypingHistory = null;
	var hotspot_data = {};
	var hotspot_charts = [];
	var tables = {};
	var total_cells = {};
	var num_cols = {};
	var plot_width;
	var plot_height;
		

	$(document).ready(function() {
		plot_width = $('#tabQC').width() * 0.8;
		plot_height = $('#tabQC').height() * 0.80;
		
		@foreach ($plot_types as $plot_type)
			tables["{{$plot_type}}"] = document.getElementById("list-{{$plot_type}}");
			total_cells["{{$plot_type}}"] = 0;
			num_cols["{{$plot_type}}"] = 2;
			var values = $('#sel-{{$plot_type}}').val().split(',');
			var patient_id = values[0];
			var case_id = values[1];
			var case_name = values[2];
			var plot_type = values[3];
			insertPlot(patient_id, case_id, case_name, plot_type);
		@endforeach

		$.ajax({ url: '{{url('/getProjectQC')}}' + '/' + '{{$project_id}}' + '/dna', async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				if (data.qc_data.cols.length == 1) {
					return;
				}
				if (data.qc_data.length == 0) {
					return;
				}
				showTable(data.qc_data, 'tblDNAQC');
				//showTable(data.qc_cutoff, 'tblDNAQCCutoff');
				var tbl = $('#tblDNAQCCutoff').DataTable( {"data": data.qc_cutoff.data,"columns": data.qc_cutoff.cols});
			}
		});
		$.ajax({ url: '{{url('/getProjectQC')}}' + '/' + '{{$project_id}}' + '/rna', async: true, dataType: 'text', success: function(data) {
				data = JSON.parse(data);
				if (data.qc_data.cols.length == 1) {
					return;
				}
				if (data.qc_data.data.length == 0) {
					return;
				}
				showTable(data.qc_data, 'tblRNAQC');
			}
		});

		/*
		$.ajax({ url: '{{url('/getProjectHotspotCoverage')}}' + '/' + '{{$project_id}}' , async: true, dataType: 'text', success: function(data) {
				hotspot_data = JSON.parse(data);
				var sample_list = [];		
				for (var sample_name in hotspot_data)
					$("#sample_list").append("<H5><input id='" + sample_name + "' type='checkbox' class='hotspot_sample' checked>" + sample_name + "</input></H5>")
				showHotspotCoverage();
				$(".hotspot_sample").on('change', function() {
					showHotspotCoverage();
				});
			}		
		});
*/
				

		$('.patient-case').on('change', function() {
			var values = $(this).val().split(',');
			var patient_id = values[0];
			var case_id = values[1];
			var case_name = values[2];
			var plot_type = values[3];
			insertPlot(patient_id, case_id, case_name, plot_type);			
		});

		$('.numcol').on('change', function() {
			var values = $(this).val().split(',');
			var plot_type = values[0];
			var value = values[1];			
			num_cols[plot_type] = parseInt(value);
			//console.log("plot_type:" + plot_type);
			removeCell(plot_type, null);			
		});

	});

	function insertPlot(patient_id, case_id, case_name, plot_type) {
		var url = '{{url("/getQCPlot")}}' + '/' + patient_id + '/' + case_id + '/' + plot_type;
		//console.log(plot_type);
		var table = tables[plot_type];
		var num_col = num_cols[plot_type];		
		var row = table.rows[ table.rows.length - 1 ];
		if (total_cells[plot_type] % num_col == 0)
			row = table.insertRow(total_cells[plot_type]/num_col);
		if (row != null) {
			var cell_idx = total_cells[plot_type] % num_col;
			var cell = row.insertCell(cell_idx);
			var cell_id = generateUUID();				
			cell.setAttribute('id',cell_id);			
			var html = '<div style="font-size:20;"><button id="btn_' + cell_id + '" class="btn btn-secondary btn-remove-cell" onclick="removeCell(\'' + plot_type + '\', this.parentNode.parentNode)"><i class="fa fa-close fa1x"></i></button>Patient&nbsp;:&nbsp;' + patient_id + '&nbsp;Case&nbsp;:&nbsp;' + case_name + '</div><div><img class="img-' + plot_type + '" src="' + url + '"></img></div>';
			if (plot_type == "hotspot") {
				html = '<div style="font-size:20;"><button id="btn_' + cell_id + '" class="btn btn-secondary btn-remove-cell" onclick="removeCell(\'' + plot_type + '\', this.parentNode.parentNode)"><i class="fa fa-close fa1x"></i></button>Patient&nbsp;:&nbsp;' + patient_id + '&nbsp;Case&nbsp;:&nbsp;' + case_name + '</div>' +
						'<table style="valign:top" class="img-' + plot_type + '">' +
							'<tr>' +
								'<td valign="top" style="width:70%;height:400px;padding:20px">' +
									'<div class="panel panel-primary">' +
										'<div class="panel-heading">' +
											'<h3 class="panel-title">Coverage</h3>' +
										'</div>' +
										'<div class="panel-body">' +
											'<div id="hotspot_' + cell_id + '" ></div>' +
										'</div>' +
									'</div>' +
								'</td>' +
							'</tr>' +
						'</table>';
			}
			cell.innerHTML = html;
			getHotspotCoverage(patient_id, case_id, cell_id)
		}
		total_cells[plot_type]++;
		$('.img-' + plot_type).attr("width", parseInt(plot_width/num_col));
		$('.img-' + plot_type).attr("height", parseInt(plot_width/num_col));
	}

	function getHotspotCoverage(patient_id, case_id, div_id) {
		$.ajax({ url: '{{url('/getHotspotCoverage')}}' + '/' + patient_id + '/' + case_id, async: true, dataType: 'text', success: function(data) {
				hotspot_data[div_id] = JSON.parse(data);
				showHotspotCoverage(div_id, patient_id);				
			}		
		});
	}
	function removeCell(plot_type, cell) {
		//var cell = btn.parentNode.parentNode;
		var table = tables[plot_type];
		var num_col = num_cols[plot_type];
		var cell_htmls = [];
		for(var i=0; i<table.rows.length; i++) {
			for(var j=0; j<table.rows[i].children.length; j++) {
				var c = table.rows[i].children[j];
				if (cell != null) {
					if (i == cell.parentNode.rowIndex && j == cell.cellIndex)
						continue;
				}
				cell_htmls.push(c.innerHTML);
			}
		}

		var row_count = table.rows.length;
		for (var i = row_count-1; i >=0; i--)
		    table.deleteRow(i);
		
		total_cells[plot_type] = 0;
		var row = null;
		for(var i=0; i<cell_htmls.length; i++) {
			var html = cell_htmls[i];
			if (total_cells[plot_type] % num_col == 0)
				row = table.insertRow(total_cells[plot_type]/num_col);
			if (row != null) {
				var cell = row.insertCell(total_cells[plot_type] % num_col);
				var cell_id = generateUUID();				
				cell.setAttribute('id',cell_id);
				cell.innerHTML = html;				
			}
			total_cells[plot_type]++;
		}
		$('.img-' + plot_type).attr("width", parseInt(plot_width/num_col));
		$('.img-' + plot_type).attr("height", parseInt(plot_width/num_col));
	}

	function showHotspotCoverage(div_id, patient_id) {
		var idx = 0;
		var box_values = [];
		var outliers = [];
		var sample_list = [];		

		for (var sample_name in hotspot_data[div_id]) {
			var cov = [];
			var hotspots = [];
			sample_list.push(sample_name);
			hotspot_data[div_id][sample_name].forEach(function (d){
				cov.push(+d[2]);
				hotspots.push(sample_name + ' ' + d[0] + ' ' + d[1]);
			})
	    	var box_value = getBoxValues(cov, idx, hotspots);
	    	box_values.push(box_value.data);
	    	if (box_value.outliers != null)
				outliers = outliers.concat(box_value.outliers);
			idx++;	    			
		}		
		//console.log(sample_list);
		//console.log(box_values);
		drawBoxPlot('hotspot_' + div_id, patient_id, sample_list, box_values, outliers );		
	}
	
	function getBoxValues(data, series_idx, names) {
        //if (data.length == 1)
        //   return {data:[data[0], data[0], data[0], data[0], data[0]], outliers:[]]};
        var q1     = getPercentile(data, 25);
        var median = getPercentile(data, 50);
        var q3     = getPercentile(data, 75);
        
        var low    = Math.min.apply(Math,data);    
        var high   = Math.max.apply(Math,data);        
        low    = Math.max(low, q1-(q3-q1)*1.5);
        high    = Math.min(high, q3+(q3-q1)*1.5);
        var outliers = [];
        data.forEach(function(d, idx) {
            if (d > high || d < low) {
            	var point_data = names[idx].split(' ');
              	outliers.push({gene:point_data[1], aachange: point_data[2], x:series_idx, y:d});
            }
        });
        return {data:[low, q1, median, q3, high], outliers:outliers};
    }


	function drawBoxPlot(div_id, title, sample_list, box_values, outliers ) {		
	    var chart = $('#' + div_id).highcharts({
			credits: false,
	        chart: {
	            type: 'boxplot',
	            zoomType: 'xy'
	        },

	        title: {
	            text: title
	        },

	        legend: {
	            enabled: false
	        },
	        xAxis: {
	            categories: sample_list,
	            labels: {
	                style: {
	                    fontSize:'15px'
	                }
	            }
	        },

	        yAxis: {
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
	            floor : 0	            
	        },

	        series: [{
	            name: 'Coverage',
	            data: box_values,
	            tooltip: {
	                headerFormat: '<em> {point.key}</em><br/>'
	            }
	        }, {
	            name: 'Outlier',
	            color: Highcharts.getOptions().colors[0],
	            type: 'scatter',
	            data: outliers,
	            marker: {
	                fillColor: 'white',
	                lineWidth: 1,
	                lineColor: 'pink'
	            },
	            tooltip: {
            		pointFormat: '<b>Gene: </b>{point.gene}<br><b>AA Change: </b>{point.aachange}<br><b>Coverage: </b>{point.y}'
		        	
		    	}
	        }]

	    });
		hotspot_charts.push(chart);
	}

	function getFirstProperty(obj) {
		for (key in obj) {
			if (obj.hasOwnProperty(key))
				return key;
		}
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
		if (type == "genotyping")
			genotyping_state = switch_details(genotyping_state, 'genotyping_history', 'imgGenotypingHistory');
		if (type == "qc_cutoff")
			qc_cutoff_state = switch_details(qc_cutoff_state, 'qc_cutoff', 'imgQCCutoff');
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
			"dom": 'B<"toolbar">lfrtip',
			"buttons": ['csv', 'excel']


		} );		
		tbls[tblId] = tbl;
		var columns =[];
		col_html[tblId] = '';
		
		//$("div.toolbar").html('<button id="popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		
		$("#" + tblId + "_wrapper").children("div.toolbar").html('<button id="' + tblId + '_popover" data-toggle="popover" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		//$("#" + tblId + "_wrapper").children("div.toolbar").html(tblId);
		tbl.columns().iterator('column', function ( context, index ) {
			var show = (hide_cols[tblId].indexOf(index) == -1);
			tbl.column(index).visible(show);
			columns.push(tbl.column(index).header().innerHTML);
			checked = (show)? 'checked' : '';
			//checked = 'checked';
			col_html[tblId] += '<input type=checkbox ' + checked + ' class="onco_checkbox data_column" id="data_column_' + tblId + '" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		});
		column_tbls[tblId] = columns;
	        
		$("#" + tblId + "_popover").popover({				
				title: 'Select column <a href="#" class="close" data-dismiss="alert">×</a>',
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
				//console.log(tblId);
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

</script>

<div id="tabQC" class="easyui-tabs" data-options="tabPosition:'left',fit:true,plain:true,pill:true,border:false,headerWidth:170" style="width:98%;padding:0px;overflow:visible;border-width:0px">
		@foreach ($plot_types as $plot_type)
		<div title="{{Lang::get("messages.$plot_type")}}" style="width:98%;padding:10px;">
			<div class="form-group form-inline" style="margin-bottom: 0px">
				<span style="font-size:20">Case&nbsp;:&nbsp;<select id="sel-{{$plot_type}}" class="form-control patient-case" style="width:250px">
				@foreach ($cases as $case)
					@if ($case->case_name != "") 
						<option value='{{$case->patient_id}},{{$case->case_id}},{{$case->case_name}},{{$plot_type}}'>{{$case->patient_id}} - {{$case->case_name}}</option>
					@endif
				@endforeach
				</select>
				&nbsp;&nbsp;Number of columns&nbsp;:&nbsp;</span>
				<select id="sel-numcol-{{$plot_type}}" class="form-control numcol" style="width:100px">
					<option value='{{$plot_type}},2'>2</option>
					<option value='{{$plot_type}},3'>3</option>
					<option value='{{$plot_type}},4'>4</option>
					<option value='{{$plot_type}},5'>5</option>
				</select>
				</span>				
			</div>			
			<table id="list-{{$plot_type}}" style="margin: 5px;width:100%;border:1px"></table>			
		</div>
		@endforeach
		
		<div title="DNA QC">
					<div style="height:98%;overflow:auto;padding:10px">
						<table cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" id="tblDNAQC" style='width:100%'></table>
						<a href=javascript:do_switch('qc_cutoff');><img id="imgQCCutoff" src='{{url('/images/details_open.png')}}' style="width:20px;height:20px"></img></a> QC threshold
						<div id="qc_cutoff" class="block_details" style="width:98%;height:450px;">
							<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblDNAQCCutoff" style='width:95%;height:80%'></table>
						</div>	 						
					</div>				
		</div>
		<div title="RNA QC">
					<div style="height:98%;overflow:auto;;padding:10px">
						<table cellpadding="0" cellspacing="0" border="0" class="order-column pretty" word-wrap="break-word" id="tblRNAQC" style='width:100%'></table>						
					</div>				
		</div>
		<div title="Genotyping" id="GenoTyping">
			<iframe scrolling="auto" frameborder="0"  src="{{$genotyping_url}}" style="width:100%;height:100%;overflow:auto;border-width:0px"></iframe>
		</div>				
</div>		

