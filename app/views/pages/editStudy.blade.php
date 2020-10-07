@extends('layouts.default')

@section('content')

  
{{ HTML::script('packages/vakata-jstree-5bece58/dist/jstree.min.js') }}
{{ HTML::script('packages/jquery-easyui/jquery.easyui.min.js') }}
{{ HTML::style('packages/vakata-jstree-5bece58/dist/themes/default/style.min.css') }}
{{ HTML::style('packages/jquery-easyui/themes/metro/easyui.css') }}
{{ HTML::style('packages/jquery-easyui/themes/icon.css') }}
{{ HTML::style('css/style_datatable.css') }}
  
<script type="text/javascript">

	var tab_counter = 1;
	var current_node_id = null;
	var current_type = null;
	var mode = '{{$mode}}';
	var study = {{$study_json}};
	var study_id = null;
	var tab_width = null;
	var patient_list = {{json_encode($patient_list)}};   
	var fromCloseAll = false;	  
  
	$(document).ready(function() { 

		tab_width = 500;
		header_height = $('.tabs-header.tabs-header-narrow').height();
		$('#tabGroups').css("width", tab_width + "px"); 
		$('#tabGroups').css("height", $('#tabSource').height() + "px"); 
		$('#lstAvail').height($('#tabSource').height() - header_height - 3); 
		//$('.tabs-panels').height($('#lstAvail').height());      
		$('#tabGroups').tabs({
			pill: true,
			narrow : true,
			height : $('#tabSource').height(),
			tools:[{
				iconCls:'icon-add',
				handler:function(){
					addTab(tab_counter++, null);
			}
			}]
		});      

		$('#patientClear').click(function(){
			$('#selectpatients').structFilter("clear");
		}); 

		$("#selectpatients").on("submit.search", function(event){
			document.getElementById('filterValue').value = JSON.stringify($('#selectpatients').structFilter("val"), null, 2);
			$('#filterForm').submit();
		});

		$('#patient_tree').on('select_node.jstree', function (e, data) {	
			var key = data.node.id; 
			parent = $('#patient_tree').jstree(true).get_parent(data.node); 
			if (patient_list[key] != null) {
				$('#lstAvail').empty();
				$('#lblAvail').text('Selected diagnosis: ' + data.node.text);
				for(var d=0;d<patient_list[key].length;d++) {                         
					var value = patient_list[key][d];                            
					$('#lstAvail').append('<option value="' + value + '">' + value + '</option>');
				}
				current_node_id = key;
			}              
		});

		$('#patient_tree').jstree({{$json_tree}});

		$('#btnNewGroup').click(function(e) {              
			addTab(tab_counter++, null); 
		});

		$('#btnCancel').click(function(e) {    
			$('#popwindow').window('close');
		});

		$('#btnOK').click(function(e) {
			$('#popwindow').window('close');
			updateTabTitle($('#tissue_cat').val() + '_' + $('#tissue_type').val()); 
		});
      
		$('#btnRight').click(function(e) {
			var selectedOpts = $('#lstAvail option:selected');
			if (selectedOpts.length == 0) {
				alert("Please select patients.");
				e.preventDefault();
				return;
			}
			var list_id = getCurrentList();
			$('#' + list_id).append($(selectedOpts).clone());
			$(selectedOpts).remove();
			e.preventDefault();
			updateLabel();
			updateType();
		});

		$('#btnRightAll').click(function(e) {
			var selectedOpts = $('#lstAvail option');
			if (selectedOpts.length == 0) {
				alert("Please select patients.");
				e.preventDefault();
			}
			var list_id = getCurrentList();
			$('#' + list_id).append($(selectedOpts).clone());
			$(selectedOpts).remove();
			e.preventDefault();
			updateLabel();
			updateType();
	      });

	      $('#btnRightAllGroup').click(function(e) {
		var selectedOpts = $('#lstAvail option');
		if (selectedOpts.length == 0) {
			alert("Please select patients.");
			e.preventDefault();
		}
		if (current_node_id == null) {
			return;
		}
		var current_node = $('#patient_tree').jstree(true).get_node(current_node_id);
		var current_tissue = current_node.text;
		var parent_id = $('#patient_tree').jstree(true).get_parent(current_node);
		var tissue_text = current_tissue.substring(0,current_tissue.indexOf('('));
		var arr = parent_id.split('?');
		var tissue_cat_text = arr[arr.length-1];
		addTab(tab_counter++, tissue_cat_text + '_' + tissue_text);
		var list_id = getCurrentList();
		$('#' + list_id).append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
		updateLabel();
		updateType();
	      });

	      $('#btnLeft').click(function(e) {
		var list_id = getCurrentList();
		var selectedOpts = $('#' + list_id + ' option:selected');
		if (selectedOpts.length == 0) {
		  alert("Please select patients.");
		  e.preventDefault();
		}
		$('#lstAvail').append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
		updateLabel();
	      });

	      $('#btnClearAll').click(function(e) {
		var root = $('#patient_tree').jstree(true).get_node('root'); 
		enable_node(root);
		var tabs = $('#tabGroups').tabs('tabs');	
		for (var i=tabs.length-1;i>=0;i--) {
			var tab = tabs[i];
			var id = tab.panel('options').id;
			var list_id = id + '_lstSelected';
			var selectedOpts = $('#' + list_id + ' option');
			$(selectedOpts).remove();
			fromCloseAll = true;
			$('#tabGroups').tabs('close', i);	
		}
		var selectedOpts = $('#lstAvail option');
		$(selectedOpts).remove();
		current_node_id = null; 
		current_type = null; 
		$('#lblType').text('No Selected Type');
		$('#lblMaterialType').text('');
		$('#lblExpType').text('');
		$('#lblPlatform').text('');
		$('#lblLibType').text('');
		$('#lblSelected').text('Selected patients (0)');
	      });

	      $('#btnLeftAll').click(function(e) {
		var list_id = getCurrentList();
		var selectedOpts = $('#' + list_id + ' option');
		if (selectedOpts.length == 0) {
		  alert("Please select patients.");
		  e.preventDefault();
		}

		$('#lstAvail').append($(selectedOpts).clone());
		$(selectedOpts).remove();
		e.preventDefault();
		updateLabel();
	      });

	      $('#tabGroups').bind('dblclick',function(){
		$('#popwindow').window('open');
		title = getTabTitle();
		sep_pos = title.indexOf('_');
		var tissue_cat = title.substring(0, sep_pos);
		var tissue_type = title.substring(sep_pos + 1);
		$('#tissue_cat').val(tissue_cat);
		$('#lblTissueCat').text(tissue_cat);
		$('#tissue_type').val(tissue_type);
		$('#tissue_type').focus();
	      });

		$('#patient_tree').bind('ready.jstree', function(e, data) {
			updateType();
		});

	      $('#tabGroups').tabs({
			onBeforeClose: function(title){
				//if ($('#tabGroups').tabs('tabs').length == 1)
				//    return false;
				if (fromCloseAll) {
					fromCloseAll = false;
					return true;
				}
				return confirm('Are you sure you want to close ' + title);
		  	},
			onSelect: function(title, index) {
				updateLabel();
			}
	      });

		$('#studyForm').submit(function() {
			if ($('#studyName').val() == '') {
				alert('Please input study name');
				$('#studyName').focus();
				return false;
			}

			//check if the study name is alread exists
			if (mode == 'create') {
				var check_url = '{{url('/checkStudyExists')}}' + '/' + $('#studyName').val();
				var res = null

				$.ajax({ url: check_url, async: false, dataType: 'text', success: function(data) {
						res = data;
					}
				});
				if (res == 'true') {
					alert('Study name "' + $('#studyName').val() + '" already exists!');
					$('#studyName').focus();
					return false;
				}
			}

			var patient_data = {};
			var groups = []; 
			patient_data.name = $('#studyName').val();
			patient_data.id = study_id;
			patient_data.mode = '{{$mode}}';
			patient_data.study_type = $('#lblType').text();
			patient_data.study_type_code = current_type;
			patient_data.material_type = $('#lblMaterialType').text();
			patient_data.exp_type = $('#lblExpType').text();
			patient_data.platform = $('#lblPlatform').text();
			patient_data.library_Type = $('#lblLibType').text();
			patient_data.study_desc = $('#studyDescription').val();
			patient_data.is_public = $('#isPublic').prop('checked').toString();
			patient_data.groups = groups;
			var tabs = $('#tabGroups').tabs('tabs');
			if (tabs.length == 0) {
				alert("Please add patient data");
				return false;
			}

			for (var i=0;i<tabs.length;i++) {
				var tab = tabs[i];
				var title = tab.panel('options').title;
				var id = tab.panel('options').id;
				var list_id = id + '_lstSelected';
				var patients = [];
				var group = {"id":title};
				group.patients = patients;
				if ($('#' + list_id + ' option').length == 0) {
					alert(title + ' is empty!');
					return false;
				}
				$('#' + list_id + ' option').each(function(j){
					var patient = {"id":$(this).val()};
					patients.push(patient);
				});
				groups.push(group);
			}             
			var json_string = JSON.stringify(patient_data);
			$('#jsonData').val(json_string);
			//alert(json_string);
			return true;
		});

		$('#tissue_cat').on('change keyup paste', function () {		
			$('#lblTissueCat').text($('#tissue_cat').val()); 
		    
		});

		$('#lblAvail').text('Available patients (0)');
		$('#lblSelected').text('Selected patients (0)');
		$('#lblType').text('No selected type');      

		if (mode == 'edit') {		
			study_id = study.id;
			$('#studyName').val(study.study_name);
			$('#studyDescription').val(study.study_desc);
			$('#ispublic').prop('checked', (study.is_public == '1'));
			for (var i=0;i<study.tissues.length;i++) {
				addTab(tab_counter++, study.tissues[i].tissue_name);
				var tab_id = 'tab_' + (tab_counter - 1).toString();
				var list_id = tab_id + '_lstSelected';
				for (var j=0;j<study.tissues[i].patients.length;j++) {
					var tab_id = 'tab_' + (tab_counter - 1).toString();
					var list_id = tab_id + '_lstSelected';
					$('#' + list_id).append(new Option(study.tissues[i].patients[j]));
				}
			}
			$("#tabGroups").tabs("select",0);
			current_node_id = study.study_type_code;
			current_type = study.study_type_code;
	      } else {
		  //addTab(tab_counter++, null);
	      }

	      
	    });


	    function updateType() {
		return;
	      if (current_node_id == null) {
		  return;
	      }
	      var current_node = $('#patient_tree').jstree(true).get_node(current_node_id);
	      current_type = current_node.type;

	      var root = $('#patient_tree').jstree(true).get_node('root'); 
	      disable_node(root, current_node.type);
	      var type_info = current_type.split("?");
	      var material_type = type_info[0];
	      var exp_type = "";
	      var platform = "";
	      var library_type = "";
	      if (material_type == "RNA")
		  $('#lblType').text("Expression");
	      if (material_type == "DNA")
		  $('#lblType').text("Mutation");
	      $('#lblMaterialType').text(material_type);
	      if (type_info[1] != null)
		  $('#lblExpType').text(type_info[1]);
	      if (type_info[2] != null)
		  $('#lblPlatform').text(type_info[2]);
	      if (type_info[3] != null)
		  $('#lblLibType').text(type_info[3]);
	      
	      $('#patient_tree').jstree(true).enable_node(current_node);
	      parent = $('#patient_tree').jstree(true).get_parent(current_node); 
	      while (parent != 'root') {                        
		      current_node = $('#patient_tree').jstree(true).get_node(parent);
		      $('#patient_tree').jstree(true).enable_node(current_node);    
		      parent = $('#patient_tree').jstree(true).get_parent(current_node);               
	      }      
	    }

	    function updateLabel() {         
	      var list_id = getCurrentList();         
	      $('#lblSelected').text('Selected patients (' + $('#' + list_id + ' option').size().toString() + ')');
	      var diag_text = $('#lblAvail').text().substring(0,$('#lblAvail').text().indexOf('('));
	      $('#lblAvail').text(diag_text + '(' + $('#lstAvail option').size().toString() + ')');
	    }

	    function updateTabTitle(title) {         
	      var tab = $('#tabGroups').tabs('getSelected'); 
	      $('#tabGroups').tabs('updateTitle', {tab:tab, title:title});
	    }

	    function getTabTitle() {         
	      var tab = $('#tabGroups').tabs('getSelected'); 
	      return tab.panel('options').title;
	    }

	    function addTab(tab_index, title){
	      var tab_id = 'tab_' + tab_index.toString();
	      if (title == null) {
		  title = 'Tumor_' + tab_index.toString();
	      }
	      var list_id = tab_id + '_lstSelected';
	      var content = '<select id="' + list_id + '" size="4" multiple="multiple" style="width:100%;height:' + $('#lstAvail').height() + ';"></select>';

	      $('#tabGroups').tabs('add',{id:tab_id, title:title, content:content, closable:true, style:'width:100%;height:' + $('#tabSource').height() + 'px;padding:0px;'});	
	    }

	    function disable_node(node, type) {
	      $.each(node.children, function(index, child) {
		    $('#patient_tree').jstree(true).disable_node(child);
		    if ($('#patient_tree').jstree(true).is_parent(child)) {
		        child_node = $('#patient_tree').jstree(true).get_node(child);
		        if (child_node.type != type) {
		            disable_node(child_node, type);
		        }
		    } 
	      });
	    }

	    function enable_node(node) {
	      $.each(node.children, function(index, child) {
		    $('#patient_tree').jstree(true).enable_node(child);
		    if ($('#patient_tree').jstree(true).is_parent(child)) {
		        child_node = $('#patient_tree').jstree(true).get_node(child);
		            enable_node(child_node);
		    } 
	      });
	    }

	    function getCurrentList() {
	      var tab = $('#tabGroups').tabs('getSelected');            
	      var list_id = tab.attr("id") + '_lstSelected';         
	      return list_id;
	    }

	    $.extend($.fn.tabs.methods, {
	      updateTitle: function(jq, param){
		return jq.each(function(){
		  var t = $(param.tab);
		  var opts = t.panel('options');
		  opts.title = param.title;
		  opts.tab.find('.tabs-title').html(param.title);
		})
	      }
	    })
        
  </script>   

  <style>
table.pretty tbody th {
	text-align: right;
	background: #E1F0FF;
	font-size: 13px;
}
table.pretty tbody td {
	color: red;
	font-size: 13px;
	background: #F1F8FF;
}
  </style>

  <div id="popwindow" class="easyui-window" title="Please set a new name" data-options="modal:true,closed:true,iconCls:'icon-save'" style="width:700px;height:250px;padding:10px;">
       <table cellpadding="10">
           <tr><td align="right">Tissue category: </td><td><input id='tissue_cat' type="text" size="20"></td></tr>
           <tr><td align="right">Tissue/group name: </td><td><div id="lblTissueCat" style="text-align:left;display:inline"></div>_&nbsp;<input id='tissue_type' type="text" size="30"></td></tr>
           <tr><td><a href="#" class="btn btn-primary"  id="btnOK" >Ok</a>&nbsp;&nbsp;<a href="#" class="btn btn-primary"  id="btnCancel">Cancel</a></td></tr>
       </table>
  </div>

 <div id="out_container" class="easyui-panel" style="width:100%;height:100%;padding:10px;">
  <div class="easyui-layout" data-options="fit:true">
    <div data-options="region:'west',split:true" style="width:400px;padding:10px;overflow:auto;" title="Choose tissuies">
        <div id="patient_tree"></div>   
    </div>
    <div data-options="region:'center'" style="padding:0px; border:0px">
       <div class="easyui-panel" style="height:100%;padding:0px;">
            <div class="easyui-layout" data-options="fit:true">
                  <div class="easyui-panel" data-options="region:'north',split:true" style="height:250px;padding:10px;overflow:auto;" title="Study information">
                       <table cellpadding="0" cellspacing="0" border="1" class="pretty" word-wrap="break-word" id="tblDetail" style='width:100%'>
				<tr><th>Study name:</th><td> <input id='studyName' size="50" type="text"></td></tr>  
				<tr><th>Study Desc:</th><td> <textarea id='studyDescription' rows="5" cols="50"></textarea> </td></tr>
				<tr><th>Make Public? </th><td> <input id='isPublic' class="onco_checkbox" type='checkbox' checked/></td></tr>
				<tr><th></th><td><a href="#" id="btnClearAll" class="btn btn-success" data-options="iconCls:'icon-cancel'">Clear all</a></td></tr>
                       </table> 
                  </div>
                  <div class="easyui-panel" data-options="region:'center',split:true" style="width:100%;padding:10px;overflow:auto;" title="Choose patients">
			<table border=0 width=100% height=100%>          
				<tr height=10px>
					<td><div id="lblAvail" style="text-align:left;" text="test"></div></td><td></td>
					<td><div id="lblSelected" style="text-align:left;" text="test"></div></td>
				</tr>
				<tr>
					<td valign="top">
						<div id="tabSource" class="easyui-tabs" data-options="pill:true,narrow:true" style="width:400px;height:100%;padding:0px">               
							<div title="patients in tissue" data-options="iconCls:'icon-ok'" style="height:100%">
								<select name="lstAvail" id="lstAvail" size="4" multiple="multiple" style="height:100%;width:400px;"> </select> 
							</div>
						</div>
					</td> 
					<td>
						<input type="button" class="btn btn-info btn-block btnEdit" id="btnRightAllGroup" value="Add Tissue" style="display:block;" />
						<input type="button" class="btn btn-info btn-block btnEdit" id="btnRightAll" value=">>&nbsp;" style="display:block;" />
						<input type="button" class="btn btn-info btn-block btnEdit" id="btnRight" value="&nbsp;>&nbsp;" style="display:block;" />
						<input type="button" class="btn btn-info btn-block btnEdit" id="btnLeft" value="&nbsp;<&nbsp;" style="display:block;" />
						<input type="button" class="btn btn-info btn-block btnEdit" id="btnLeftAll" value="<<&nbsp;" style="display:block;" />
					</td>
					<td valign="top">               
						<div id="tabGroups" class="easyui-tabs" style="width:100%;height:100%;padding:0px"></div>                        
					</td>
				</tr> 
			</table>
                  </div>
                  <div class="easyui-panel" data-options="region:'south',split:true" style="height:50px;padding:10px;border:0px;overflow:hidden;">   
                        {{Form::open(array('url' => '/saveStudy', 'method' => 'post', 'id' => 'studyForm') )}} 
                              <input type="hidden" id="jsonData" name="jsonData"/>
                              <input class="btn btn-primary" id="btnSubmit" type="submit" value="Save Study"/>
                        {{Form::close()}}         
                  </div>
           </div>
       </div>
   </div>
 </div>
</div>

@stop
