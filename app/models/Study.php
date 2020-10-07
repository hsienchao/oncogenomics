<?php

class Study extends Eloquent {
	protected $fillable = [];
    protected $table = 'studies';
	private $data_type = 'UCSC';
	private $sample_cnt = null;
	private $patient_cnt = null;
	private $analysis_cnt = null;
	private $survival_cnt = null;
	private $normal_cnt = null;
	private $tumor_cnt = null;
	private $samples = null;
	private $study_samples = null;
	private $patients = null;
	private $gene_info = null;
	private $gene_exp = array();
	private $trans_exp = array();
	private $ttest_results = array();
	private $cli_data_mapping = array('inss_stage'=> array('0'=>'Pending', '1'=>'1', '2'=>'2a', '3'=>'2b', '4'=>'3', '5'=>'4', '6'=>'4s'), 'gender'=>array('1'=>'Male', '2'=>'Female'), 'mycn'=>array('0'=>'Not Amp','1'=>'Amp','9'=>'Unknown'), 'scens'=>array('0'=>'Alive','1'=>'Dead'));
	private $survival_table = "nbrnaseq";
	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	static public function getAllStudies() {
		$studies = null;
		if (Sentry::getUser() != null) {
			$user_id = Sentry::getUser()->id;
			$studies = Study::where('user_id', '=', $user_id)->orWhere('is_public', '=', 1)->get();
		} else {
			$studies = Study::where('is_public', '=', 1)->get();
		}
		return $studies;
	}

	static public function getStudy($id, $data_type = 'UCSC') {
		$key = 'study'.$id."_".$data_type;
		if (Cache::has($key))
			return Cache::get($key);
		$study = Study::find($id);
		$study->setDataType($data_type);
		Study::saveToCache($study);
		return $study;
	}

	/*	
	function __construct() {
		foreach ($this->samples as $sample) {
			if ($sample->tissue_cat == 'normal') $normal_num++;
			if ($sample->tissue_cat == 'tumor') $tumor_num++;
		}
	}
	*/

	public function setDataType($data_type) {
		$this->data_type = $data_type;
	}

	public function getSampleCount() {
		if ($this->sample_cnt == null) {
			$res = DB::select("select count(*) as cnt from study_samples where study_id = ".$this->id);
			$this->sample_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return $this->sample_cnt;
	}

	public function getPatientCount() {
		if ($this->sample_cnt == null) {
			$res = DB::select("select count(distinct patient_id) as cnt from study_samples where study_id = ".$this->id);
			$this->patient_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return $this->patient_cnt;
	}

	public function getNormalSampleCount() {
		if ($this->normal_cnt == null) {
			$res = DB::select("select count(*) as cnt from study_samples where tissue_cat='normal' and study_id = ".$this->id);
			$this->normal_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return $this->normal_cnt;
	}

	public function getTumorSampleCount() {
		if ($this->tumor_cnt == null) {
			$res = DB::select("select count(*) as cnt from study_samples where tissue_cat='tumor' and study_id = ".$this->id);
			$this->tumor_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return $this->tumor_cnt;
	}	

/*
	public function analyses() {
		return $this->hasMany('Analyses', 'study_id');
        }
*/
	public function getAnalysisCount () {
		if ($this->analysis_cnt == null) {
			$res = DB::select("select count(*) as cnt from analysis where study_id = ".$this->id);
			$this->analysis_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return $this->analysis_cnt;
	}


	public function hasSurvivalSample() {
		if ($this->survival_cnt == null) {
			$res = DB::select("select count(*) as cnt from study_samples s1, $this->survival_table s2 where s1.sample_id=s2.sample_id and s1.study_id = ".$this->id);
			$this->survival_cnt = $res[0]->cnt;
			Study::saveToCache($this);
		}
		return ($this->survival_cnt > 10);
	}

	public function getSamples() {
		if ($this->samples == null) {
			$this->samples = array();
			$rows = DB::select("select s1.*,age,inss_stage,gender,mycn,scens,risk from samples s1, study_samples s2 left join $this->survival_table s3 on s2.sample_id=s3.sample_id where s2.study_id = ".$this->id." and s1.sample_id=s2.sample_id");
			foreach ($rows as $sample) {
				$sample->inss_stage = ($sample->inss_stage != null)?$this->cli_data_mapping['inss_stage'][$sample->inss_stage]:'Unknown';
				$sample->gender = ($sample->gender != null)?$this->cli_data_mapping['gender'][$sample->gender]:'Unknown';
				$this->samples[$sample->sample_id] = $sample;
			}
			Study::saveToCache($this);
		}		
		return $this->samples;

	}

	public function getStudySamples() {
		//if ($this->study_samples == null) {
			$this->study_samples = array();
			$rows = DB::select("select s1.*,age,inss_stage,gender,mycn,scens,risk from study_samples s1 left join $this->survival_table s2 on s1.sample_id=s2.sample_id where s1.study_id = ".$this->id);
			foreach ($rows as $sample) {
				$sample->inss_stage = ($sample->inss_stage != null)?$this->cli_data_mapping['inss_stage'][$sample->inss_stage]:'Unknown';
				$sample->gender = ($sample->gender != null)?$this->cli_data_mapping['gender'][$sample->gender]:'Unknown';
				$sample->mycn = ($sample->mycn != null)?$this->cli_data_mapping['mycn'][$sample->mycn]:'Unknown';
				$sample->scens = ($sample->scens != null)?$this->cli_data_mapping['scens'][$sample->scens]:'Unknown';
				$this->study_samples[$sample->sample_id] = $sample;
			}
			Study::saveToCache($this);
		//}		
		return $this->study_samples;

	}	

	public function getPatients() {
		if ($this->patients == null) {
			$this->patients = array();
			$rows = DB::select("select p.* from patients p, samples s1, study_samples s2 where s2.study_id = ".$this->id." and s1.sample_id=s2.sample_id and s1.patient_id=p.patient_id");
			foreach ($rows as $patient) {
				$this->patients[$patient->patient_id] = $patient;
			}
			Study::saveToCache($this);
		}		
		return $this->patients;

	}

	public function getSummary() {
		$samples = $this->getStudySamples();
		$patients = $this->getPatients();
		$tissue_cnt = array();
		$tissue_cat_cnt = array();
		$age_cnt = array();
		$stage_cnt = array();
		$sex_cnt = array();
		$mortality_cnt = array();
		$mycn_cnt = array();
		$risk_cnt = array();
		foreach ($samples as $sample_id=>$sample) {
			if (isset($tissue_cnt[$sample->tissue_type]))
				$tissue_cnt[$sample->tissue_type]++;
			else
				$tissue_cnt[$sample->tissue_type] = 1;
			if (isset($tissue_cat_cnt[$sample->tissue_cat]))
				$tissue_cat_cnt[$sample->tissue_cat]++;
			else
				$tissue_cat_cnt[$sample->tissue_cat] = 1;
			if ($sample->inss_stage == '') $sample->inss_stage = 'Unknown';
			if (isset($stage_cnt[$sample->inss_stage]))
				$stage_cnt[$sample->inss_stage]++;
			else
				$stage_cnt[$sample->inss_stage] = 1;
			if ($sample->mycn == '') $sample->mycn = 'Unknown';
			if (isset($mycn_cnt[$sample->mycn]))
				$mycn_cnt[$sample->mycn]++;
			else
				$mycn_cnt[$sample->mycn] = 1;
			if (is_numeric($sample->age))
				$age_cnt[$sample->sample_id] = $sample->age;
			if (isset($risk_cnt[$sample->risk]))
				$risk_cnt[$sample->risk]++;
			else
				$risk_cnt[$sample->risk] = 1;
		}
		foreach ($patients as $patient_id=>$patient) {
			/*
			if ($patient->sex == '') $patient->sex = 'Unknown';
			if ($patient->mortality_status == '') $patient->mortality_status = 'Unknown';			
			if (isset($sex_cnt[$patient->sex]))
				$sex_cnt[$patient->sex]++;
			else
				$sex_cnt[$patient->sex] = 1;
			if (isset($mortality_cnt[$patient->mortality_status]))
				$mortality_cnt[$patient->mortality_status]++;
			else
				$mortality_cnt[$patient->mortality_status] = 1;
			*/
		}
		return array($tissue_cnt, $tissue_cat_cnt, $age_cnt, $stage_cnt, $sex_cnt, $mortality_cnt, $mycn_cnt,$risk_cnt);
	}

	public function getGeneInfoAll() {
		if ($this->gene_info == null) {
			$this->gene_info = array();
			$rows = DB::select("select * from study_genes where study_id = ".$this->id);
			foreach ($rows as $gene) {
				$this->gene_info[$gene->gene] = $gene;
			}
			Study::saveToCache($this);
		}		
		return $this->gene_info;		
	}

	public function getGeneInfo($genes) {
		$gene_list = implode("','", $genes);
		if ($this->gene_info == null) {
			$this->gene_info = array();
			$rows = DB::select("select * from study_genes where study_id = ".$this->id." and gene in ('$gene_list')");
			foreach ($rows as $gene) {
				$this->gene_info[$gene->gene] = $gene;
			}
			Study::saveToCache($this);
		}		
		return $this->gene_info;		
	}

	public function getCorrelationExp($genes) {		
		$field_name = "gene";
		if ($this->data_type == 'Ensembl' && strpos($genes[0], "ENSG") != 0)
			$field_name = "alt";
		$table_name = $this->getGeneExprTableName();	
		$gene_list = "'".implode("','", $genes)."'";
		$sql = "select e.gene as id, s.tissue_type, s.sample_id, exp_value from study_samples s, $table_name e where s.study_id = ".$this->id." and e.$field_name in ($gene_list) and s.sample_id = e.sample_id";
		$rows = DB::select($sql);
		$raw_data = array();
		$exps = array();
		if (count($rows) == 0) { 
			echo "no results!";
			return;
		}
		$groups = array();
		foreach ($rows as $row) {
			$raw_data[$row->sample_id][$row->id] = round($row->exp_value, 2);
			$groups[$row->sample_id] = $row->tissue_type;         
		}
		return array($raw_data, $groups);
	}

	public function getExpByGenes($genes) {
		$new_list = array();
		$field_name = "gene";
		if ($this->data_type == 'Ensembl' && strpos($genes[0], "ENSG") != 0)
			$field_name = "alt";
		foreach ($genes as $gene) {
			if (!isset($this->gene_exp[$gene])) {
				$new_list[] = $gene;
			}
		}
		$gene_list = implode("','", $new_list);
		$table_name = $this->getGeneExprTableName();	

		if (count($new_list) > 0) {
			#$sql = "select e.gene, e.exp_value, s.sample_id from study_samples s, $table_name e where s.study_id=".$this->id." and (e.gene in ('$gene_list') or e.alt in ('$gene_list')) and s.sample_id=e.sample_id";
			$sql = "select e.gene, e.exp_value, s.sample_id from study_samples s, $table_name e where s.study_id=".$this->id." and e.$field_name in ('$gene_list') and s.sample_id=e.sample_id";
			$rows = DB::select($sql);

			$gene_info = $this->getGeneInfo($genes);

			$exp_data = array();
			foreach ($rows as $row) {				
				$exp = new stdClass();
				$exp->log2 = round($row->exp_value,3);
				if ($this->status > 0) {
					$exp->mcenter = round($row->exp_value - $gene_info[$row->gene]->median, 3);
					$exp->zscore = 0;
					if ($gene_info[$row->gene]->std != 0) 
						$exp->zscore = round(($row->exp_value - $gene_info[$row->gene]->mean) / $gene_info[$row->gene]->std, 3);
					if ($this->getNormalSampleCount() > 0) {				
						$exp->mcenter_normal = round($row->exp_value - $gene_info[$row->gene]->normal_median, 3);
						$exp->zscore_normal = 0;
						if ($gene_info[$row->gene]->normal_std != 0) 
							$exp->zscore_normal = round(($row->exp_value - $gene_info[$row->gene]->normal_mean) / $gene_info[$row->gene]->normal_std, 3);
					}
				}
				$exp_data[$row->gene][$row->sample_id] = $exp;
					
			}
			foreach ($exp_data as $key=>$value) {
				$this->gene_exp[$key] = $value;				
			}
			Study::saveToCache($this);
		}
		$gene_exp = array();
		foreach ($genes as $gene)
			if (isset($this->gene_exp[$gene]))
				$gene_exp[$gene] = $this->gene_exp[$gene];
		return $gene_exp;
	}

	public function getTransStat($trans) {
		$sql = "select * from study_trans where study_id=".$this->id." and trans = '$trans'";
		$rows = DB::select($sql);
		$stat = null;
		if (count($rows) > 0) {
			return $rows[0];
		}
	}

	public function getTransExpByGene($gene) {
		$table_name = $this->getTransExprTableName();
		$sql = "select e.trans as id, e.exp_value, s.sample_id, s.tissue_cat from study_samples s, $table_name e where s.study_id=".$this->id." and e.gene = '$gene' and s.sample_id=e.sample_id";
		$resultset = DB::select($sql);
		return $this->processExpData($resultset);

	}

	public function getExonExpByGene($gene) {
		if ($this->data_type == 'Ensembl') {
			$g = new Gene($gene);
			$gene = $g->getSymbol();
		}
		$table_name = $this->getExonExprTableName();
		$sql = "select e.chr || ':' || start_pos || '-' || end_pos as id, e.exp_value, s.sample_id, s.tissue_cat from study_samples s, $table_name e where s.study_id=".$this->id." and e.gene = '$gene' and s.sample_id=e.sample_id";
		$resultset = DB::select($sql);
		foreach ($resultset as $row) {
			if ($row->exp_value == 0)
				$row->exp_value = -6.644;
			else
				$row->exp_value = log($row->exp_value, 2);
		}
		return $this->processExpData($resultset);

	}

	public function getTransExpByTrans($trans) {
		$table_name = $this->getTransExprTableName();
		$sql = "select e.trans as id, e.exp_value, s.sample_id, s.tissue_cat from study_samples s, $table_name e where s.study_id=".$this->id." and e.trans = '$trans' and s.sample_id=e.sample_id";
		$resultset = DB::select($sql);
		return $this->processExpData($resultset);
	}

	public function processExpData($resultset) {
		$trans_stats= array();
		$raw_data = array();
		$exp_data = array();
		foreach ($resultset as $row) {
			$exp = new stdClass();
			$exp->log2 = round($row->exp_value,3);
			$exp_data[$row->id][$row->sample_id] = $exp;
			$raw_data[$row->id]["all"][] = $row->exp_value;
			if ($row->tissue_cat == 'normal') 
				$raw_data[$row->id]["normal"][] = $row->exp_value;
		}

		$trans_ids = array_keys($raw_data);

		foreach ($trans_ids as $trans) {
			$trans_stat = new stdClass();
			$trans_stat->mean = $this->getMean($raw_data[$trans]["all"]);
			$trans_stat->std = $this->getStdev($raw_data[$trans]["all"]);
			$trans_stat->median = $this->getMedian($raw_data[$trans]["all"]);
			if (isset($raw_data[$trans]["normal"])) {
				$trans_stat->normal_mean = $this->getMean($raw_data[$trans]["normal"]);
				$trans_stat->normal_std = $this->getStdev($raw_data[$trans]["normal"]);
				$trans_stat->normal_median = $this->getMedian($raw_data[$trans]["normal"]);
			}
			$trans_stats[$trans] = $trans_stat;
		}

		foreach ($exp_data as $trans=>$smp_exp) {
			$trans_stat = $trans_stats[$trans];
			foreach ($smp_exp as $sample_id=>$exp) {
				$exp->mcenter = round($exp->log2 - $trans_stat->median, 3);
				$exp->zscore = 0;
				if ($trans_stat->std != 0) {
					$exp->zscore = round(($exp->log2 - $trans_stat->mean) / $trans_stat->std, 3);
					if (isset($raw_data[$trans]["normal"])) {			
						$exp->mcenter_normal = round($exp->log2 - $trans_stat->normal_median, 3);
						$exp->zscore_normal = 0;
						if ($trans_stat->normal_std != 0) 
							$exp->zscore_normal = round(($exp->log2 - $trans_stat->normal_mean) / $trans_stat->normal_std, 3);
					}
				}
				//$exp_data[$trans][$sample_id] = $exp;
			}
		}
		return $exp_data;
	}

	
	public function getTTestResults($gene) {
		$table_name = $this->getGeneExprTableName();
		if (isset($this->ttest_results[$gene]))
			return $this->ttest_results[$gene];
		$sql = "select s.tissue_type, s.tissue_cat, s.sample_id, exp_value from study_samples s, $table_name e where s.study_id=".$this->id." and gene='$gene' and s.sample_id=e.sample_id";
      		$gene_exprs = DB::select($sql);
		$exps = array();
		foreach ($gene_exprs as $row) {
			$exps[$row->tissue_type][$row->sample_id] = $row->exp_value;
		}
		$tscore = array();
		$pvalue = array();
				
		$tissue_exprs = array();
		foreach ($exps as $tissue=>$samples) {
			$tissue_exp = array();
			foreach ($samples as $sample_id=>$exp_value) {
				$tissue_exp[] = $exp_value;
			}
			if (count($tissue_exp) > 1)
				$tissue_exprs[$tissue] = implode(',', $tissue_exp);
		}
		foreach ($tissue_exprs as $tissue1=>$exp1) {
			foreach ($tissue_exprs as $tissue2=>$exp2) {
				if (!isset($tscore[$tissue1][$tissue2]) && !isset($tscore[$tissue2][$tissue1])){
					if ($tissue1 == $tissue2) {
						$tscore[$tissue1][$tissue2] = 0;
						$tscore[$tissue2][$tissue1] = 0;
						$pvalue[$tissue1][$tissue2] = 1;
						$pvalue[$tissue2][$tissue1] = 1;
					} else {
						$cmd = "Rscript ".app_path()."/scripts/tTest.r $exp1 $exp2";
						//echo "$tissue1 ~> $tissue2 $cmd"."<BR>";
						$ret = shell_exec($cmd);
						$results = explode(PHP_EOL, $ret);
						if (count($results) >= 5) {
							$ts = $results[1];
							if (is_numeric($ts))
								$ts = -1;
							$pv = str_replace('[1] ', '', $results[4]);
							$tscore[$tissue1][$tissue2] = trim($ts);
							$tscore[$tissue2][$tissue1] = $tscore[$tissue1][$tissue2];
							$pvalue[$tissue1][$tissue2] = trim($pv);
							$pvalue[$tissue2][$tissue1] = $pvalue[$tissue1][$tissue2];
						}
					}
				}
			}
		}
		$res = array($tscore, $pvalue);
		$this->ttest_results[$gene] = $res;
		Study::saveToCache($this);
		return $res;
	}

	public function getSurvivalFile($target_id, $level) {
		$gene_table = $this->getGeneExprTableName();
		$trans_table = $this->getTransExprTableName();
		$exon_table = $this->getExonExprTableName();
		$surv_file = public_path()."/expression/".$this->id."/survival_data.$target_id.".$this->data_type.".tsv";
		if (file_exists($surv_file))
			return $surv_file;
		if ($level == 'gene')
			$sql = "select s1.sample_id, stime, scens, exp_value from study_samples s1, $this->survival_table s2, $gene_table e where s1.sample_id=s2.sample_id and s1.sample_id=e.sample_id and e.gene = '$target_id' and s1.study_id= ".$this->id;
		if ($level == 'trans')
			$sql = "select s1.sample_id, stime, scens, exp_value from study_samples s1, $this->survival_table s2, $trans_table e where s1.sample_id=s2.sample_id and s1.sample_id=e.sample_id and e.trans = '$target_id' and s1.study_id= ".$this->id;
		if ($level == 'exon') {
			preg_match('/(.*):(.*)-(.*)/', $target_id, $matches);
			$chr = $matches[1];
			$start_pos = $matches[2];
			$end_pos = $matches[3];
			$sql = "select s1.sample_id, stime, scens, exp_value from study_samples s1, $this->survival_table s2, $exon_table e where s1.sample_id=s2.sample_id and s1.sample_id=e.sample_id and e.chr = '$chr' and e.start_pos = '$start_pos' and e.end_pos = '$end_pos' and s1.study_id= ".$this->id;
		}
      		$survivals = DB::select($sql);
		if (count($survivals) < 10)
			return null;
		$surv_data = "Sample ID\tTime\tStatus\tExp";
		foreach ($survivals as $survival) {
			$surv_data.="\n".$survival->sample_id."\t".$survival->stime."\t".$survival->scens."\t".$survival->exp_value;
		}
		$bytes_written = File::put($surv_file, $surv_data);
		return $surv_file;
	}


	public function getCorrelation($gid, $cutoff, $data_type="UCSC") {
		$geneInfo = $this->getGeneInfoAll();
		$exps = array();
		$std = array();
		$mean = array();
		$median = array();

		foreach ($geneInfo as $row) {
			$mean[$row->gene] = $row->mean - $row->median;
			#$mean[$row->gene] = $row->mean;
			$std[$row->gene] = $row->std;
			$median[$row->gene] = $row->median;
		}

		$exp_file = $this->getExprFileName();
		if (!file_exists($exp_file)) {
			return null;
		}
		$handle = fopen($exp_file, "r");
		if ($handle) {
			$header = fgets($handle);
			$samples = explode("\t", $header); 
			while (($line = fgets($handle)) != false) {
				$fields = explode("\t", $line);
				$gene = $fields[0];		
				$expressed = 0;
				for ($i=1;$i<count($fields);$i++) {
					if ($fields[$i] > 0) {
						$expressed = 1;
						break;
					}
				}
				if ($expressed || $gene == $gid)
					for ($i=1;$i<count($fields);$i++)
						#$exps[$gene][] = $fields[$i]; 
						$exps[$gene][] = $fields[$i] - $median[$gene];
			}
			fclose($handle);
		} else {
			return null;
		}
 
		
		if (count($mean) == 0) {
			return null;
		}		
		$target_gene_mean = $mean[$gid];
		$target_gene_std = $std[$gid];
		$target_gene_exp = $exps[$gid];

		$genes = array_keys($exps); 
		$corr_n = array();
		$corr_p = array();
		foreach ($genes as $gene) {
			if ($gene == $gid) continue;
			$corr_coef = $this->calculateCorrelation($target_gene_exp, $exps[$gene], $target_gene_mean, $mean[$gene], $std[$gene], $target_gene_std);
			if ($corr_coef > 1.01)
				$corr_coef = 0;
			if ($corr_coef >= $cutoff)
				$corr_p[$gene] = number_format($corr_coef,3);
			if ($corr_coef <= $cutoff*(-1))
				$corr_n[$gene] = number_format($corr_coef,3);

		}
		return array($corr_p, $corr_n);
	}

	public function calculateCorrelation($exp1, $exp2, $mean1, $mean2, $std1, $std2) {        
		$correlation = 0;
      
		$sum = 0;
		$n = count($exp1);
		for ($i=0;$i<$n;$i++) {
			$sum += $exp1[$i] * $exp2[$i];
		}
		$correlation = ($sum - $mean1*$mean2*$n)/(($n-1)*$std1*$std2);
		return $correlation;
	}

	public function getTwoGenesExp($g1, $g2) {
		$table_name = $this->getGeneExprTableName();
		$sql = "select gene, e.sample_id, exp_value, s.tissue_type from $table_name e, study_samples s where s.study_id=".$this->id." and e.gene in ('$g1','$g2') and s.sample_id=e.sample_id";
      		$rows = DB::select($sql);
		
		$exprs = array();
		$samples = array();

		foreach ($rows as $row) {
			$exprs[$row->sample_id][$row->gene] = $row->exp_value;
			$samples[$row->sample_id] = $row->tissue_type;
		}
		return array($exprs, $samples);
   	}

	public function getMean($array){
		return array_sum($array)/count($array);
	}

	public function getStdev($array){
        	$average = $this->getMean($array);
		$sqtotal = 0;
		foreach ($array as $item)
			$sqtotal += pow($average-$item, 2);        
		$std = sqrt($sqtotal / (count($array)-1));
		return $std;
	}

	public function getMedian($array) {
		sort($array);
		$count = count($array);
		$middleval = floor(($count-1)/2);
		$median = 0;
		if ($count % 2)
			$median = $array[$middleval];
		else {
			$low = $array[$middleval];
			$high = $array[$middleval+1];
			$median = (($low+$high)/2);
		}
		return $median;
	}

	private function getGeneExprTableName() {
		return ($this->data_type == 'Ensembl')? 'expr_gene_ensembl': 'expr_gene';
	}

	private function getTransExprTableName() {
		return ($this->data_type == 'Ensembl')? 'expr_trans_ensembl': 'expr_trans';
	}

	private function getExonExprTableName() {
		return ($this->data_type == 'Ensembl')? 'expr_exon_ensembl': 'expr_exon';
	}

	private function getExprFileName() {
		$dir = public_path()."/expression/".$this->id."/";
		return ($this->data_type == 'Ensembl')? $dir.'exp_ensembl.tsv': $dir.'exp.tsv';
	}

	public function hasEnsemblData() {
		$sql = "select count(*) as cnt from expr_gene_ensembl e, study_samples s where e.sample_id=s.sample_id and s.study_id=".$this->id;
		$rows = DB::select($sql);
		return ($rows[0]->cnt > 0)?'Y':'N';
	}


	public function getMutations() {
		$sql = "select m.*, s1.sample_id from study_samples s1, var_sample s2, var_main m where s1.sample_id=s2.sample_id and s1.study_id=".$this->id." and
			s2.chromosome=m.chromosome and 
			s2.start_pos=m.start_pos and 
			s2.end_pos=m.end_pos and 
			s2.ref=m.ref and 
			s2.alt=m.alt";
		$vars = DB::select($sql);
		return $vars;
	}

	public function getMutationByGenes($genes) {
		$gene_list = implode("','", $genes);
		$sql = "select distinct m.*, p.patient_id from study_samples s, var_patient p, var_annotation m where s.patient_id=p.patient_id and s.study_id=".$this->id." and
			m.gene in ('$gene_list') and
			p.chromosome=m.chromosome and 
			p.start_pos=m.start_pos and 
			p.end_pos=m.end_pos and 
			p.ref=m.ref and 
			p.alt=m.alt";
		$vars = DB::select($sql);
		//echo $sql."<BR>";
		$mutation_genes = array();
		foreach ($vars as $var) {
			if ($var->exonicfunc != null)
				$mutation_genes[$var->gene][$var->patient_id] = $var->exonicfunc;

		}
		return $mutation_genes;
	}

	public function getCNVByGenes($genes) {
		$gene_list = implode("','", $genes);
		$sql = "select distinct m.*, p.patient_id from study_samples s, var_patient p, var_main m where s.patient_id=p.patient_id and s.study_id=".$this->id." and
			m.gene in ('$gene_list') and
			p.chromosome=m.chromosome and 
			p.start_pos=m.start_pos and 
			p.end_pos=m.end_pos and 
			p.ref=m.ref and 
			p.alt=m.alt";
		$vars = DB::select($sql);
		//echo $sql."<BR>";
		$mutation_genes = array();
		foreach ($vars as $var) {
			if ($var->exonicfunc != null)
				$mutation_genes[$var->gene][$var->patient_id] = $var->exonicfunc;

		}
		return $mutation_genes;
	}

	public static function saveToCache($study, $data_type = 'UCSC') {
		$key = 'study'.$study->id."_".$data_type;
		//Cache::forever($key, $study);
	}

}
