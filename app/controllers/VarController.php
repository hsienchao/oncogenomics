<?php

/**
 *
 * VarController is the main controller dealing with variant data including mutation, CNV and fusion.
 *
 * @copyright 2018 Javed Khan's group
 * @author Hsien-chao Chou, Scott Goldweber
 * @package controllers
 */

class VarController extends BaseController {

	/**
	 * 
	 * This function generates the view object for variant pages. The type of variants could be germline, somatic, rnaseq or variants (unpaired sample).
	 *
	 * <b>Use case I, showing variants at patient level:</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotation/22112/CL0036/null/20160415/germline
	 *
	 * This URL will return germline mutation page of patient CL0035 of case 20160415.The mutation cohort will be the calculation of project 22112.
	 *
	 * <b>Use case II, showing variants at library level:</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotation/22112/CL0036/CL0036_N1D_E_HWMTYBGXX/20160415/germline.
	 *
	 * This URL will return germline mutation page of library CL0035_T1D_E_HWJW5BGXX of case 20160415.
	 *
	 * <b>Use case III, showing merged variants</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotation/22112/CL0045/null/any/germline.
	 *
	 * This URL will return merged germline mutation page for all cases.
	 *	 
	 * @param int $project_id project ID. We use this project ID to calculatr the gene/site cohort mutation percentage.
	 * @param string $patient_id patient ID
	 * @param string $sample_id sample ID. If view is at library level (exome or panel), this sample ID must be provided.
	 * @param string $case_id case ID. For merged view, use "any". 
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']	 
	 * @return view pages/viewVarDetail view object
	 */
	public function viewVarAnnotation($project_id, $patient_id, $sample_id="null", $case_id="any", $type) {

		//check if the current user does permission to access this patient
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error_no_header', ['message' => 'Access denied or session timed out!']);
		}

		$project = Project::getProject($project_id);
		$filter_definition = Config::get('onco.filter_definition');
		$type_str = ($type == "hotspot")? "all" : $type;
		$filter_lists = UserGeneList::getDescriptions($type_str);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}

		$patient = Patient::where('patient_id', '=', $patient_id)->get()[0];

		$status = "active";
		$exp_type = "null";
		$var_list = "[]";
		if ($sample_id != "null") {
			$sample = Sample::find($sample_id);
			if ($sample == null) {
				return View::make('pages/error_no_header', ['message' => "Sample $sample_id not found!"]);
			}
			$exp_type = $sample->exp_type;
			//if (User::isSignoutManager()) {
				$var_signouts = VarSignout::where('sample_id', '=', $sample_id)->where('case_id', '=', $case_id)->where('type', '=', $type)->orderBy('updated_at', 'desc')->get();
				if (count($var_signouts) > 0) {
					$status = $var_signouts[0]->status;
					$var_list = json_encode(explode(',',$var_signouts[0]->var_list));
				}
			//}
		}

        $setting = UserSetting::getSetting("page.$type"); 
        $show_columns = array_values((array)UserSetting::getSetting("page.columns"))[0];
        if ($project != null && !$project->showFeature('igv')) {
	        if (($key = array_search("IGV", $show_columns)) !== false) {
				unset($show_columns[$key]); 
				$show_columns = array_values($show_columns);
			}
		}
		if (Config::get('site.isPublicSite')) {
			if (($key = array_search("HGMD", $show_columns)) !== false) {
				unset($show_columns[$key]); 
				$show_columns = array_values($show_columns);
			}
		}        
        return View::make('pages/viewVarDetail', ['with_header' => 0, 'project_id' => $project_id, 'patient' => $patient, 'patient_id' => $patient_id, 'sample_id' => $sample_id, 'exp_type' => $exp_type, 'case_id' => $case_id, 'gene_id' => 'null', 'status' => $status, 'type' => $type, 'filter_definition' => $filter_definition, 'setting' => $setting, 'show_columns' => json_encode($show_columns), 'var_list' => $var_list, 'update_setting' => true]);
	}

	/**
	 * 
	 * This function generates the view object for variant pages for specific gene. This view includes a lollipop plot. The type of variants could be germline, somatic, rnaseq or variants (unpaired sample).
	 *
	 * <b>Use case I, showing all MYCN germline variants in project 22112:</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotationByGene/22112/MYCN/germline.
	 *
	 * This URL will return germline mutation page of gene MYCN.The mutation cohort will be the calculation of project 22112.
	 *
	 * <b>Use case II, showing variants for specific diagnosis:</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotationByGene/22112/MYCN/germline/0/null/null/Melanoma.
	 *
	 * This URL will return germline mutation page of diagnosis Melanoma.
	 *
	 * <b>Use case III, showing variants with filters</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewVarAnnotationByGene/22112/BAP1/germline/0/germline/tier1/null/null/false/0.01/20/0.2.
	 *
	 * This URL will return mutation page with filter {Tier: 'Tier1', MAF:0.01, : Totcal_cov: 20, VAF: 0.2}.
	 *	 
	 * @param int $project_id project ID. We use this project ID to calculatr the gene/site cohort mutation percentage.
	 * @param string $gene_id Gene symbol
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']	 
	 * @param int $with_header if in iframe, set it 0 to avoid the menu bar in the iframe
	 * @param string $tier_type Tier type filter. Value could be 'germline' or 'somatic'
	 * @param string $tier Tier filer: ['Tier 1','Tier 2','Tier 3','Tier 4']. Default is the user setting
	 * @param string $diagnosis Diagnosis filer
	 * @param string $patient_id Patient filer
	 * @param string $no_fp Filer for false positive variants
	 * @param string $maf Filer for maximun population frequency
	 * @param string $min_total_cov Filer for total coverage
	 * @param string $vaf Filer for minimun VAF
	 * @return view pages/viewVarDetail view object
	 */
	public function viewVarAnnotationByGene($project_id, $gene_id, $type, $with_header=0, $tier_type = "null", $tier = "null", $meta_type = "null", $meta_value = "null", $patient_id = "null", $no_fp="false", $maf=1, $min_total_cov=0, $vaf=0) {
		//check if the current user does permission to access this patient
		if (!User::hasProject($project_id)) {
			return View::make('pages/error_no_header', ['message' => 'Access denied!']);
		}

		$filter_definition = Config::get('onco.filter_definition');
		$filter_lists = UserGeneList::getDescriptions($type);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$var_list = "[]";
        $setting = UserSetting::getSetting("page.$type");
        
        if ($setting == null)
        	$setting = new stdClass;
        $setting->filters = "[]";
			
        if ($tier_type != "null") {
        	$setting->tier1 = "false";
			$setting->tier2 = "false";
			$setting->tier3 = "false";
			$setting->tier4 = "false";
			$setting->no_tier = "false";
			$setting->maf = $maf;
			$setting->total_cov = $min_total_cov;
			$setting->vaf = $vaf;
        	$setting->{$tier} = "true";
        	if ($type == "rnaseq" || $type == "variants")
        		$setting->tier_type = $tier_type."_only";
        }
        
        $setting->no_fp = $no_fp;

        //$show_columns = (array)(UserSetting::getSetting("page.columns"))["show"];
        $show_columns = array_values((array)UserSetting::getSetting("page.columns"))[0];
        
        $project = Project::getProject($project_id);	        
        if ($project != null && !$project->showFeature('igv')) {
	        if (($key = array_search("IGV", $show_columns)) !== false) {	        	
				unset($show_columns[$key]); 
				$show_columns = array_values($show_columns);
			}
		}
		if (Config::get('site.isPublicSite')) {
			if (($key = array_search("HGMD", $show_columns)) !== false) {
				unset($show_columns[$key]); 
				$show_columns = array_values($show_columns);
			}
		}
		$meta = array();		
		if ($project != null) {
			$meta_list = $project->getProperty("survival_meta_list");		
			$patient_meta = $project->getPatientMetaData(true,false,false,$meta_list);
			$meta = $patient_meta["meta"];
		}

        return View::make('pages/viewVarDetail', ['with_header' => "$with_header", 'project_id' => $project_id, 'patient' => null, 'patient_id' => $patient_id, 'sample_id' => 'null', 'exp_type' => 'null', 'case_id' => 'null', 'gene_id' => $gene_id, 'tier_type' => $tier_type, 'tier' => $tier, 'status' => 'null', 'type' => $type, 'filter_definition' => $filter_definition, 'setting' => $setting, 'show_columns' => json_encode($show_columns), 'var_list' => $var_list, 'update_setting' => false, 'meta_type' => $meta_type, 'meta_value' => $meta_value, 'meta' => $meta]);
	}

	/**
	 * 
	 * This function generates the view object for fusion pages for specific patient/case.
	 *
	 * <b>Use case</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/viewFusion/CL0047/20170912_SmartRNATrim2
	 *
	 * This URL will return fusion list for patient CL0047 and case 20170912_SmartRNATrim2.
	 *
	 * @param string $patient_id patient ID
	 * @param string $case_id case ID. For merged view, use "any". 
	 * @param string $with_header 1: show menu bar, 0: no menu bar (default)
	 * @return view pages/viewVarDetail view object
	 */
	public function viewFusion($patient_id, $case_name, $with_header=0) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error_no_header', ['message' => 'Access denied!']);
		}
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('fusion');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		
        $setting = UserSetting::getSetting("page.fusion");
        $url = url("/getFusion/$patient_id/$case_name");
        $view_id = $with_header ? 'pages/viewFusionHeader' : 'pages/viewFusion';
		return View::make($view_id, ['url' => $url, 'patient_id' => $patient_id, 'case_name' => $case_name, 'filter_definition' => $filter_definition, 'setting' => $setting, 'update_setting' => true]);
	}
	
	/**
	 * 
	 * This function generates fusion table for all or specific patient.
	 *
	 * <b>Use case</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/getAllFusions/all
	 *
	 * This URL will return fusion list for all patients.
	 *
	 * @param string $patient_id patient ID (all for all patients)
	 * @return string tab seperated table 
	 */
	public function getAllFusions($patient_id="all") {
		set_time_limit(5*60);
		if ($patient_id=="all")
			$fusions = DB::table('var_fusion')->get();
		else
			$fusions = DB::table('var_fusion')->where('patient_id', $patient_id)->get();
		$user_filter_list = UserGeneList::getGeneList("fusion");
		$lines = array();
		$header = "";
		foreach ($fusions as $fusion) {
			$left_gene = $fusion->left_gene;
			$right_gene = $fusion->right_gene;
			$fusion_arr = (array) $fusion;
			$headers = array_keys($fusion_arr);
			$data = array_values($fusion_arr);
			if ($header == "") {
				$header = implode("\t", array_merge($headers, array_keys($user_filter_list)));
				$lines[] = $header;
			}
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = (array_key_exists($left_gene, $gene_list) || array_key_exists($right_gene, $gene_list))? "Y":"";
				$data[] = $has_gene;
			}
			$lines[] = implode("\t", $data);
		}
		$content = implode("\n", $lines);
		$filename = "fusion_$patient_id.tsv";
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
		return Response::make($content, 200, $headers);			
	}

	/**
	 * 
	 * This function generates fusion results for specific patient or case.
	 *
	 * <b>Use case</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/getFusion/CL0047/20170912_SmartRNATrim2
	 *
	 * This URL will return results in JSON format (used in JQuery DataTable).
	 *
	 * @param string $patient_id patient ID (all for all patients)
	 * @return string tab seperated table 
	 */
	public function getFusion($patient_id, $case_name) {
		$fusions = VarAnnotation::getFusionByPatient($patient_id, $case_name);		
		//$fusion_array = $fusion->toArray();
		$new_array = array();
		$user_filter_list = UserGeneList::getGeneList("fusion");
		foreach ($fusions as $fusion) {
			$row = (array)$fusion;
			$details = array("Plot" => "");
			if ($row["type"] != "No-info")
				$details = array("Plot" => "<img width=20 height=20 src='".url('images/details_open.png')."'></img>");
			$row["type"] = $this->formatLabel($row["type"]);
			$row["var_level"] = $this->formatLabel($row["var_level"]);
			$row = $details + $row;
			$new_array[] = $row;
		}
		return $this->getDataTableJson($new_array);		
	}

	/**	 
	 *
	 * @deprecated
	 *	 
	 */
	public function saveVarAnnoationData($project_id, $patient_id, $case_id, $type) {
		$var = VarAnnotation::getVarAnnotation("null", $patient_id, $case_id, "null", $type);
		list($data, $columns) = $var->getDataTable();
		$json = json_encode(array("data"=>$data, "cols"=>$columns));
		return $json;
	}

	/**
	 * 
	 * This function generates flag history JSON table for specific variant of a patient.
	 *
	 * <b>Use case</b>
	 * 
	 * Route => https://clinomics.ncifcrf.gov/production/public/getFlagHistory/chr3/52442567/52442567/G/A/germline/CL0073
	 *
	 * This URL will return fusion list for all patients.
	 *
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @param string $patient_id patient ID	 
	 * @return string results in JSON format
	 */
	public function getFlagHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id) {
		$comment_historys = VarAnnotation::getFlagHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id);
		foreach ($comment_historys as $comment_history) {
			$comment_history->var_comment = "<PRE>".$comment_history->var_comment."</PRE>";
			if (User::isSuperAdmin()) {
				$comment_history->{'Action'} = "<a class='btn btn-warning' href=\"javascript:deleteFlag('$chromosome', '$start_pos', '$end_pos', '$ref', '$alt', '$type', '$patient_id', '$comment_history->updated_at')\">Delete</a>";
			}
		}
		return $this->getDataTableJson($comment_historys);
	}

	/**
	 * 
	 * This function deletesl comment of specific variant of a patient.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @param string $patient_id patient ID	 
	 * @param string $updated_at timestamp of comment	 
	 * @return string comment history in JSON format
	 */
	public function deleteFlag($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id, $updated_at) {
		if (!User::isSuperAdmin())
			return "Access denied";
		VarFlagDetails::where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->where('patient_id', $patient_id)->where('updated_at', $updated_at)->update(array('status' => '-1'));
		$historys = $this->getFlagHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id);
		if (count($historys["data"]) == 0)
			VarFlag::where('patient_id', $patient_id)->where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->delete();
		return $historys;

	}
	
	/**
	 * 
	 * This function deletes ACMG guide of specific variant of a patient.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $patient_id patient ID	 
	 * @param string $updated_at timestamp of comment	 
	 * @return string ACMG comment history in JSON format
	 */
	public function deleteACMGGuide($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id, $updated_at) {
		if (!User::isSuperAdmin())
			return "Access denied";
		VarACMGGuideDetails::where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->where('patient_id', $patient_id)->where('updated_at', $updated_at)->update(array('status' => '-1'));
		VarAnnotation::updateACMGGuideClass($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id);
		$historys = $this->getACMGGuideHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id);
		if (count($historys["data"]) == 0)
			VarACMGGuide::where('patient_id', $patient_id)->where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->delete();
		return $historys;

	}

	/**
	 * 
	 * This function returns the class of ACMG comment of specific variant of a patient.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $patient_id patient ID	 
	 * @return string ACMG comment class in JSON format
	 */
	public function getACMGGuideClass($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id) {
		$acmg_class = VarAnnotation::getACMGGuideClass($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id);
		return json_encode($acmg_class);
	}
	
	/**
	 * 
	 * This function returns the ACMG comment history of specific variant of a patient.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $patient_id patient ID	 
	 * @return string ACMG comment history in JSON format
	 */
	public function getACMGGuideHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id) {
		$acmg_historys = VarAnnotation::getACMGGuideHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id);
		foreach ($acmg_historys as $acmg_history) {
			$acmg_id = "btn_".implode('_', [$chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id]);
			$action_html = "<a id='$acmg_id' class='btn btn-info' href=\"javascript:copyACMG('$acmg_history->checked')\">Copy</a>";
			if (User::isSuperAdmin()) {
				$action_html .= "&nbsp;&nbsp;<a class='btn btn-warning' href=\"javascript:deleteACMGGuide('$chromosome', '$start_pos', '$end_pos', '$ref', '$alt', '$patient_id', '$acmg_history->updated_at')\">Delete</a>";
			}
			$acmg_history->{'Action'} = $action_html;
		}
		return  $this->getDataTableJson($acmg_historys);
	}

	/**
	 * 
	 * This function returns the comment status of specific variant of a patient.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @param string $patient_id patient ID	 
	 * @return string comment status
	 */
	public function getFlagStatus($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id) {
		return VarAnnotation::getFlagStatus($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id);
	}

	/**
	 * 
	 * This function allows users to add a new comment and returns the comment history.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @param string $old_status not used, always 1	 
	 * @param string $new_status not used, always 1
	 * @param string $patient_id patient ID	 
	 * @param string $is_public flag indicating if other users can see this
	 * @param string $comment comment string
	 * @return array comment history
	 */
	public function addFlag($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $old_status, $new_status, $patient_id, $is_public, $comment) {
		$logged_user = User::getCurrentUser();
		if ($logged_user == null)
			return "NoUserID";
		try {
			if ($old_status == '0') {
				$var_flag = new VarFlag;	
				$var_flag->chromosome = $chromosome;
				$var_flag->start_pos = $start_pos;			
				$var_flag->end_pos = $end_pos;
				$var_flag->ref = $ref;
				$var_flag->alt = $alt;
				$var_flag->patient_id = $patient_id;
				//$var_flag->type = $type;
				$var_flag->status = $new_status;
				$var_flag->save();
			} else
				DB::table('var_flag')->where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->where('patient_id', $patient_id)->update(['status' => $new_status]);
			$var_flag_details = new VarFlagDetails;	
			$var_flag_details->chromosome = $chromosome;
			$var_flag_details->start_pos = $start_pos;			
			$var_flag_details->end_pos = $end_pos;
			$var_flag_details->ref = $ref;
			$var_flag_details->alt = $alt;
			$var_flag_details->patient_id = $patient_id;
			$var_flag_details->type = $type;
			$var_flag_details->user_id = $logged_user->id;
			$var_flag_details->is_public = $is_public;
			$var_flag_details->var_comment = $comment;
			$var_flag_details->status = '1';
			$var_flag_details->save();				
		} catch (\Exception $e) { 
				return $e->getMessage();			
		}
		return  $this->getFlagHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $type, $patient_id);
	}

	/**
	 * 
	 * This function allows users to add a new comment and returns the comment history.
	 *	 
	 * @param string $chromosome chromosome : ['chr1', 'chr2' ... 'chrX']
	 * @param int $start_pos start position (one-based)
	 * @param int $end_pos end position (one-based)
	 * @param string $ref reference sequence
	 * @param string $alt mutation sequence
	 * @param string $mode ['append','update']
	 * @param string $classification ACMG classification (Pathogenic...)	 
	 * @param string $checked_list The options checked
	 * @param string $patient_id patient ID	 
	 * @param string $is_public flag indicating if other users can see this
	 * @param string $comment comment string
	 * @return array ACMG history
	 */
	public function addACMGClass($chromosome, $start_pos, $end_pos, $ref, $alt, $mode, $classification, $checked_list, $patient_id, $is_public, $comment = '') {
		$logged_user = User::getCurrentUser();
		if ($logged_user == null)
			return "NoUserID";
		try {
			if ($checked_list == "null")
				$checked_list = "";
			if ($mode == 'append') {
				$var_acmg_guide = new VarACMGGuide;	
				$var_acmg_guide->chromosome = $chromosome;
				$var_acmg_guide->start_pos = $start_pos;			
				$var_acmg_guide->end_pos = $end_pos;
				$var_acmg_guide->ref = $ref;
				$var_acmg_guide->alt = $alt;
				$var_acmg_guide->patient_id = $patient_id;
				$var_acmg_guide->class = $classification;
				$var_acmg_guide->checked_list = $checked_list;
				$var_acmg_guide->save();
			} else
				DB::table('var_acmg_guide')->where('chromosome', $chromosome)->where('start_pos', $start_pos)->where('end_pos', $end_pos)->where('ref', $ref)->where('alt', $alt)->where('patient_id', $patient_id)->update(['class' => $classification, 'checked_list' => $checked_list]);
			$var_acmg_guide_details = new VarACMGGuideDetails;	
			$var_acmg_guide_details->chromosome = $chromosome;
			$var_acmg_guide_details->start_pos = $start_pos;			
			$var_acmg_guide_details->end_pos = $end_pos;
			$var_acmg_guide_details->ref = $ref;
			$var_acmg_guide_details->alt = $alt;
			$var_acmg_guide_details->patient_id = $patient_id;
			$var_acmg_guide_details->class = $classification;
			$var_acmg_guide_details->checked_list = $checked_list;
			$var_acmg_guide_details->user_id = $logged_user->id;
			$var_acmg_guide_details->is_public = $is_public;
			$var_acmg_guide_details->var_comment = $comment;
			$var_acmg_guide_details->status = '1';
			$var_acmg_guide_details->save();				
		} catch (\Exception $e) { 
				return $e->getMessage();			
		}
		return  $this->getACMGGuideHistory($chromosome, $start_pos, $end_pos, $ref, $alt, $patient_id);
	}

	/**
	 * 
	 * This function returns tier information for specific patient/case. This function is used by loadVarPatients.pl -t tier
	 *	 
	 * @param string $patient_id patient ID
	 * @param string $case_id case ID
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @param string $annotation annotation: ['all','khanlab','avia']
	 * @param string $avia_table_name AVIA table name
	 * @return string tab separated text string
	 */
	public function getVarTier($patient_id, $case_id, $type, $sample_id=null, $annotation="all", $avia_table_name="var_sample_avia") {
		set_time_limit(200000);
		$var = new VarAnnotation();
		
		//get AVIA and Khanlab Tier
		$results = array();
		if ($annotation == "all" || $annotation == "avia") {
			$rows_avia = $var->processAVIAPatientData(null, $patient_id, $case_id, $type, $sample_id, null, false, false, $avia_table_name);
			$results["AVIA"] = $rows_avia;
		}
		if ($annotation == "all" || $annotation == "khanlab") {
			$rows_khanlab = $var->processKhanlabPatientData(null, $patient_id, $case_id, $type);
			$results["Khanlab"] = $rows_khanlab;
		}		
		
		$sample_id_col_name = Lang::get("messages.sample_id");
		$somatic_col_name = Lang::get("messages.somatic_level");
		$germline_col_name = Lang::get("messages.germline_level");
		$gene_col_name = Lang::get("messages.gene");
		$maf_col_name = Lang::get("messages.frequency");
		$vaf_col_name = Lang::get("messages.vaf");
		$total_cov_col_name = Lang::get("messages.total_cov");
		$content = "";
		foreach ($results as $annotation => $rows) {
			list($data, $columns) = $var->postProcessVarData($rows, "any", $type);
			$time_start = microtime(true);
			$id_idx = 0;
			$gene_idx = 0;
			$sample_id_idx = 0;
			$germline_tier_idx = 0;
			$somatic_tier_idx = 0;
			$maf_idx = 0;
			$vaf_idx = 0;
			$total_cov_idx = 0;
			if (count($columns) > 0) {		
				$col_names = array_values($columns);
				for ($i=0; $i<count($col_names);$i++) {
					$col_name = $col_names[$i]["title"];
					if (strtolower($col_name) == "id")
						$id_idx = $i;
					if ($col_name == $somatic_col_name)
						$somatic_tier_idx = $i;
					if ($col_name == $sample_id_col_name)
						$sample_id_idx = $i;
					if ($col_name == $germline_col_name)
						$germline_tier_idx = $i;
					if ($col_name == $gene_col_name)
						$gene_idx = $i;
					if ($this->getTagValue($col_name) == $maf_col_name)
						$maf_idx = $i;
					if ($col_name == $vaf_col_name)
						$vaf_idx = $i;
					if ($col_name == $total_cov_col_name)
						$total_cov_idx = $i;

				}
				foreach ($data as $row) {
					$var_id = explode(":", $row[$id_idx]);
					$sample_id = $this->getTagValue($row[$sample_id_idx]);
					$gene = $this->getTagValue($row[$gene_idx]);
					$somatic_tier = $this->remove_badge($row[$somatic_tier_idx]);
					$germline_tier = $this->remove_badge($row[$germline_tier_idx]);
					$maf = $this->getTagValue($row[$maf_idx], 3);
					$vaf = $this->remove_badge($row[$vaf_idx]);
					$total_cov = $this->remove_badge($row[$total_cov_idx]);
					if ($germline_tier == "" && $somatic_tier == "")
						continue;
					if ($type == "germline" && $germline_tier == "")
						continue;
					if ($type == "somatic" && $somatic_tier == "")
						continue;					
					//$content .= $row[$id_idx]."\n";
					$content .= "$var_id[2]\t$var_id[3]\t$var_id[4]\t$var_id[5]\t$var_id[6]\t$sample_id\t$gene\t$somatic_tier\t$germline_tier\t$maf\t$total_cov\t$vaf\t$annotation\n";
				}
			}
			$time = microtime(true) - $time_start;
			Log::info("execution time ($annotation): $time seconds");
		}		
		$headers = array('Content-Type' => 'text/txt');				
		
		return Response::make($content, 200, $headers);

	}

	private function remove_badge($input_string) {
		return str_replace("</span>", "", str_replace("<span class='badge'>", "", $input_string));
	}

	private function getTagValue($input_string, $pos=1) {
		$tmp1 = explode(">", $input_string);
		if (count($tmp1) > $pos) {
			$tmp2 = explode("<", $tmp1[$pos]);
			return $tmp2[0];
		}
		return $input_string;		
	}
	/**
	 * 
	 * This function returns the JQueryTable JSON of specific patient/case/sample. It is called by viewVarDetails page.
	 *	 
	 * @param int $project_id this is used to determine the cohort count
	 * @param string $patient_id patient ID
	 * @param string $sample_id sample ID
	 * @param string $case_id case ID	 
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @return string JQueryTable JSONf
	 */
	public function getVarAnnotation($project_id, $patient_id, $sample_id, $case_id, $type) {
		$time_start = microtime(true);
		$id = "$patient_id-$sample_id-$case_id-$type";
		/*
		if (Config::get('onco.cache.var')) {			
			$var_cache = OncoCache::find($id);
			if ($var_cache)
				return $var_cache->data;			
		}
		*/
		$use_table = Config::get("onco.var.use_table");
		if ($sample_id == "null")
			$var = VarAnnotation::getVarAnnotationByPatient($project_id, $patient_id, $case_id, $type, $use_table);
		else
			$var = VarAnnotation::getVarAnnotationBySample($project_id, $patient_id, $sample_id, $case_id, $type);
		list($data, $columns) = $var->getDataTable();
		/*
		list($domain, $domain_range) = $this->getPfamDomains($gene_id);
		$mutPlotData = $var->getMutationPlotData();
		$margin = 50;
		$min_coord = max(min($domain_range["start_pos"], $mutPlotData->sample->range["start_pos"], $mutPlotData->ref->range["start_pos"]) - $margin, 0);
		$max_coord = max($domain_range["end_pos"], $mutPlotData->sample->range["end_pos"], $mutPlotData->ref->range["end_pos"]) + $margin;
		$var_plot_data= array("domain"=>$domain, "sample_data"=>$mutPlotData->sample->data, "ref_data"=>$mutPlotData->ref->data, "min_coord" => $min_coord, "max_coord" => $max_coord);
		

		$exp_plot_data = array();
		if ($gene_id != 'null') {
			$study = Study::getStudy($sid, "UCSC");
			$gene_exprs = $study->getExpByGenes([$gene_id]);
			$samples = array();
			$log2 = array();
			$zscore = array();
			$mcenter = array();
			$selected = array();
			if (array_key_exists($gene_id, $gene_exprs))
				foreach($gene_exprs[$gene_id] as $sample=>$exp_data) {
					$samples[] = $sample;
					$log2[] = $exp_data->log2;
					$zscore[] = $exp_data->zscore;
					$mcenter[] = $exp_data->mcenter;
					$selected[] = "no";
				}
			$exp_plot_data = array("log2" => $log2, "zscore"=>$zscore, "mcenter"=>$mcenter, "x"=> array("selected" => $selected), "y" => array("smps" => $samples, "vars" => ["expression"], "data" => array($log2)));
		}
		*/
		//return json_encode($columns);
		
		$var_data = json_encode(array("data"=>$data, "cols"=>$columns));
		#Log::info($data);
#		$json_file=fopen("../app/tests/getVarDetailTest_Avia_rnaseq.json","w");
#		fwrite($json_file,$var_data);
		/*
		if (Config::get('onco.cache.var')) {
			$var_cache = new OncoCache;
			$var_cache->id = $id;
			$var_cache->data = $var_data;
			$var_cache->save();
			//$var_cache = User::create(array('id' => $id, data => '$data'));
		}
		*/
		$time = microtime(true) - $time_start;
		Log::info("execution time (getVarAnnotation): $time seconds");
		return $var_data;
	}

	/**
	 * 
	 * This function returns the JQueryTable JSON of specific gene. It is called by viewVarDetails page.
	 *	 
	 * @param int $project_id this is used to determine the cohort count
	 * @param string $gene_id gene ID
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @return string JQueryTable JSON
	 */
	public function getVarAnnotationByGene($project_id, $gene_id, $type) {
		/*
		$id = "$patient_id-$sample_id-$case_id-$type";		
		if (Config::get('onco.cache.var')) {			
			$var_cache = OncoCache::find($id);
			if ($var_cache)
				return $var_cache->data;			
		}
		*/
		$use_table = Config::get("onco.var.use_table");
		$var = VarAnnotation::getVarAnnotationByGene($project_id, $gene_id, $type, $use_table);
		list($data, $columns) = $var->getDataTable();
		list($domain, $domain_range) = $this->getPfamDomains($gene_id);
		$mutPlotData = $var->getMutationPlotData();		
		$margin = 50;
		$min_coord = max(min($domain_range["start_pos"], $mutPlotData->sample->range["start_pos"], $mutPlotData->ref->range["start_pos"]) - $margin, 0);
		$max_coord = max($domain_range["end_pos"], $mutPlotData->sample->range["end_pos"], $mutPlotData->ref->range["end_pos"]) + $margin;
		$var_plot_data= array("domain"=>$domain, "sample_data"=>$mutPlotData->sample->data, "ref_data"=>$mutPlotData->ref->data, "min_coord" => $min_coord, "max_coord" => $max_coord);
		//Log::info(json_encode($mutPlotData->sample->data));
		/*
		$exp_plot_data = array();
		if ($gene_id != 'null') {
			$study = Study::getStudy($sid, "UCSC");
			$gene_exprs = $study->getExpByGenes([$gene_id]);
			$samples = array();
			$log2 = array();
			$zscore = array();
			$mcenter = array();
			$selected = array();
			if (array_key_exists($gene_id, $gene_exprs))
				foreach($gene_exprs[$gene_id] as $sample=>$exp_data) {
					$samples[] = $sample;
					$log2[] = $exp_data->log2;
					$zscore[] = $exp_data->zscore;
					$mcenter[] = $exp_data->mcenter;
					$selected[] = "no";
				}
			$exp_plot_data = array("log2" => $log2, "zscore"=>$zscore, "mcenter"=>$mcenter, "x"=> array("selected" => $selected), "y" => array("smps" => $samples, "vars" => ["expression"], "data" => array($log2)));
		}
		*/
		
		//return json_encode($columns);
		
		$var_data = json_encode(array("data"=>$data, "cols"=>$columns, "var_plot_data" => $var_plot_data));
		/*
		if (Config::get('onco.cache.var')) {
			$var_cache = new OncoCache;
			$var_cache->id = $id;
			$var_cache->data = $var_data;
			$var_cache->save();
			//$var_cache = User::create(array('id' => $id, data => '$data'));
		}
		*/
		return $var_data;
	}

	/**
	 * 
	 * This function returns the JQueryTable JSON of specific gene. It is called by viewVarDetails page.
	 *	 
	 * @param int $project_id this is used to determine the cohort count
	 * @param string $gene_id gene ID
	 * @param string $type variant type: ['germline','somatic','rnaseq','variants']
	 * @return string JQueryTable JSON
	 */
	public function getVarDetails($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $gene_id) {
		if ($type == "stjude")			
			return json_encode(VarAnnotation::getVarStjudeDetails($chr, $start_pos, $end_pos, $ref_base, $alt_base));
		return json_encode(VarAnnotation::getVarDetails($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $gene_id));
		
	}

	public function getVarSamples($chr, $start_pos, $end_pos, $ref_base, $alt_base, $patient_id, $case_id, $type) {
		return json_encode(VarAnnotation::getVarSamples($chr, $start_pos, $end_pos, $ref_base, $alt_base, $patient_id, $case_id, $type));
	}

	public function getPfamDomains($symbol) {
		$gene = Gene::getGene($symbol);
		if ($gene == null)
			return [[],["start_pos"=>0, "end_pos"=>0]];
		return $gene->getPfamDomains();
	}

	public function getMutationPlotData($sid, $patient_id, $gene_id) {
		$var = VarAnnotation::getVarAnnotation($sid, $patient_id, $gene_id);
		list($domain, $domain_range) = $this->getPfamDomains($gene_id);
		$mutPlotData = $var->getMutationPlotData();
		$margin = 50;
		$min_coord = max(min($domain_range["start_pos"], $mutPlotData->sample->range["start_pos"], $mutPlotData->ref->range["start_pos"]) - $margin, 0);
		$max_coord = max($domain_range["end_pos"], $mutPlotData->sample->range["end_pos"], $mutPlotData->ref->range["end_pos"]) + $margin;
		return json_encode(array("domain"=>$domain, "sample_data"=>$mutPlotData->sample->data, "ref_data"=>$mutPlotData->ref->data, "min_coord" => $min_coord, "max_coord" => $max_coord));
	}

	public function getVarDetailsAnnotation($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $sample_id) {
		$variance = DB::select("select * from var_annotation where chr='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref_base = '$ref_base' and alt_base = '$alt_base' and samplename = '$sample_id'");
		$var = $variance[0];
		$type_fields = array("sample_id" => [127,141], "acmg" => [121,127], "grand" => [81, 120], "cancer" => [81, 117], "mycg" => [70,74], "match" => [65,68], "hgmd" => [59,64], 'freq' => [12, 49], 'prediction' => [49,56]);

		$data = array();
		$var_keys = array_keys((array)$var);
		$var_values = array_values((array)$var);
		for ($i=$type_fields[$type][0];$i<$type_fields[$type][1];$i++) {
			if ($var_values[$i] == "" || $var_values[$i] == "-1" || $var_values[$i] == "NA" || $var_values[$i] == "-" || $var_values[$i] == "." || $var_values[$i] == "0") {
				continue;					
			}
			if ($var_keys[$i] == 'acmg_lsdb') {
				$var_values[$i] = '<a target=_blank href="'.$var_values[$i].'">'.$var_values[$i].'</a>';
			}
			if ($var_keys[$i] == 'grand_total') {
				continue;
			}
			$var_keys[$i] = str_replace('hgmd2014_3_', '', $var_keys[$i]);
			$var_keys[$i] = str_replace('acmg_', '', $var_keys[$i]);
			$var_keys[$i] = str_replace('mycg_', '', $var_keys[$i]);
			$data[] = array($this->processColumnName($var_keys[$i]),$var_values[$i]);
		}
		return json_encode(array("data" => $data));
	}

	

	public function getHotspotGenes() {
		$file = storage_path()."/hotspots.txt";
		$hotspots = $this->readFile($file);
		$hotspot_list = array();
		$hotspot_desc = "The hot spot genes include: ";
		foreach ($hotspots as $hotspot) {			
			$hotspot_list[$hotspot] = '';
			$hotspot_desc .= $hotspot.", ";
		}
		$hotspot_desc = rtrim($hotspot_desc, ", ");
		return array($hotspot_list, $hotspot_desc);
	}

	public function getHotspots() {
		$file = storage_path()."/hotspot_sites.txt";
		$hotspots = $this->readFile($file);
		$hotspot_list = array();
		$hotspot_desc = "The hot spots include: ";
		foreach ($hotspots as $hotspot) {
			//$h = explode("\t", $hotspot);
			$h= preg_split('/\s+/', $hotspot);
			$hotspot_list[$h[0]][$h[1]] = '';
			$hotspot_desc .= $h[0]."(".$h[1]."), ";
		}
		$hotspot_desc = rtrim($hotspot_desc, ", ");
		return array($hotspot_list, $hotspot_desc);
	}

	public function readFile($file) {		
		$fh = fopen($file, "rb");
		$lines = array();
		while (!feof($fh) ) {
			$line = fgets($fh);
			$line = trim($line);
			if ($line == '') continue;
			$lines[] = $line;
		}
		fclose($fh);
		return $lines;
	}


	public function processColumnName($colName) {
		return ucfirst(str_replace("_", " ", $colName));
	}
	
	public function getFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction, $sample_id, $type) {		
		if (substr($sample_id, 0, 7) == "Sample_") 
    		$sample_id = substr($sample_id, 7); 

    	$time_start = microtime(true);			   	
    	$trans_exp = Sample::getTranscriptExpression(array($left_gene,$right_gene), array($sample_id), "all");
    	$time = microtime(true) - $time_start;
		Log::info("execution time (getTranscriptExpression): $time seconds");
		
    	$time_start = microtime(true);	
    	$rows = VarAnnotation::getFusionDetailData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction);
    	$time = microtime(true) - $time_start;
		Log::info("execution time (var_fusion_details query time): $time seconds");
		$time_start = microtime(true);	
    	$results = array();
    	$fused_peps = array();
    	$idx = 1;
    	foreach ($rows as $row) {    		
    		$trans_list = json_decode($row->trans_list);
    		$info = array();
    		foreach ($trans_list as $trans_pair) {
    			$left_trans = $trans_pair[0];
    			$right_trans = $trans_pair[1];
    			$left_exp = "NA";
    			$right_exp = "NA";
    			if (array_key_exists($left_trans, $trans_exp[$sample_id][$left_gene]["trans"]))
    				$left_exp = $trans_exp[$sample_id][$left_gene]["trans"][$left_trans];
    			if (array_key_exists($right_trans, $trans_exp[$sample_id][$right_gene]["trans"]))
    				$right_exp = $trans_exp[$sample_id][$right_gene]["trans"][$right_trans];
    			$info[] = array("left_trans"=> $left_trans, "left_exp"=>$left_exp, "right_trans"=>$right_trans, "right_exp"=>$right_exp, "type"=>$row->type);
    			//$fused_peps[$row->fused_pep][] = array("left_trans"=> $left_trans, "left_exp"=>$left_exp, "right_trans"=>$right_trans, "right_exp"=>$right_exp, "type"=>$row->type);    			
    		}
    		$sorted_results[] = array("id"=>"Fused protein$idx","type"=>$row->type, "length" => $row->pep_length, "info" => $info);
    		$idx++;
    	}
    	/*
		$idx = 1;
    	foreach ($fused_peps as $seq => $info) {    		
    		$results[strlen($seq)] = $info;
    		$idx++;
    	}
    	
    	krsort($results);
		
    	$sorted_results = array();
    	$idx = 1;
    	foreach ($results as $key => $info) {    		
    		$sorted_results[] = array("id"=>"Fused protein$idx","type"=>$info[0]["type"], "length" => $key, "info" => $info);
    		$idx++;
    	}
    	
		$idx = 1;    	
		foreach ($results as $key => $info) {
    		$info["id"] = "Fused protein$idx"
    			$results[strlen($seq)] = array("id"=>"Fused protein$idx","type"=>$info[0]["type"], "length" => strlen($seq), "info" => $info);
    		$idx++;
    	}
*/
		$time = microtime(true) - $time_start;
		Log::info("execution time (var_fusion_details process time): $time seconds");

		
    	return json_encode($sorted_results);    	
	}

	//get the pre-calculated gene fusion information
	public function getFusionDetailData($left_gene, $left_trans, $right_gene, $right_trans, $left_chr, $right_chr, $left_junction, $right_junction, $sample_id) {
		//$fusion_details = DB::table('var_fusion_details')->where('left_gene', $left_gene)->where('right_gene', $right_gene)->where('left_position', $left_junction)->where('right_position', $right_junction)->where('left_trans', $left_trans)->where('right_trans', $right_trans)->orderBy('left_gene', 'right_gene')->first();
		$lgene = new Gene($left_gene);
		$rgene = new Gene($right_gene);
		$ltrans = $lgene->getTrans($left_trans, true, true, true);
		$rtrans = $rgene->getTrans($right_trans, true, true, true);
		return $this->calculateTransFusionData($lgene, $ltrans, $rgene, $rtrans, $left_junction, $right_junction, $sample_id);
		if (is_object($fusion_details))
			echo $fusion_details->json;
		else
			echo "{}";
	}

	public function calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction) {
		set_time_limit(240);
		$content = $this->_calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction);
		$headers = array('Content-Type' => 'text/txt');
		return Response::make($content, 200, $headers);
	}

	public function getFusionBEDPE() {
		set_time_limit(2000);
   		$file = Input::file('fusion_file');
   		$target_type = Input::get('annotation');
   		$type = Input::get('type');
   		$protein_seq = Input::get('protein_seq');
   		if ($protein_seq == null)
   			$protein_seq = "n";
   		if ($target_type == null)
   			$target_type = "refseq";
   		$inframe_only = (strtolower($type) == "in-frame");
   		$content = file_get_contents($file->getRealPath());
   		$lines = explode("\n", $content);
   		$content = "";
   		$keys = array();
   		$read_counts = array();
   		$fusion_file = Config::get('onco.fusion_genes');
   		$fusion_pairs = file_get_contents(storage_path()."/data/$fusion_file");
   		$pair_lines = explode("\n", $fusion_pairs);
   		$fusion_list = array();
   		$fusion_pair_list = array();
   		foreach ($pair_lines as $pair_line) {
   			$pairs = explode("\t", $pair_line);
			if (count($pairs) == 2) {
				$fusion_list[$pairs[0]] = '';
				$fusion_list[$pairs[1]] = '';	
				$fusion_pair_list[$pair_line] = '';
			}
   		}
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];
   			$tool = $fields[7];
   			$reads = $fields[8];

   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			if (array_key_exists($key, $read_counts))
   				$read_counts[$key][] = "$tool:$reads";
   			else
   				$read_counts[$key] = array("$tool:$reads");
   		}
   		Log::info("start");
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];   			
   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			$read_count = implode(",", $read_counts[$key]);
   			if (array_key_exists($key, $keys))
   				continue;   			
   			else
   				$keys[$key][] = "";
   			$result = $this->_calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction, $target_type, false, true, $inframe_only, true, ($protein_seq=="y"));
			$res_lines = explode("\n", $result);
			//determine type
			$type_in_frame = false;
			$type_right_in_tact = false;
			$type_left_in_tact = false;
			$type_out_of_frame = false;
			$type_truncated_orf = false;
			$type_alternative_start_codon = false;

			$protein_seqs = array();
			$epitope_seqs = array();
			$left_strand = "";
			$right_strand = "";
			$is_cancer_genes = explode("\t", $res_lines[0]);
			$is_left_cancer_gene = $is_cancer_genes[0];
			$is_right_cancer_gene = $is_cancer_genes[1];
			for ($j=1;$j<count($res_lines);$j++) {
				$res_fields = explode("\t", $res_lines[$j]);
				#Log::info($res_fields);
				if (count($res_fields) < 7)
					continue;
				$fusion_type = $res_fields[0];										
				if (strtolower($fusion_type) == strtolower($type) || $type == "all") {
					$left_strand = $res_fields[4];
					$right_strand = $res_fields[5];
					if ($protein_seq == "y") {
						$epitope_seq = $res_fields[6];
						$pep = $res_fields[7];
						$trans = $res_fields[8];
						$protein_seqs[] = $pep;
						$epitope_seqs[] = $epitope_seq;
					}	
					if (strtolower($fusion_type) == "in-frame")
						$type_in_frame = true;
					if (strtolower($fusion_type) == "right gene intact")
						$type_right_in_tact = true;
					if (strtolower($fusion_type) == "left gene intact")
						$type_left_in_tact = true;
					if (strtolower($fusion_type) == "out-of-frame")
						$type_out_of_frame = true;
					if (strtolower($fusion_type) == "truncated orf")
						$type_truncated_orf = true;
					if (strtolower($fusion_type) == "alternative start codon")
						$type_alternative_start_codon = true;					
				}
			}
			
			if ($left_strand == "")
				continue;
			$fusion_type = "Truncated ORF";
			if ($type_out_of_frame)
				$fusion_type = "Out-of-frame";	
			if ($type_alternative_start_codon)
				$fusion_type = "Alternative start codon";		
			if ($type_left_in_tact)
				$fusion_type = "Left gene intact";		
			if ($type_right_in_tact)
				$fusion_type = "Right gene intact";		
			if ($type_in_frame)
				$fusion_type = "In-frame";
			$tier = $this->getFusionTier($fusion_list, $fusion_pair_list, $fusion_type, $left_gene, $right_gene, $is_left_cancer_gene, $is_right_cancer_gene);
			Log::info("TYPE");
			Log::info($type);
			if ($fusion_type == $type || $type == "all") {
				$data = array($left_chr, $left_junction-1, $left_junction, $right_chr, $right_junction-1, $right_junction, "$left_gene-$right_gene", $left_strand, $right_strand, $sample, $read_count, $fusion_type, $tier, $is_left_cancer_gene, $is_right_cancer_gene);
				Log::info("DATA");
				#Log::info($data);
				if ($protein_seq == "y") {
					$data[] = implode(',', $protein_seqs);
					$data[] = implode(',', $epitope_seqs);
				}
				$content .= implode("\t", $data)."\n";
			}
						
   		}
   		#Log::info(count($res_lines));
   		Log::info("DONE");
   		#Log::info($type);
   		#Log::info($content);
   		return $content;
   	}

   	public function getFusionBEDPEv2() {
		set_time_limit(2000);
   		$file = Input::file('fusion_file');
   		$target_type = Input::get('annotation');
   		$type = Input::get('type');
   		$protein_seq = Input::get('protein_seq');
   		$include_details = Input::get('include_details');
   		$in_exon_only = Input::get('in_exon_only');
   		if ($in_exon_only == null)
   			$in_exon_only = "n";
   		if ($include_details == null)
   			$include_details = "n";
   		if ($protein_seq == null)
   			$protein_seq = "n";
   		if ($target_type == null)
   			$target_type = "refseq";
   		$inframe_only = (strtolower($type) == "in-frame");
   		$content = file_get_contents($file->getRealPath());
   		$lines = explode("\n", $content);
   		$content = "";
   		$keys = array();
   		$read_counts = array();
   		$fusion_file = Config::get('onco.fusion_genes');
   		$fusion_pairs = file_get_contents(storage_path()."/data/$fusion_file");
   		$pair_lines = explode("\n", $fusion_pairs);
   		$fusion_list = array();
   		$fusion_pair_list = array();
   		foreach ($pair_lines as $pair_line) {
   			$pairs = explode("\t", $pair_line);
			if (count($pairs) == 2) {
				$fusion_list[$pairs[0]] = '';
				$fusion_list[$pairs[1]] = '';	
				$fusion_pair_list[$pair_line] = '';
			}
   		}
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];
   			$tool = $fields[7];
   			$reads = $fields[8];

   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			if (array_key_exists($key, $read_counts)) {
   				if ($read_counts[$key] < $reads)
   					$read_counts[$key] = $reads;
   			}
   			else
   				$read_counts[$key] = $reads;
   		}
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];   			
   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			$read_count = $read_counts[$key];
   			if (array_key_exists($key, $keys))
   				continue;   			
   			else
   				$keys[$key][] = "";
   			$result = $this->_calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction, $target_type, false, true, $inframe_only, true, ($protein_seq=="y"), $in_exon_only);
			$res_lines = explode("\n", $result);
			//determine type
			$type_in_frame = false;
			$type_right_in_tact = false;
			$type_left_in_tact = false;
			$type_out_of_frame = false;
			$type_truncated_orf = false;
			$type_alternative_start_codon = false;

			$protein_seqs = array();
			$epitope_seqs = array();
			$positions = array();
			$left_strand = "";
			$right_strand = "";
			$is_cancer_genes = explode("\t", $res_lines[0]);
			$is_left_cancer_gene = $is_cancer_genes[0];
			$is_right_cancer_gene = $is_cancer_genes[1];
			for ($j=1;$j<count($res_lines);$j++) {
				$res_fields = explode("\t", $res_lines[$j]);
				if (count($res_fields) < 7)
					continue;
				$fusion_type = $res_fields[0];										
				if (strtolower($fusion_type) == strtolower($type) || $type == "all") {
					$left_strand = $res_fields[4];
					$right_strand = $res_fields[5];
					if ($protein_seq == "y") {
						$epitope_seq = $res_fields[6];
						$pep = $res_fields[7];
						$positions[] = $res_fields[8];
						$protein_seqs[] = $pep;
						$epitope_seqs[] = $epitope_seq;
					}	
					if (strtolower($fusion_type) == "in-frame")
						$type_in_frame = true;
					if (strtolower($fusion_type) == "right gene intact")
						$type_right_in_tact = true;
					if (strtolower($fusion_type) == "left gene intact")
						$type_left_in_tact = true;
					if (strtolower($fusion_type) == "out-of-frame")
						$type_out_of_frame = true;
					if (strtolower($fusion_type) == "truncated orf")
						$type_truncated_orf = true;
					if (strtolower($fusion_type) == "alternative start codon")
						$type_alternative_start_codon = true;					
				}
			}
			
			if ($left_strand == "")
				continue;
			$fusion_type = "Truncated ORF";
			if ($type_out_of_frame)
				$fusion_type = "Out-of-frame";	
			if ($type_alternative_start_codon)
				$fusion_type = "Alternative start codon";		
			if ($type_left_in_tact)
				$fusion_type = "Left gene intact";		
			if ($type_right_in_tact)
				$fusion_type = "Right gene intact";		
			if ($type_in_frame)
				$fusion_type = "In-frame";
			$tier = $this->getFusionTier($fusion_list, $fusion_pair_list, $fusion_type, $left_gene, $right_gene, $is_left_cancer_gene, $is_right_cancer_gene);
			if ($fusion_type == $type || $type == "all") {
				$data = array(substr($left_chr,3), ($left_strand=="+")? "-1" : $left_junction -1, ($left_strand=="+")? $left_junction : "-1", substr($right_chr,3), ($right_strand=="+")? $right_junction - 1: "-1", ($right_strand=="+")? "-1" : $right_junction, $left_gene.">>".$right_gene, ".",$left_strand, $right_strand, $read_count);
				if ($include_details == "y")
					$data[] = implode(":", array($sample, $fusion_type, $tier, $is_left_cancer_gene, $is_right_cancer_gene));
				if ($protein_seq == "y") {
					$data[] = implode(',', $protein_seqs);
					$data[] = implode(',', $epitope_seqs);
					$data[] = implode(',', $positions);
				}
				$content .= implode("\t", $data)."\n";
			}
						
   		}
   		#Log::info($content);
   		return $content;
   	}

   	public function getFusionBEDPEv3() {
		set_time_limit(2000);
   		$file = Input::file('fusion_file');
   		$target_type = Input::get('annotation');
   		$type = Input::get('type');
   		$protein_seq = Input::get('protein_seq');
   		$include_details = Input::get('include_details');
   		$in_exon_only = Input::get('in_exon_only');
   		if ($in_exon_only == null)
   			$in_exon_only = "n";
   		if ($include_details == null)
   			$include_details = "n";
   		if ($protein_seq == null)
   			$protein_seq = "n";
   		if ($target_type == null)
   			$target_type = "refseq";
   		$inframe_only = (strtolower($type) == "in-frame");
   		$content = file_get_contents($file->getRealPath());
   		$lines = explode("\n", $content);
   		$content = "";
   		$keys = array();
   		$read_counts = array();
   		$fusion_file = Config::get('onco.fusion_genes');
   		$fusion_pairs = file_get_contents(storage_path()."/data/$fusion_file");
   		$pair_lines = explode("\n", $fusion_pairs);
   		$fusion_list = array();
   		$fusion_pair_list = array();
   		foreach ($pair_lines as $pair_line) {
   			$pairs = explode("\t", $pair_line);
			if (count($pairs) == 2) {
				$fusion_list[$pairs[0]] = '';
				$fusion_list[$pairs[1]] = '';	
				$fusion_pair_list[$pair_line] = '';
			}
   		}
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];
   			$tool = $fields[7];
   			$reads = $fields[8];

   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			if (array_key_exists($key, $read_counts)) {
   				if ($read_counts[$key] < $reads)
   					$read_counts[$key] = $reads;
   			}
   			else
   				$read_counts[$key] = $reads;
   		}
   		for ($i=1;$i<count($lines);$i++) {
   			$fields = explode("\t", $lines[$i]);
   			if (count($fields) < 9)
   				continue;
   			$left_gene = $fields[0];
   			$right_gene = $fields[1];
   			$left_chr = $fields[2];
   			$left_junction = $fields[3];
   			$right_chr = $fields[4];   			
   			$right_junction = $fields[5];
   			$sample = $fields[6];   			
   			$key = implode(",", array($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction));
   			$read_count = $read_counts[$key];
   			if (array_key_exists($key, $keys))
   				continue;   			
   			else
   				$keys[$key][] = "";
   			$result = $this->_calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction, $target_type, false, true, $inframe_only, true, ($protein_seq=="y"), $in_exon_only);
			$res_lines = explode("\n", $result);
			//determine type
			$type_in_frame = false;
			$type_right_in_tact = false;
			$type_left_in_tact = false;
			$type_out_of_frame = false;
			$type_truncated_orf = false;
			$type_alternative_start_codon = false;

			$protein_seqs = array();
			$epitope_seqs = array();
			$positions = array();
			$left_strand = "";
			$right_strand = "";
			$is_cancer_genes = explode("\t", $res_lines[0]);
			$is_left_cancer_gene = $is_cancer_genes[0];
			$is_right_cancer_gene = $is_cancer_genes[1];
			for ($j=1;$j<count($res_lines);$j++) {
				$res_fields = explode("\t", $res_lines[$j]);
				if (count($res_fields) < 7)
					continue;
				$fusion_type = $res_fields[0];										
				if (strtolower($fusion_type) == strtolower($type) || $type == "all") {
					$left_strand = $res_fields[4];
					$right_strand = $res_fields[5];
					if ($protein_seq == "y") {
						$epitope_seq = $res_fields[6];
						$pep = $res_fields[7];
						$pos=str_replace(array( '[', ']' ), '', $res_fields[8]);
						$pos=preg_replace('/^([^,]*).*$/', '$1', $pos);

						$positions[] = $pos;
						$protein_seqs[] = $pep;
						$epitope_seqs[] = $epitope_seq;
					}	
					if (strtolower($fusion_type) == "in-frame")
						$type_in_frame = true;
					if (strtolower($fusion_type) == "right gene intact")
						$type_right_in_tact = true;
					if (strtolower($fusion_type) == "left gene intact")
						$type_left_in_tact = true;
					if (strtolower($fusion_type) == "out-of-frame")
						$type_out_of_frame = true;
					if (strtolower($fusion_type) == "truncated orf")
						$type_truncated_orf = true;
					if (strtolower($fusion_type) == "alternative start codon")
						$type_alternative_start_codon = true;					
				}
			}
			
			if ($left_strand == "")
				continue;
			$fusion_type = "Truncated ORF";
			if ($type_out_of_frame)
				$fusion_type = "Out-of-frame";	
			if ($type_alternative_start_codon)
				$fusion_type = "Alternative start codon";		
			if ($type_left_in_tact)
				$fusion_type = "Left gene intact";		
			if ($type_right_in_tact)
				$fusion_type = "Right gene intact";		
			if ($type_in_frame)
				$fusion_type = "In-frame";
			$tier = $this->getFusionTier($fusion_list, $fusion_pair_list, $fusion_type, $left_gene, $right_gene, $is_left_cancer_gene, $is_right_cancer_gene);
			if ($fusion_type == $type || $type == "all") {
				for ($k=0;$k<count($protein_seqs);$k++) {
					$data = array(substr($left_chr,3), ($left_strand=="+")? "-1" : $left_junction -1, ($left_strand=="+")? $left_junction : "-1", substr($right_chr,3), ($right_strand=="+")? $right_junction - 1: "-1", ($right_strand=="+")? "-1" : $right_junction, $left_gene.">>".$right_gene, ".",$left_strand, $right_strand, $read_count);
					if ($include_details == "y")
						$data[] = implode(":", array($sample, $fusion_type, $tier, $is_left_cancer_gene, $is_right_cancer_gene));
					if ($protein_seq == "y") {
							$data[] = rtrim($protein_seqs[$k],'*');				
							$data[] = $epitope_seqs[$k];
							$data[] = $positions[$k];
	 				}
					$content .= implode("\t", $data)."\n";

				}
			}
						
   		}
   		#Log::info($content);
   		return $content;
   	}

   	private function getFusionTier($fusion_list, $fusion_pair_list, $type, $left_gene, $right_gene, $is_left_cancer_gene, $is_right_cancer_gene) {
		$left_sanger = (array_key_exists($left_gene, $fusion_list));
		$right_sanger = (array_key_exists($right_gene, $fusion_list));
		$inframe_right_intact = (strtolower($type) == "in-frame" || strtolower($type) == "right gene intact");
		if (array_key_exists("$left_gene\t$right_gene", $fusion_pair_list) && $inframe_right_intact)
			return "Tier 1.1";		
		if (($left_sanger || $right_sanger) && $inframe_right_intact)
			return "Tier 1.2";		
		if ($is_left_cancer_gene == "Y" || $is_right_cancer_gene == "Y") {
			if ($inframe_right_intact) {
				if ($is_left_cancer_gene == "Y" && $is_right_cancer_gene  == "Y") {
					return "Tier 1.3";
				} else {
					return "Tier 2.1";
				}
			}		
			if (strtolower($type) == "left gene intact") {
				return "Tier 2.2";
			}
			return "Tier 3";
		}
		if ($inframe_right_intact) {
			return "Tier 4";
		}
		return "No Tier";
	}

	private function _calculateGeneFusionData($left_gene, $right_gene, $left_chr, $right_chr, $left_junction, $right_junction, $target_type="all", $get_domain=true, $show_cancer_gene=true, $inframe_only=false, $show_strand=false, $get_pep=false, $in_exon_only="n") {
		$time_start = microtime(true);

		$content = "";
		if ($show_cancer_gene) {
			$left_cancer_gene_exists = UserGeneList::geneInList("clinomics_gene_list", $left_gene)? 'Y' : 'N';
			$right_cancer_gene_exists = UserGeneList::geneInList("clinomics_gene_list", $right_gene)? 'Y' : 'N';
    	
    		$content = "$left_cancer_gene_exists\t$right_cancer_gene_exists\n";
    	}
		$time = microtime(true) - $time_start;
		//Log::info("execution time (_calculateGeneFusionData first part): ".round($time,2)." seconds");
		$time_start = microtime(true);

		$lgene = Gene::getGene($left_gene);
		$rgene = Gene::getGene($right_gene);

		if ($lgene == null || $rgene == null)
			return "N\tN";

		$time = microtime(true) - $time_start;
		//Log::info("execution time (_calculateGeneFusionData second part): ".round($time,2)." seconds");
		$time_start = microtime(true);

		$left_trans_list = $lgene->getTransList(false, false, false, $target_type);
		$right_trans_list = $rgene->getTransList(false, false, false, $target_type);
		

		$fused_peps = array(); 
		$epitope_peps = array(); 
		$peps = array();   	    	

		$fusion_types = array();

		$time = microtime(true) - $time_start;
		//Log::info("execution time (_calculateGeneFusionData third part): ".round($time,2)." seconds");

		$found_inframe = false;
    	foreach ($left_trans_list as $left_trans => $ltrans) {
    		#Log::info("left_trans :". $left_trans);

    		if ($ltrans->chromosome != $left_chr)
    			continue;
		    foreach ($right_trans_list as $right_trans => $rtrans) {
		    	//Log::info("right_trans : $right_trans");
		    	if ($rtrans->chromosome != $right_chr)
    				continue;
    			if ($ltrans->target_type != $rtrans->target_type)
    				continue;
    			//$time_start = microtime(true);
				list($type, $fused_pep, $epitope_seq, $left_pep_junction, $right_pep_junction)=$this->calculateTransFusionData($lgene,$ltrans,$rgene,$rtrans,$left_junction,$right_junction, "null", true, $in_exon_only);
				//$time = microtime(true) - $time_start;
				//Log::info("execution time (calculateTransFusionData $left_trans vs $right_trans): ".round($time,2)." seconds");
				$pep_length = strlen($fused_pep);
				if ($pep_length < 50)
    				continue;
    			if (!$inframe_only || ($inframe_only && $type == "In-frame")) {
    				if ($get_pep) {
						$fused_peps[$fused_pep][$type] = array($left_pep_junction, $right_pep_junction);
						$epitope_peps[$fused_pep] = $epitope_seq;

					} else
						$fused_peps[$fused_pep][$type][] = array($left_trans,$right_trans);
    			}
				if ($type == "In-frame")
					$found_inframe;
				//if ($found_inframe && $inframe_only)
				//	break;
				$fusion_types[$type] = '';				
			}
			//if ($found_inframe && $inframe_only)
			//	break;
		}
		$time = microtime(true) - $time_start;
		//Log::info("execution time (calculateTransFusionData $left_gene vs $right_gene): ".round($time,2)." seconds");
				
		$remove_truncated = (array_key_exists("In-frame", $fusion_types) || array_key_exists("Left gene intact", $fusion_types) || array_key_exists("Right gene intact", $fusion_types));
		foreach ($fused_peps as $seq => $types) {
			foreach ($types as $type => $trans) {    		
	    		if ($remove_truncated && $type == "Truncated ORF")
	    			continue;
				if ($type == "In-frame" && $get_domain) {
					$time_start = microtime(true);
	    			$pep_str = ">fused\n$seq";
					$domains = Gene::predictPfamDomain($pep_str);
					$domain_json = "{}";
					if (array_key_exists("fused", $domains))
						$domain_json = json_encode($domains["fused"]);
					$content .= "$type\t".strlen($seq)."\t".json_encode($trans)."\t$domain_json\n";
					$time = microtime(true) - $time_start;
					//Log::info("execution time (predictPfamDomain): ".round($time,2)." seconds");
					//$content .= "$type\t".strlen($seq)."\t".json_encode($trans)."\n";
				} else {
					$content .= "$type\t".strlen($seq)."\t".json_encode($trans)."\t.";
					if ($show_strand)
						$content .= "\t".$lgene->getStrand()."\t".$rgene->getStrand();
					if ($get_pep) {
						$epitope_seq = $epitope_peps[$seq];
						$content .= "\t$epitope_seq\t$seq\t".json_encode($trans);
					}
					$content .= "\n";
				}
			} 
   		}
		return $content;		
	}

	public function getExonInfo($exons, $strand, $exon_exp, $sample_id, $gene, $junction) {		
		if ($strand == "-")
			$exons = array_reverse($exons);
		$exon_num = 0;
		$previous_exon_coord = 0;
		$processed_exons = array();
		$in_exon = false;
		$has_cds = false;
		for ($i=0; $i<count($exons); $i++) {
			$exon = $exons[$i];
			$exp = 0;
			if ($exon_exp != null) {
				$exon_id = $exon->chromosome.":".$exon->start_pos."-".$exon->end_pos;
				if (isset($exon_exp[$sample_id][$gene->getSymbol()][$exon_id]))
					$exp = $exon_exp[$sample_id][$gene->getSymbol()][$exon_id];
			}
			//if not in the same exon (e.g UTR and first CDS)
			$exon_coord = ($strand == "+")? $exon->start_pos : $exon->end_pos;
			if ($exon_coord != $previous_exon_coord)
				$exon_num++;
			$processed_exons[] = array("start_pos" => $exon->start_pos, "end_pos" => $exon->end_pos, "name" => "exon".($exon_num), "type" => $exon->region_type, "hint" => array("Name" => "exon".($exon_num), "Type" => $exon->region_type, "Coordinate" => $exon->start_pos." - ".$exon->end_pos, "Length" => $exon->end_pos - $exon->start_pos, "Expression" => $exp), "value" => $exp);
			if ($junction > $exon->start_pos && $junction <= $exon->end_pos) {
				$in_exon = true;				
			}
			if ($exon->region_type == "cds")
				$has_cds = true;
			$previous_exon_coord = ($strand == "+")? $exon->end_pos : $exon->start_pos;
		}
		if ($strand == "-")
			$processed_exons = array_reverse($processed_exons);
		return array($processed_exons, $in_exon, $has_cds);
	}
	//used by load_var_patients.pl, pre-calculate the fusion cDNA, proteins and PFAM domains
	public function calculateTransFusionData($lgene, $ltrans, $rgene, $rtrans, $left_junction, $right_junction, $sample_id="null", $type_only=false, $in_exon_only="n") {		

		if ($ltrans == null || $rtrans == null) {
			if ($type_only)
				return array("no info", "", "", 0, 0);
			return json_encode(array("has_protein" => 0, "left_exons" => array()));
		}
		
		$time_start = microtime(true);

		$lexons = $ltrans->getExons();
		$rexons = $rtrans->getExons();

		
		//$lcoding_seq = $ltrans->coding_seq;
		//$rcoding_seq = $rtrans->coding_seq;
		$lcoding_seq = $ltrans->getTranscriptSeq(false, false);
		$rcoding_seq = $rtrans->getTranscriptSeq(false, false);

		if ($lcoding_seq == "" && $rcoding_seq == "")
			return array("no protein", "", "", 0, 0);

		$lpep = $ltrans->getAASeq();
		$rpep = $rtrans->getAASeq();
				
		$time = microtime(true) - $time_start;
		//Log::info("execution time (calculateTransFusionData part 1): ".round($time,2)." seconds");
		$time_start = microtime(true);

		//$sample_id = null;		
		//left transcript: whole transcript, right transcript: TSS -> stop codon
		
		$lseq = $ltrans->getTranscriptSeq(true, true);
		$rseq = $rtrans->getTranscriptSeq(true, false);

		$left_exons = array();
		$right_exons = array();
		$left_in_exons = true;
		$right_in_exons = true;
		$previous_exon_end = -1;
		$exon_exp = null;
		$left_junction_status = "In exon.";
		$right_junction_status = "In exon.";
		//$time = microtime(true) - $time_start;
		//Log::info("execution time (first part): ".round($time,2)." seconds");
		//$time_start = microtime(true);
		//$sample_id = null;
		if ($sample_id != "null" && !$type_only) {
			
			$exon_exp = Sample::getExonExpression(array($lgene->getSymbol(),$rgene->getSymbol()), array($sample_id), "all");

			$time = microtime(true) - $time_start;
		
			Log::info("execution time (getExonExpression): $time seconds");
			$time_start = microtime(true);
		}
		
		//Log::info(json_encode($exon_exp));

		list($left_exons, $left_in_exons, $left_has_cds) = $this->getExonInfo($lexons, $ltrans->strand, $exon_exp, $sample_id, $lgene, $left_junction);
		list($right_exons, $right_in_exons, $right_has_cds)	= $this->getExonInfo($rexons, $rtrans->strand, $exon_exp, $sample_id, $rgene, $right_junction);
		

		$time = microtime(true) - $time_start;
		//Log::info("execution time (calculateTransFusionData part 2): ".round($time,2)." seconds");
		$time_start = microtime(true);

		//get junction positions
		$left_in_5utr = !$left_has_cds;
		$ignore_left = false;
		$ignore_right = false;
		$left_gene_intact = false;
		$right_gene_intact = false;
		$type = "";
		$has_protein = true;
		
		if ($ltrans->strand == "+") {
			if ($left_junction <= $ltrans->coding_start && $left_junction > $ltrans->start_pos)
				$left_in_5utr = true;
			if ($left_junction <= $ltrans->start_pos) {
				$left_junction_status = "Before TSS. Left gene ignored";
				$ignore_left = true;				
			}
			if ($left_junction > $ltrans->coding_end && $left_has_cds) {
				$left_junction_status = "Left gene intact";
				$left_gene_intact = true;				
			}
		}
		else {
			//if ($left_junction > $ltrans->coding_end) {
			if ($left_junction > $ltrans->coding_end && $left_junction <= $ltrans->end_pos)
				$left_in_5utr = true;
			if ($left_junction > $ltrans->end_pos) {
				$left_junction_status = "Before TSS. Left gene ignored";
				$ignore_left = true;				
			}
			if ($left_junction <= $ltrans->coding_start && $left_has_cds) {
				$left_junction_status = "Left gene intact";
				$left_gene_intact = true;				
			}
		}
		if ($ltrans->strand == "+") {
			$start_pos = $ltrans->coding_start + 1;
			if ($left_in_5utr)
				$start_pos = $ltrans->start_pos + 1;			
			$lcoding_pos = $ltrans->getDistInTrans($start_pos, $left_junction);
			$lpos = $ltrans->getDistInTrans($ltrans->start_pos + 1, $left_junction);			
		}
		else {
			$end_pos = $ltrans->coding_end;
			if ($left_in_5utr)
				$end_pos = $ltrans->end_pos;			
			$lcoding_pos = $ltrans->getDistInTrans($left_junction, $end_pos);
			$lpos = $ltrans->getDistInTrans($left_junction, $ltrans->end_pos);
		}
		if ($left_gene_intact) {
			$lcoding_pos = 0;
			$lpos = 0;
		}
		//Log::info("rcoding_seq:".($rcoding_seq == ''));
		if ($rtrans->strand == "+") {
			$rcoding_pos = $rtrans->getDistInTrans($rtrans->coding_start + 1, $right_junction);
			$rpos = $rtrans->getDistInTrans($rtrans->start_pos + 1, $right_junction);
			if ($right_junction <= $rtrans->coding_start && $rcoding_seq != '') {
				//$type = "right junction before start codon,";
				
				$right_junction_status = "Right gene intact";
				$right_gene_intact = true;
				$ignore_left = true;
				$has_protein = true;
				$rpos = 0;
				$rcoding_pos = 0;
			}
			if ($right_junction > $rtrans->coding_end) {
				//$type = "right junction after stop codon";
				//$has_protein = false;
				$right_junction_status = "After stop codon. Right gene ignored";
				$ignore_right = true;
			}			
			
		}
		else {
			$rcoding_pos = $rtrans->getDistInTrans($right_junction, $rtrans->coding_end);
			$rpos = $rtrans->getDistInTrans($right_junction, $rtrans->end_pos);
			if ($right_junction > $rtrans->coding_end  && $rcoding_seq != '') {
				//$type = "right junction before stop codon,";
				$right_junction_status = "Right gene intact";
				$right_gene_intact = true;
				$ignore_left = true;
				$has_protein = true;
				$rcoding_pos = 0;
				$rpos = 0;
			}
			if ($right_junction <= $rtrans->coding_start) {
				//$type = "right junction after stop codon";
				$right_junction_status = "After stop codon. Right gene ignored";
				$ignore_right = true;
			}
		}
		if ($lpep == '') {
			$left_junction_status = "No coding sequence";			
			//$ignore_left = true;
		}
		if ($rpep == '') {
			$right_junction_status = "No coding sequence";
			$ignore_right = true;
		}
		if (!$left_in_exons && !$ignore_left && $left_junction_status == "In exon.") {
			$left_junction_status = "In intron.";			
		}
		if (!$right_in_exons && $rpos != 0 && $right_junction_status == "In exon.") {
			$right_junction_status = "In intron.";			
		}
		$fused_ltrans = "";
		$fused_rtrans = "";
		if (($ignore_left || $left_in_5utr) && $ignore_right)
			$has_protein = false;
		if ($has_protein) {
			$search_range = [0,0];
			if ($ignore_left) {
				$left_gene_intact = false;
				$lpos = 0;				
				$lcoding_pos = 0;
				$fused_ltrans = "";
				$left_pep_junction = 0;				
				$fused_lcdna = "";				
				$fused_lpep = "";
				$lpep = "";				
			}			
			else{	
				$seq = $lcoding_seq;
				if ($left_in_5utr) {
					$left_pep_junction = 0;						
					$fused_lcdna = "";				
					$fused_lpep = "";
					$lpep = "";		
					$seq = $lseq;
					$left_junction_status = "In 5UTR";
					$search_range = [0, $lcoding_pos];	
					//$search_range = [0, 2];
				}
				$fused_ltrans = substr($seq, 0, $lcoding_pos);
			}
			if ($right_gene_intact)
				$fused_ltrans = '';
			
			//print "lcoding_pos left=====$lcoding_pos";
			$fused_rtrans = '';
			if ($rpos >= 0) {
				if ($rpos == 0 )
					$fused_rtrans = $rcoding_seq;
				else
					$fused_rtrans = substr($rseq, $rpos - 1);
			}			
			
			if ($left_gene_intact) {
				$lcoding_pos = strlen($lcoding_seq);
				$rcoding_pos = 0;
				$fused_ltrans = $lcoding_seq;
				$fused_rtrans = '';
				$right_pep_junction = 0;
			}

			if ($ignore_right) {
				$rcoding_pos = strlen($rcoding_seq);
				$right_pep_junction = strlen($rpep);
				$fused_rtrans = "";
				$rpos = strlen($rpep);
				$rpep = "";

			}
			//Log::info("fused_rtrans:$fused_rtrans");
			$fused_trans = $fused_ltrans.$fused_rtrans;
			//return $fused_ltrans."<BR>".$fused_rtrans;
			
			list($fused_pep, $offset) = Gene::translateDNA($fused_trans, $search_range);			

			if ($left_in_5utr && $fused_ltrans != "") {
				$lcoding_pos = $search_range[1] - $offset;
				//Log::info("lcoding_pos : $lcoding_pos");
				//Log::info("fused_ltrans : $fused_ltrans");
				
				//$lcoding_pos = $offset;
				if ($lcoding_pos < 0)
					$lcoding_pos = 3;
				$fused_trans = substr($fused_trans, $offset);
				$lpep = substr($fused_pep, 0, (int)($lcoding_pos/3));				
			} else
				$fused_trans = substr($fused_trans, $offset);
			//return $fused_rtrans."<BR>".$start_pos."<BR>".$fused_pep;
			//$fused_cdna = substr($fused_trans, $start_pos);			
			

			$left_pep_junction = (int)($lcoding_pos/3);
			//Log::info("rcoding_pos:$rcoding_pos");
			$right_pep_junction = (int)($rcoding_pos/3);
			//$right_pep_junction = 0;
				
			//if ($start_pos > $lpos) {
			//	$fused_lcdna = "";
			//	$fused_rcdna = $fused_cdna;
			//	$fused_lpep = "";
			//	$fused_rpep = $fused_pep;					
			//} else {
				//if ($lcoding_pos >=0 ){
					$fused_lcdna = substr($fused_trans, 0, $lcoding_pos);
					$fused_rcdna = substr($fused_trans, $lcoding_pos);
					$fused_lpep = substr($fused_pep, 0, $left_pep_junction);
					$fused_rpep = substr($fused_pep, $left_pep_junction);

					$epitope_length = 12;
					$epitope_seq = "";
					if ($left_pep_junction - $epitope_length > 0 && $left_pep_junction + $epitope_length < strlen($fused_pep))
						$epitope_seq = substr($fused_pep, $left_pep_junction - $epitope_length, $epitope_length * 2);
				//}
			//}
			if ($fused_rtrans == '') {
				$fused_rpep = '';
				$left_pep_junction++;
			}

			if ($in_exon_only == "y" && (strtolower($left_junction_status) != "in exon." || strtolower($right_junction_status) != "in exon.")) {
				#Log::info($left_junction_status."====>".$right_junction_status);
				return array("not in exons", "", "", 0, 0);
			}
			//Determine fusion type
			if (substr($fused_pep, 0, 1) == 'M' || strpos($fused_pep, '*') == strlen($fused_pep) - 1 || strpos($fused_pep, '*') === false )
				$type = "Truncated ORF";
			else
				$type = "Out-of-frame";
			if (substr_count($fused_pep, '*') > 1 || (substr_count($fused_pep, '*') == 1 && strpos($fused_pep, '*') != strlen($fused_pep) - 1))
				$type = "Out-of-frame";
			if (substr($fused_pep, 0, 1) == 'M' && strpos($fused_pep, '*') == strlen($fused_pep) - 1)
				$type = "In-frame";	
			if ($offset > 0 && $type == "In-frame")
				$type = "Alternative start codon";		
			if ($left_gene_intact)
				$type = "Left gene intact";
			if ($right_gene_intact)
				$type = "Right gene intact";
			if (strlen($fused_pep) < 10) {
				$type = "No fused protein";
				$fused_pep = "";
			}

			$time = microtime(true) - $time_start;
			//Log::info("execution time (part 3): ".round($time,2)." seconds");

			if ($type_only)
				return array($type, $fused_pep, $epitope_seq, $left_pep_junction, $right_pep_junction);

			$fused_domains = array();

			if ($type != "In-frame" && $type != "Left gene intact" && $type != "Right gene intact") {
				//$pep_str = ">$ltrans->trans\n$lpep\n>$rtrans->trans\n$rpep\n>fused\n$fused_pep";
				$pep_str = ">fused\n$fused_pep";
				$time_start = microtime(true);
				$domains = Gene::predictPfamDomain($pep_str);
				if (array_key_exists("fused", $domains))
					$fused_domains = $domains["fused"];
				$time = microtime(true) - $time_start;				
			} else {
				if ($type == "In-frame")
					$domain = VarAnnotation::getFusionDomain($lgene->getSymbol(),$rgene->getSymbol(), $lgene->getChr(), $rgene->getChr(), $left_junction, $right_junction, $ltrans->trans, $rtrans->trans);
				if ($type == "Left gene intact")
					$domain = $ltrans->domain;
				if ($type == "Right gene intact")
					$domain = $rtrans->domain;
				$fused_domains = json_decode($domain);
			}			
			
			$domains = array($ltrans->trans => json_decode($ltrans->domain), $rtrans->trans => json_decode($rtrans->domain), 'fused' => $fused_domains);
			return json_encode(array("has_protein" => $has_protein, "leftpos"=>$lpos, "left_dna" => $lseq, "left_exons" => $left_exons, "right_exons" => $right_exons, "left_chr"=>$lgene->getChr(), "right_chr"=>$rgene->getChr(), "left_strand" => $ltrans->strand, "right_strand" => $rtrans->strand, "type" => $type, "left_junction_status"=> $left_junction_status, "right_junction_status"=> $right_junction_status, "left_pep" => $lpep, "right_pep" => $rpep, "fused_pep" => $fused_pep, "fused_lpep" => $fused_lpep, "fused_rpep" => $fused_rpep, "left_pep_junction" => $left_pep_junction, "right_pep_junction" => $right_pep_junction, "left_junction" => $left_junction, "right_junction" => $right_junction, "domains" => $domains, "fused_lcdna" => $fused_lcdna, "fused_rcdna" => $fused_rcdna));
		}
		if ($type_only)
			return array($type, "", "", 0, 0);
		return json_encode(array("has_protein" => $has_protein, "leftpos"=>$lpos, "left_junction_status"=> $left_junction_status, "right_junction_status"=> $right_junction_status, "left_dna" => $lseq, "left_exons" => $left_exons, "right_exons" => $right_exons, "left_chr"=>$lgene->getChr(), "right_chr"=>$rgene->getChr(), "left_strand" => $ltrans->strand, "right_strand" => $rtrans->strand, "type" => $type, "fused_lcdna" => "", "fused_rcdna" => "", "left_junction" => $left_junction, "right_junction" => $right_junction));	
	}

	public function saveQCLog() {		
		try {
			$user_id = Sentry::getUser()->id;
		} catch (Exception $e) {
			return "NoUserID";
		}
		$data = Input::all();		
		try {
			$qclog = new QCLog;	
			$qclog->patient_id = $data["patient_id"];
			$qclog->case_id = $data["case_id"];
			$qclog->log_type = $data["log_type"];			
			$qclog->log_decision = $data["log_decision"];
			$qclog->log_comment = $data["log_comment"];
			$qclog->user_id = $user_id;
			$results = $qclog->save();
			return "Success";
		} catch (\Exception $e) { 
			return $e->getMessage();			
		}	

	}

	public function getQCLogs($patient_id, $case_id, $log_type) {
		$logs = QCLog::getLogByPatientAndType($patient_id, $case_id, $log_type);
		return json_encode($this->getDataTableJson($logs));
	}

	public function getQC($patient_id, $case_id, $type) {
#		$json_file=fopen("../app/tests/getQC_".$type."_Test.json","w");
#		fwrite($json_file,json_encode(VarQC::getQCByPatientID($patient_id, $case_id, $type)));
		return json_encode(VarQC::getQCByPatientID($patient_id, $case_id, $type));
	}




	public function signOutCase($patient_id, $case_id, $type) {
		try {
			$user_id = Sentry::getUser()->id;
		} catch (Exception $e) {
			return "NoUserID";
		}
		try {
			VarCases::where('patient_id', '=',$patient_id)->where('case_id', '=',$case_id)->where('type', '=',$type)->update(['status' => 'closed', 'user_id' => $user_id]);
			$path = VarCases::getPath($patient_id, $case_id);
			$filename = "$patient_id.$case_id.$type.actionable.txt";
			$path = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$filename";
			$file = fopen($path, "w");
			$content = VarAnnotation::getVarActionable($patient_id, $case_id, $type);
			fwrite($file, $content);
			fclose($file);
			return "Success";
		} catch (\Exception $e) { 
			return $e->getMessage();			
		}	

	}

	public function getVarActionable($patient_id, $case_name, $type, $flag) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$flag_only = ($flag == 'Y');
		if ($flag_only) {
			$filename = "$patient_id.$case_id.$type.actionable.flagged.txt";
			$content = VarAnnotation::getVarActionable($patient_id, $case_name, $type, true);
		}
		else {
			//$case = VarCases::where('patient_id', '=',$patient_id)->where('case_id', '=',$case_id)->where('type', '=',$type)->get()[0];
			$path = VarCases::getPath($patient_id, $case_name);
			$filename = "$patient_id.$case_id.$type.actionable.txt";
			$path = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$filename";
			//if ($status == "active") {			
				$content = VarAnnotation::getVarActionable($patient_id, "null", $case_name, $type, false);
			//} else {
			//	$content = file_get_contents($path);
			//}
		}
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
		return Response::make($content, 200, $headers);

	}


	public function downloadVariants() {
		set_time_limit(3600);
		$project_id = Input::get('project_id');
		$case_id = Input::get('case_id');
		$gene_id = Input::get('gene_id');
		$sample_id = Input::get('sample_id');
		$type = Input::get('type');
		$flag = Input::get('flag');
		$var_list = Input::get('var_list');
		$include_cohort = Input::get('include_cohort');
		$avia_table_name = Input::get('avia_table_name');
		$annotation = Input::get('annotation');
		$stdout = Input::get('stdout');
		$include_details = Input::get('include_details');
		$diagnosis = Input::get('diagnosis');
		$vars = explode(',', $var_list);
		$var_hash = array();
		$case_ids=array();

	/*	if ($case_id==''){
			$patient_id = Input::get('patient_id');
			$var = new VarAnnotation();	
			$rows=$var->getCaseidsByType($patient_id, $type);
			foreach ($rows as $row){
				$case_ids[]=$row->case_id;
			}	
			
		}
		else{
			$case_ids[]=$case_id;
		}*/
		//foreach ($case_ids as $case_id_tmp){
			if ($annotation != null)
				$avia_mode = ($annotation == "avia");
			else
				$avia_mode = VarAnnotation::is_avia();
			if (!$avia_mode){
				Log::info("GETING FUSION");
				return $this->downloadKhanlabVariants();
			}
			$patient_id = Input::get('patient_id');
			if ($avia_table_name == null) {
				if ($gene_id == "null" && !User::hasPatient($patient_id)) {
					return View::make('pages/error', ['message' => 'Access denied!']);
				}
			}
			
			if ($gene_id == null)
				$gene_id = "null";
			if ($sample_id == null)
				$sample_id = "null";
			if ($include_cohort != null)
				$include_cohort = ($include_cohort == "true");
			else
				$include_cohort = true;
			if ($include_details != null)
				$include_details = ($include_details == "true");
			else
				$include_details = false;
			//Log::info("var list: $var_list");
			foreach ($vars as $var) {
				$var_hash[$var] = '';
			}
			if ($sample_id == "null")
				$sample_id = null;

			$filename = "$patient_id.$case_id.$type.txt";
			if ($sample_id != "null")
				$filename = "$sample_id.$case_id.$type.txt";
			$var = new VarAnnotation();		
			if ($gene_id != "null") {
				$filename = "$gene_id.$type.txt";
				$rows = $var->processAVIAPatientData($project_id, null, null, $type, null, $gene_id);
			}
			else {
				Log::info("DETAILS");
				#Log::info($project_id." ".$patient_id." ".$case_id." ".$type." ".$sample_id." ".null." ".$include_details." ".$include_cohort." ".$avia_table_name." ".$diagnosis);
				$rows = $var->processAVIAPatientData($project_id, $patient_id, $case_id, $type, $sample_id, null, $include_details, $include_cohort, $avia_table_name, $diagnosis);
			}
			$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
			$avia_rows = array();
			if (count($rows) == 0) {
				return Response::make("", 200, $headers);
			}
			$avia_length = count(array_values((array)$rows[0]));
			
			foreach ($rows as $row)
				$avia_rows[] = clone $row;	

			list($data, $columns) = $var->postProcessVarData($rows, $project_id, $type);
			Log::info(json_encode($columns));

			$tier_idx = 0;
			$chr_idx = 0;
			for ($i=0; $i<count($columns); $i++) {
				if ($columns[$i]["title"] == "Somatic Tier")
					$tier_idx = $i;
				if ($columns[$i]["title"] == "Patient ID")
					$patient_idx = $i;				
				
			}
			$additional_columns = array();
			$offset = -4;
			foreach ($data as $row_data)
				$additional_columns[$row_data[count($row_data) - 1]] = $row_data;
			$new_rows = array();
			foreach ($avia_rows as $row) {
				$var_id = implode(":", [$row->patient_id, $row->case_id, $row->chromosome, $row->start_pos, $row->end_pos, $row->ref, $row->alt]);
				if ($var_list != null && !array_key_exists($var_id, $var_hash))
	                continue;            
				$row->loss_func = VarAnnotation::isLOF($row->func, $row->exonicfunc);
				$somatic_tier = $additional_columns[$var_id][$tier_idx];
				$germline_tier = $additional_columns[$var_id][$tier_idx + 1];
				$somatic_tier = str_replace("<span class='badge'>", "", $somatic_tier);
				$somatic_tier = str_replace("</span>", "", $somatic_tier);
				$germline_tier = str_replace("<span class='badge'>", "", $germline_tier);
				$germline_tier = str_replace("</span>", "", $germline_tier);
				$row->somatic_level = $somatic_tier;
				$row->germline_level = $germline_tier;
				for ($i=$avia_length; $i<count($columns) + $offset; $i++) {
					$row->{$columns[$i]["title"]} = ($additional_columns[$var_id][$i] == "")? "" : "Y";
				}
				$new_rows[] = $row;
			}
			
			$results = $this->getDataTableJson($new_rows);

			$content = "";
			$header = array();
			foreach ($results["cols"] as $column)
				$header[] = $column["title"];
			
			$content .= implode("\t", array_splice($header, $patient_idx))."\n";
			//$content .= implode("\t", $header)."\n";
			foreach ($results["data"] as $row) {
				//$content .= implode("\t", $row)."\n";
				$content .= implode("\t", array_splice($row, $patient_idx))."\n";
			}
			

			if ($stdout !=null && $stdout == "true")
				return $content;
			return Response::make($content, 200, $headers);
		//}
	}

	public function downloadKhanlabVariants() {
		$patient_id = Input::get('patient_id');
		#if (!User::hasPatient($patient_id)) {
		#	return View::make('pages/error', ['message' => 'Access denied!']);
	#	}
		$case_id = Input::get('case_id');
		$sample_id = Input::get('sample_id');
		$gene_id = Input::get('gene_id');
		$type = Input::get('type');
		$flag = Input::get('flag');

		$var_list = Input::get('var_list');
		$vars = explode(',', $var_list);
		$stdout = Input::get('stdout');
		$var_hash = array();
		foreach ($vars as $var) {
			$var_hash[$var] = '';
		}	

		$content = VarAnnotation::getVarActionable($patient_id, $sample_id, $case_id, $type, false, $var_hash);

		if ($stdout !=null && $stdout == "true")
				return $content;		
		$filename = "$patient_id.$case_id.$type.txt";
		if ($sample_id != "null")
			$filename = "$sample_id.$case_id.$type.txt";
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);		
		return Response::make($content, 200, $headers);
		
	}

	public function signOut() {
		try {
			if (!User::isSignoutManager())
				return "Access denied";
			$logged_user = User::getCurrentUser();
			if ($logged_user == null)
				return "NoUserID";
			$data = Input::all();
			$var_signout = new VarSignout();			
			$var_signout->patient_id = $data['patient_id'];
			$var_signout->sample_id = $data['sample_id'];
			$var_signout->case_id = $data['case_id'];
			$var_signout->sample_id = $data['sample_id'];
			$var_signout->type = $data['type'];			
			$var_signout->status = $data['status'];
			$var_signout->var_list = $data['var_list'];
			$var_signout->user_id = $logged_user->id;			
			$vars = explode(',', $var_signout->var_list);
			$var_signout->var_num = count($vars);
			$var_signout->save();
			$var_hash = array();						
			foreach ($vars as $var) {
				$var_hash[$var] = '';
			}
			$user = User:: getCurrentUser();
			$user_id=$user->email;
			$cmd=app_path()."/scripts/backend/signoutEmail.pl -p ".$data['patient_id']." -c ". $data['case_id']." -t ".$data['type']." -u "."Phase_I -r ".$user_id;
			log::info($cmd);
			shell_exec($cmd);
			$content = VarAnnotation::getVarActionable($var_signout->patient_id, $var_signout->sample_id, $var_signout->case_id, $var_signout->type, false, $var_hash);		
			$filename = "$var_signout->patient_id.$var_signout->sample_id.$var_signout->case_id.$var_signout->type.$var_signout->status.$var_signout->updated_at.txt";
			$path = storage_path()."/signed_out/$filename";
			$file = fopen($path, "w");			
			fwrite($file, $content);
			fclose($file);
			return "Success";
		} catch (\Exception $e) { 
			return $e->getMessage();			
		}	
	}

	public function getVariants(){
		try {
	#	$file = Input::file('file');
	#	$content = file_get_contents($file->getRealPath());
	#	$file = fopen('/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/data/TARGET_NBL.tsv', "w");
		$file = "/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev/app/data/TARGET_NBL.tsv";
	#	fwrite($file, $content);
	#	$file = fopen($path, "w");			
		$cmd=app_path()."/scripts/ExacWrapper.pl -i ".$file." -t Var_Sample_avia";	
		Log::info($cmd);
		shell_exec($cmd);
		} catch (\Exception $e) { 
			return $e->getMessage();			
		}

		
	}
	public function getVCF($patient_id, $case_id) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$patient_id.$case_id.vcf.zip";
		return Response::download($pathToFile);
	}	

	public function getCNVPlot($patient_id, $sample_name, $case_id, $type) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		Log::info("PATIENT AND CASE FOR CNV: ".$patient_id." ".$case_id. " ".$sample_name);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$file_types = ["png","pdf"];
		foreach ($file_types as $file_type) {
			if ($file_type == "png") {
				$content_type = "image/png";
			} else {
				$content_type = "application/pdf";
			}
			if ($type == "cnvkit")
				$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/cnvkit/$sample_name.$file_type";
			else
				$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/$sample_name/sequenza/$sample_name/$sample_name"."_"."$type.$file_type";
			if (file_exists($pathToFile))
				return Response::make(file_get_contents($pathToFile), 200, ['Content-Type' => $content_type,'Content-Disposition' => "inline; filename=".$sample_name.'_'."$type.$file_type"]);
		}
		//return $pathToFile;
		return "PNG/PDF file not exists!";
		

	}

	public function getSignaturePlot($patient_id, $sample_name, $case_id) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$sample_id = Sample::getSampleIDByName($sample_name);
		$pathToFile = VarAnnotation::getSignatureFileName($path, $patient_id, $case_id, $sample_id, $sample_name);
		//$pathToFile = storage_path()."/ProcessedResults/".$path."/$patient_id/$case_id/Actionable/$sample_name".".mutationalSignature.pdf";

		//return $pathToFile;
		if ($pathToFile == "")
			return "PDF file not exists!";
		return Response::make(file_get_contents($pathToFile), 200, ['Content-Type' => 'application/pdf','Content-Disposition' => 'inline; filename="'.$sample_name."."."mutationalSignature.pdf"]);

	}

	public function getHLAData($patient_id, $case_id, $sample_name) {		
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$sample_id = Sample::getSampleIDByName($sample_name);
		// Log::info("sample_id : $sample_id");
		$pathToFile = VarAnnotation::getHLAFileName($path, $patient_id, $case_id, $sample_id, $sample_name);
		$file_data = file_get_contents($pathToFile);
		$lines = explode("\n", $file_data);
		$cols = array();
		$data = array();
		$line_cnt = 0;
		foreach ($lines as $line) {
			if ($line == "")
				continue;
			$fields = explode("\t", $line);
			$row_data = array();
			foreach ($fields as $field) {
				if ($line_cnt == 0) {
					$cols[] = array("title" => $field);
				} else {
					$row_data[] = $field;
				}
			}
			if ($line_cnt != 0)
				$data[] = $row_data;
			$line_cnt++;
		}
//		$json_file=fopen("../app/tests/getHLAData_Test.json","w");
//		fwrite($json_file,json_encode(array("hide_cols" => array(), "cols" => $cols, "data" => $data)));
		return json_encode(array("hide_cols" => array(), "cols" => $cols, "data" => $data));

	}

	public function downloadAntigenData($patient_id, $case_id, $sample_id) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$sample = Sample::find($sample_id);
		if ($sample == null)
			return View::make('pages/error', ['message' => "Sample $sample_id not found!"]);
		$pathToFile = VarAnnotation::getAntigenFileName($path, $patient_id, $case_id, $sample->sample_id, $sample->sample_name);
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."NeoAntigen_$sample->sample_name.txt");
		return Response::make(file_get_contents($pathToFile), 200, $headers);		
	}

	public function downloadHLAData($patient_id, $case_id, $sample_name) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}
		$sample_id = Sample::getSampleIDByName($sample_name);
		$pathToFile = VarAnnotation::getHLAFileName($path, $patient_id, $case_id, $sample_id, $sample_name);
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='."HLA_$sample_name.txt");
		return Response::make(file_get_contents($pathToFile), 200, $headers);		
	}

	public function viewAntigen($project_id, $patient_id, $case_id, $sample_name) {
		if ($sample_name == "any") {
			$samples = Sample::where('patient_id', $patient_id)->get();
		}
		else {
			$sample_name = str_replace("Sample_", "", $sample_name);
			$samples = Sample::where('sample_name', $sample_name)->orWhere("sample_id", $sample_name)->get();
		}
		if (count($samples) == 0)
			return View::make('pages/error_no_header', ['message' => "samples not found"]);
		
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('all');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		
		$rnaseq_samples = array();
		foreach ($samples as $sample) {
			$sample_id = $sample->sample_id;
			$rnaseq_samples[] = $sample->rnaseq_sample;			
		}
		if ($sample_name == "any")
			$sample_id = "any";
		
		$hide_columns = array_values((array)UserSetting::getSetting("antigen.columns"))[0];

		return View::make('pages/viewAntigen', ['project_id' => $project_id, 'patient_id' => $patient_id, 'case_id' => $case_id, 'sample_id' => $sample_id, 'rnaseq_samples' => $rnaseq_samples, 'hide_columns' => json_encode($hide_columns), 'filter_definition' => $filter_definition]);
	}

	public function getAntigenData($project_id, $patient_id, $case_id, $sample_id) {
		if (!User::hasPatient($patient_id)) {
			return View::make('pages/error', ['message' => 'Access denied!']);
		}
		$path = VarCases::getPath($patient_id, $case_id);
		if ($path == null) {
			return View::make('pages/error', ['message' => 'No case found!']);
		}		
		$sample = Sample::find($sample_id);
		if ($sample == null) {
			return View::make('pages/error', ['message' => "Sample $sample->sample_id found!"]);
		}
		$normal_sample = Sample::find($sample->normal_sample);
		$hla_data = json_decode($this->getHLAData($patient_id, $case_id, $normal_sample->sample_name));

		$high_conf_alleles = array();
		foreach ($hla_data->data as $row) {
			if ($row[1] != "NotCalled" && $row[2] != "NotCalled")
				$high_conf_alleles[$row[0]] = '';
		}
		
		$rows = VarAnnotation::getAntigen($patient_id, $case_id, $sample->sample_id);
		$antigen_data = $this->getDataTableJson($rows);
		$user_filter_list = UserGeneList::getGeneList("all");
		$cols = $antigen_data["cols"];
		$data = $antigen_data["data"];
		array_splice($cols,0, 3);
		$cols = array_merge(array(array("title" => "IGV"), array("title" => "High conf")), $cols);
		$gene_idx = 0;
		$hla_idx = 0;
		$matched_var_idx = 0;
		for ($i=0; $i<count($cols);$i++) {
			if (strtolower($cols[$i]["title"]) == "gene")
				$gene_idx = $i;
			if (strtolower($cols[$i]["title"]) == "hla allele")
				$hla_idx = $i;
			if ($cols[$i]["title"] == Lang::get("messages.matched_var_cov"))
				$matched_var_idx = $i;			
		}

		$gene_list_idx = count($cols);

		foreach ($user_filter_list as $list_name => $gene_list)
			$cols[] = array("title" => $list_name);
		$processed_data = array();
		$base_url = url("/");
		foreach ($data as $row) {
			array_splice($row,0, 3);
			$row = array_merge(array("", ""), $row);
			$chr = $row[2];
			$start_pos = $row[3];
			$end_pos = $row[4];			
			$gene = $row[$gene_idx];
			$row[0] = "<a target=_blank href='$base_url/viewIGV/$patient_id/$sample->sample_name/$case_id/somatic/".($start_pos-1)."/$chr".":".($start_pos - 51)."-".($end_pos + 50)."'><img width=20 hight=20 src='$base_url/images/igv.jpg'/></a>";
			$row[1] = (array_key_exists($row[$hla_idx], $high_conf_alleles))? "Y" : "N";			
			$row[$gene_idx] = "<a target=_blank href='$base_url/viewVarAnnotationByGene/$project_id/$row[$gene_idx]/somatic/1/null/null/any/$patient_id'>$row[$gene_idx]</a>";			
			if ($row[$matched_var_idx] == '') {
				$row[$matched_var_idx] = 0;
				$row[$matched_var_idx + 1] = 0;
			}
			foreach ($user_filter_list as $list_name => $gene_list)
				$row[] = array_key_exists($gene, $gene_list)? 'Y' : '';			
			$processed_data[] = $row;
		}
		/*$pathToFile = VarAnnotation::getAntigenFileName($path, $patient_id, $case_id, $sample_id, $sample_name);
		$cols = array(array("title" => "IGV"), array("title" => "High conf"));
		$data = array();
		if ($pathToFile == "")
			return json_encode(array("cols" => $cols, "data" => $data));
		$file_data = file_get_contents($pathToFile);
		$lines = explode("\n", $file_data);		
		$line_cnt = 0;
		$gene_idx = 5;
				
		foreach ($lines as $line) {
			if ($line == "")
				continue;
			$fields = explode("\t", $line);
			$row_data = array("<a target=_blank href='$base_url/viewIGV/$patient_id/$sample_name/$case_id/somatic/".($fields[1]-1)."/$fields[0]".":".($fields[1] - 51)."-".($fields[2] + 50)."'><img width=20 hight=20 src='$base_url/images/igv.jpg'/></a>");
			$row_data[] = (array_key_exists($fields[$gene_idx+1], $high_conf_alleles))? "Y" : "N";
			$aa_list = array();
			for ($i=0; $i<count($fields); $i++) {
				$field = $fields[$i];
				//if (($i >= 21 && $i <= 28))
				//	continue;
				if ($line_cnt == 0) {
					//if ($i != $gene_idx - 1) {
						$cols[] = array("title" => $field);
					//}
				} else {
					if (($i == $gene_idx - 1) && (count($aa_list) == 2)) {
						$field = $aa_list[0].$field.$aa_list[1];
					}
					if ($i == $gene_idx) {
						$field = "<a target=_blank href='$base_url/viewVarAnnotationByGene/$project_id/$field/somatic/1/null/null/any/$patient_id'>$field</a>";
					}
					$row_data[] = $field;					
				}
			}
			
			
			if ($line_cnt > 0) {
				$data[] = $row_data;
			}
			$line_cnt++;
		}
		*/
#		$json_file=fopen("../app/tests/getAntigen_Test.json","w");
#		fwrite($json_file,json_encode(array("gene_list_idx" => $gene_list_idx, "cols" => $cols, "data" => $processed_data)));
		return json_encode(array("gene_list_idx" => $gene_list_idx, "cols" => $cols, "data" => $processed_data));

	}

	public function getCohorts($patient_id, $gene, $type) {
		$projects = Patient::getProjects($patient_id);
		$patient_aa_sites = Patient::getVarAASite($patient_id, $gene, $type);
		$patient_aa_sites_hash = array();
		foreach ($patient_aa_sites as $site)
			$patient_aa_sites_hash[$site->aa_site] = '';
		$sites = array();
		$cnt = array();
		$gene_cnt = array();
		foreach ($projects as $project) {			
			$cohorts = VarAnnotation::getDiagnosisAACohorts($project->id, $gene, $type);
			$gene_cohorts = VarAnnotation::getDiagnosisGeneCohorts($project->id, $gene, $type);
			foreach ($cohorts as $cohort) {				
				if ($cohort->aa_site == null) {
					$cohort->aa_site = "Others";
					$sites[$cohort->aa_site] = 99999;
				}
				else {
					
					if (is_int($cohort->aa_site))
						$site_pos = $cohort->aa_site;
					else
						$site_pos = substr($cohort->aa_site, 1);
					$sites[$cohort->aa_site] = $site_pos;
				}
				$cnt["prj".$project->id][$cohort->diagnosis][$cohort->aa_site] = $cohort->cnt;			
			}

			foreach ($gene_cohorts as $gene_cohort) {				
				$gene_cnt["prj".$project->id][$gene_cohort->diagnosis] = $gene_cohort->cnt;			
			}
		}
		asort($sites);
		$sites = array_keys($sites);		
		$cols = array(array("title"=>"Project"), array("title"=>"Diagnosis"), array("title" => "$gene mutation Patients"), array("title"=>"Total patients"));
		$data = array();
		foreach ($sites as $site) {
			if (array_key_exists($site, $patient_aa_sites_hash))
				$cols[] = array("title" => "<font color=lightgreen>*$site</font>");
			else
				$cols[] = array("title" => "$site");
		}

		foreach ($projects as $project) {
			$total_patients = Project::totalPatientsGroupByDiagnosis($project->id);
			$total_patients_by_diag = array();
			foreach ($total_patients as $total_patient)
				$total_patients_by_diag[$total_patient->diagnosis] = $total_patient->patient_count;
			if (!isset($cnt["prj".$project->id]))
				continue;
			foreach ($cnt["prj".$project->id] as $diagnosis => $aa_sites) {
				$total_patients = $total_patients_by_diag[$diagnosis];
				if (!isset($gene_cnt["prj".$project->id][$diagnosis]))
					continue;
				$mutation_patients = $gene_cnt["prj".$project->id][$diagnosis];
				$row_data = array();
				$row_data[] = $project->name;
				$row_data[] = $diagnosis;
				$row_data[] = '<a target=_blank href="'.url("/viewVarAnnotationByGene/$project->id/$gene/$type/1/null/null/$diagnosis").'">'."<span class='cohortDetailtooltip badge' title=\"$mutation_patients $gene mutation patients in project $project->name\">$mutation_patients</span></a>";
				$row_data[] = "<span class='cohortDetailtooltip badge' title=\"$total_patients $diagnosis patients in project $project->name\">$total_patients</span>";
				foreach ($sites as $site) {
					$site_cnt = 0;					
					if (isset($cnt["prj".$project->id][$diagnosis][$site]))
						$site_cnt = $cnt["prj".$project->id][$diagnosis][$site];
					$cohort_value = round($site_cnt/$total_patients * 100, 2);
					$bar_class = VarAnnotation::getCohortClass($cohort_value);
					$hint = "$site_cnt out of $total_patients patients have mutation at $site in gene ".$gene;				
					$row_data[] = "<span class='cohortDetailtooltip' title='$hint'><div class='progress text-center'><div class='progress-bar $bar_class progress-bar-striped' role='progressbar' aria-valuenow='$cohort_value' aria-valuemin='0' aria-valuemax='100' style='width:$cohort_value%'><span>$cohort_value%</span></div></div></span>";
				}
				$data[] = $row_data;
			}			
		}
		return array("columns" => $cols, "data" => $data);	
	}

	public function viewIGV($patient_id, $sample_id, $case_id, $type, $center, $locus) {		
		$case = VarCases::getCase($patient_id, $case_id);
		$path = $case->path;
		$case_name = $case->case_name;
		if ($case_id == "any")
			$case_name = "All cases";
		$samples = array();
		Log::info("case name: $case_name");
		if ($case_name == null)
			$samples = Sample::getProcessedSamplesByPatient($patient_id, $case_id);
		else
			$samples = Sample::getVarSamplesByPatient($patient_id, $case_name);
		$sample_files = array();
		$exp_types = array();
		$tissue_cats = array();
		$rnaseq_samples = array();
		$first_bam = '';
		$first_bams = array();
		$bams = array();
		Log::info(json_encode($samples));
		foreach ($samples as $sample) {				
			//print $sample->sample_id;
			$sample_file = VarAnnotation::findBAMfile($path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name, 'bwa');
			//print $sample_file;
			if ($sample_file == '')
				$sample_file = VarAnnotation::findBAMfile($path, $patient_id, $sample->case_id, $sample->sample_id, $sample->sample_name, 'star');

			if ($sample_file == '') 
				continue;			
			
			$bam = new stdClass();
			$bam->sample_file = $sample_file;
			//$bam->sample_name = $sample->sample_name;
			$bam->exp_type = $sample->exp_type;
			$bam->tissue_cat = $sample->tissue_cat;
			$bam->sample_name = '<font color="red">'.$sample->sample_name.'</font> '.$sample->exp_type.', '.$sample->tissue_cat;
			//if library level, default is that library. otherwise, pick the first bam matches the type
			if ($sample_id != 'null') {
				if ($sample_id == $sample->sample_id || $sample_id == $sample->sample_alias) {
					$first_bam = $bam;
					//print "First bam $sample_file<BR>"; 
				}					
			} else {
				if ($type == "rnaseq") {
					if ($sample->exp_type == "RNAseq")
						$first_bam = $bam;					
				}
				else if ($type == "germline"){
					if ($sample->tissue_cat == "normal") {						
						if ($sample->exp_type == "Exome") {							
							$first_bam = $bam;
						}
						$first_bams[] = $bam;
					}
				} else {
					if ($sample->tissue_cat == "tumor" && $sample->exp_type != "RNAseq") {
						if ($sample->exp_type == "Exome")
							$first_bam = $bam;
						$first_bams[] = $bam;
					}
				}
			}
			$bams[] = $bam;			
		}		

		//print(json_encode(['bams' => $bams, 'first_bam' => $first_bam, 'center' => $center, 'locus' => $locus, 'patient_id' => $patient_id, 'case_id' => $case_id, 'case_name' => $case_name]));
		//return;	
		if (count($bams) > 0) {
			if ($first_bam == '') {
				if (count($first_bams) > 0)
					$first_bam = $first_bams[0];
				else
					$first_bam = $bams[0];
			}
			return View::make('pages/viewIGV', ['bams' => $bams, 'first_bam' => $first_bam, 'center' => $center, 'locus' => $locus, 'patient_id' => $patient_id, 'case_id' => $case_id, 'case_name' => $case_name]);
		}
		else
			return View::make('pages/error', ['message' => 'No bam files found']);

	}

	function getBAM($path, $patient_id, $case_id, $sample_id, $filename) {
		if (!User::hasPatient($patient_id)) {
			return FALSE;
		}
		$path_to_file = storage_path()."/ProcessedResults/$path/$patient_id/$case_id/$sample_id/$filename";
		Log::info("BAM file: $path_to_file");
		if (substr($path_to_file, -3) == "bai") {
			return Response::download($path_to_file);
		}
		if(isset($_SERVER['HTTP_RANGE'])) {			
            list($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
            list($fbyte, $lbyte) = explode("-", $range);             
            //if(!$lbyte)
            //    $lbyte = $size - 1;             
            $new_length = $lbyte - $fbyte + 1; 
            $size = filesize($path_to_file);
            header("HTTP/1.1 206 Partial Content", true);            
            header("Content-Length: $new_length", true);            
            header("Content-Range: bytes $fbyte-$lbyte/$size", true);

            $file = fopen($path_to_file, 'r');            
            if(!$file)
            	return FALSE;
            fseek($file, $fbyte);
            
            $chunksize = 512 * 1024;
            while(!feof($file) and (connection_status() == 0)) {
                $buffer = fread($file, $chunksize);
                echo $buffer;
                flush();
            }
            fclose($file);
        }
        else
			print "Please view BAM using IGV page";
	}

	public function getAAChangeHGVSFormat($chr, $start_pos, $end_pos, $ref, $alt, $gene, $transcript) {
		return VarAnnotation::getAAChangeHGVSFormat($chr, $start_pos, $end_pos, $ref, $alt, $gene, $transcript);
	}

	public function getSignoutHistory($patient_id, $sample_id, $case_id, $type) {
		if (!User::hasPatient($patient_id))
			return 'Access denied!';
		$vars = VarSignout::getSignoutHistory($patient_id, $sample_id, $case_id, $type);
		foreach ($vars as $var) {
			$filename = "$patient_id.$sample_id.$case_id.$type.$var->status.$var->signout_time.txt";
			$var->{'download'} = "<a target=_blank class='btn btn-info' href='".url("/downloadSignout/$patient_id/$filename")."''>Download</a>";
			//$var->{'retrieve'} = "<a class='btn btn-info' href=\"javascript:retrieveVar('$patient_id','$sample_id','$case_id','$type','$var->signout_time');\">Retrieve</a>";
			$var->{'load'} = "<a class='btn btn-info' href=\"javascript:checkoutVar('$var->var_list');\">Load</a>";			
			unset($var->var_list);
		}
		
		return $this->getDataTableJson($vars);
	}

	public function getSignoutVars($patient_id, $sample_id, $case_id, $type, $updated_at) {
		if (!User::hasPatient($patient_id))
			return 'Access denied!';
		$vars = VarSignout::where('patient_id', $patient_id)->where('sample_id', $sample_id)->where('case_id', $case_id)->where('type', $type)->where('updated_at', '=', new DateTime($updated_at))->get();
		if (count($vars) == 0)
			return "No variants found!";
		return $vars[0]->var_list;
	}

	public function downloadSignout($patient_id, $filename) {
		if (!User::hasPatient($patient_id))
			return 'Access denied!';
		$path_to_file = storage_path()."/signed_out/$filename";
		if (!file_exists($path_to_file))
			return 'File does not exist!';
		$content = file_get_contents($path_to_file);			
		$headers = array('Content-Type' => 'text/txt','Content-Disposition' => 'attachment; filename='.$filename);
		return Response::make($content, 200, $headers);
		
	}

	public function getCircosData($patient_id, $case_name) {
		if (!User::hasPatient($patient_id))
                        return 'Access denied!';
                //get CNV data
                //$var_cnvs = VarCNV::where('patient_id', '=', $patient_id)->where('case_id', $case_id)->orderBy('chromosome','asc')->orderBy('start_pos','asc')->get();
                $case_condition="";
                if ($case_name!='any'){
                	$case_condition=" and c.case_name='$case_name'";
                }
                /*else{
                	
                	$cases = Patient::getCasesByPatientID('any', $patient_id);
                	Log::info($cases);
                	$first=0;
                	foreach ($cases as $case) {
                		$case_id_tmp=$case->case_id;
                		if($first==0)
                			$case_condition.="(t.case_id='$case_id_tmp'";
                		else
                			$case_condition.=" OR t.case_id='$case_id_tmp'";
                		$first=1;	
                	}
                	$case_condition.=")";
                }
                */
                $var_cnvs = VarCNV::getCNVByCaseName($patient_id, $case_name);
                $data = array();
                $cnv_header = array("chromosome", "start", "end", "value", "diploid");
                $sample_data = array();
                foreach ($var_cnvs as $var_cnv) {
                        $diploid = ($var_cnv->cnt == 2 && $var_cnv->allele_a == 1 && $var_cnv->allele_b == 1)? 'Y' : 'N';
                        $sample_data[$var_cnv->sample_id][] = array($var_cnv->chromosome, $var_cnv->start_pos, $var_cnv->end_pos, $var_cnv->cnt, $diploid);
                }
                foreach ($sample_data as $sample_id => $sample_cnv) {
                        $data[] = array("name" => $sample_id, "description" => "Copy number in tumor", "data_type" => "span", "plot_type" => "histogram", "header" => $cnv_header, "range" => array(0,6), "data" => $sample_cnv);
                }

                //get SNP data (only Tier 1)
                $avia_mode = VarAnnotation::is_avia();
                if (!$avia_mode)
					$tier_table="var_tier";
				else
					$tier_table="var_tier_avia";
				$sql="select distinct p.chromosome, p.start_pos, p.end_pos, p.ref, p.alt, t.gene, p.type, max(p.vaf) as vaf, t.somatic_level, t.germline_level from var_samples p, $tier_table t,processed_sample_cases c where
                        t.patient_id='$patient_id' and
                        t.sample_id=p.sample_id and
                        t.sample_id=c.sample_id and
                        t.case_id = c.case_id
                        $case_condition and
                        p.chromosome=t.chromosome and
                        p.start_pos=t.start_pos and
                        p.end_pos=t.end_pos and
                        p.ref=t.ref and
                        p.alt=t.alt and
                        p.type=t.type and
                        ((t.somatic_level like 'Tier 1%' and p.type='somatic') or (t.germline_level like 'Tier 1%' and p.type='germline'))
		     		 group by p.chromosome, p.start_pos, p.end_pos, p.ref, p.alt, t.gene, p.type, t.somatic_level, t.germline_level";
		     	Log::info($sql);
                $vars = DB::select($sql);

                $var_header = array("chromosome", "start", "end", "label", "value");
                $var_data = array();
                foreach ($vars as $var) {
                        $var_data[$var->type][] = array($var->chromosome, $var->start_pos, $var->end_pos, $var->gene, round($var->vaf, 2));
                }

                foreach ($var_data as $type => $type_data) {
                        $data[] = array("name" => Lang::get("messages.$type"), "description" => "Tier 1 VAF in ".Lang::get("messages.$type"), "data_type" => "points", "range" => array(0,1),"header" => $var_header, "data" => $type_data);
                }

                //get Fusion data
                $fusions = VarAnnotation::getFusionByPatient($patient_id, $case_name);
                $fusion_header = array("source_chromosome", "source_position", "source_gene", "target_chromosome", "target_position", "target_gene", "type", "tier");
                $fusion_data = array();
                foreach ($fusions as $fusion) {
                    if (substr($fusion->var_level,0,6) == 'Tier 1' || substr($fusion->var_level,0,6) == 'Tier 2')
                    //if ($fusion->var_level != '')
                        $fusion_data[] = array($fusion->left_chr, $fusion->left_position, $fusion->left_gene, $fusion->right_chr, $fusion->right_position, $fusion->right_gene, $fusion->type, $fusion->var_level);
				}

				//Log::info("fusion count:".count($fusion_data));
				if (count($fusion_data) > 0)
					$data[] = array("name" => "Gene fusion", "data_type" => "links", "header" => $fusion_header, "data" => $fusion_data);
                $unformatted_json = json_encode($data);
//                $json_file=fopen("../app/tests/getCircosData_Test.json","w");
//				fwrite($json_file,$unformatted_json);                
                //$cmd = "echo '$unformatted_json' | ".public_path()."/node/bin/node json";
                //$formatted_json = shell_exec($cmd);
                return $unformatted_json;		
	}

	public function getCircosDataFromDB($patient_id, $case_id) {
		if (!User::hasPatient($patient_id))
			return 'Access denied!';
		$var_cnvs = VarCNV::where('patient_id', '=', $patient_id)->where('case_id', $case_id)->get();
		$data = array();
		$cnv_header = array("chromosome", "start", "end", "value", "diploid");
		$sample_data = array();
		foreach ($var_cnvs as $var_cnv) {
			$diploid = ($var_cnv->cnt == 2 && $var_cnv->allele_a == 1 && $var_cnv->allele_b == 1)? 'Y' : 'N';
			$sample_data[$var_cnv->sample_id][] = array($var_cnv->chromosome, $var_cnv->start_pos, $var_cnv->end_pos, $var_cnv->cnt, $diploid);			
		}
		foreach ($sample_data as $sample_id => $sample_cnv) {
			$data[] = array("name" => $sample_id, "data_type" => "span", "plot_type" => "histogram", "header" => $cnv_header, "data" => $sample_cnv);
		}
		$fusions = VarAnnotation::getFusionByPatient($patient_id, $case_id);

		$fusion_header = array("source_chromosome", "source_position", "source_gene", "target_chromosome", "target_position", "target_gene", "type", "tier");
		$fusion_data = array();
		foreach ($fusions as $fusion) {
			if (substr($fusion->var_level,0,6) == 'Tier 1' || substr($fusion->var_level,0,6) == 'Tier 2')
				$fusion_data[] = array($fusion->left_chr, $fusion->left_position, $fusion->left_gene, $fusion->right_chr, $fusion->right_position, $fusion->right_gene, $fusion->type, $fusion->var_level);
		}

		if (count($fusion_data) > 0)
			$data[] = array("name" => "Gene fusion", "data_type" => "links", "header" => $fusion_header, "data" => $fusion_data);
		$unformatted_json = json_encode($data);
		$cmd = "echo '$unformatted_json' | ".public_path()."/node/bin/node json";
		$formatted_json = shell_exec($cmd);
		return $formatted_json;		
	}

	public function getCytobandData() {

      $path_to_file = public_path() . "/packages/gene_fusion/data/hg19_cytoband.json";
      if (!file_exists($path_to_file)) {
        return 'File does not exist!';
      }

      $hg19_cytoband_json = file_get_contents($path_to_file);

      return $hg19_cytoband_json;
    }

	public function viewCircos($patient_id, $case_name) {
		return View::make('pages/viewCircos', ['patient_id' => $patient_id, 'case_name' => $case_name, 'cytoband_url' => url('/packages/gene_fusion/data/hg19_cytoBand.txt')]);
	}

	public function getCNV($patient_id, $case_id, $sample_id, $source="sequenza") {
		$rows = VarAnnotation::getCNV($patient_id, $case_id, $sample_id, $source);
		if ($source == "sequenza"){
#			$json_file=fopen("../app/tests/getCNV_Test.json","w");
#			fwrite($json_file,$this->processCNV($rows));
			return $this->processCNV($rows);
		}
#		$json_file=fopen("../app/tests/getCNV_Test.json","w");
#		fwrite($json_file,$this->processCNV($rows));
		return $this->processCNVKit($rows);
		
	}

	public function getCNVByGene($project_id, $gene_id) {
		$rows = VarAnnotation::getCNVByGene($project_id, $gene_id);
		return $this->processCNV($rows);
	}


	public function processCNVKit($rows) {
		$cytoband_range = Gene::getCytobandRange();
		$cnv_hash = array();
		$user_filter_list = UserGeneList::getGeneList("all");				
		
		$cols = array(array("title" => "Patient ID"), array("title" => "Case"), array("title" => "Sample ID"), array("title" => "Chromosome"), array("title" => "Start"), array("title" => "End"), array("title" => "Length"), array("title" => "Log2"), array("title" => "Depth"), array("title" => "Probes"), array("title" => "Weight"), array("title" => "Hotspot Genes"), array("title" => "Gene List"));
		foreach ($user_filter_list as $list_name => $gene_list)
			$cols[] = array("title" => $list_name);

		$data = array();
		$genes_hash = array();
		
		$sample_id = "";
		$chromosome = "";
		$start_pos = -1;
		$end_pos = -1;
		$log2 = -1;
		$depth = -1;
		$probes = -1;
		$weight = -1;
		list($hotspot_actionable_list, $hotspot_actionable_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.actionable'));		
		foreach($rows as $row) {
			if ($sample_id != $row->sample_id || $start_pos != $row->start_pos || $end_pos != $row->end_pos || $log2 != $log2 || $chromosome != $row->chromosome) {
				//found new segment
				if ($chromosome != "") {
					//save previous segment;
					$length = round(($end_pos - $start_pos) / 1000000, 2);
					$length .= "MB";
					ksort($genes_hash);
					$gene_original = array_keys($genes_hash);
					$genes = array();
					$hotspot_genes = array();
					foreach ($gene_original as $gene) {
						$gene_link = "<a id='$gene' href='#' onclick='showExp(this)'>$gene</a>";
						$genes[] = $gene_link;
						if (array_key_exists($gene, $hotspot_actionable_list))
							$hotspot_genes[] = $gene_link;
					}
					$row_value = array($patient_id, $case_id, $sample_id, $chromosome, $start_pos, $end_pos, $length, $log2, $depth, $probes, $weight, implode(",", $hotspot_genes), implode(",", $genes));
					
					foreach ($user_filter_list as $list_name => $gene_list) {
						$has_gene = '';
						foreach ($gene_original as $gene) {
							if (array_key_exists($gene, $gene_list)) {
								$has_gene = 'Y';
								break;
							}
						}
						$row_value[] = $has_gene;
					}
					$data[] = $row_value;
				}				
				$genes_hash = array($row->gene => "");
				$patient_id = $row->patient_id;
				$case_id = $row->case_name;
				$sample_id = $row->sample_id;
				$log2 = $row->log2;
				$depth = $row->depth;
				$probes = $row->probes;
				$weight = $row->weight;
				$chromosome = $row->chromosome;
				$start_pos = $row->start_pos;
				$end_pos = $row->end_pos;
			} else {
				$genes_hash[$row->gene] = '';				
			}
		}
		
		if ($chromosome != "") {
			//save previous segment;
			$length = round(($end_pos - $start_pos) / 1000000, 2);
			$length .= "MB";
			ksort($genes_hash);
			$gene_original = array_keys($genes_hash);
			$genes = array();
			$hotspot_genes = array();
			foreach ($gene_original as $gene) {
				$gene_link = "<a id='$gene' href='#' onclick='showExp(this)'>$gene</a>";
				$genes[] = $gene_link;
				if (array_key_exists($gene, $hotspot_actionable_list))
					$hotspot_genes[] = $gene_link;
			}
			$row_value = array($patient_id, $case_id, $sample_id, $chromosome, $start_pos, $end_pos, $length, $log2, $depth, $probes, $weight, implode(",", $hotspot_genes), implode(",", $genes));
					
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = '';
				foreach ($gene_original as $gene) {
					if (array_key_exists($gene, $gene_list)) {
						$has_gene = 'Y';
						break;
					}
				}
				$row_value[] = $has_gene;
			}
			$data[] = $row_value;			
		}
		
		return json_encode(array("cols" => $cols, "data" => $data, "a" => '', "c" => '', "gi" => ''));
	}

	public function processCNV($rows) {
		$cytoband_range = Gene::getCytobandRange();
		$cnv_hash = array();
		$user_filter_list = UserGeneList::getGeneList("all");				
		$a = 0;
		$c = 0;	
		
		$cols = array(array("title" => "Patient ID"), array("title"=>"Diagnosis"),array("title" => "Case"), array("title" => "Sample ID"), array("title" => "Chromosome"), array("title" => "Start"), array("title" => "End"), array("title" => "Length"), array("title" => "CN in Tumor"), array("title" => "Allele A"), array("title" => "Allele B"), array("title" => "Hotspot Genes"), array("title" => "Gene List"));
		foreach ($user_filter_list as $list_name => $gene_list)
			$cols[] = array("title" => $list_name);

		$data = array();
		$chrs = array();

		$allele_a = -1;
		$allele_b = -1;
		$arm = "";
		$patient_id = "";
		$case_id = "";
		$sample_id = "";		
		$chromosome = "";
		$start_pos = -1;
		$end_pos = -1;
		$diagnosis='';
		$genes_hash = array();
		$total_length = 0;
		$nondiploid_length = 0;

		list($hotspot_actionable_list, $hotspot_actionable_desc) = VarAnnotation::getHotspots(storage_path()."/data/".Config::get('onco.hotspot.actionable'));		
		foreach($rows as $row) {
			$mid_point = $cytoband_range[$row->chromosome]["p"][1];
			$current_arm = ($mid_point > $row->end_pos)? "p" : "q";
			if ($sample_id != $row->sample_id || $allele_a != $row->allele_a || $allele_b != $row->allele_b || $arm != $current_arm || $chromosome != $row->chromosome) {
				//found new segment
				if ($chromosome != "") {
					//save previous segment;
					$length = round(($end_pos - $start_pos) / 1000000, 2);
					$total_length += $length;
					$length .= "MB";
					ksort($genes_hash);
					$gene_original = array_keys($genes_hash);
					$genes = array();
					$hotspot_genes = array();
					foreach ($gene_original as $gene) {
						$gene_link = "<a id='$gene' href='#' onclick='showExp(this)'>$gene</a>";
						$genes[] = $gene_link;
						if (array_key_exists($gene, $hotspot_actionable_list))
							$hotspot_genes[] = $gene_link;
					}
					$row_value = array($patient_id, $diagnosis,$case_id, $sample_id, $chromosome, $start_pos, $end_pos, $length, $allele_a + $allele_b, $allele_a, $allele_b, implode(",", $hotspot_genes), implode(",", $genes));
					if (strtolower($chromosome) != 'chry' && ($allele_a != 1 || $allele_b != 1)) {
						$nondiploid_length += $length;
						$a++;				
						$chrs[$chromosome] = '';
					}
					foreach ($user_filter_list as $list_name => $gene_list) {
						$has_gene = '';
						foreach ($gene_original as $gene) {
							if (array_key_exists($gene, $gene_list)) {
								$has_gene = 'Y';
								break;
							}
						}
						$row_value[] = $has_gene;
					}
					$data[] = $row_value;
				}				
				$genes_hash = array($row->gene => "");
				$patient_id = $row->patient_id;
				$case_id = $row->case_name;
				$sample_id = $row->sample_id;
				$allele_a = $row->allele_a;
				$allele_b = $row->allele_b;
				$arm = $current_arm;
				$chromosome = $row->chromosome;
				$start_pos = $row->start_pos;
				$end_pos = $row->end_pos;
				$diagnosis=$row->diagnosis;
			} else {
				//merge segments
				$end_pos = $row->end_pos;
				$genes_hash[$row->gene] = '';				
			}

			//$row->lpp = round($row->lpp, 2);		
		}
		//The last one
		if ($chromosome != "") {
			//save previous segment;
			$length = round(($end_pos - $start_pos) / 1000000, 2);
			$length .= "MB";
			$total_length += $length;
			ksort($genes_hash);
			$gene_original = array_keys($genes_hash);
			$genes = array();
			$hotspot_genes = array();
			foreach ($gene_original as $gene) {
				$gene_link = "<a id='$gene' href='#' onclick='showExp(this)'>$gene</a>";
				$genes[] = $gene_link;
				if (array_key_exists($gene, $hotspot_actionable_list))
					$hotspot_genes[] = $gene_link;
			}
			$row_value = array($patient_id, $diagnosis,$case_id, $sample_id, $chromosome, $start_pos, $end_pos, $length, $allele_a + $allele_b, $allele_a, $allele_b, implode(",", $hotspot_genes), implode(",", $genes));
			if (strtolower($chromosome) != 'chry' && ($allele_a != 1 || $allele_b != 1)) {
				$nondiploid_length += $length;
				$a++;				
				$chrs[$chromosome] = '';
			}
			foreach ($user_filter_list as $list_name => $gene_list) {
				$has_gene = '';
				foreach ($genes as $gene) {
					if (array_key_exists($gene, $gene_list)) {
						$has_gene = 'Y';
						break;
					}
				}
				$row_value[] = $has_gene;
			}
			$data[] = $row_value;
		}
		
		$c = count(array_keys($chrs));
		Log::info(json_encode(array_keys($chrs)));
		$gi = ($c==0)? 'NA' : round($a/$c,2);
		return json_encode(array("cols" => $cols, "data" => $data, "a" => $a, "c" => $c, "gi" => $gi, "total_length" => $total_length, "nondiploid_length" => $nondiploid_length));
	}

	public function viewCNV($project_id, $patient_id, $case_id, $sample_name, $source) {
		if ($sample_name == "any") {
			$samples = Sample::where('patient_id', $patient_id)->get();
		}
		else {
			$sample_name = str_replace("Sample_", "", $sample_name);
			$samples = Sample::where('sample_name', $sample_name)->orWhere("sample_id", $sample_name)->get();
		}
		if (count($samples) == 0)
			return View::make('pages/error_no_header', ['message' => "samples not found"]);
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('all');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		$rnaseq_samples = array();
		foreach ($samples as $sample) {
			$sample_id = $sample->sample_id;
			$rnaseq_sample_id = $sample->rnaseq_sample;
			$rows = Sample::where('sample_id', $rnaseq_sample_id)->get();
			if (count($rows) > 0)
				$rnaseq_samples[$rnaseq_sample_id] = $rows[0]->sample_name;
		}
		if ($sample_name == "any")
			$sample_id = "any";
		return View::make('pages/viewCNV', ['project_id' => $project_id, 'gene_id' => 'null', 'patient_id' => $patient_id, 'case_id' => $case_id, 'sample_id' => $sample_id, 'rnaseq_samples' => $rnaseq_samples, 'filter_definition' => $filter_definition, 'source' => $source]);
	}


	public function viewCNVByGene($project_id, $gene_id) {
		$filter_definition = array();
		$filter_lists = UserGeneList::getDescriptions('all');
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}
		return View::make('pages/viewCNV', ['project_id' => $project_id, 'gene_id' => $gene_id, 'patient_id' => 'null','diagnosis'=>'null', 'case_id' => 'null', 'sample_id' => 'null', 'rnaseq_samples' => array(), 'filter_definition' => $filter_definition, 'source' => 'sequenza']);
	}

	public function createReport() {
		$source = storage_path()."/data/reports/Clinomics_report.docx";
		
		$phpWord = \PhpOffice\PhpWord\IOFactory::load($source);

		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');
		$objWriter->save(storage_path().'/data/reports/output.pdf');
		return;
		//$phpWord = new \PhpOffice\PhpWord\PhpWord();
		/* Note: any element you append to a document must reside inside of a Section. */

 		// Adding an empty Section to the document...
		$section = $phpWord->addSection();

		// Adding Text element to the Section having font styled by default...
		$section->addText(
    		htmlspecialchars(
        		'"Learn from yesterday, live for today, hope for tomorrow. '
            	. 'The important thing is not to stop questioning." '
            	. '(Albert Einstein)'
    		)
		);

		// Saving the document as HTML file...
		$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
		$objWriter->save(storage_path().'/data/reports/output.docx');
	}

	public function uploadVarData() {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user","desc"=>""));
		}
		$output_dir = storage_path()."/ProcessedResults/uploads/files/$user->id";
		system("mkdir -p $output_dir");
		if(isset($_FILES["myfile"]))
		{
			$ret = array();
			
		 	$file_name = $_FILES["myfile"]["name"];
			move_uploaded_file($_FILES["myfile"]["tmp_name"], "$output_dir/$file_name");
			$fh = fopen("$output_dir/$file_name",'r');
			$header = array();
			while ($line = fgets($fh)) {
				if (substr($line, 0, 6) == "#CHROM") {
					$header = explode("\t", rtrim($line));
					array_splice($header, 0, 9);
					break;
				}
			}
			fclose($fh);			
			$cmd = app_path()."/scripts/backend/vcf2txt.pl $output_dir/$file_name ".app_path()."/bin/ANNOVAR/2016-02-01 Y";
			Log::info($cmd);
			exec($cmd, $output);
		   	$ret[]= array("code"=>"ok", "upload_id" => uniqid(), "file_name" => $file_name, "samples" => $header, "caller" => $output[0]);
			echo json_encode($ret);
		 }
	}

	public function uploadExpData() {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user","desc"=>""));
		}
		$output_dir = storage_path()."/ProcessedResults/uploads/files/$user->id";
		system("mkdir -p $output_dir");
		if(isset($_FILES["myfile"]))
		{
			$ret = array();
			
		 	$file_name = $_FILES["myfile"]["name"];
			move_uploaded_file($_FILES["myfile"]["tmp_name"], "$output_dir/$file_name");
			$fh = fopen("$output_dir/$file_name",'r');
			$header = array();
			$level = "NA";
			$type = "NA";
			if ($line = fgets($fh)) {
				$fields = explode("\t", rtrim($line));
				if (count($fields) == 2) {
					if (substr($fields[0], 0, 4) == "ENSG") {
						$level = "gene";
						$type = "ensembl";
					}
					else if (substr($fields[0], 0, 4) == "ENST") {
						$level = "trans";
						$type = "ensembl";
					}
					else if (substr($fields[0], 0, 2) == "NM" || substr($fields[0], 0, 2) == "NR") {
						$level = "trans";
						$type = "refseq";
					} else {
						$level = "gene";
						$type = "refseq";
					}
				}
			}
			fclose($fh);			
			$ret[]= array("code"=>"ok", "upload_id" => uniqid(), "file_name" => $file_name, "level" => $level, "type" => $type);
			echo json_encode($ret);
		 }
	}

	public function uploadFusionData() {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user"));
		}
		$output_dir = storage_path()."/ProcessedResults/uploads/files/$user->id";
		system("mkdir -p $output_dir");
		if(isset($_FILES["myfile"]))
		{
			$ret = array();
			
		 	$file_name = $_FILES["myfile"]["name"];
			move_uploaded_file($_FILES["myfile"]["tmp_name"], "$output_dir/$file_name");
			$fh = fopen("$output_dir/$file_name",'r');
			$header = array();
			$code = "failed";
			if ($line = fgets($fh)) {
				$fields = explode("\t", rtrim($line));
				if (count($fields) >= 10) {
					$code = "ok";
				}
			}
			fclose($fh);			
			$ret[]= array("code"=> $code, "file_name" => $file_name);
			echo json_encode($ret);
		 }
	}

	public function processVarUpload() {
		$user = User::getCurrentUser();
		if ($user == null) {
			return json_encode(array("code"=>"no_user","desc"=>""));
		}
		$user_id = $user->id;
		$email = $user->email;
		$data = Input::all();		
		$output_dir = storage_path()."/ProcessedResults/uploads";
		$vars = array();
		$tissue_type = array();
		$sample_info = array();
		$sample_mappings = array();
		$project_id = $data["project_id"];
		$patient_id = $data["patient_id"];
		$case_id = $data["case_id"];
		$diagnosis = $data["diagnosis"];
		$exp_type = $data["exp_type"];
		$exp_sample_id = array_key_exists("exp_sample_id", $data)? $data["exp_sample_id"] : "";
		$exp_tissue_cat = array_key_exists("exp_tissue_cat", $data)? $data["exp_tissue_cat"] : "";
		$exp_library_type = array_key_exists("exp_library_type", $data)? $data["exp_library_type"] : "";
		$fusion_sample_id = array_key_exists("fusion_sample_id", $data)? $data["fusion_sample_id"] : "";
		$fusion_tissue_cat = array_key_exists("fusion_tissue_cat", $data)? $data["fusion_tissue_cat"] : "";
		$fusion_library_type = array_key_exists("fusion_library_type", $data)? $data["fusion_library_type"] : "";
		#$override = $data["override"];
		$override = "Y";
		$patient = Patient::find($patient_id);
		if ($patient != null) {
			if ($patient->user_id == $user_id) {
				if  ($override == "N")
					return json_encode(array("code"=>"patient_exists_prompt","desc"=>""));
			}
		//	else
		//		return json_encode(array("code"=>"error","desc"=>"Patient $patient_id already exists!"));
		}
		if (array_key_exists("vcfs", $data)) {
			foreach ($data["vcfs"] as $file_name => $vcf) {			 
				$txt_name = "$output_dir/files/$user_id/$file_name.txt";
				$cmd = app_path()."/scripts/backend/vcf2txt.pl $output_dir/files/$user_id/$file_name ".app_path()."/bin/ANNOVAR/2016-02-01 N > $txt_name";
				Log::info($cmd);
				system($cmd, $ret);
				Log::info("return code: ".$ret );
				system("chmod 775 $txt_name");
				$caller = $vcf["caller"];
				$type = $vcf["type"];
				
				foreach ($vcf["samples"] as $sample_mapping) {
					$sample_id = $sample_mapping["sample_id"];
					$current_sample = Sample::getSample($sample_id);
					if ($current_sample != null && $current_sample->patient_id != $patient_id)
						return json_encode(array("code"=>"error","desc"=>"Sample $sample_id already exists!"));
					$sample_mappings[$sample_mapping["sample_id_vcf"]] = $sample_id;
					$sample_info[$sample_id] = array("tissue_cat" => $sample_mapping["tissue_cat"], "material_type" => $sample_mapping["material_type"], "library_type" => "");


				}
 
				Log::info(json_encode($sample_mappings));
				Log::info(json_encode($sample_info));

				$fh = fopen($txt_name,'r');
				$header = array();
				while ($line = fgets($fh)) {
					if (count($header) == 0) {
						$header = explode("\t", rtrim($line));
						continue;
					}
					$fields = explode("\t", rtrim($line));
					$key = implode("\t", array_slice($fields, 0, 5));
					$qual = $fields[5];				
					for ($i=9;$i<count($fields);$i+=5) {					
						$sample_id_vcf = substr($header[$i], 0, -3);
						$sample_id = $sample_mappings[$sample_id_vcf];
						$total_cov = $fields[$i + 1];
						$var_cov = $fields[$i + 3];
						$vars[$type][$key][$sample_id][$caller] = array($total_cov, $var_cov);
					}
				}
				fclose($fh);			
			}

		}
				

		$current_sample = Sample::getSample($exp_sample_id);
		if ($current_sample != null && $current_sample->patient_id != $patient_id)
			return json_encode(array("code"=>"error","desc"=>"Sample $exp_sample_id already exists!"));
		if (array_key_exists("exps", $data))
			$sample_info[$exp_sample_id] = array("tissue_cat" => $exp_tissue_cat, "material_type" => "RNA", "library_type" => $exp_library_type);
		if ($data["fusion_data"] != null)
			$sample_info[$fusion_sample_id] = array("tissue_cat" => $fusion_tissue_cat, "material_type" => "RNA", "library_type" => $fusion_library_type);		

		try {				
			DB::beginTransaction();						
			DB::table('project_patients')->insert(['project_id' => $project_id, 'patient_id' => $patient_id, 'case_name' => $case_id]);
			if ($patient == null) {
				Patient::where('patient_id', '=', $patient_id)->delete();
				$project = Project::find($project_id);
				$patient = new Patient;
				$patient->patient_id = $patient_id;
				$patient->diagnosis = $diagnosis;
				$patient->project_name = ($project == null)? "" : $project->name;
				$patient->case_list = $case_id;
				$patient->is_cellline = 'N';
				$patient->user_id = $user_id;				
				$patient->save();
			} else {
				Patient::where('patient_id', "$patient_id")->update(['case_list' => $patient->case_list.",".$case_id, "diagnosis" => $diagnosis]);
			}			
			foreach ($sample_info as $sample_id => $sample_data) {
				Sample::where('sample_id', '=', $sample_id)->delete();
				DB::table('sample_cases')->where('sample_id', '=', $sample_id)->where('patient_id', $patient_id)->where('case_name',$case_id)->delete();
				$sample = new Sample;
				$sample->sample_id = $sample_id;
				$sample->sample_name = $sample_id;
				$sample->alias = $sample_id; 
				$sample->patient_id = $patient_id;
				$sample->sample_id = $sample_id;
				$sample->material_type = $sample_data["material_type"];
				$sample->exp_type = ($sample->material_type == "RNA")? "RNAseq" : $exp_type;				
				$sample->tissue_cat = $sample_data["tissue_cat"];
				$sample->tissue_type = $diagnosis;
				$sample->library_type = ($sample_id == $exp_sample_id)? $exp_library_type : "";
				$sample->normal_sample = array_key_exists("normal_sample_id", $data)? $data["normal_sample_id"] : "";
				$sample->rnaseq_sample = array_key_exists("rnaseq_sample_id", $data)? $data["rnaseq_sample_id"] : "";
				$sample->case_name = $case_id;					
				$sample->relation = "self";
				$sample->save();
				//DB::table('project_samples')->insert(['project_id' => $project_id, 'patient_id' => $patient_id, 'sample_id' => $sample_id, 'sample_name' => $sample_id, "tissue_cat" => $sample_data["tissue_cat"], 'tissue_type' => $diagnosis, 'material_type' => $sample_data["material_type"], 'exp_type' => $exp_type]);				
				DB::table('sample_cases')->insert(['sample_id' => $sample_id, 'patient_id' => $patient_id, 'case_name' => $case_id]);
			}				
			DB::commit();			
		} catch (\PDOException $e) { 
			DB::rollBack();
			return json_encode(array("code"=>"error","desc"=>$e->getMessage()));
			
		}
		$db_folder = "$output_dir/$patient_id/$case_id/$patient_id/db";
		exec("mkdir -p $db_folder");
		Log::info("mkdir -p $db_folder");
		foreach ($vars as $type => $var_data) {
			$fh = fopen("$db_folder/$patient_id.$type","w");
			foreach ($var_data as $key => $sample_data) {
				$total_cov = 0;
				$var_cov = 0;
				foreach ($sample_data as $sample_id => $caller_data) {					
					$callers = array_keys($caller_data);
					$caller_str = implode(";", $callers);
					$tissue_type = $sample_info[$sample_id]["tissue_cat"];
					foreach ($caller_data as $caller => $cov_data) {
						list($caller_total_cov, $caller_var_cov) = $cov_data;
						if ($caller_total_cov > $total_cov) {
							$total_cov = $caller_total_cov;
							$var_cov = $caller_var_cov;
						}
					}
					fwrite($fh, "$key\t$sample_id\t$tissue_type\tNA\t$caller_str\tNA\tNA\t$total_cov\t$var_cov\n");
				}
			}			
			fclose($fh);

		}
		
		if (array_key_exists("exps", $data)) {
			foreach ($data["exps"] as $file_name => $exp) {	
				$exp_file = "$output_dir/files/$user_id/$file_name";
				$type_folder = "TPM_UCSC";
				$type=$exp["type"];
				if ($exp["type"] == "ensembl")
					$type_folder = "TPM_ENS";
				$level_str = "Gene";
				if ($exp["level"] == "trans")
					$level_str = "Transcript";
				$exp_folder = "$output_dir/$patient_id/$case_id/$exp_sample_id/$type_folder";
				$count_file = $exp_sample_id."_counts.$level_str.txt";
#				$fpkm_file = $exp_sample_id."_fpkm.$level_str.txt";
				exec("mkdir -p $exp_folder");
#				$fh_count = fopen("$exp_folder/$count_file","w");
#				$fh_fpkm = fopen("$exp_folder/$fpkm_file","w");			
#				fwrite($fh_count, "Chr\tStart\tEnd\t$level_str"."ID\tLength\t$exp_sample_id\n");
#				fwrite($fh_fpkm, "Chr\tStart\tEnd\t$level_str"."ID\t$exp_sample_id\n");
				$fh = fopen($exp_file,'r');
				while ($line = fgets($fh)) {
					$fields = explode("\t", rtrim($line));
					if (count($fields) != 2)
						continue;
#					$target_id = $fields[0];
#					$fpkm = $fields[1];
#					$count = $fields[1];
#					fwrite($fh_count, "-\t-\t-\t$target_id\t-\t$count\n");
#					fwrite($fh_fpkm, "-\t-\t-\t$target_id\t$fpkm\n");
				}
				#Creates a gene expression file and filters out genes not found in Annotation file
				Log::info("Rscript ".app_path()."/scripts/ProcessExpressionFile.R ".$exp_file." ".app_path()."/storage/data/AnnotationRDS/annotation_".strtoupper($type)."_gene_19.RDS ".$exp_folder."/ ".$exp_sample_id); 
				exec("Rscript ".app_path()."/scripts/ProcessExpressionFile.R ".$exp_file." ".app_path()."/storage/data/AnnotationRDS/annotation_".strtoupper($type)."_gene_19.RDS ".$exp_folder."/ ".$exp_sample_id);
				

				$sample_file=$exp_folder."/".$exp_sample_id."_counts.gene.fc.RDS";
				$rds_string=$exp_sample_id."\t".$exp_sample_id."\t".$sample_file."\n";
				$rds_list_file = fopen($exp_folder."/".$exp_sample_id."_list.tsv",'w');
				$email = $user->email_address; 
				fwrite($rds_list_file,$rds_string);
				
				#Calculates tmm
				Log::info('Rscript '.app_path().'/scripts/tmmNormalize.r '.$exp_folder."/".$exp_sample_id."_list.tsv"." ".app_path().'/storage/data/AnnotationRDS/annotation_'.strtoupper($type).'_gene_19.RDS gene '.$exp_folder.' exp.gene');
				exec('Rscript '.app_path().'/scripts/tmmNormalize.r '.$exp_folder."/".$exp_sample_id."_list.tsv"." ".app_path().'/storage/data/AnnotationRDS/annotation_'.strtoupper($type).'_gene_19.RDS gene '.$exp_folder.'/ tmm.gene');

				#Creates TPM file
				Log::info("Rscript ".app_path()."/scripts/CreateTPMFile.R ".$exp_folder.'/tmm.gene.tpm.tsv'." ".app_path()."/storage/data/AnnotationRDS/annotation_".strtoupper($type)."_gene_19.RDS ".$exp_folder."/ ".$exp_sample_id);
				exec("Rscript ".app_path()."/scripts/CreateTPMFile.R ".$exp_folder.'/tmm.gene.tpm.tsv'." ".app_path()."/storage/data/AnnotationRDS/annotation_".strtoupper($type)."_gene_19.RDS ".$exp_folder."/ ".$exp_sample_id);

#				fclose($fh_count);
#				fclose($fh_fpkm);
			}
		}		

		if ($data["fusion_data"] != null) {
			$fusion_data = $data["fusion_data"];
			$file_name = $fusion_data["file_name"];
			$fusion_in_file = "$output_dir/files/$user_id/$file_name";
			$fusion_folder = "$output_dir/$patient_id/$case_id/Actionable";
			$fusion_out_file = "$fusion_folder/$patient_id.fusion.actionable.txt";
			exec("mkdir -p $fusion_folder");
			$fh_in = fopen($fusion_in_file,'r');
			$fh_out = fopen($fusion_out_file,'w');
			$headers="#LeftGene\tRightGene\tChr_Left\tPosition\tChr_Right\tPosition\tSample\tTool\tSpanReadCount\n";
			fwrite($fh_out, $headers);
			while ($line = fgets($fh_in)) {
				$fields = explode("\t", rtrim($line));
				if (count($fields) >= 10) {
					Log::info("FUSION");
					$chr_left=$fields[0];
					$chr_left_start=$fields[1];
					$chr_left_end=$fields[2];
					$chr_left_strand=$fields[8];

					$chr_right=$fields[3];
					$chr_right_start=$fields[4];
					$chr_right_end=$fields[5];
					$chr_right_strand=$fields[9];

					$read_count=$fields[10];

					$row_left=VarFusion::getfusionGenes($chr_left,$chr_left_start,$chr_left_end,$chr_left_strand);
					$row_right=VarFusion::getfusionGenes($chr_right,$chr_right_start,$chr_right_end,$chr_right_strand);

					if(count($row_right)>0 && count($row_left)>0){

						$pos_left=$row_left{0}->end_pos;
						$gene_left=$row_left{0}->symbol;

						$pos_right=$row_right{0}->end_pos;
						$gene_right =$row_right{0}->symbol;

						$new_line = "$gene_left\t$gene_right\t$chr_left\t$pos_left\t$chr_right\t$pos_right\t$fusion_sample_id\tNA\t$read_count\n";
						fwrite($fh_out, $new_line);

					}


				}
			}
			$email = $user->email_address;
			Log::info($patient_id);
			Log::info($case_id);
			Log::info($output_dir);
			#Log::info(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -s -t fusion -e $email 2>&1 -r");
			#exec(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -s -t fusion -e $email 2>&1 -r", $output);
#			DB::table('cases')->where('patient_id', "$patient_id")->where('case_id', $case_id)->update(['case_name' => $case_id,'case_id' => $case_id]);
#			DB::table('sample_cases')->where('patient_id', "$patient_id")->where('sample_id', $fusion_sample_id)->update(['case_name' => $case_id,'case_id' => $case_id]);
			fclose($fh_in);
			fclose($fh_out);
		}

		$output = "";
		
#UNCOMMENT
#		Log::info($patient_id);
#		Log::info($case_id);
#		Log::info("SAMPLE_ID: $fusion_sample_id");
		
		if (array_key_exists("vcfs", $data)) {
			Log::info("EXECUTING INSERT VARIANT SCRIPT");
			Log::info(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -t variants -k -g -s -e $email 2>&1");
			exec(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -k -g -s -e $email 2>&1", $output);
			Log::info("DONE WITH INSERT VARIANT SCRIPT");
		}
		if ($data["fusion_data"] != null) {
			Log::info("EXECUTING INSERT FUSION SCRIPT");
			exec(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -g -s -e -t fusion $email 2>&1", $output);
			Log::info("DONE WITH INSERT FUSION SCRIPT");
		}
		if (array_key_exists("exps", $data)) {
			Log::info("EXECUTING INSERT EXPRESSION SCRIPT");
			exec(app_path()."/scripts/backend/loadVarPatients.pl -i $output_dir -d Oncogenomics -p $patient_id -c $case_id -t exp -g -s -x -e $email 2>&1", $output);
			DB::table('cases')->where('patient_id', "$patient_id")->where('case_id', $case_id)->update(['case_name' => $case_id,'case_id' => $case_id]);
			DB::table('sample_cases')->where('patient_id', "$patient_id")->where('sample_id', $exp_sample_id)->update(['case_name' => $case_id,'case_id' => $case_id]);
			Log::info("DONE WITH INSERT EXPRESSION SCRIPT");
		}
		exec(app_path()."/scripts/backend/updateVarCases.pl");
		exec(app_path()."/scripts/backend/refreshProcessedSamplesView.pl");
		
		Log::info($output);

#UNCOMMENT		

		

		#DB::delete("delete var_patients v where patient_id='$patient_id' and case_id='$case_id' and exists(select * from hg19_annot@pub_lnk a where SUBSTR(v.chromosome,4) = a.chr and v.start_pos=a.query_start and v.end_pos=a.query_end and v.ref=a.allele1 and v.alt=a.allele2 and (maf > 0.05 or annovar_annot not in ('exonic','splicing','exoinc;splicing')))");
		#DB::delete("delete var_samples v where patient_id='$patient_id' and case_id='$case_id' and exists(select * from hg19_annot@pub_lnk a where SUBSTR(v.chromosome,4) = a.chr and v.start_pos=a.query_start and v.end_pos=a.query_end and v.ref=a.allele1 and v.alt=a.allele2 and (maf > 0.05 or annovar_annot not in ('exonic','splicing','exoinc;splicing')))");
		
		return json_encode(array("code"=>"success","desc"=>json_encode($output)));
	}
	public function getTopVarGenes() {
   		$top_vars = VarAnnotation::getTopVarGenes();   		
   		return json_encode($top_vars);
   	}
   	public function getVarGeneSummary($gene_id, $value_type, $category, $min_pat = 0, $tiers = 'Tier 1') {
   		return json_encode(VarAnnotation::getVarGeneSummary($gene_id, $value_type, $category, $min_pat, $tiers));
   	}

   	public function getCNVGeneSummary($gene_id, $value_type, $category, $min_pat = 0, $min_amplified = 3, $max_deleted = 1) {
   		return json_encode(VarAnnotation::getCNVGeneSummary($gene_id, $value_type, $category, $min_pat, $min_amplified, $max_deleted));	
   	}

   	public function getFusionGeneSummary($gene_id, $value_type, $category, $min_pat = 0, $fusion_type = 'All', $tiers = 'Tier 1') {   		
   		return json_encode(VarAnnotation::getFusionGeneSummary($gene_id, $value_type, $category, $min_pat, $fusion_type, $tiers));
   	}

   	public function getFusionGenePairSummary($gene_id, $value_type, $category, $min_pat = 0, $fusion_type = 'All', $tiers = 'Tier 1') {   		
   		return json_encode(VarAnnotation::getFusionGenePairSummary($gene_id, $category, $min_pat, $fusion_type, $tiers));   		
   	}

   	public function getPatientsByFusionGene($gene_id, $cat_type, $category, $fusion_type = 'All', $tiers = 'Tier 1') {
		return json_encode(VarAnnotation::getPatientsByFusionGene($gene_id, $cat_type, $category, $fusion_type, $tiers));
   	}

   	public function getPatientsByFusionPair($left_gene, $right_gene, $fusion_type = 'All', $tiers = 'Tier 1') {
		return json_encode(VarAnnotation::getPatientsByFusionPair($left_gene, $right_gene, $fusion_type, $tiers));
   	}

   	public function getPatientsByCNVGene($gene_id, $cat_type, $category, $min_amplified = 3, $max_deleted = 1) {
		return json_encode(VarAnnotation::getPatientsByCNVGene($gene_id, $cat_type, $category, $min_amplified, $max_deleted));
   	}

   	public function getPatientsByVarGene($gene_id, $type, $cat_type, $category, $tiers = 'Tier 1') {
		return json_encode(VarAnnotation::getPatientsByVarGene($gene_id, $type, $cat_type, $category, $tiers));
   	}

   	public function getMutationBurden($project_id, $patient_id, $case_id) {
   		return $this->getDataTableJson(VarAnnotation::getMutationBurden($project_id, $patient_id, $case_id));
   	}

	public function viewMutationBurden($project_id, $patient_id, $case_id) {
   		return View::make('pages/viewMutationBurden', ['project_id' => $project_id, 'patient_id'=>$patient_id, 'case_id' => $case_id]);
   	}
   	public function getVarAnnotationByVariant($chr,$start,$end,$ref,$alt){
   		#$chr='chr5';
		#$start=90087023;
		#$end=90087023;
		#$ref='A';
		#$alt='G';
		UserSetting::saveSetting('default_annotation', 'avia', false);
   		$var = VarAnnotation::getVarAnnotationByVariant($chr,$start,$end,$ref,$alt);
		list($data, $columns) = $var->getDataTable();
		$json = json_encode(array("data"=>$data, "cols"=>$columns));
		return $json;
   	}
   	public function viewVariant($chr,$start,$end,$ref,$alt){
   		$show_columns = array_values((array)UserSetting::getSetting("page.columns"))[0];
   		$filter_definition = Config::get('onco.filter_definition');
   		$var_list = "[]";
   		$type='germline';
   		$status = "active";
		$type_str = ($type == "hotspot")? "all" : $type;
		$filter_lists = UserGeneList::getDescriptions($type_str);
		foreach ($filter_lists as $list_name => $desc) {
			$filter_definition[$list_name] = $desc;
		}

		$setting = UserSetting::getSetting("page.$type"); 
        
        return View::make('pages/viewVariant', ['with_header' => 0, 'filter_definition' => $filter_definition, 'setting' => $setting, 'show_columns' => json_encode($show_columns), 'var_list' => $var_list, 'update_setting' => true,'status' => $status,'type' => $type,'gene_id' => 'null','chr'=>$chr,'start'=>$start,'end'=>$end,'ref'=>$ref,'alt'=>$alt]);
   		#return View::make('pages/viewVariant');

   	}
   	public function insertVariant($chr,$start,$end,$ref,$alt){
   			VarAnnotation::insertVariant($chr,$start,$end,$ref,$alt);
   	}
}
