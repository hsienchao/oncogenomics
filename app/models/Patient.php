<?php

/**
 * Patient Eloquent object.
 *
 * Table: patients.
 * Primary key: patient_id
 * Patient is the model class to access patients and patient_details table
 *
 * @package models
 */

class Patient extends Eloquent {
	protected $fillable = [];
	protected $table = 'patients';
	protected $primaryKey = 'patient_id';
	public $timestamps = false;
	public $incrementing = false;
	private $var_count = [];

	static public function getPatient($patient_id) {
		$sql = "select * from patients where patient_id='$patient_id' or alternate_id='$patient_id'";
		$patients = DB::select($sql);
		if (count($patients) == 0)
			return null;
		return $patients[0];
	}
	
	public function samples() {
		return $this->hasMany('Sample', 'patient_id', 'patient_id');
	}

	function getCNVCount() {
		$sql = "select count(*) as cnt from var_cnv where patient_id = '". $this->patient_id."'";
		$results = DB::select($sql);
		return $results[0]->cnt;
	}

	function getSampleCount() {
		$sql = "select exp_type, count(exp_type) as cnt from samples where patient_id='".$this->patient_id."' group by exp_type";
		$rows = DB::select($sql);
		return $rows;
	}

	function getVariantsCount($case_name=null) {
		$tbl_name = "processed_sample_cases";
		$case_condition = '';
		if ($case_name != "any" && $case_name != null) 
			$case_condition = "and case_name='$case_name'";
		$sql = "select type, var_cnt from $tbl_name where patient_id='".$this->patient_id."' $case_condition";			
		$rows = DB::select($sql);
		$type_cnt = array();
		foreach ($rows as $row)
			$type_cnt[$row->type] = $row->var_cnt;
		return $type_cnt;
	}

	function getVarSamples($case_name=null) {
		$tbl_name = "processed_sample_cases";
		$sql = "select distinct type, case_id, path, sample_id, sample_name, sample_alias, exp_type, tissue_cat from $tbl_name where patient_id='$this->patient_id'";
		if ($case_name != "any" && $case_name != null) 
			$sql .= " and case_name='$case_name'";
		$rows = DB::select($sql);
		$samples = array();
		foreach ($rows as $row)
			$samples[$row->type][$row->tissue_cat][] = $row;
		$sample_types = array();
		$types = array('germline' => 'normal', 'somatic' => 'tumor', 'variants' => 'all', 'rnaseq' => 'all', 'hotspot' => 'all');
		foreach ($types as $type => $tissue) {
			Log::info("working on $type,$tissue for $case_name");
						
			if ($tissue == "all")
				$tissue_cats = array("tumor", "normal", "cell line");
			else
				$tissue_cats = array($tissue, "cell line");
			foreach ($tissue_cats as $tissue_cat) {
				if (isset($samples[$type])) {
					if (isset($samples[$type][$tissue_cat])) {
						if (isset($sample_types[$type]))
							$sample_types[$type] = array_merge($sample_types[$type], $samples[$type][$tissue_cat]);
						else
							$sample_types[$type] = $samples[$type][$tissue_cat];
						Log::info("------>added $type,$tissue_cat");
					}
				}
			}
		}
		Log::info(json_encode($sample_types));
		return $sample_types;
	}

	function getVarTypes($case_name) {
		$sql = "select * from var_cases where patient_id='$this->patient_id'";
		if ($case_name != "any" && $case_name != null) 
			$sql .= " and case_name='$case_name'";
		$rows = DB::select($sql);
		$ct = array();
		foreach ($rows as $row)
			$ct[] = $row->type;
		$types = array('germline', 'somatic', 'variants' , 'rnaseq', 'hotspot');
		$cases = array();
		foreach ($types as $type) {
			if (in_array($type, $ct))
				$cases[] = $type;
		}
		return $cases;
	}

	function getTumorSamples($case_id) {
		$sql = "select distinct sample_id, sample_name from var_samples where case_id='$case_id' and patient_id='$this->patient_id' and tissue_cat = 'tumor' ";
		$rows = DB::select($sql);		
		return $rows;
	}

	function hasVar($case_id, $var_type = "dna") {
		if (!isset($this->var_count[$case_id]))
			$this->var_count[$case_id] = $this->getVariantsCount($case_id);		
		foreach ($this->var_count[$case_id] as $type => $cnt) {
			if (strtolower($var_type) == "dna") {
				if (($type == "germline" || $type == "somatic" || $type == "variants") && $cnt > 0)
					return true;				
			}
			if (strtolower($var_type) == "rna" && $type == "rnaseq" && $cnt > 0)
				return true;
		}
		return false;
	}

	function getFusionCount($case_name=null) {
		$tbl_name = "fusion_count";
		$sql = "select * from $tbl_name where patient_id = '". $this->patient_id."'";
		if ($case_name != "any" && $case_name != null) 
			$sql .= " and case_name='$case_name'";
		$rows = DB::select($sql);
		if (count($rows) == 0)
			return 0;
		return $rows[0]->fusion_cnt;
	}

	static function getDiagnosisCount() {
		$sql = "select diagnosis, count(distinct patient_id) as patient_count from patients group by diagnosis";
		return DB::select($sql);		
	}

	function getQCCount($case_id) {
		if ($case_id == "any")
			$sql = "select type, count(*) as cnt from var_qc where patient_id = '". $this->patient_id."' group by type";
		else
			$sql = "select type, count(*) as cnt from var_qc where case_id='$case_id' and patient_id = '". $this->patient_id."' group by type";
		$rows = DB::select($sql);
		$results = array("dna" => 0, "rna" => 0);
		foreach ($rows as $row) {
			$results[$row->type] = $row->cnt;
		}
		return $results;
	}
	
	function hasExpressionData() {
		$sql = "select count(*) as cnt from samples s1, sample_values s2 where s1.sample_id=s2.sample_id and s1.patient_id='".$this->patient_id."' and rownum=1";
		Log::info("hasExpressionData: $sql");
		$results = DB::select($sql);
		return ($results[0]->cnt > 0);
	}

	static function getExpressionSamples($patient_id, $case_id) {
		$case_condition = "";
		if ($case_id != "any")
			$case_condition = " and s2.case_id='$case_id'";
		$sql = "select * from samples s1 where patient_id='$patient_id' and exists(select * from var_samples s2 where s2.type='rnaseq' and s1.sample_id=s2.sample_id and s2.patient_id='$patient_id' $case_condition)";
		Log::info("getExpressionSamples: $sql");
		$results = DB::select($sql);
		return $results;
	}
	
	static public function getCount() {
		return DB::select('select count(*) as cnt from patients')[0]->cnt;
	}

	static function getExpressionByCase($patient_id, $case_id, $target_type="all", $sample_id="all") {		
		$time_start = microtime(true);
		/*
		$key = "case_exp.$patient_id.$case_id.$target_type.$sample_id";
		Cache::forget($key);
		if (Cache::has($key)) {
			$ret = Cache::get($key);
			$time = microtime(true) - $time_start;
			Log::info("time (getExpressionByCase, Cache): $time seconds");
			return $ret;			
		}
		*/
		$case_condition = "";
		if ($case_id != "any") 
			$case_condition = " and case_id='$case_id'";
		#$sql_samples = "select distinct case_id,sample_id,sample_name from var_samples where type='rnaseq' and patient_id='$patient_id' $case_condition";
		#$sql_samples = "select distinct s.sample_id, s.sample_name from samples s, cases c1, processed_sample_cases c2 where c2.patient_id='$patient_id' and c1.case_id='$case_id' and c1.patient_id=s.patient_id and c1.case_id=c2.case_id and c2.sample_id=s.sample_id and s.exp_type='RNAseq'";
		$sql_samples = "select distinct s.sample_id, s.sample_name, s.sample_alias from samples s, sample_cases c where c.patient_id='$patient_id' and c.case_id='$case_id' and c.patient_id=s.patient_id and c.sample_id=s.sample_id and s.exp_type='RNAseq'";
		$rows = DB::select($sql_samples);
		Log::info("SQL(get samples)".$sql_samples);
		$samples = array();
		$sample_case = $case_id;
		foreach ($rows as $row) {
			Log::info($row->sample_id);
			if ($sample_id == "all" ||  $sample_id == $row->sample_id) {
				$samples[$row->sample_id] = $row->sample_name;
				$sample_aliases[$row->sample_id] = $row->sample_alias;
				if ($case_id == "any")
					$sample_case = $row->case_id;
			}
		}

		if (count($samples) == 0)
			return array(array(), $samples, "");
		
		//if sample_id assigned, use expression file
		$exp_file = "";
		if ($sample_id != "all" && array_key_exists($sample_id, $samples)) {
			$sample_name = $samples[$sample_id];
			$path = VarCases::getPath($patient_id, $sample_case);
			Log::info("Path : $path");
			if ($path != null) {
				$path = storage_path()."/ProcessedResults/$path/$patient_id/$sample_case";
				if ($target_type == "all") {
					$target_type = "ensembl";
					$exp_file = Sample::getExpressionFile($path, $sample_id, $sample_name, $target_type, "gene");
					if ($exp_file == "") {
						$target_type = "refseq";
						$exp_file = Sample::getExpressionFile($path, $sample_id, $sample_name, $target_type, "gene");					
					}
				} else {
					$exp_file = Sample::getExpressionFile($path, $sample_id, $sample_name, $target_type, "gene");
				}				
			}
		}
		Log::info("Expression file: $exp_file");
		$count_type='NA';
		$expression_type='NA';
		if ($exp_file != "") {	
			$starttime = microtime(true);	
			$rows = array();
			$fh = fopen($exp_file, "rb");
			$header = fgets($fh);
			while (!feof($fh) ) {
				$line = fgets($fh);
				$line = trim($line);
				if ($line == '') continue;
				$fields = explode("\t", $line);
				$row = new stdClass;
				if (strpos($exp_file, 'rsem') !== false) {
					$count_type='RSEM';
					$expression_type='ENSEMBL';
					$row->sample_id = $sample_id;
					$row->symbol = $fields[0];
					$row->gene = $fields[0];
					$row->target_type = $target_type;
					$row->value = log10($fields[5]+1)/log10(2);
				}
				else{
					$symbol_idx = count($fields) - 2;
					$value_idx = count($fields) - 1;
					$count_type='Feature Count';
					$expression_type='ENSEMBL';
					$row->sample_id = $sample_id;
					$row->symbol = $fields[$symbol_idx];
					$row->gene = $fields[$symbol_idx];
					$row->target_type = $target_type;
					$row->value = $fields[$value_idx];
				}
				$rows[] = $row;
			}
			fclose($fh);
			Log::info("get expression data from file: $exp_file");
			$endtime = microtime(true);
			$timediff = $endtime - $starttime;
			Log::info("execution time (read expression file): $timediff seconds");
			return array($rows, $sample_aliases, $target_type,$expression_type,$count_type);
		}

		$sample_list = "'".implode("','", array_keys($samples))."'";
		$target_type_condition = "";
		if ($target_type != "all")
			$target_type_condition = "and s.target_type='$target_type'";
		#$sql = "select * from sample_values where sample_id in ($sample_list) and target_level='gene' $target_type_condition";
		$sql = "select s.sample_id, s.symbol, s.gene, s.target_type, s.value, g.type from sample_values s, gene g where sample_id in ($sample_list) and s.target_level='gene' and s.symbol=g.symbol $target_type_condition and g.type='protein-coding' and g.target_type='refseq'";
		Log::info("SQL(getExpressionByCase)".$sql);
		$rows = DB::select($sql);
		$ret = array($rows, $samples, $target_type,$expression_type,$count_type);
		$time = microtime(true) - $time_start;
		/*		
		Cache::put($key, $ret, 24*60);
		Log::info("time(getExpressionByCase): $time seconds");
		*/
		return $ret;
	}	

	function getGeneExpression($genes, $target_type) {
		$normal_projects = Project::where('name','Normal')->get();
		$normal_project_id = '';
		if (count($normal_projects) == 1)
			$normal_project_id = $normal_projects[0]->id;
		$tissue_types = array();
		//get RNAseq sample list
		$rows = DB::table('samples')->where('patient_id', $this->patient_id)->where('exp_type', 'RNAseq')->get();
		$samples = array();
		foreach ($rows as $row) {
			$samples[$row->sample_id] = $row;
			$tissue_types[$row->sample_id] = $row->tissue_type;
		}
		$sample_list = "'".implode("','", array_keys($samples))."'";
		$gene_list = "'".implode("','", $genes)."'";

		$symbols = array();
		//get gene expression
		$sql = "select * from sample_values where symbol in ($gene_list) and sample_id in ($sample_list) and target_level='gene' and target_type='$target_type'";
		$rows = DB::select($sql);		
		if (count($rows) == 0)
			return "{}";

		$patient_values = array();
		foreach ($rows as $row) {
			$patient_values[$row->sample_id][$row->symbol] = $row->value;
			$symbols[$row->symbol] = '';
		}

		//get transcript expression
		/*
		$sql = "select distinct sample_id, trans, exp_value from expr_trans where gene='$gene' and sample_id in ($sample_list)";
		$rows = DB::select($sql);
		
		foreach ($rows as $row) {
			$samples[$row->sample_id][$row->trans] = $row->exp_value;
			$targets[$row->trans] = '';
		}
		*/
		$symbols = array_keys($symbols);
		//get normal samples
		$sql = "select * from project_values where project_id=$normal_project_id and symbol in ('_list',$gene_list) ";
		$means = array();
		$medians = array();
		$stds = array();
		
		$rows = DB::select($sql);

		$normal_exp_data = array();
		$normal_samples = array();
		foreach ($rows as $row) {
			if ($row->target == "_list")
				$normal_samples = explode(',',$row->value_list);
			else
				$values = explode(',',$row->value_list);
				$target_raw = array();
				$target_log2 = array();	
				$target_mcenter = array();
				$target_zscore = array();
				//calculate log, mean, std and median first
				foreach ($values as $raw_value) {
					$target_raw[] = round($raw_value , 2);
					$target_log2[] = round(log($raw_value + 1, 2),2);
				}
				$means[$row->symbol] = Utility::getMean($target_log2);
				$stds[$row->symbol] = Utility::getStdev($target_log2);
				$medians[$row->symbol] = Utility::getMedian($target_log2);
				//calculate m-center and zscore
				foreach ($target_log2 as $log2_value) {
					$target_mcenter[] = round($log2_value - $medians[$row->symbol],2);
					$zscore = 0;
					if ($stds[$row->symbol] != 0) {
						$zscore = ($log2_value - $means[$row->symbol])/$stds[$row->symbol];
					}
					$target_zscore[] = round($zscore, 2);
				}
				$normal_exp_data["raw"][$row->symbol] = $target_raw;
				$normal_exp_data["log2"][$row->symbol] = $target_log2;
				$normal_exp_data["mcenter-normal"][$row->symbol] = $target_mcenter;
				$normal_exp_data["zscore-normal"][$row->symbol] = $target_zscore;
		}
		
		
		//get normal statistics
		/*
		$sql = "select * from project_stat where target in ('".implode("','", $symbols)."') and project_id=$normal_project_id and \"LEVEL\"='gene";
		$rows = DB::select($sql);
		
		if (count($rows) == 0)
			return "{}";
		
		foreach ($rows as $row) {			
			$means[$row->target] = $row->mean;
			$medians[$row->target] = $row->median;
			$stds[$row->target] = $row->std;
		}
		*/
		//save log2, mcenter and zscores of patient samples
		$exp_data = array("raw"=> array(), "log2" => array(), "mcenter-normal" => array(), "zscore-normal" => array());
		foreach ($patient_values as $sample_id=>$target_exp) {
			$target_raw = array();
			$target_log2 = array();	
			$target_mcenter = array();
			$target_zscore = array();
			foreach ($symbols as $symbol) {				
				$raw_value = round($target_exp[$symbol],2);
				$log2_value = round(log($raw_value + 1, 2),2);
				$target_log2[] = $log2_value;
				$target_mcenter[] = round($log2_value - $medians[$symbol],2);
				$zscore = 0;
				if ($stds[$symbol] != 0) {
					$zscore = ($log2_value - $means[$symbol])/$stds[$symbol];
				}
				$target_zscore[] = round($zscore,2);
			}
			$exp_data["raw"][] = $target_raw;
			$exp_data["log2"][] = $target_log2;
			$exp_data["mcenter-normal"][] = $target_mcenter;
			$exp_data["zscore-normal"][] = $target_zscore;
		}
		
		$exp_data["raw"] = array_merge($exp_data["raw"], $normal_exp_data["raw"]);
		$exp_data["log2"] = array_merge($exp_data["log2"], $normal_exp_data["log2"]);
		$exp_data["mcenter-normal"] = array_merge($exp_data["mcenter-normal"], $normal_exp_data["mcenter-normal"]);
		$exp_data["zscore-normal"] = array_merge($exp_data["zscore-normal"], $normal_exp_data["zscore-normal"]);		
		
		$patient_samples = array_keys($samples);
		$all_samples = array_merge($patient_samples, $normal_samples);

		//get normal tissue type
		$normal_samples = Project::getSamples($normal_project_id);
		foreach ($normal_samples as $sample)
			$samples[$sample->sample_id] = $sample;
		
		$tissue_type_list = array();

		foreach ($all_samples as $sample) {			
			$tissue_type_list[] = $samples[$sample]->tissue_type;
		}

		/*
		for ($i=0;$i<count($all_samples);$i++)
			$all_samples[$i] = substr($all_samples[$i], 0, strpos($all_samples[$i], "_"));
		for ($i=0;$i<count($patient_samples);$i++)
			$patient_samples[$i] = substr($patient_samples[$i], 0, strpos($patient_samples[$i], "_"));
		*/
		return json_encode(array("all_samples" => $all_samples, "patient_samples" => $patient_samples, "exp_data" =>$exp_data, "symbols" => $symbols, "tissue_type" => $tissue_type_list));

	}

	static function	all_with_samples() {
		$sql = "select distinct r.patient_id as str, '' as genotyping, '' as tree, p.* from patients p left join STR r on p.patient_id=r.patient_id where exists(select * from samples s where p.patient_id=s.patient_id)";
		//$sql = "select * from patients p where exists(select * from samples s where p.patient_id=s.patient_id)";
		return DB::select($sql);
	}

	static function all_with_details() {
		$patient_details = patient_details::all();
		$patients = patient::all();
		
		$detail_fields = array_keys($detail_array);
		foreach ($patients as $patient) {
			foreach ($detail_fields as $detail_field) {
				$patient->{$detail_field} = "";
				if (isset($detail_array[$detail_field][$patient->patient_id]))
					$patient->{$detail_field} = $detail_array[$detail_field][$patient->patient_id];
			}

		}
	}

	static function getCasesByPatientID($project_id, $patient_id, $case_name=null) {
		if ($case_name != null) {
			return DB::select("select * from cases where patient_id='$patient_id' and case_name='$case_name'");
		}
		$tbl_name = "processed_sample_cases";
		$sql = "select distinct c1.case_id, c1.case_name, c1.patient_id, 
				(select sum(var_cnt) from $tbl_name p where p.patient_id=c1.patient_id and p.case_name=c1.case_name and type='germline') as germline,
				(select sum(var_cnt) from $tbl_name p where p.patient_id=c1.patient_id and p.case_name=c1.case_name and type='somatic') as somatic,
				(select sum(var_cnt) from $tbl_name p where p.patient_id=c1.patient_id and p.case_name=c1.case_name and type='rnaseq') as rnaseq,
				(select sum(var_cnt) from $tbl_name p where p.patient_id=c1.patient_id and p.case_name=c1.case_name and type='variants') as variants,
				(select fusion_cnt from fusion_count p where p.patient_id=c1.patient_id and p.case_name=c1.case_name) as fusion,
				c2.finished_at as pipeline_finish_time, 
				c2.updated_at as upload_time,
				status,version
				from sample_cases c1, cases c2, project_cases c3 where c1.patient_id = '$patient_id' and c1.patient_id=c2.patient_id and c1.case_id=c2.case_id and c1.patient_id=c3.patient_id and c1.case_name=c3.case_name";
		if (!User::accessAll()) {
			$logged_user = User::getCurrentUser();
			$sql .= " and exists(select * from user_projects u where u.project_id=c3.project_id and u.user_id = $logged_user->id";
			if ($project_id != "any" ) 
				$sql .= " and u.project_id = $project_id";
			$sql .= ")";
		}
		//if ($project_id != "all")
		//	$sql .= " and exists (select * from project_patients p where p.project_id = $project_id and (p.case_name = c.case_name or c.case_name='20160415') and p.patient_id = '$patient_id')";
		Log::info($sql);
		$rows = DB::select($sql);
		return $rows;		
	}

	//get all patient/case list
	static function	getVarPatientList($user_id) {
		//$sql = "select distinct p1.patient_id, p1.diagnosis, p2.case_id from patients p1, var_patients p2 where p1.patient_id=p2.patient_id order by patient_id";
		//if (User::accessAll()) 
		//	$sql = "select distinct p4.id as project_id, p4.name as project_name, p1.patient_id, p1.diagnosis, p2.case_id, p2.case_name from patients p1, var_cases p2, project_patients p3, projects p4 where p1.patient_id=p2.patient_id and (p2.case_name=p3.case_name or p2.case_name='20160415') and p1.patient_id=p3.patient_id and p3.project_id = p4.id  order by patient_id";
		//else
		$time_start = microtime(true);
		$key = "patient_list.$user_id";
		//if (Cache::has($key))
		//	return Cache::get($key);
		
		$case_condition = "and p2.status='passed'";
		if (User::isSuperAdmin())
			$case_condition = "and p2.status<>'failed'";
		// $sql = "select distinct u.project_id, u.project_name, p1.patient_id, p1.diagnosis, p2.case_id, p2.case_name, path from patients p1, cases p2, project_patients p3, user_projects u where p1.patient_id=p2.patient_id and p1.patient_id=p3.patient_id and (p2.case_name=p3.case_name) $case_condition and u.project_id=p3.project_id and u.user_id=$user_id";#20190502
		//$sql = "select distinct u.project_id, u.project_name, p1.patient_id, p1.diagnosis, p2.case_id, p2.case_name, path from patients p1, cases p2, project_patients p3, user_projects u where p1.patient_id=p2.patient_id and p1.patient_id=p3.patient_id and (p2.case_name=p3.case_name) $case_condition and u.project_id=p3.project_id and u.user_id=$user_id";#original back 20190814 due to RMS2162
		$sql = "select distinct u.project_id, u.project_name, p1.patient_id, p1.diagnosis, p2.case_id, p2.case_name, p2.path from patients p1, project_processed_cases p2, user_projects u where p1.patient_id=p2.patient_id and u.project_id=p2.project_id and u.user_id=$user_id";
		Log::info("SQL(getVarPatientList) = ".$sql);
		$rows = DB::select($sql);
		$time = microtime(true) - $time_start;		
		//Cache::put($key, $rows, 12*60);
		Log::info("time: $time seconds");
		return $rows;		
	}

	static function	accessible($patient_id, $user_id) {
		$sql = "select count(*) from project_patients p, user_projects u where p.project_id=u.project_id and p.patient_id='$patient_id' and u.user_id=$user_id";
		$rows = DB::select($sql);
		return $rows;		
	}

	static function getVarAASite($patient_id, $gene, $type) {
		$tbl_name = "var_samples";
		$sql = "select distinct NVL(aaref,'') || aapos as aa_site from var_annotation a, $tbl_name p where 
				p.chromosome=a.chromosome and
				p.start_pos=a.start_pos and
				p.end_pos=a.end_pos and
				p.ref=a.ref and
				p.alt=a.alt and 
				p.patient_id='$patient_id' and gene='$gene' and type = '$type'";
		return DB::select($sql);
	}
	static function getProjects($patient_id) {
		$sql = "select * from projects p1 where exists(select * from project_patients p2 where p1.id = p2.project_id and p2.patient_id='$patient_id')";
		if (User::accessAll()) 
			$user_where = " ";
		else {
			$logged_user = User::getCurrentUser();
			if ($logged_user != null)
				$user_where = " and exists(select * from user_projects u where p1.id=u.project_id and u.user_id=". $logged_user->id.")";
			else
				$user_where = " and p1.ispublic=1";
		}
		$sql .= $user_where;
		return DB::select($sql);
	}

	static function	search($project_id, $search_text, $patient_id_only = false, $case_id=null, $include_meta=false) {
		$starttime = microtime(true);
		
		$logged_user = User::getCurrentUser();
		$project_condition = "";
		if (strtolower($project_id) != 'null' && strtolower($project_id) != 'any')
			$project_condition = " and p3.project_id = $project_id";
		if ($logged_user != null)
			$user_where = " exists(select * from project_patients p3, user_projects u where p1.patient_id = p3.patient_id and u.project_id=p3.project_id and u.user_id=". $logged_user->id." $project_condition) and ";
		else
			$user_where = " exists(select * from projects p2, project_patients p3 where p1.patient_id = p3.patient_id and p2.id = p3.project_id and p2.ispublic=1 $project_condition) and";
		$search_text = strtoupper($search_text);
		$sql = "select '' as samples, '' as cases, p1.*, 
					(select count(distinct case_id) from var_cases s where s.patient_id=p1.patient_id) processed_cases, 
					(select count(*) from samples s where s.patient_id=p1.patient_id and s.exp_type='Exome') as exome, 
					(select count(*) from samples s where s.patient_id=p1.patient_id and s.exp_type='Panel') as panel, 
					(select count(*) from samples s where s.patient_id=p1.patient_id and s.exp_type='RNAseq') as rnaseq,
					(select count(*) from samples s where s.patient_id=p1.patient_id and s.exp_type='Whole Genome') as whole_genome
				from patients p1 where $user_where";
		$patient_details = array();
		if ($patient_id_only)
			$patient_details = PatientDetail::getPatientDetailByPatientID($search_text);		
		else {
			if ($include_meta || (strtolower($project_id) != "any" && strtolower($project_id) != "null"))
				$patient_details = PatientDetail::getPatientDetailByProject($project_id);		
		}

		//if ($project_id != 'null' && $project_id != 'any') {
		//	$sql .= "exists(select * from project_patients p2 where p1.patient_id=p2.patient_id and p2.project_id = $project_id) and ";	
		//}
		if (strtolower($search_text) == 'any')
			$sql .= "exists(select * from samples s where p1.patient_id=s.patient_id)";	
		else {
			if ($patient_id_only)
				$sql .= "upper(p1.patient_id) = '$search_text'";
			else
				$sql .= "upper(p1.patient_id) = '$search_text' or exists(select * from patient_details d where p1.patient_id=d.patient_id and (upper(d.attr_name) like '%$search_text%' or upper(d.attr_value) like '%$search_text%'))
					or exists(select * from samples s where p1.patient_id=s.patient_id and (upper(s.sample_id) like '%$search_text%' or upper(s.exp_type) like '%$search_text%'))";
		}
		$detail_array = array();
		$detail_fields = array();
		foreach ($patient_details as $patient_detail) {
			$detail_array[$patient_detail->attr_name][$patient_detail->patient_id] = $patient_detail->attr_value;
			$detail_fields[$patient_detail->patient_id][] = $patient_detail->attr_name;
		}
		$time = microtime(true) - $starttime;
		Log::info("get all: $time seconds");
		$starttime = microtime(true);	
		Log::info($sql);	
		$patients = DB::select($sql);
		$time = microtime(true) - $starttime;
		Log::info("execution time : $time seconds");
		$starttime = microtime(true);

		$fields = array();		
		foreach ($patients as $patient) {
			$cases = explode(",", $patient->case_list);
			$uniq_cases = array();
			foreach ($cases as $case) {
				$uniq_cases[trim($case)] = '';
				//Log::info("CASE ".$case);
				if($case_id!=null){
					if($case==$case_id){
						$uniq_cases[$case] = '';
					}
				}
			}
			$patient->case_list = implode(",", array_keys($uniq_cases));
			if (isset($detail_fields[$patient->patient_id])) {
				$patient_fields = $detail_fields[$patient->patient_id];
				foreach ($patient_fields as $patient_field) {
					//echo $patient_field. " ";
					$fields[$patient_field] = '';
				}
			}			
		}
		$fields = array_keys($fields);		

		foreach ($patients as $patient) {
			/*
			foreach ($patient_attrs as $patient_attr) {
				if ($patient_attr->included) {
					$patient->{$patient_attr->display_name} = "";
					if (isset($detail_array[$patient_attr->attr_id][$patient->patient_id]))
						$patient->{$patient_attr->display_name} = $detail_array[$patient_attr->attr_id][$patient->patient_id];
				}
			}
			*/
			
			foreach ($fields as $field) {
				$patient->{$field} = "";
				
				if (isset($detail_array[$field][$patient->patient_id])) {

					$patient->{$field} = $detail_array[$field][$patient->patient_id];
				}
			}
			
		}
		$time = microtime(true) - $starttime;
		Log::info("execution time (processing) : $time seconds");
		return $patients;
	}
	static function getPatientBySample($sample_id){
		$sql = "select patient_id from samples where SAMPLE_ID='$sample_id'";
		$rows = DB::select($sql);
		return $rows;
	}
	static function getGSEArecords($user_id){
		$sql = "select * from gsea_stats where USER_ID=$user_id or MAKE_PUBLIC='Y'";
		$rows = DB::select($sql);
		return $rows;
	}
	static function removeGSEArecords($token_id){
		$sql = "delete from gsea_stats WHERE token_id=$token_id";
		DB::delete($sql);
	}
	static function getTierCounts2($patient_id,$case_name=null,$type=null) {
		$tier_table = "var_tier_avia";
		$type_condition = "";
		$case_condition = "";
		if ($type != "any" && $type != null) 
			$type_condition = " and type='$type'";
		if ($case_name != "any" && $case_name != null) 
			$case_condition = " and s.case_name='$case_name'";
		$sql="select substr(germline_level,1,6) as germline_level, substr(somatic_level,1,6) as somatic_level,type,sum(cnt) as cnt,s.sample_alias,s.tissue_cat from var_tier_avia_count v, sample_cases s where v.patient_id='$patient_id' and v.patient_id=s.patient_id and v.sample_id=s.sample_id $case_condition $type_condition and v.case_id=s.case_id group by substr(germline_level,1,6),substr(somatic_level,1,6),type,s.sample_alias,s.tissue_cat order by type";
		Log::info($sql);
		$rows = DB::select($sql);
		$counts = array();
		foreach ($rows as $row) {			
			if ($row->type == "germline" && $row->tissue_cat == "tumor")
				continue;
			if ($row->type == "somatic" && $row->tissue_cat == "normal")
				continue;
			$level = $row->somatic_level;
			if ($row->type == "germline")
				$level = $row->germline_level;
			$type =Lang::get("messages.$row->type");
			$counts["$type - ".$row->sample_alias][$level] = $row->cnt; 
		}
		$types = array_keys($counts);
		sort($types);
		$data = array();
		for ($i=1;$i<=4;$i++) {
			$tier_data = array();
			foreach ($types as $type) {
				$cnt = 0;
				if (isset($counts[$type]["Tier $i"])) {
					$cnt = intval($counts[$type]["Tier $i"]);
				}
				$tier_data[] = $cnt;
			}
			$data[] = $tier_data;
		}		
		return array("data" => $data, "variants" => $types); 
	}
	static function getTierCounts($patient_id,$case_name,$type){
		$tier_table = "var_tier_avia";		
		$sql="select germline_level, somatic_level,type,cnt from var_tier_avia_count v, sample_cases s where v.patient_id='$patient_id' and v.patient_id=s.patient_id and v.sample_id=s.sample_id and v.type='$type'
and s.case_name='$case_name' and v.case_id=s.case_id";
		
		Log::info($sql);
		$counts = DB::select($sql);
		return $counts; 
	}


}
