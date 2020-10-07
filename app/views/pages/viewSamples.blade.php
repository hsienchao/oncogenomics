@extends('layouts.default')
@section('content')

{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}
{{ HTML::style('packages/yadcf-0.8.8/jquery.dataTables.yadcf.css') }}
{{ HTML::style('packages/jquery-easyui/themes/default/easyui.css') }}

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
    
<script type="text/javascript">
	var tbl;
	var data = {{json_encode($data)}};
	var cols = {{json_encode($cols)}};
	var default_cols = [false, true, true, true, true, true, true, true, true, true, true, false, false, false, false];
	$(document).ready(function() {
		tbl = $('#tblOnco').DataTable( 
		{
			"data": data,
			"columns": cols,
			"ordering":    true,
			"lengthMenu": [[15, 25, 50, -1], [15, 25, 50, "All"]],
			"pageLength":  15,
			"deferRender": true,
			"pagingType":  "simple_numbers",			
			"dom": 'B<"toolbar">lfrtip',
			"buttons": ['csv', 'excel']
		} );

		tbl.columns().iterator('column', function ( context, index ) {
			
			tbl.column(index).visible(default_cols[index]);
		} );

$("div.toolbar").html('<button id="popover" data-toggle="popover" title="Select Column" data-placement="bottom" type="button" class="btn btn-default" style="font-size: 12px;">Select Columns</button>');
		col_html = '';
		tbl.columns().iterator('column', function ( context, index ) {
			tbl.column(index).visible(default_cols[index]);
			checked = (default_cols[index])? 'checked' : '';
			col_html += '<input type=checkbox ' + checked + ' class="onco_checkbox" id="data_column" value=' + index + '><font size=3>&nbsp;' + tbl.column(index).header().innerHTML + '</font></input><BR>';
		} );

		yadcf.init(tbl, [
			{column_number : 4},
			{column_number : 5},
			{column_number : 6},
			{column_number : 7},
			{column_number : 8},
			{column_number : 9},
		]);
        

		$('[data-toggle="popover"]').popover({
			placement : 'bottom',  
			html : true,
			content : col_html
		}); 

		$('body').on('change', 'input#data_column', function() {             
			tbl.column($(this).attr("value")).visible($(this).is(":checked"));             
		});


     });
</script>
<div class="easyui-panel" style="height:100%;padding:0px;">
	<div id="var_layout" class="easyui-layout" data-options="fit:true">
		<div data-options="region:'center',split:true" style="width:100%;padding:10px;overflow:none;" title="">
			<table cellpadding="0" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblOnco" style='width:100%'>
			</table> 
		</div>
	</div>
</div>
@stop
