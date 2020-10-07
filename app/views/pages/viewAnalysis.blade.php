{{ HTML::style('packages/jquery/jquery.dataTables.css') }}
{{ HTML::style('packages/Buttons-1.0.0/css/buttons.dataTables.min.css') }}
{{ HTML::style('css/style_datatable.css') }}

{{ HTML::script('packages/jquery/jquery-1.11.1.min.js') }}

{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.js') }}
{{ HTML::script('packages/DataTables-1.10.8/media/js/jquery.dataTables.min.js') }}

{{ HTML::script('packages/Buttons-1.0.0/js/dataTables.buttons.min.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.flash.js') }}

{{ HTML::script('packages/Buttons-1.0.0/js/buttons.html5.js') }}
{{ HTML::script('packages/Buttons-1.0.0/js/buttons.print.js') }}

  <script type="text/javascript">
    $(document).ready(function() {
	var tbl = $('#tblAnalysis').dataTable( {
		"processing": true,
		"serverSide": true,
                //"bFilter": false,
                "scrollX": true,
                "ordering":  false,
                "pageLength":  25,
                "ajax": {
                        "url": "{{url('/dataTableProcessing')}}",
                        "data": function ( d ) {
                             d.aid = "{{$aid}}";
                        }
                },
                "aoColumns": {{$colnames}}
	} );

        $('#tblAnalysis_filter input').unbind();
        $('#tblAnalysis_filter input').bind('keyup', function(e) {
             if (e.keyCode == 13) {
                 tbl.fnFilter(this.value);                    
             }
        });  
        $('#selAnalysis').on('change', function() {
            window.location.replace("{{url('/viewAnalysis')}}" + "/{{$sid}}/" + this.value);
        });
        $("#selAnalysis").val({{$aid}});

    });
  </script>
Analysis: 
  <select id='selAnalysis'>
@foreach ($analyses as $analysis)
    <option value="{{$analysis->id}}">{{$analysis->analysis_name}}</option>
@endforeach
  </select>
  <table id="tblAnalysis" class="display" cellspacing="0" width="100%"> 
<!--table cellpadding="10" cellspacing="0" border="0" class="pretty" word-wrap="break-word" id="tblAnalysis" style='width:100%;overflow:none;'-->
		</table>       
  </table>
