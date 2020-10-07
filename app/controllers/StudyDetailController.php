<?php

use App;
use Log;
use App\Models;
use Input;
use Validator;
use Redirect;
use Request;
use Session;

class StudyDetailController extends BaseController {


	private $cli_data_mapping = array('inss_stage'=> array('0'=>'Pending', '1'=>'1', '2'=>'2a', '3'=>'2b', '4'=>'3', '5'=>'4', '6'=>'4s'), 'gender'=>array('1'=>'Male', '2'=>'Female'));
	/**
	 * View study details.
	 *
	 * @return study detail page
	 */
	public function viewStudyDetails($sid) {
		$study = Study::getStudy($sid);
		$cols = $this->getColumnJson("samples", Config::get('onco.sample_column_exclude'));
		$pca_json = array();		
		//echo json_encode($pca_json);return;
		return View::make('pages/viewStudyDetails', ['cols'=>$cols, 'col_hide'=> Config::get('onco.sample_column_hide'), 'filters'=> Config::get('onco.sample_column_filter'), 'url'=>url('/getStudyDetails/'.$sid), 'study' =>$study]);
		
	}  

	public function getPCAPlatData($sid) {
		$study = Study::getStudy($sid);
		$loading_file = public_path()."/expression/$sid/loading.tsv";
		$coord_file = public_path()."/expression/$sid/coord.tsv";
		$std_file = public_path()."/expression/$sid/std.tsv";		
		$samples = $study->getStudySamples();
		$groups = array();
		foreach ($samples as $sample_id => $sample) {
			$group_data = array();
			$group_data[] = $sample->tissue_type;
			$group_data[] = $sample->tissue_cat;
			if ($study->hasSurvivalSample()) {
				$lbl_age = 'Unknown';
				/*
				if ($sample->age != null) {
					if ($sample->age < 500) $lbl_age = '<500';
					if ($sample->age >= 500 && $sample->age < 1500) $lbl_age = '>= 500, <1500';
					if ($sample->age >= 1500) $lbl_age = '>= 2000';
				}
				*/
				if ($sample->age < 500) 
					$lbl_age = 'Young';
				else
					$lbl_age = 'Old';
				$group_data[] = $lbl_age;
				$group_data[] = $sample->inss_stage;
				$group_data[] = $sample->gender;
				$group_data[] = $sample->scens;
				$group_data[] = $sample->mycn;
				$group_data[] = $sample->risk;
			}
			$groups[$sample->sample_id] = $group_data;
		}
		$pca_json = $this->getPCAPlotjson($loading_file, $coord_file, $std_file, $groups);
		return json_encode($pca_json);
	}

	public function getPCAPlotjson($loading_file, $coord_file, $std_file, $groups) {
		list($loadings, $coord, $std) = $this->getPCAResult($loading_file, $coord_file, $std_file);
		$samples = array_keys($coord);
		$genes = array_keys($loadings);
		$var_sum = 0;
		$variances = array();
		$variance_prop = array();
		$num_pc = 20;
		foreach ($std as $pc=>$std_value) {
			$var = $std_value[0] * $std_value[0];
			$var_sum = $var_sum + $var;
			$variances[] = $var;
		}
		$variances = array_splice($variances, 0, $num_pc);
		$pca_seq = array();
		$i = 1;
		for ($i=0;$i<count($variances);$i++) {
			$variance_prop[] = $variances[$i] / $var_sum;
			$pca_seq[] = $i+1;
		}
		$loading = array();
		foreach ($loadings as $key=>$values) {
			for ($i=0;$i<count($values);$i++)
				$loading[$i][$key] = round($values[$i],4);
		}
		$top_ploading = array();
		$top_nloading = array();		
		for ($i=0;$i<$num_pc;$i++) {
			arsort($loading[$i]);
			$ploading = array_splice($loading[$i], 0, $num_pc);
			asort($loading[$i]);
			$nloading = array_splice($loading[$i], 0, $num_pc);
			$top_ploading["PC".($i+1)] = array("y"=>array("smps"=>array_keys($ploading), "data"=>array(array_values($ploading))));
			$top_nloading["PC".($i+1)] = array("y"=>array("smps"=>array_keys($nloading), "data"=>array(array_values($nloading))));
		}
		$data_values = array_values($coord);
		$tissue_cat = array();
		$tissue_type = array();
		$age = array();
		$gender = array();
		$stage = array();
		$mortality = array();
		$mycn = array();
		$risk = array();
		$has_cli_data = false;
		foreach ($samples as $sample) {
			$tissue_cat[] = $groups[$sample][1];
			$tissue_type[] = $groups[$sample][0];
			if (count($groups[$sample]) > 2) {
				$has_cli_data = true;
				$age[] = $groups[$sample][2];
				$stage[] = $groups[$sample][3];
				$gender[] = $groups[$sample][4];
				$mortality[] = $groups[$sample][5];
				$mycn[] = $groups[$sample][6];
				$risk[] = $groups[$sample][7];
			}
		}

		if ($has_cli_data) {
			$z = array("Tissue type"=>$tissue_type, "Tissue category"=>$tissue_cat, "Age"=>$age, "Stage"=>$stage, "Gender"=>$gender, "Mortality"=>$mortality, "MYCN"=>$mycn, "Risk"=>$risk);
		} else {
			$z = array("Tissue type"=>$tissue_type, "Tissue category"=>$tissue_cat);
		}	
		$pca_data = array('pca_scatter'=>array("z"=>$z, "y"=>array('vars'=>$samples, 'smps'=>array("PC1 (".number_format($variance_prop[0]*100,1)."%)","PC2 (".number_format($variance_prop[1]*100,1)."%)","PC3 (".number_format($variance_prop[2]*100,1)."%)"), 'data'=>$data_values)), 'pca_variance'=>array("y"=>array("smps"=>$pca_seq, "data"=>array($variances))), 'pca_loading'=>array("p"=>$top_ploading, "n"=>$top_nloading));
		return $pca_data;

	}
	public function getStudySummaryJson($sid) {
		$study = Study::getStudy($sid);
		list($tissue_cnt, $tissue_cat_cnt, $age_cnt, $stage_cnt, $sex_cnt, $mortality_cnt, $mycn_cnt, $risk_cnt) = $study->getSummary();
		$sexes_list = array();
		$mortalities_list = array();
		$tissue_cat_list = array();
		$tissue_list = array();
		$stage_list = array();
		$age_list = array();
		$mycn_list = array();
		$risk_list = array();
		$data_tissue_cat = array();
		$data_tissue = array();
		$data_age = array();
		$data_stage = array();
		$data_sexes = array();
		$data_mortalities = array();
		$data_mycn = array();
		$data_risk = array();

		foreach ($tissue_cat_cnt as $t=>$cnt) {
			$data_tissue_cat[] = array($cnt);
			$tissue_cat_list[] = $t;
		}
		foreach ($tissue_cnt as $t=>$cnt) {
			$data_tissue[] = array($cnt);
			$tissue_list[] = $t;
		}
		foreach ($sex_cnt as $t=>$cnt) {
			$data_sexes[] = array($cnt);
			$sexes_list[] = $t;
		}
		foreach ($age_cnt as $t=>$cnt) {
			$data_age[] = array((int)$cnt);
			$age_list[] = $t;
		}
		foreach ($stage_cnt as $t=>$cnt) {
			$data_stage[] = array($cnt);
			$stage_list[] = $t;
		}
		foreach ($mortality_cnt as $t=>$cnt) {
			$data_mortalities[] = array($cnt);
			$mortalities_list[] = $t;
		}
		foreach ($mycn_cnt as $t=>$cnt) {
			$data_mycn[] = array($cnt);
			$mycn_list[] = $t;
		}
		foreach ($risk_cnt as $t=>$cnt) {
			$data_risk[] = array($cnt);
			$risk_list[] = $t;
		}

		$plot_json = array('tissue_cat'=>array("y"=>array('vars'=>$tissue_cat_list, 'data'=>$data_tissue_cat), "m"=>array("Name"=>'Tissue category')), 'tissue'=>array("y"=>array('vars'=>$tissue_list, 'data'=>$data_tissue), "m"=>array("Name"=>'Tissue')), 'stage'=>array("y"=>array('vars'=>$stage_list, 'data'=>$data_stage), "m"=>array("Name"=>'Stage')), 'sex'=>array("y"=>array('vars'=>$sexes_list, 'data'=>$data_sexes), "m"=>array("Name"=>'Sex')), 'mortality'=>array("y"=>array('vars'=>$mortalities_list, 'data'=>$data_mortalities), "m"=>array("Name"=>'Mortality Status')),'age'=>array("y"=>array('vars'=>$age_list, 'data'=>$data_age), "m"=>array("Name"=>'Age')), 'mycn'=>array("y"=>array('vars'=>$mycn_list, 'data'=>$data_mycn), "m"=>array("Name"=>'MYCN')), 'risk'=>array("y"=>array('vars'=>$risk_list, 'data'=>$data_risk), "m"=>array("Name"=>'Risk')));
		return json_encode($plot_json);
	}


	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function getStudyDetails($sid) {
		$study = Study::getStudy($sid);
		$samples = $study->getSamples();
		foreach ($samples as $sample_id => $sample) {
			$sample->patient_id = '<a target=_blank href='.url("/viewVarAnnotation/$sid/".$sample->patient_id).'/null>'.$sample->patient_id.'</a>';
			$sample->biomaterial_id = '<a href='.url('/viewBiomaterial/'.$sample->biomaterial_id).'>'.$sample->biomaterial_id.'</a>';
		}
		return $this->getDataTableAjax($samples, Config::get('onco.sample_column_exclude'));
	}

	public function hasEnsemblData($sid) {
		$study = Study::getStudy($sid);
		return $study->hasEnsemblData();
	}
 
	public function viewExpressionHeatmapByLocus($sid, $chr, $start_pos, $end_pos, $data_type) {
		$uid = $this->getUserID();
		$genes = Gene::getGeneListByLocus($chr, $start_pos, $end_pos, $data_type);
		$gene_list = implode(' ', $genes);
		if ($uid) {
			$this->setConfig("users.s$sid.u$uid.chr", $chr);
			$this->setConfig("users.s$sid.u$uid.start_pos", $start_pos);
			$this->setConfig("users.s$sid.u$uid.end_pos", $end_pos);
		}
		$study = Study::getStudy($sid, $data_type);
		return View::make('pages/viewGeneExpression', ['study'=>$study, 'uid'=>$uid, 'gene_list'=>$gene_list, 'chr'=>$chr, 'start_pos'=>$start_pos, 'end_pos'=>$end_pos, 'data_type'=>$data_type]);
	}

	public function viewStudyQuery($sid) {
		$uid = $this->getUserID();
		$spath = storage_path();
		$gene_list = Input::get('gene_list');
		$data_type = Input::get('selDataType');
		if (!$data_type)
			$data_type = "UCSC";
		if ($gene_list) {
			$gene_list = str_replace("\r\n", ' ', $gene_list);
			$gene_list = strtoupper($gene_list);
			$this->setConfig("users.s$sid.u$uid.gene_list", $gene_list);
		}
		if ($uid) {
			if (!$gene_list) {
				$gene_list = $this->getConfig("users.s$sid.u$uid.gene_list");
				if (!$gene_list) $gene_list = Config::get("onco.default.gene_list");
			}
			$chr = $this->getConfig("users.s$sid.u$uid.chr");
			$start_pos = $this->getConfig("users.s$sid.u$uid.start_pos");
			$end_pos = $this->getConfig("users.s$sid.u$uid.end_pos");
				
		} 
		if (!$uid || !$gene_list)
			list($gene_list, $chr, $start_pos, $end_pos) = $this->getDefaultStudyQuerySetting();
		$study = Study::getStudy($sid, $data_type);
		$status = $study->status;
		//$normal_cnt = DB::select("select count(*) as cnt from study_samples where study_id = $sid and tissue_cat='normal'");
		return View::make('pages/viewStudyQuery', ['study'=>$study, 'uid'=>$uid, 'gene_list'=>$gene_list, 'url'=>url("/getStudyQueryData/$sid/$gene_list"), 'chr'=>$chr, 'start_pos'=>$start_pos, 'end_pos'=>$end_pos, 'data_type'=>$data_type]);
	}

	public function getDefaultStudyQuerySetting() {
		$gene_list = Config::get("onco.default.gene_list");
		$chr = Config::get("onco.default.chr");
		$start_pos = Config::get("onco.default.start_pos");
		$end_pos = Config::get("onco.default.end_pos");
		return array($gene_list, $chr, $start_pos, $end_pos);
	}

	public function getStudyQueryData($sid, $gene_list, $data_type)
	{
		if (trim($gene_list) == 'null') return '{"no results!"}';
		$gene_list = strtoupper($gene_list);
	$start = microtime(true);
		$spath = storage_path();
		$study = Study::getStudy($sid, $data_type);
		$exp_type = $study->exp_type;
		$status = $study->status;
		$exprdb="expr";
		$genes= preg_split('/\s+/', $gene_list);		
		$gene_info = null;
		//the study_genes data has been inserted
	//echo (microtime(true) - $start)."<BR>";
	$start = microtime(true);

		if ($data_type == "Ensembl")
			$genes = Gene::getEnsemblList($genes);

		if ($status > 0) 			
			$gene_info = $study->getGeneInfo($genes);
		//$search_genes = $genes;
		
	//echo (microtime(true) - $start)."<BR>";
	$start = microtime(true);
		$gene_exp = $study->getExpByGenes($genes);
	//echo (microtime(true) - $start)."<BR>";
	$start = microtime(true);
		$genes = array_keys($gene_exp);
		if (count($gene_exp) == 0)
			return '{"no results!"}';

		$samples = $study->getStudySamples();
	//echo (microtime(true) - $start)."<BR>";
	$start = microtime(true);
		$sample_list = array();

		$mutation_genes = $study->getMutationByGenes($genes);
		foreach ($samples as $sample_id=>$sample) {
			$data_row = array();
			$mutation_row = array();
			$data_zscore = array();
			$data_mcenter = array();
			$data_zscore_normal = array();
			$data_mcenter_normal = array();			
			foreach ($genes as $gene) {
				if (!isset($gene_exp[$gene][$sample_id])) break;
				$data_row[] = $gene_exp[$gene][$sample_id]->log2;
				if (isset($mutation_genes[$gene][$sample->patient_id]))
					$mutation_row[] = "Yes";
				else
					$mutation_row[] = "No";
				if ($study->status > 0) {
					$data_zscore[] = $gene_exp[$gene][$sample_id]->zscore;
					$data_mcenter[] = $gene_exp[$gene][$sample_id]->mcenter;
					if ($study->getNormalSampleCount() > 0) {
						$data_zscore_normal[] = $gene_exp[$gene][$sample_id]->zscore_normal;
						$data_mcenter_normal[] = $gene_exp[$gene][$sample_id]->mcenter_normal;
					}
				}
			}
			//$exp_string .= $sample_id."\t".implode("\t",$data_row)."\n";
			if (count($data_row) > 0) {
				$data_values[] = $data_row;
				$mutation_data[] = $mutation_row;
				$data_values_zscore[] = $data_zscore;
				$data_values_mcenter[] = $data_mcenter;
				$data_values_zscore_normal[] = $data_zscore_normal;
				$data_values_mcenter_normal[] = $data_mcenter_normal;
				$group_json[]  = $sample->tissue_type;
				$sample_list[] = $sample_id;
			}
		} 

	//echo (microtime(true) - $start)."<BR>";
	$start = microtime(true);
		$gene_surf = GeneSurf::getAll();
		$surface_json = array();
		$mem_json = array();
		foreach($genes as $gene) { 
			if (isset($gene_surf[$gene])) {
				$surface_json[]  = $gene_surf[$gene]->membranous_protein;
				$mem_json[]  = $gene_surf[$gene]->evidence_count;
			}
		}

		$pca_json = array();
		/*
		if (count($genes) > 2) {
			$in_file = tempnam(sys_get_temp_dir(), 'onco');
			$loading_file = tempnam(sys_get_temp_dir(), 'onco');
			$coord_file = tempnam(sys_get_temp_dir(), 'onco');
			$std_file = tempnam(sys_get_temp_dir(), 'onco');
			echo $loading_file."<BR>".$coord_file."<BR>".$std_file."<BR>".
			$bytes_written = File::put($in_file, $exp_string);
			$this->runPCA($in_file, $loading_file, $coord_file, $std_file);
			$pca_json = $this->getPCAPlotjson($loading_file, $coord_file, $std_file);
		}
		*/

		$header = 150;
		$max_x_label_len = max(array_map('strlen', $sample_list));
		$max_y_label_len = max(array_map('strlen', $genes));
		$width  = $header * 2 + count(array_unique($sample_list)) * 10 + $max_y_label_len * 2;
		$height = $header * 2 + count(array_unique($genes))  * 12 + $max_x_label_len * 2;
		$plot_json = array("z" => array('Group'=> $group_json), "x"=>array('surface'=>$surface_json, "membranous"=>$mem_json), "y"=>array('vars'=>$sample_list, 'smps'=>$genes, 'data'=>$data_values, "mutation"=>$mutation_data), "m"=>array("Name"=>'expression'));

		$time_elapsed_secs = microtime(true) - $start;
		//echo "Read DB: $time_elapsed_secs <BR>";
		$json = array("data"=>$plot_json, "width"=>$width, "height"=>$height, "log2"=> $data_values, "zscore"=>$data_values_zscore, "mcenter"=>$data_values_mcenter, "zscore_normal"=>$data_values_zscore_normal, "mcenter_normal"=>$data_values_mcenter_normal, "pca_data"=>$pca_json);
		return json_encode($json);
	}


	

	public function runPCA($in_file, $loading_file, $coord_file, $std_file) {
		$cmd = "Rscript ".app_path()."/scripts/PCA.r $in_file $loading_file $coord_file $std_file T";
		echo $cmd;
		shell_exec($cmd);		
	}

	public function getPCAResult($loading_file, $coord_file, $std_file) {
		$loadings = $this->readPCAOutput($loading_file, 20);
		$coord = $this->readPCAOutput($coord_file, 3);
		$std = $this->readPCAOutput($std_file, 1);
		return array($loadings, $coord, $std);
	}

	public function readPCAOutput($file, $col_num) {
		$output = array();
		$handle = fopen($file, "r");
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				$line = trim($line);
				$fields = preg_split('/\s+/', $line);		
				//if (count($fields) < $col_num + 1) continue; 
				//if ($fields[0] == '') continue;
				$key = str_replace('"', '', $fields[0]);
				$fields = array_slice($fields, 1);
				foreach ($fields as $field)
					$output[$key][] = $field;
			}
			fclose($handle);
		}
		return $output;
	}


	public function getExpr($sid, $sample_list, $genes) {
		$start = microtime(true);
		$study = Studies::find($sid);
		$exp_file = public_path()."/expression/exp_".$sid.".tsv";
		if ($study->status == 1 && file_exists($exp_file)) {
			$fh_exp = fopen($exp_file, "r");
			while ($line=fgets($fh_exp)) {
				$line = chop($line);
				$fields = preg_split("/[\t]/", $line);
				if (in_array($fields[0], $genes)) {
					//echo $fields[0];
				}				
			}
			fclose($fh_exp);
		}
		$time_elapsed_secs = microtime(true) - $start;
		//echo "Read file: $time_elapsed_secs <BR>";
	}

   public function getHeatmapJson($sid, $Dsmpls, $Dgenes, $status) {
	$start = microtime(true);
      //$sql = "select 'gene' as exp_level, e.gene as id, s.GROUP_NAME, g.SAMPLE_ID, log2 from study_groups s, expression_rsu_gene e, group_samples g where s.STUDY_ID=$sid and s.ID = g.GROUP_ID and g.SAMPLE_ID=e.SAMPLE_NAME and e.sample_name in ('".implode("','", $Dsmpls)."') and e.gene in ('".implode("','", $Dgenes)."')  ";

	$gene_list = implode("','", $Dgenes);
	$sample_list = implode("','", $Dsmpls);
      $sql = "
         select e.gene as id, s.sample_id, s.tissue_type, exp_value, evidence_count, membranous_protein 
         from study_samples s  
         join expr e on s.sample_id=e.sample_id
         left join (select distinct * from gene_surf) f on e.gene=f.gene 
         where s.STUDY_ID=$sid 
         and e.gene in ('$gene_list')";
//and e.sample_id in ('$sample_list')

      $rows = DB::select($sql);
      $raw_data = array();
      $exps = array();
      if (count($rows) == 0) { 
          echo "no results!";
          return;
      }

	//the study_genes data has been inserted
	$gene_info = null;
	if ($status > 0) {
		$gene_info = array();
		$study_genes = DB::select("select * from study_genes where study_id = $sid and gene in ('$gene_list')");
		foreach ($study_genes as $study_gene) {
			$gene_info[$study_gene->gene]["mean"] = $study_gene->mean;
			$gene_info[$study_gene->gene]["std"] = $study_gene->std;
			$gene_info[$study_gene->gene]["median"] = $study_gene->median;
			$gene_info[$study_gene->gene]["normal_mean"] = $study_gene->normal_mean;
			$gene_info[$study_gene->gene]["normal_std"] = $study_gene->normal_std;
			$gene_info[$study_gene->gene]["normal_median"] = $study_gene->normal_median;
		}

	}
      $genes_a = array();
      $genes_b = array();
      $groups = array();
      foreach ($rows as $row) {
         $raw_data[$row->sample_id][$row->id] = $row->exp_value;
         $groups[$row->sample_id] = $row->tissue_type;
         $genes_a[$row->id] = $row->evidence_count; 
         #if($row->evidence_count==null) {$genes_a[$row->id] =-1;}
         $genes_b[$row->id] = 'N';
         if($row->membranous_protein==1)  { $genes_b[$row->id] = 'Y'; }
      }

      $samples = array_keys($raw_data);
      $genes = array_keys($genes_a);
      $data_values = array();
      $data_value_zscore = array();
      $data_value_mcenter = array();
      $data_value_zscore_normal = array();
      $data_value_mcenter_normal = array();
      $group_json = array();
      $genes_json = array();
      foreach ($samples as $sample) {
           $data_row = array();
           $data_zscore = array();
           $data_mcenter = array();
           $data_zscore_normal = array();
           $data_mcenter_normal = array();
           foreach ($genes as $gene) {
		$data_row[] = $raw_data[$sample][$gene];
		if ($status > 0) {
			if ($gene_info[$gene]["std"] != 0)
				$zscore = ($raw_data[$sample][$gene] - $gene_info[$gene]["mean"]) / $gene_info[$gene]["std"];
			$data_zscore[] = $zscore;
			$data_mcenter[] = $raw_data[$sample][$gene] - $gene_info[$gene]["median"];
			if ($gene_info[$gene]["normal_std"] != 0)
				$zscore_normal = ($raw_data[$sample][$gene] - $gene_info[$gene]["normal_mean"]) / $gene_info[$gene]["normal_std"];
			$data_zscore_normal[] = $zscore_normal;
			$data_mcenter_normal[] = $raw_data[$sample][$gene] - $gene_info[$gene]["normal_median"];
		}
           }
           $data_values[] = $data_row;
           $data_values_zscore[] = $data_zscore;
           $data_values_mcenter[] = $data_mcenter;
           $data_values_zscore_normal[] = $data_zscore_normal;
           $data_values_mcenter_normal[] = $data_mcenter_normal;
           $group_json[]  = $groups[$sample];
      } 
      foreach($genes as $gene) { 
           $genes_a_json[]  = $genes_a[$gene];
           $genes_b_json[]  = $genes_b[$gene];
      }  

      $header = 150;
      $max_x_label_len = max(array_map('strlen', $samples));
      $max_y_label_len = max(array_map('strlen', $genes));
      $width  = $header * 2 + count(array_unique($samples)) * 10 + $max_y_label_len * 2;
      $height = $header * 2 + count(array_unique($genes))  * 12 + $max_x_label_len * 2;
      $plot_json = array("z" => array('Group'=> $group_json), "x"=>array('surface'=>$genes_a_json, "membranous"=>$genes_b_json), "y"=>array('vars'=>$samples, 'smps'=>$genes, 'data'=>$data_values), "m"=>array("Name"=>'expression'));

	$time_elapsed_secs = microtime(true) - $start;
	//echo "Read DB: $time_elapsed_secs <BR>";
	$json = array("data"=>$plot_json, "width"=>$width, "height"=>$height, "log2"=> $data_values, "zscore"=>$data_values_zscore, "mcenter"=>$data_values_mcenter, "zscore_normal"=>$data_values_zscore_normal, "mcenter_normal"=>$data_values_mcenter_normal);
	return $json;
   }


	public function getMutationGenes($sid) {
		$study = Study::getStudy($sid);
		$vars = $study->getMutations();
		$genes = array();
		$samples = array();
		foreach ($vars as $var) {
			if ($var->func == 'exonic') {
				if (array_key_exists($var->gene, $genes))
					$genes[$var->gene]++;
				else
					$genes[$var->gene] = 1;
				$samples[$var->gene][$var->sample_id] = 0;
			}			

		}
		arsort($genes);
		$data = array();
		foreach ($genes as $gene_id => $cnt) {
			$sample_cnt = count($samples[$gene_id]);
			$gene_url = "<a href=".url("/viewGeneDetail/$sid/$gene_id/7/UCSC").">$gene_id</a>";
			$data[] = array($gene_url, $cnt, $sample_cnt);
		}
		$cols = array(array("title"=>"Gene"), array("title"=>"Count"), array("title"=>"Samples"));
		return json_encode(array("cols" => $cols, "data" => $data));
	}



   ############## run GSEA ###########################################################

	public static function normalizeString ($str = '')
   {
      $str = strip_tags($str); 
      $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
      $str = preg_replace('/[\"\*\/\:\<\>\?\'\|\,\(\)]+/', ' ', $str);
      //$str = strtolower($str);
      //$str = html_entity_decode( $str, ENT_QUOTES, "utf-8" );
      //$str = htmlentities($str, ENT_QUOTES, "utf-8");
      $str = preg_replace("/(&)([a-z])([a-z]+;)/i", ' ', $str);
      $str = str_replace(' ', '-', $str);
      //$str = rawurlencode($str);
      $str = str_replace('%', '-', $str);
      return $str;
   }

   public function gsea($sid)
   {

      #-----parameters------
      $path      = public_path();
      $gpath     = "$path/data/gsea";
      $uid       = Sentry::getUser()->id;
      $email     = Sentry::getUser()->email; 
      if (strpos($email, '@') == FALSE)
      	$email = Sentry::getUser()->email_address;
      $geneset   = Input::get ('geneset');
      #exec("chmod -R 777 $gpath/pub");

      if(isset($geneset)==false){

         ###########################################################
         #################  GSEA Info  #############################
         ###########################################################
         #================get sample list for gsea==================
         $ss = "
            select distinct sample_id
            from study_samples where study_id=".$sid."
            ";

         $study = Study::getStudy($sid);
         $Data = $study->getStudySamples();
         //$Data = DB::select($ss);
         $smpls='';
         foreach($Data as $sample_id => $dd){
            //foreach($dd as $key=>$value) {
               $smpls .= "<option value=$sample_id>$sample_id</option>\n";
            //}
         }
         
         #================get gsea result list =====================
         $dirs = scandir("$gpath/pub/");
         $gsealist ='<table id=gseajobs name=gseajobs>';
         //$gsealist.='<tr><td>Job</td><td>Download</td><td>User(ID email)</td><td>Study</td><td>Data Source</td></tr>';
         $gsealist.='<tr><td>Job</td><td>Status</td><td>Del?</td></tr>';
         foreach($dirs as $dir) { 
            $dd = explode(".", $dir);
            if(count($dd)>6 and $dd[6]==$uid and file_exists("$gpath/pub/$dir/$dir.rnk")) { 
               $gsealist .='<tr>';
               if(file_exists("$gpath/pub/$dir.tar")) {
                  $gsealist .= '<td style="border: 1px dotted #5F5; background:#EFE; color:green">'.substr($dir,0,50);
                  $dd   = scandir("$gpath/pub/$dir/");
                  $logs = scandir("$gpath/pub/$dir/pub");                  
                  foreach($logs as $log) {
                     $gset = substr($log, 4);
                     foreach($dd as $d) { 
                        if(strpos($d, $gset)!==false) { 
                           if (file_exists("$gpath/pub/$dir/$d/index.html")){
                              $gsealist .= '<br><a target="gseaiframe" href='.asset("data/gsea/pub/$dir/$d/index.html").'>'.substr($d,0,40).'</a>';
                           } 
                           else { 
                              $gsealist .= '<br><font color=#999>'.substr($d,0,40).'</font>';
                           }
                           $gsealist .= ' <a target="gseaiframe" href='.asset("data/gsea/pub/$dir/pub/$log").'><img title=log style=\"width:12px;height:12px;\" src=https://help.sap.com/static/saphelp_470/en/35/3d9f3cad1e3251e10000000a114084/Image185.gif></a>';                              
                        }
                     }
                  }
                  $gsealist .='</td>'; 
                  $gsealist .= '<td><a target="gseaiframe" href='.asset('data/gsea/pub/'.$dir.'.tar'       ).'><img title=Download style=\"width:16px;height:16px;\" src='.url('images/smallZipFileIcon.png').'></a></td>';  
               }
               else {
                  #$gsealist .= "<td><font color=red>".substr($dir,0,50)."</font></td>";
                  $gsealist .= '<td style="border: 1px dotted #F55; background:#FEE; color:#999">'.substr($dir,0,50);
                  $dd   = scandir("$gpath/pub/$dir/");
                  $logs = scandir("$gpath/pub/$dir/pub");                  
                  foreach($logs as $log) {
                     $gset = substr($log, 4);
                     foreach($dd as $d) { 
                        if(strpos($d, $gset)!==false) { 
                           if (file_exists("$gpath/pub/$dir/$d/index.html")){
                              $gsealist .= '<br><a target="gseaiframe" href='.asset("data/gsea/pub/$dir/$d/index.html").'>'.substr($d,0,40).'</a>';
                           } 
                           else { 
                              $gsealist .= '<br><font color=#999>'.substr($d,0,40).'</font>';
                           }
                           $gsealist .= ' <a target="gseaiframe" href='.asset("data/gsea/pub/$dir/pub/$log").'><img title=log style=\"width:12px;height:12px;\" src=https://help.sap.com/static/saphelp_470/en/35/3d9f3cad1e3251e10000000a114084/Image185.gif></a>';                              
                        }
                     }
                  }
                  $gsealist .='</td>'; 
                  $status="<img title=failed style=\"width:16px;height:16px;\" src=https://build.spring.io/images/iconsv4/icon-build-failed.png>"; 
                  $logs = scandir("$gpath/pub/$dir/pub/");
                  foreach($logs as $log) { if ((time()-filectime("$gpath/pub/$dir/pub/$log")) < 300) { $status="<A title=\"running/refresh\" HREF=\"javascript:history.go(0)\"><img  style=\"width:16px;height:16px;\" src=https://icons.iconarchive.com/icons/fatcow/farm-fresh/16/arrow-refresh-icon.png></A>"; } }
                  $gsealist .= "<td>$status</td>";
               }
               $gsealist .='<td><a href='.asset("/gseadel/$sid=$dir").'><img style=\"width:16px;height:16px;\" src=https://www.quickbase.com/user-assistance/images/delete-icon.png></a></td></tr>';
            }
         }
         $gsealist .='</table>';
         return View::make('pages/gsea', ['sid'=>$sid, 'uid'=>$uid, 'storage'=>url(), 'smpls'=>$smpls, 'gsealist'=>$gsealist ]);
      }
      else{ 

        $smplid    = Input::get ('smplid'    );
        $geneset   = Input::get ('geneset'   );
        $geneduprm = Input::get ('geneduprm' );
        $gene      = Input::get ('gene'      );
        
        
        #-----genesets------
        $grp       = Input::get('grp');
        $gmx       = implode("|",$grp);
        if (Input::hasFile('gsea_file')) {
           $file     = Input::file('gsea_file');
           $fgsea    = "$uid.".$file->getClientOriginalName();
           $uploaded = $file->move("$gpath/pub/", $fgsea);
           $gmx.= "|$gpath/pub/$fgsea";
        }
        #-----prerank file------
        if (Input::hasFile('prerank_file')) {
           $file     = Input::file('prerank_file');
           $fprerank = "$uid.".$file->getClientOriginalName();
           $uploaded = $file->move("$gpath/pub/", $fprerank);
        }
        #-----DB info------
        $study = Studies::find($sid);
        $exp_type = $study->exp_type;
        $exprdb="expr";
        
        
       
        #-----write the file::head
        if(    isset($fprerank)) { $fn="$fprerank";    $data="[user file] $fprerank" ; }
        elseif(isset($smplid  )) { $fn="$uid.$smplid"; $data="[sample] $smplid";       }
        elseif(isset($gene    )) { $fn="$uid.$gene";   $data="[correlation] $gene";    }
        date_default_timezone_set('America/New_York');
        $fn=date("Y.m.d.G.i.s.").$this->normalizeString($fn);
        //$cmd = "/opt/nasapps/production/java/java-1.8.0-oraclejdk/bin/java -cp gsea2-2.0.10.jar -Xmx4192m xtools.gsea.GseaPreranked -gmx '$gmx' -collapse false -mode Max_probe -norm meandiv -nperm 1000 -rnk pub/$fn/$fn.rnk -scoring_scheme weighted -rpt_label my_analysis -chip gseaftp.broadinstitute.org://pub/gsea/annotations/GENE_SYMBOL.chip -include_only_symbols true -make_sets true -plot_top_x 20 -rnd_seed timestamp -set_max 500 -set_min 15 -zip_report false -out pub/$fn -gui false ";
        $cmd = "java -cp gsea2-2.0.10.jar -Xmx4192m xtools.gsea.GseaPreranked -gmx '$gmx' -collapse false -mode Max_probe -norm meandiv -nperm 1000 -rnk pub/$fn/$fn.rnk -scoring_scheme weighted -rpt_label my_analysis -chip gseaftp.broadinstitute.org://pub/gsea/annotations/GENE_SYMBOL.chip -include_only_symbols true -make_sets true -plot_top_x 20 -rnd_seed timestamp -set_max 500 -set_min 15 -zip_report false -out pub/$fn -gui false ";
        exec("mkdir        $gpath/pub/$fn");
        exec("mkdir        $gpath/pub/$fn/pub/");
        $fout=fopen("$gpath/pub/$fn/$fn.rnk", "w");
        fwrite($fout, "#User: \t$email\n");
        fwrite($fout, "#Study:\t$sid\n"        );
        fwrite($fout, "#Data:\t$data\n"        );
        fwrite($fout, "#CMD:\t$cmd\n"          );
        
        #-----write the file::data        
        if(isset($fprerank)) { 
           #------get expression data from uploaded file-----
           $fin=fopen("$gpath/pub/$fprerank", "r");
           while(($line=fgets($fin))!=false) { 
              if($line[0]!="#") { 
                 fwrite($fout, $line);
              }
           }
           fclose($fin);
        }         
        elseif(isset($smplid)) { 
              $ss = " 
                 select gene, exp_value 
                 from $exprdb e
                 where sample_id='".$smplid."'   
                 ";
           $Data = DB::select($ss);
           foreach($Data as $dd){
              $rr='';
              foreach($dd as $key=>$value) {
                 $rr .= "$value\t";
              }
              $rr=trim($rr, "\t");   
              fwrite($fout, $rr."\n");
           }        
        }
        
        
        #run the gsea wrapper
        //$host = "http://fr-s-bsg-onc-d.ncifcrf.gov/onco.sandbox2/public";
        $host = url("/");
        $cmd = "python $gpath/gsea.wrapper.py $host $fn & ";
        pclose(popen($cmd, 'r'));
        
        Mail::send(
           'emails.gsea', 
           array(
              'link'=>link_to('data/gsea/pub/'.$fn.'/index.html'),
              'zip' =>link_to('data/gsea/pub/'.$fn.'.tar'),
           ), 
           function($message) use ($email, $fn) {
              $message->to($email, 'GSEA User')->subject("GSEA Done: ".$fn);
           }
        );
        
        //$t = new gseaThread();
        //if($t->start()) { 
        //   while($t->isRunning()) { 
        //      echo ".";
        //      usleep(100);
        //   }
        //   $t->join();
        //}
        }
        return Redirect::to("/viewGSEA/$sid");
        return View::make('pages/gsea', ['user'=>"$uid\t$email", 'study'=>"$sid", 'data'=>"$data"]);
        
   }


	

	############## delete run data ###########################################################
	public function gseadel($target) {
		$path = public_path();
		$ss   = explode('=', $target);
		#if (file_exists("$path/data/gsea/pub/$dir")) { unlink ("$path/data/gsea/pub/$dir"); }
		exec("rm -rf $path/data/gsea/pub/".$ss[1]);
		return Redirect::to("/gsea/".$ss[0]);
	}

}
