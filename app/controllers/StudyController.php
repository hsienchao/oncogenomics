<?php

use Log;

class StudyController extends BaseController {


	private $types;

	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function viewStudies() {
		$cols = $this->getColumnJson("studies", Config::get('onco.study_column_exclude'));
		$cols[] = array("title" => 'Patients');
		$cols[] = array("title" => 'Samples');
		$cols[] = array("title" => 'Analyses');
		$cols[] = array("title" => 'Kaplan-Meier');
		$cols[] = array("title" => 'Edit');
		$cols[] = array("title" => 'Delete');
		return View::make('pages/viewTable', ['cols'=>$cols, 'col_hide'=> Config::get('onco.study_column_hide'), 'filters'=> Config::get('onco.study_column_filter'),'title'=>'Study', 'primary_key'=>'all', 'url'=>url('/getStudies')]);
		
	}
	

	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function getStudies() {
		$studies = Study::getAllStudies();
		$user_id = $this->getUserID();
		$permissions = User::getCurrentUserPermissions();
		$isadmin = false;
		//echo json_encode($permissions)
		foreach ($permissions as $permission => $permission_id) {
			if ($permission == "_superadmin") {
				$isadmin = true;
				break;
			}
		}

		foreach ($studies as $study){
			$study->patient_num = $study->getPatientCount();
			$study->sample_num = $study->getSampleCount();			
			$study->analysis_num = $study->getAnalysisCount();
			$study->kaplan_meier = ($study->hasSurvivalSample())?'Y':'N';
			//$study->sample_num = 0;
			if ($user_id == $study->user_id || $isadmin) {
				$study->edit = '<a href='.url('/editStudy/'.$study->id).'>Edit</a>';
				//$study->delete = '<a href='.url('/deleteStudy/'.$study->id).'>Delete</a>';
				$study->delete = "<a href=javascript:deleteStudy('".$study->id."');>Delete</a>";
			} else {
				$study->edit = "";
				$study->delete = "";
			}
			$study->study_name = '<a href='.url('/viewStudyDetails/'.$study->id).'>'.$study->study_name.'</a>';

		}
		//return $studies->toArray();
		return $this->getDataTableAjax($studies->toArray(), Config::get('onco.study_column_exclude'));
	}
	

	/**
	 * Prepare create study page. It will generate the tree json and sample list
	 *
	 * @return editStudy view page
	 */
	public function prepareCreateStudy() {     
		//list($json_tree, $sample_list) = $this->prepareSampleStudy();
		list($json_tree, $patient_list) = $this->prepareStudy();
		return View::make('pages/editStudy', ['json_tree'=>$json_tree, 'patient_list' => $patient_list, 'study_json' => "''", 'mode' => 'create']);
	}

	/**
	 * Prepare edit study page. It will generate the tree json and sample list
	 *
	 * @return editStudy view page
	 */	

	public function prepareEditStudy($sid) {      
		$study = Study::getStudy($sid);
		//$study_samples = Study_sample::where('study_id', '=', $sid)->get();
		$study_samples = DB::select("select * from study_samples where study_id = $sid");
		$tissues = array();
		foreach ($study_samples as $sample) {
			$tissues[$sample->tissue_cat."_".$sample->tissue_type][] = $sample->patient_id;
		}
		$sample_tissues = array();
		foreach ($tissues as $tissue=>$patient_id) {
			$sample_tissues[] = array("tissue_name"=>$tissue, "patients"=>$patient_id);
		}
		list($json_tree, $patient_list) = $this->prepareStudy();
		$study->tissues = $sample_tissues;
		$json = json_encode($study);		
		//return $json;
		return View::make('pages/editStudy', ['json_tree'=>$json_tree, 'patient_list' => $patient_list, 'study_json' => $json, 'mode' => 'edit']);      
	}


	public function getSampleSubTree($node_name, $arr, $parent_name, $sample_list) {
		$num_children = 0;
		$node = new stdClass();		
		$node->id = ltrim($parent_name."?".$node_name, "?All?");
		$node->type = $this->getType($node->id);
		if ($this->is_assoc($arr)) {
			$node->children = array();
			foreach ($arr as $key=>$value) {
				list($c, $n, $d, $s) = $this->getSampleSubTree($key, $value, $node->id, $sample_list);
				$sample_list = $s;
				$node->children[] = $c;
				$num_children += $n;
				$depth = $d + 1;
			}
		}
		else {
			$num_children = count($arr);
			$sample_list[$node->id] = $arr; 
			$depth = 1;
		}		
		$node->text = $node_name."(".$num_children.")";
		if ($node->id == '') $node->id = 'root';
		if ($depth > 2)
			$node->state = array('opened'=>'true');

		return array($node, $num_children, $depth, $sample_list);
	}

	public function getType($id) {
		$type_keys = array_keys($this->types);
		foreach ($type_keys as $type_key) {
			if (strlen($type_key) > strlen($id)) continue;
			if (substr($id, 0, strlen($type_key)) == $type_key)
				return $type_key;
		}
		return "default";
	
	}

	/**
	 * Prepare edit study page. It will generate the tree json and sample list
	 *
	 * @return editStudy view page
	 */
	public function prepareSampleStudy() {
		$tree = new stdClass();
		$tree->types = array('default'=>new stdClass(), 'DNA'=>new stdClass(), 'RNA?RNAseq?Illumina?ribo' => new stdClass(), 'RNA?RNAseq?Illumina?polyA' => new stdClass(), 'RNA?RNAseq?Illumina?access' => new stdClass(), 'RNA?Array?plus2' => new stdClass(), 'RNA?Array?uber' => new stdClass(), 'RNA?RNAseq?Solid?polyA' => new stdClass(), 'RNA?RNAseq?Solid?unknown' => new stdClass());
		$this->types = $tree->types;
		$tree->plugins = array('types');
		$samples = Sample::all();
		$sample_tree = array();
		foreach ($samples as $sample) {		
			if ($sample->material_type == '' || $sample->exp_type == '' || $sample->platform == '' || $sample->tissue_cat == '' || $sample->tissue_type == '') 
				continue;
			if ($sample->material_type == 'RNA' && $sample->platform == 'Solid' && $sample->library_type == '') $sample->library_type = 'unknown';
			if ($sample->library_type == '') {				
				$sample_tree[$sample->material_type][$sample->exp_type][$sample->platform][$sample->tissue_cat][$sample->tissue_type][] = $sample->sample_id;
			} else {
				if (substr($sample->library_type,0,4) == 'ribo') $sample->library_type = 'ribo';
				$sample_tree[$sample->material_type][$sample->exp_type][$sample->platform][$sample->library_type][$sample->tissue_cat][$sample->tissue_type][] = $sample->sample_id;
			}
		}

		list($t, $c, $d, $sample_list) = $this->getSampleSubTree("All", $sample_tree, "", array());		
		$tree_data = array($t);
		$tree->core = array('data' => $tree_data);
		$json = json_encode($tree);
		//echo $json;return;
		
		return array($json, $sample_list);
	}

/**
	 * Prepare edit study page. It will generate the tree json and sample list
	 *
	 * @return editStudy view page
	 */
	public function prepareStudy() {
		$tree = new stdClass();
		$tree->types = array('default'=>new stdClass());
		$this->types = $tree->types;
		$tree->plugins = array('types');
		//$patients = Patient::all();
		$patients = Patient::all_with_samples();
		$patient_tree = array();

		foreach ($patients as $patient) {
			if ($patient->patient_id == '' || $patient->diagnosis == '') 
				continue;
			$patient_type = ($patient->is_cellline == 'Y')?'Cell line':'Patient';
			$patient_tree[$patient_type][$patient->diagnosis][] = $patient->patient_id;			
		}

		list($t, $c, $d, $patient_list) = $this->getSubTree("All", $patient_tree, "", array());		
		$tree_data = array($t);
		$tree->core = array('data' => $tree_data);
		$json = json_encode($tree);
		//echo $json;return;
		
		return array($json, $patient_list);
	}
	
	public function getSubTree($node_name, $arr, $parent_name, $patient_list) {
		$num_children = 0;
		$node = new stdClass();		
		$node->id = ltrim($parent_name."?".$node_name, "?All?");
		$node->type = $this->getType($node->id);
		if ($this->is_assoc($arr)) {
			$node->children = array();
			foreach ($arr as $key=>$value) {
				list($c, $n, $d, $s) = $this->getSubTree($key, $value, $node->id, $patient_list);
				$patient_list = $s;
				$node->children[] = $c;
				$num_children += $n;
				$depth = $d + 1;
			}
		}
		else {
			$num_children = count($arr);
			$patient_list[$node->id] = $arr; 
			$depth = 1;
		}		
		$node->text = $node_name."(".$num_children.")";
		if ($node->id == '') $node->id = 'root';
		//if ($depth > 2)
			$node->state = array('opened'=>'true');

		return array($node, $num_children, $depth, $patient_list);
	}  

	/**
	 * delete study
	 *
	 * @return viewStudy page
	 */
	public function deleteStudy($sid) {      
		try {
			DB::beginTransaction();
			$study = Studies::find($sid);
			$study->delete();
			DB::table('study_samples')->where('study_id', '=', $sid)->delete();
			DB::table('study_genes')->where('study_id', '=', $sid)->delete();
			DB::table('study_trans')->where('study_id', '=', $sid)->delete();
			DB::commit();
			Cache::forget('studies');
		} catch (\PDOException $e) { 
			return $e->getMessage();
			DB::rollBack();           
		}
		return Redirect::to('/viewStudies');
	}

	/**
	 * save study
	 *
	 * @return viewStudy page
	 */
	public function saveStudy() {      
		$jsonData = Input::get('jsonData');
		$study = json_decode($jsonData);
		try {
			DB::beginTransaction();
			$user_study;
			if ($study->mode == "create") {
				$user_study = new Studies;
			}
			if ($study->mode == "edit") {
				$user_study = Studies::find($study->id);
				//remove old groups and samples
				DB::table('study_samples')->where('study_id', '=', $study->id)->delete();
				DB::table('study_genes')->where('study_id', '=', $study->id)->delete();
				DB::table('study_trans')->where('study_id', '=', $study->id)->delete();
			}
			try {
				$user_id = Sentry::getUser()->id;
			} catch (Exception $e) {
				return Redirect::to('/login');
			}
			$user_study->user_id = $user_id;
			$user_study->study_name = $study->name;
			$user_study->study_desc = $study->study_desc;
			$user_study->is_public = ($study->is_public=="true");
			$user_study->status = 0;
			$user_study->save();      

			$sid=$user_study->id;
			$patient_list = array();
			foreach ($study->groups as $group) {
				//$pos = strpos($group->id, "_");
				//$tissue_cat = substr($group->id, 0, $pos);
				//$tissue_type = substr($group->id, $pos+1);
				
				foreach ($group->patients as $patient) {
					$patient_list[] = $patient->id;
					//DB::table('study_samples')->insert(['study_id' => $sid, 'sample_id' => $sample->id, 'tissue_cat' => $tissue_cat, 'tissue_type' => $tissue_type]); 
					//Log::info($sample->id);
				}      
			}
			$samples = Sample::getSamplesByPatients($patient_list);
			foreach ($samples as $sample) {
				DB::table('study_samples')->insert(['study_id' => $sid, 'sample_id' => $sample->sample_id, 'tissue_cat' => $sample->tissue_cat, 'tissue_type' => $sample->tissue_type, 'patient_id' => $sample->patient_id]); 
			}
			DB::commit();
			Cache::forget('studies');
		} catch (\PDOException $e) { 			
			DB::rollBack();
			return $e->getMessage(); 
		}
    

		// run background process to prepare the expression file and calculate mean and std
		$db = Config::get("database.default");
		$host = Config::get("database.connections.$db.host");
		$dbname = Config::get("database.connections.$db.database");
		$u = Config::get("database.connections.$db.username");
		$p = Config::get("database.connections.$db.password");
		shell_exec("mkdir -p ".public_path()."/expression/$sid");
		//shell_exec("mkdir -p /mnt/webrepo/fr-s-bsg-onc-d/htdocs/onco.data/expression/$sid");
		$cmd = app_path()."/scripts/postSaveStudy.pl -h $host -i $dbname -u $u -p $p -s $sid -o ".public_path()."/expression/$sid  > ".public_path()."/expression/$sid/save_study_$sid.log 2>&1&";
		pclose(popen($cmd, "r"));
		return Redirect::to('/viewStudies');
	}

	/**
	 * Check if the study exists
	 *
	 * @return 'true' or 'false'
	 */   
	function checkStudyExists($study_name) {
		$studies = Studies::where('study_name', '=', $study_name)->get();
		return (count($studies)>0)? 'true':'false';
	}

	public function is_assoc(array $array) {
		$keys = array_keys($array);
    		return array_keys($keys) !== $keys;
	}


}
