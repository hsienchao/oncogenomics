<?php

//use Log;
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/


Route::get('/getVarGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{tiers}','VarController@getVarGeneSummary');
Route::get('/getCNVGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{min_amplified}/{max_deleted}','VarController@getCNVGeneSummary');
Route::get('/getFusionGeneSummary/{gene_id}/{value_type}/{category}/{min_pat}/{fusion_type}/{tiers}','VarController@getFusionGeneSummary');
Route::get('/getFusionGenePairSummary/{gene_id}/{value_type}/{category}/{min_pat}/{fusion_type}/{tiers}','VarController@getFusionGenePairSummary');
Route::get('/getExpGeneSummary/{gene_id}/{category}/{tissue}/{target_type?}/{lib_type?}','GeneDetailController@getExpGeneSummary');
Route::get('/viewFASTQC/{patient_id}/{case_id}/{path}','VarQCController@viewQC');
Route::get('/viewrnaQC/{patient_id}/{case_id}/{path}','VarQCController@viewQC');
Route::get('/getContent/{patient_id}/{case_id}/{file_path}/{type}/{subtype?}','VarQCController@getContent');
Route::get('/getPatients/{sid}/{search_text?}/{patient_id_only?}/{format?}', 'SampleController@getPatients');
Route::get ('/getPatientMetaData/{pid}/{format?}/{include_diagnosis?}/{includeOnlyRNAseq?}/{include_numeric?}/{meta_list_only?}', 'ProjectController@getPatientMetaData');

Route::group(['before' => ['authorized_project']], function () {
	Route::get('/getSurvivalData/{project_id}/{filter_attr_name1}/{filter_attr_value1}/{filter_attr_name2}/{filter_attr_value2}/{group_by1}/{group_by2}/{group_by_values?}' , 'ProjectController@getSurvivalData');
	Route::get('/getExpressionByGeneList/{project_id}/{patient_id}/{case_id}/{gene_list}/{target_type?}/{library_type?}/{value_type?}', 'ProjectController@getExpressionByGeneList');
	Route::get('/getExpression/{project_id}/{gene_list}/{target_type?}/{library_type?}', 'ProjectController@getExpression');
	Route::get('/getProjectCNV/{project_id}/{gene_list}', 'ProjectController@getCNV');
	Route::get('/getExpressionByLocus/{project_id}/{patient_id}/{case_id}/{chr}/{start_pos}/{end_pos}/{target_type}/{library_type}', 'ProjectController@getExpressionByLocus');
	Route::get('/getCases/{project_id}'                       , 'SampleController@getCases');
	Route::get('/getProjectSummary/{project_id}', 'ProjectController@getProjectSummary');
	Route::get('/getPCAData/{project_id}/{target_type}/{value_type?}' , 'ProjectController@getPCAData');
	Route::get('/getMutationGenes/{project_id}/{type}/{meta_type?}/{meta_value?}/{maf?}/{min_total_cov?}/{vaf?}', 'ProjectController@getMutationGenes' );
	Route::get('/getMutationGeneList/{project_id}/{tier?}', 'ProjectController@getMutationGeneList' );
	Route::get('/getFusionProjectDetail/{project_id}/{cutoff?}', 'ProjectController@getFusionProjectDetail' );
	Route::get('/getFusionGenes/{project_id}/{left_gene}/{right_gene?}/{type?}/{value?}', 'ProjectController@getFusionGenes' );
	Route::get('/getSampleByPatientID/{project_id}/{patient_id}/{case_id?}', 'SampleController@getSampleByPatientID');	
	Route::get('/getProjectQC/{project_id}/{type}', 'ProjectController@getQC' );
	Route::get('/getCorrelationData/{project_id}/{gene_id}/{cufoff}/{target_type}/{method?}/{value_type?}' , 'ProjectController@getCorrelationData');
	Route::get('/getExpMatrixFile/{project_id}/{target_type}/{data_type?}', 'ProjectController@getExpMatrixFile');
	Route::get('/getVarAnnotation/{project_id}/{patient_id}/{sample_id}/{case_id}/{type}', 'VarController@getVarAnnotation'  );
	Route::get('/getVarAnnotationByGene/{project_id}/{gene_id}/{type}'            , 'VarController@getVarAnnotationByGene'  );
	Route::get('/getExpressionByCase/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}'            , 'SampleController@getExpressionByCase'  );
	Route::get('/getGSEAResults/{project_id}/{token_id}'            , 'SampleController@getGSEAResults'  );
	Route::get('/getExpSurvivalData/{project_id}/{target_id}/{level}/{cutoff?}/{target_type?}/{data_type?}/{value_type?}/{diagnosis?}' , 'ProjectController@getExpSurvivalData');
	Route::get('/plotExpSurvival/{project_id}/{target_id}/{level}/{cutoff}/{pvalue}/{target_type}' , 'ProjectController@plotExpSurvival');	
	
});

Route::group(['before' => ['authorized_patient']], function () {
	Route::get('/getPatientProjects/{patient_id}', 'ProjectController@getPatientProjects');
	Route::get('/getCohorts/{patient_id}/{gene}/{type}', 'VarController@getCohorts');
	Route::get('/getSamplesByCaseName/{patient_id}/{case_name}', 'SampleController@getSamplesByCaseName');
	Route::get('/getTierCount/{project_id}/{patient_id}/{case_id?}', 'SampleController@getTierCount');
	Route::get('/getQCLogs/{patient_id}/{case_id}/{log_type}', 'VarController@getQCLogs' );
	Route::get('/getQC/{patient_id}/{case_id}/{type}', 'VarController@getQC' );
	Route::get('/publishCase/{patient_id}/{case_id}', 'SampleController@publishCase');
	Route::get('/getHLAData/{patient_id}/{case_id}/{sample_name}'            , 'VarController@getHLAData'  );
	Route::get('/getAntigenData/{project_id}/{patient_id}/{case_id}/{sample_id}'            , 'VarController@getAntigenData'  );
	Route::get('/downloadAntigenData/{patient_id}/{case_id}/{sample_name}'            , 'VarController@downloadAntigenData'  );
	Route::get('/downloadHLAData/{patient_id}/{case_id}/{sample_name}'            , 'VarController@downloadHLAData'  );
	Route::get('/createReport'            , 'VarController@createReport'  );
	Route::get('/getExpressionByCase/{patient_id}/{case_id}/{target_type?}/{sample_id?}'            , 'SampleController@getExpressionByCase'  );
	Route::get('/getGSEA/{project_id}/{patient_id}/{case_id}/{sample_id}'            , 'SampleController@getGSEA'  );
	Route::post('/GSEAcalc/{project_id}/{patient_id}/{case_id}'            , 'SampleController@GSEAcalc'  );
	Route::get('/getVarActionable/{patient_id}/{case_id}/{type}/{flag}', 'VarController@getVarActionable'  );
	Route::get('/getVCF/{patient_id}/{case_id}'            , 'VarController@getVCF'  );
	Route::get('/downloadProjectVariants/{project_id}/{type}'            , 'ProjectController@downloadProjectVariants'  );
	Route::get('/getQCPlot/{patient_id}/{case_id}/{type}'            , 'VarQCController@getQCPlot'  );
	Route::get('/getCoveragePlotData/{patient_id}/{case_name}/{samples}'            , 'VarQCController@getCoveragePlotData'  );

	Route::get('/getCNVPlot/{patient_id}/{sample_name}/{case_id}/{type}'            , 'VarController@getCNVPlot'  );
	Route::get('/getmixcrPlot/{patient_id}/{sample_name}/{case_id}/{type}'            , 'SampleController@getmixcrPlot'  );
	Route::get('/getmixcrTable/{patient_id}/{sample_name}/{case_id}/{type}'            , 'SampleController@getmixcrTable'  );

	Route::get('/getCNV/{patient_id}/{case_id}/{sample_id}/{source?}'            , 'VarController@getCNV'  );	
	Route::get('/getPatientExpression/{patient_id}/{gene}', 'SampleController@getPatientExpression' );
	Route::get('/signOutCase/{patient_id}/{case_id}/{type}', 'VarController@signOutCase' );
	Route::get('/saveVarAnnoationData/{patient_id}/{case_id}/{type}', 'VarController@saveVarAnnoationData' );
	Route::get('/getSignoutHistory/{patient_id}/{sample_id}/{case_id}/{type}', 'VarController@getSignoutHistory' );
	Route::get('/getSignoutVars/{patient_id}/{sample_id}/{case_id}/{type}/{update_at}', 'VarController@getSignoutVars' );
	Route::get('/downloadSignout/{patient_id}/{filename}', 'VarController@downloadSignout' );

	Route::get('/getCircosData/{patient_id}/{case_id}', 'VarController@getCircosData' );
	Route::get('/getCircosDataFromDB/{patient_id}/{case_id}', 'VarController@getCircosDataFromDB' );
	Route::get('/getHotspotCoverage/{patient_id}/{case_id}', 'VarQCController@getHotspotCoverage' );

});

Route::group(['before' => ['logged', 'can_see']], function () {
	Route::get('/', 'BaseController@viewHome');
	Route::get('/home', 'BaseController@viewHome');
	Route::get('/getTopVarGenes','VarController@getTopVarGenes');
	Route::get ('/viewCreateProject'                   , 'ProjectController@viewCreateProject' );
	Route::get ('/viewEditProject/{project_id}'               , 'ProjectController@viewEditProject'   );
	Route::post('/saveProject'                     , 'ProjectController@saveProject'          );
	Route::get ('/deleteProject/{project_id}'             , 'ProjectController@deleteProject'        );
	                                                                                         
	Route::get('/viewGeneDetail/{gene_id}' , 'GeneDetailController@viewGeneDetail'   );
	Route::get('/viewProjectGeneDetail/{project_id}/{gid}/{tab_id?}' , 'GeneDetailController@viewProjectGeneDetail'   );
	Route::get('/viewPatient/{project_name}/{patient_id}/{case_id?}'                       , 'SampleController@viewPatient');
	Route::get('/viewCase/{project_name}/{patient_id}/{case_id}/{with_header?}'                       , 'SampleController@viewCase');
	Route::get('/viewCases/{project_id}'                       , 'SampleController@viewCases');		
	Route::get('/viewPatients/{sid}/{search_text}/{include_header}/{source}'                       , 'SampleController@viewPatients');

	Route::get('/viewProjects', 'ProjectController@viewProjects');
	Route::get('/viewExpression/{project_id}/{patient_id?}/{case_id?}/{meta_type?}/{setting?}', 'ProjectController@viewExpression');
	Route::get('/viewExpressionByGene/{project_id}/{gene_id}', 'ProjectController@viewExpressionByGene');
	Route::get('/getProjects', 'ProjectController@getProjects');	
	Route::get('/getGeneListByLocus/{chr}/{start_pos}/{end_pos}/{target_type}', 'GeneController@getGeneListByLocus');
	
	Route::get('/viewProjectDetails/{project_id}', 'ProjectController@viewProjectDetails');
	Route::get('/viewIGV/{patient_id}/{sample_id}/{case_id}/{type}/{center}/{locus}', 'VarController@viewIGV');
	
	Route::get('/getFlagHistory/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}', 'VarController@getFlagHistory');
	Route::get('/getFlagStatus/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}', 'VarController@getFlagStatus');
	Route::get('/deleteFlag/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{patient_id}/{updated_at}', 'VarController@deleteFlag');
	Route::get('/deleteACMGGuide/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}/{updated_at}', 'VarController@deleteACMGGuide');
	Route::get('/getACMGGuideClass/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}', 'VarController@getACMGGuideClass');
	Route::get('/getACMGGuideHistory/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{patient_id}', 'VarController@getACMGGuideHistory');

	Route::get('/addFlag/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{type}/{old_status}/{new_status}/{patient_id}/{is_public}/{comment}', 'VarController@addFlag');
	Route::get('/addACMGClass/{chromosome}/{start_pos}/{end_pos}/{ref}/{alt}/{mode}/{classification}/{checked_list}/{patient_id}/{is_public}', 'VarController@addACMGClass');
	
	Route::post('/downloadCaseExpression', 'SampleController@downloadCaseExpression');
	Route::get('/viewVarProjectDetail/{project_id}/{type}/{diagnosis?}', 'ProjectController@viewVarProjectDetail');
	Route::get('/viewFusionProjectDetail/{project_id}',  'ProjectController@viewFusionProjectDetail');
	Route::get('/viewFusionGenes/{project_id}/{left_gene}/{right_gene?}/{type?}/{value?}',  'ProjectController@viewFusionGenes');

	Route::get('/viewVarAnnotation/{project_id}/{patient_id}/{sample_id}/{case_id}/{type}'            , 'VarController@viewVarAnnotation'  );	
	Route::get('/getVarAnnotationByVariant/{chr}/{start}/{end}/{ref}/{alt}'            , 'VarController@getVarAnnotationByVariant'  );
	Route::get('/insertVariant/{chr}/{start}/{end}/{ref}/{alt}'            , 'VarController@insertVariant'  );

	Route::get('/viewVariant/{chr}/{start}/{end}/{ref}/{alt}'            , 'VarController@viewVariant'  );

	Route::get('/viewVarAnnotationByGene/{project_id}/{gene_id}/{type}/{with_header?}/{tier_type?}/{tier?}/{meta_type?}/{meta_value?}/{patient_id?}/{no_fp?}/{maf?}/{total_cov?}/{vaf?}'            , 'VarController@viewVarAnnotationByGene'  );
	
	
	Route::get('/viewExpressionByCase/{project_id}/{patient_id}/{case_id}/{sample_id?}'            , 'SampleController@viewExpressionByCase'  );
	Route::get('/viewMixcrTable/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}', 'SampleController@viewMixcrTable' );
	
	Route::get('/viewGSEA/{project_id}/{patient_id}/{case_id}/{token_id}'            , 'SampleController@viewGSEA'  );	
	Route::get('/viewGSEAResults/{project_id}/{token_id}'            , 'SampleController@viewGSEAResults'  );
	Route::get('/getGSEAInput/{token_id}'            , 'SampleController@getGSEAInput'  );
	Route::get('/downloadGSEAResults/{project_id}/{token_id}'            , 'SampleController@downloadGSEAResults'  );	
	Route::get('/removeGSEArecords/{token_id}'            , 'SampleController@removeGSEArecords'  );
	Route::get('/viewContact'                       , function() { return View::make('pages/viewContact'       ); });
	Route::get('/viewAPIs'                       , function() { return View::make('pages/viewAPIs'       ); });
	Route::get('/getSample/{id}'                       , 'SampleController@getSample');
	
	Route::get('/getCaseDetails/{sid}/{search_text}/{case_id}/{patient_id_only?}'                       , 'SampleController@getCaseDetails');

	Route::get('/getPatientIDs/{sid}/{search_text}'                       , 'SampleController@getPatientIDs');
	Route::get('/viewPatientTree/{custom_id}'                       , 'SampleController@viewPatientTree');
	Route::get('/viewGenotyping/{id}/{type?}/{source?}/{has_header?}'                       , 'SampleController@viewGenotyping');
	Route::get('/getPatientTreeJson/{id}'                       , 'SampleController@getPatientTreeJson');
	Route::get('/getCasesByPatientID/{project_id}/{patient_id}'                       , 'SampleController@getCasesByPatientID');
	Route::get('/getCaseSummary{case_id}/'                       , 'SampleController@getCaseSummary');
	Route::get('/getpipeline_summary/{patient_id}/{case_id}'                       , 'SampleController@getpipeline_summary');
	Route::get('/getAvia_summary'                       , 'SampleController@getAvia_summary');

	Route::get('/getGenotyping/{id}/{type?}/{source?}'                       , 'SampleController@getGenotyping');
	Route::get('/getPatientGenotyping/{patient_id}/{case_id}'                       , 'SampleController@getPatientGenotyping');
	Route::get ('/getTranscriptExpressionData/{gene_list}/{sample_id}', 'GeneDetailController@getTranscriptExpressionData');
	
	
	Route::get('/getCNVByGene/{project_id}/{gene_id}'            , 'VarController@getCNVByGene'  );
	Route::get('/getFusionByPatient/{patient_id}/{case_id}'            , 'VarController@getFusionByPatient'  );
	Route::get('/viewFusion/{patient_id}/{case_id}/{with_header?}', 'VarController@viewFusion'  );
	Route::get('/getFusion/{patient_id}/{case_id}', 'VarController@getFusion'  );

	Route::get('/getVarDetails/{type}/{chr}/{start_pos}/{end_pos}/{ref_base}/{alt_base}/{gene_id}', 'VarController@getVarDetails'  );
	Route::get('/getVarSamples/{chr}/{start_pos}/{end_pos}/{ref_base}/{alt_base}/{patient_id}/{case_id}/{type}', 'VarController@getVarSamples'  );
	Route::get('/getBAM/{path}/{patient_id}/{case_id}/{sample_id}/{file}', 'VarController@getBAM');

	Route::get('/getPatientDetails/{patient_id}'            , 'SampleController@getPatientDetails'  );

	Route::get('/updatePatientDetail/{patient_id}/{old_key}/{key}/{value}', 'SampleController@updatePatientDetail'  );
	Route::get('/addPatientDetail/{patient_id}/{key}/{value}', 'SampleController@addPatientDetail'  );
	Route::get('/deletePatientDetail/{patient_id}/{key}', 'SampleController@deletePatientDetail'  );
	Route::get('/getExpSamplesFromVarSamples/{sample_list}', 'SampleController@getExpSamplesFromVarSamples'  );
	Route::get('/getIGVLink/{patient_id}/{locus}', 'SampleController@getIGVLink'  );
	Route::get('/getSignaturePlot/{patient_id}/{sample_name}/{case_id}', 'VarController@getSignaturePlot');
	Route::get('/viewSetting', 'UserSettingController@viewSetting'  );
	Route::get('/viewIGVByLocus/{locus}', function($locus) { return View::make('pages/viewIGV', ['locus'=>$locus]);});

	Route::get('/viewUploadClinicalData',  function() { return View::make('pages/viewUploadClinicalData', ["projects" => User::getCurrentUserProjects()]);});
	Route::get('/viewUploadVarData',  function() { return View::make('pages/viewUploadVarData', ["projects" => User::getCurrentUserProjectsData()]);});

	Route::get('/calculateTransFusionData/{left_gene}/{left_trans}/{right_gene}/{right_trans}/{left_junction}/{right_junction}',  'VarController@calculateTransFusionData');
	Route::get('/getFusionDetailData/{left_gene}/{left_trans}/{right_gene}/{right_trans}/{left_chr}/{right_chr}/{left_junction}/{right_junction}/{sample_id}',  'VarController@getFusionDetailData');
	Route::get('/getFusionData/{left_gene}/{right_gene}/{left_chr}/{right_chr}/{left_junction}/{right_gene_junction}/{sample_id}/{type}', 'VarController@getFusionData');
	Route::post('/saveGeneList', 'UserSettingController@saveGeneList'  );
	Route::post('/saveSetting/{attr_name}', 'UserSettingController@saveSetting'  );
	Route::post('/saveSystemSetting/{attr_name}', 'UserSettingController@saveSystemSetting'  );
	Route::get('/syncClinomics', 'UserSettingController@syncClinomics'  );

	Route::post('/processVarUpload', 'VarController@processVarUpload'  );
	Route::get('/saveSettingGet/{attr_name}/{attr_value}', 'UserSettingController@saveSettingGet'  );
	Route::post('/saveClinicalData', 'SampleController@saveClinicalData' );
	Route::post('/uploadVarData', 'VarController@uploadVarData' );
	Route::post('/uploadExpData', 'VarController@uploadExpData' );
	Route::post('/uploadFusionData', 'VarController@uploadFusionData' );
	Route::post('/signOut', 'VarController@signOut' );

	Route::get ('/viewProjectPatient/{project_id}'                       , 'ProjectController@viewPatient');
	Route::get('/getProject/{id}', 'ProjectController@getProject' );

	Route::post('/saveQCLog', 'VarController@saveQCLog' );
	Route::get('/getCytobandData', 'VarController@getCytobandData' );
	Route::get('/viewCircos/{patient_id}/{case_id}', 'VarController@viewCircos' );


	Route::get('/viewCNV/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}', 'VarController@viewCNV' );

	Route::get('viewAntigen/{project_id}/{patient_id}/{case_id}/{sample_name}', 'VarController@viewAntigen' );
	Route::get('/viewCNVByGene/{project_id}/{gene_id}', 'VarController@viewCNVByGene' );
	Route::get('/getAllFusions/{patient_id?}', 'VarController@getAllFusions' );	

	Route::get('/getPatientTree', 'ProjectController@getPatientTree');
	Route::get('/getOncoTree', 'ProjectController@getOncoTree');
	Route::post('/runPipeline', 'SampleController@runPipeline');

	Route::get('/viewVarQC/{patient_id}/{case_id}',  'VarQCController@viewVarQC');
	Route::get('/viewProjectQC/{project_id}',  'VarQCController@viewProjectQC');

	Route::get('/getPatientsByFusionGene/{gene_id}/{cat_type}/{category}/{fusion_type}/{tiers}', 'VarController@getPatientsByFusionGene');
	Route::get('/getPatientsByFusionPair/{left_gene}/{right_gene}/{fusion_type}/{tiers}', 'VarController@getPatientsByFusionPair');
	Route::get('/getPatientsByVarGene/{gene_id}/{type}/{cat_type}/{category}/{tiers}', 'VarController@getPatientsByVarGene');
	Route::get('/getPatientsByCNVGene/{gene_id}/{cat_type}/{category}/{min_amplified}/{max_deleted}', 'VarController@getPatientsByCNVGene');
	Route::get ('/getMutationBurden/{project_id}/{patient_id}/{case_id}', 'VarController@getMutationBurden');
	Route::get ('/viewMutationBurden/{project_id}/{patient_id}/{case_id}', 'VarController@viewMutationBurden');
	
	//unused links

	Route::get('/viewSample/{id}'                       , 'SampleController@viewSample');
	Route::get('/viewSTR/{id}'                       , 'SampleController@viewSTR');
	Route::get('/getSampleByBiomaterialID/{id}'                       , 'SampleController@getSampleByBiomaterialID');
	Route::get('/viewBiomaterial/{id}'                       , 'SampleController@viewBiomaterial');
	Route::get('/getBiomaterial/{id}'                       , 'SampleController@getBiomaterial');
	Route::get('/getSampleDetails/{id}'                       , 'SampleController@getSampleDetails');
	Route::get('/getSTR/{id}'                       , 'SampleController@getSTR');
	Route::get('/getStudies'                       , 'StudyController@getStudies');
	Route::get('/viewStudyDetails/{id}'                       , 'StudyDetailController@viewStudyDetails');
	Route::get('/getStudyDetails/{id}'                       , 'StudyDetailController@getStudyDetails');
	Route::get('/viewCorrelation/{sid}/{gid}' , 'GeneDetailController@viewCorrelation'   );
	Route::get('/getCorrelationHeatmapData/{sid}/{gid}/{cufoff}/{topn}/{target_type}' , 'GeneDetailController@getCorrelationHeatmapData');
	Route::get('/getTTestHeatmapData/{sid}/{gid}/{target_type}' , 'GeneDetailController@getTTestHeatmapData');
	Route::get('/getTwoGenesDotplotData/{sid}/{g1}/{g2}/{target_type}' , 'ProjectController@getTwoGenesDotplotData'   );
	Route::get('/getStudyQueryData/{sid}/{gene_list}/{target_type}' , 'StudyDetailController@getStudyQueryData');
	Route::get('/getStudySummaryJson/{sid}' , 'StudyDetailController@getStudySummaryJson');
	Route::get('/getPCAPlatData/{sid}' , 'StudyDetailController@getPCAPlatData');
	Route::get ('/viewStudyQuery/{sid}'           , 'StudyDetailController@viewStudyQuery'         );
	Route::post('/viewStudyQuery/{sid}'           , 'StudyDetailController@viewStudyQuery'         );
	Route::get('/viewExpressionHeatmapByLocus/{sid}/{chr}/{start}/{end}/{target_type}'           , 'StudyDetailController@viewExpressionHeatmapByLocus'         );
	Route::get ('/getGeneDetailExpressionData/{sid}/{gid}/{target_type}', 'GeneDetailController@getGeneDetailExpressionData');
	Route::get ('/getGeneStructure/{gid}/{target_type}', 'GeneDetailController@getGeneStructure');
	Route::get ('/getCodingSequences/{gid}/{target_type}', 'GeneDetailController@getCodingSequences');
	Route::get ('/hasEnsemblData/{sid}', 'StudyDetailController@hasEnsemblData');

	Route::get ('/getPfamDomains/{symbol}', 'VarianceController@getPfamDomains');
	Route::get ('/predictPfamDomain/{seq}', 'GeneDetailController@predictPfamDomain');
	Route::get ('/getSampleMutation/{sample_id}/{gene_id}', 'VarianceController@getSampleMutation');
	Route::get ('/getRefMutation/{sample_id}/{gene_id}', 'VarianceController@getRefMutation');
	Route::get ('/viewMutationPlot/{sample_id}/{gene_id}/{type}', 'VarController@viewMutationPlot');
	Route::get ('/getMutationPlotData/{sample_id}/{gene_id}/{type}', 'VarController@getMutationPlotData');
	Route::get ('/downloadExampleExpression/{type}', 'ProjectController@downloadExampleExpression');

	//end of unused links
});

Route::get ('/getCaseByLibrary/{sample_name}/{FCID}', 'SampleController@getCaseByLibrary');
Route::get ('/getPatientsJsonByProject/{project_name}/{patient_list?}/{exp_types?}/{excluded_list?}', 'SampleController@getPatientsJsonByProject');
Route::get ('/getPatientsJson/{patient_list}/{case_id_list?}/{exp_types?}/{source?}/{fcid?}/{do_format?}/{sample_name?}/{excluded_samples?}', 'SampleController@getPatientsJson');
Route::post ('/getPatientsJson', 'SampleController@getPatientsJsonByPost');
Route::post ('/getPatientsJsonByFCID', 'SampleController@getPatientsJsonByFCID');
Route::get ('/getPatientsJsonV2/{patient_list}/{case_id_list?}/{exp_types?}/{source?}/{fcid?}/{do_format?}/{sample_name?}/{excluded_samples?}', 'SampleController@getPatientsJsonV2');
Route::get('/getChIPseqSampleSheet/{sample_id}'            , 'SampleController@getChIPseqSampleSheet'  );
Route::get('/calculateGeneFusionData/{left_gene}/{right_gene}/{left_chr}/{right_chr}/{left_junction}/{right_junction}',  'VarController@calculateGeneFusionData');
Route::get('/getAAChangeHGVSFormat/{chr}/{start_pos}/{end_pos}/{ref}/{alt}/{gene}/{transcript}',  'VarController@getAAChangeHGVSFormat');
Route::get('/getVarTier/{patient_id}/{case_id}/{type}/{sample_id?}/{annotation?}/{avia_table_name?}',  'VarController@getVarTier');
Route::get('/predictPfamDomain/{id}/{seq}',  'GeneController@predictPfamDomain');
Route::post('/getFusionBEDPE', 'VarController@getFusionBEDPE');
Route::post('/getFusionBEDPEv2', 'VarController@getFusionBEDPEv2');
Route::post('/getFusionBEDPEv3', 'VarController@getFusionBEDPEv3');
Route::post('/getVariants', 'VarController@getVariants');


Route::post('/downloadVariants', 'VarController@downloadVariants');
Event::listen('illuminate.query', function($query)
{
    //Log::info($query);
});

Route::filter('check_ip', function()
{
    //$deny = array("10.133.25.40");
	//$accept = array("10.133.27.37");
	//$ip = getenv('REMOTE_ADDR');
	//return $ip;
	//if (in_array ($ip, $deny)) {
	//if (!in_array ($ip, $accept)) {
		//App::abort(401, 'Your IP is not authorized');
		//View::make('pages/error', ['message' => "You IP is not authorized!"]);
	//} 
});

Route::when('*', 'check_ip');

