@extends('layouts.default')
@section('content')

{{ HTML::style('css/light-bootstrap-dashboard.css') }}
{{ HTML::style('css/sb-admin.css') }}
{{ HTML::style('css/font-awesome.min.css') }}

{{ HTML::script('packages/highchart/js/highcharts.js')}}
{{ HTML::script('packages/highchart/js/highcharts-3d.js')}}
{{ HTML::script('packages/highchart/js/highcharts-more.js')}}
{{ HTML::script('packages/highchart/js/modules/exporting.js')}}
{{ HTML::script('js/onco.js') }}

<script type="text/javascript">
	
	$(document).ready(function() {

		var url = '{{url('/getTopVarGenes')}}';
		console.log(url);		
		$.ajax({ url: url, async: true, dataType: 'text', success: function(data) {
				//console.log(data);
				data = JSON.parse(data);
				keys = Object.keys(data);
				//console.log(keys.length);
				//show one plot for now...
				$('#one_col').css("display","none");
				$('#two_cols').css("display","block");
				var type = "germline";
				drawStackPlot("col2_v1", capitalize(type), data[type].category, data[type].series);
				type = "somatic";
				drawStackPlot("col2_v2", capitalize(type), data[type].category, data[type].series);					
				//$('#one_col').css("display","block");
				//$('#two_cols').css("display","none");
				//	var type = keys[0];
				//	drawStackPlot("col1_v1", capitalize(type), data[type].category, data[type].series);
			}			
		});
		console.log('{{json_encode($exp_types)}}');
		console.log('{{json_encode($tissue_cats)}}');
		var pie_data = {{json_encode($exp_types)}};
		showPieChart("exp_type", "Library Type", pie_data, null, true, false, false, 'Number of samples');
		var pie_data = {{json_encode($tissue_cats)}};
		showPieChart("tissue_cats", "Normal/Tumor", pie_data, null, true, false, false, 'Number of samples');

		$('#search_gene').keyup(function(e){
			if(e.keyCode == 13) {
				var gene_id = $('#search_gene').val();
				@if ($project_count > 1)
        			window.open("{{url('/viewGeneDetail')}}" + "/" + gene_id.toUpperCase());
        		@else
        			window.open("{{url("/viewProjectGeneDetail/$project_id")}}" + "/" + gene_id.toUpperCase());
        		@endif
    		}
		});

		$('#search_patient').keyup(function(e){
			if(e.keyCode == 13) {
				var patient_id = $('#search_patient').val();
        		window.open("{{url('/viewPatient')}}" + "/" + '{{$project_id}}' + "/" + patient_id.toUpperCase() + "/any");
    		}
		});

		$("#btnAnnoSearch").on('click', function(){
			var chr=$("#search_variant_chr").val(); 
			var start=$("#search_variant_start").val(); 
			var end=$("#search_variant_end").val();
			var ref=$("#search_variant_ref").val();
			var alt=$("#search_variant_alt").val();
			url="{{url('/viewVariant')}}"+"/"+chr+"/"+start+"/"+end+"/"+ref+"/"+alt
			console.log(url);
			window.open(url);
    		
		});

	});
</script>
<!--div class="main-panel" -->
    <div class="sr-only">
      <a href="#main" data-skip-link>Skip to content</a>
    </div>
	<div class="pane-content" style="text-align: center; padding: 10px 0 0 20px">
		<div  class="container-fluid" style="padding:10px" >
			<div class="row">
				<div class="col-md-9">
					<!--div class="row">
	                	<div class="card">
	                		<div class="row">
								<div class="col-md-12">
									<div style='text-align:center'>
									    <H4>Search Gene: <input class="form-control"></input></H4>
									</div>
								</div>
							</div>
						</div>
					</div-->
					<div class="row">
						<div class="col-md-6">
	                		<div class="card" style="padding:10px">
	                			<div id="main" style='text-align: left; height:230px' role="main" >
									    <H1 style="font-size:28px; margin:20px 0 10px">Mission of the Oncogenomics Section</H1><hr>
									    The mission of the Oncogenomics Section is to harness the power of high throughput genomic and proteomic methods to improve the outcome of children with high-risk metastatic, refractory and recurrent cancers. The research goals are to integrate the data, decipher the biology of these cancers and to identify and validate biomarkers and novel therapeutic targets and to rapidly translate our findings to the clinic. For more information about our research, visit the Oncogenomics Section website.<br><br>
								</div>
							</div>
						</div>
						<div class="col-md-3">
	                		<div class="card">
	                			<div id="exp_type" style="height:230px">
								</div>
							</div>
						</div>
						<div class="col-md-3">
	                		<div class="card">
	                			<div id="tissue_cats" style="height:230px">
								</div>
							</div>
						</div>
					</div>					
					<br>
					<div class="row">
						<div class="card">
	                    	<div class="row">
								<div class="col-md-4">
									<div class="panel panel-primary">
										<div class="panel-heading">
											<div class="row">
												<div class="col-xs-3">
													<i class="fa fa-institution fa-4x"></i>
												</div>
												<div class="col-xs-9 text-right">
													<div class="huge">{{$project_count}}<br>Projects</div>                                        
	                                    		</div>
	                                    	</div>
										</div>
										<a href="{{url('/viewProjects')}}">
											<div class="panel-footer">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>
									</div>
								</div>
								<div class="col-md-4">
									<div class="panel panel-green">
										<div class="panel-heading">
											<div class="row">
												<div class="col-xs-3">
													<i class="fa fa-address-card fa-4x"></i>
												</div>
												<div class="col-xs-9 text-right">
													<div class="huge">{{$patient_count}}<br>Patients</div>                                        
												</div>
	                                    	</div>
										</div>
										<a href="{{url('/viewPatients/null/any/1/normal')}}">
											<div class="panel-footer">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>
									</div>
								</div>
								<div class="col-md-4">
									<div class="panel panel-yellow">
										<div class="panel-heading">
											<div class="row">
												<div class="col-xs-3">
													<i class="fa fa-briefcase fa-4x"></i>
												</div>
												<div class="col-xs-9 text-right">
													<div class="huge">{{$case_count}}<br>Cases</div>                                        
												</div>
	                                    	</div>
										</div>
										@if (Config::get('site.isPublicSite'))
										<a href="{{url('/viewPatients/null/any/1/normal')}}">
										@else
										<a href="{{url('/viewCases/any')}}">
										@endif
											<div class="panel-footer">
												<span class="pull-left">View Details</span>
												<span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
												<div class="clearfix"></div>
											</div>
										</a>										
									</div>
								</div>
							</div>
	                    </div>
	                </div>
	                <br>
	                @if ($project_count > 0)
	                <div class="row">
						<div class="card">
							<div id="two_cols" sytle="display:none">
		                		<div class="row">
									<div class="col-md-6">
										<div id="col2_v1" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
									</div>
									<div class="col-md-6">
										<div id="col2_v2" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
									</div>
								</div>
							</div>
							<div id="one_col" sytle="display:none">
								<div class="row">
									<div class="col-md-12">
										<div id="col1_v1" style="min-width: 310px; height: 350px; margin: 0 auto"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					@endif
					<br>                			
				</div>
				@if ($project_count > 0)
				<div class="col-md-3">
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-green">								
								<div class="panel-body">
									<i class="fa fa-search"></i><span style="font-size:16">Gene:</span>
									<input id="search_gene" class="form-control" type="text" placeholder="Gene Symbol" aria-label="Search Gene Symbol"></input>
									<i class="fa fa-search"></i><span style="font-size:16">Patient:</span>
									<input id="search_patient" class="form-control" type="text" placeholder="Patient ID" aria-label="Search Patient Symbol"></input>
								</div>						
							</div>						
						</div>
					</div>					
					@if (!Config::get('site.isPublicSite'))
					<!--div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-default">
								
								<div class="panel-body">
									<div class="form-group form-inline" style="margin-bottom: 0px">
										<i class="fa fa-search"></i><span style="font-size:20">Variant:</span></br>
										<input id="search_variant_chr" class="form-control" type="text" placeholder="Chr" aria-label="Search Gene Symbol"   ></input></br>
										<input id="search_variant_start" class="form-control" type="text" placeholder="Start" aria-label="Search Gene Symbol" ></input></br>
										<input id="search_variant_end" class="form-control" type="text" placeholder="End" aria-label="Search Gene Symbol"  ></input></br>
										<input id="search_variant_ref" class="form-control" type="text" placeholder="Ref" aria-label="Search Gene Symbol"  ></input></br>
										<input id="search_variant_alt" class="form-control" type="text" placeholder="Alt" aria-label="Search Gene Symbol" ></input></br></br>
										<button id="btnAnnoSearch" type="button" class="btn btn-infcommit ./ap	o">Search Variant</button>
									</div>
								</div>						
							</div>						
						</div>
					</div-->
					@endif
					@if (count($user_log) > 0)
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-green">
								<div class="panel-heading">
									Recently Visited Patients
								</div>
								<div class="panel-body">
								@foreach ($user_log as $patient_id)
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{{url("/viewPatient/null/$patient_id")}}">{{$patient_id}}</a></h2>
								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
					@if (count($project_list) > 0)
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-primary">
								<div class="panel-heading">
									Popular Projects
								</div>
								<div class="panel-body">
								@foreach ($project_list as $project_id => $name)
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{{url("/viewProjectDetails/$project_id")}}">{{$name}}</a></h2>
								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
					@if (count($gene_list) > 0)
					<div class="row" style="padding: 0px 20px 0px 20px">
						<div class="col-md-12">
							<div class="panel panel-yellow">
								<div class="panel-heading">
									Popular Genes
								</div>
								<div class="panel-body">
								@foreach ($gene_list as $gene)
									@if ($project_count > 1)
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{{url("/viewGeneDetail/$gene")}}">{{$gene}}</a></h5>
									@else
									<h2 style="margin:5px 0 2px;font-size:16px"><a target="_blank" href="{{url("/viewProjectGeneDetail/$project_id/$gene")}}">{{$gene}}</a></h5>
									@endif

								@endforeach
								</div>						
							</div>						
						</div>
					</div>
					@endif
				</div>
				@endif
			</div>
		</div>
	</div>
<!--/div-->
@stop
