<?php

use Log;

class VarianceController extends BaseController {


	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function viewVarAnnotation($sample_id, $gene_id) {
		$samples = $this->getAllSamples();
		//$variance = DB::table('var_annotation')->where('sampleName', $sample_id)->get();
		$sql = "select '&genome=hg19&locus='||chr||':'||start_pos||'-'||end_pos||'&merge=true' as view_igv, 'N' as Hotspots, 'N' as Hotspot_genes, 'N' as Panel, 'N' as Exome, a.* from var_annotation a where sampleName='$sample_id'";
		$current_gene_label = "";
		if ($gene_id != 'null') {
			$gene_id = strtoupper($gene_id);
			$sql .= "and gene_refgene='$gene_id'";
			$current_gene_label = '&nbsp;&nbsp;; Current gene: <font color="red">'.$gene_id.'</font>';
		}
		$variance = DB::select($sql);
		if (count($variance) == 0) {
			return View::make('pages/error', ['message' => 'No results!']);
		}
		$diagnosis = $variance[0]->diagnosis;
		$target = $this->checkTargetBySample($sample_id);
		list($hotspot_gene_list, $hotspot_gene_desc) = $this->getHotspotGenes();
		list($hotspot_list, $hotspot_desc) = $this->getHotspots();
		list($data, $columns, $col_groups) = $this->prepareVarData($variance, $target, $hotspot_list, $hotspot_gene_list);
		$current_setting = 'Current diagnosis: <font color="red">'.$diagnosis.'</font> &nbsp;&nbsp; Current sample: <font color="red">'.$sample_id.'</font>'.$current_gene_label;

		return View::make('pages/viewVarAnnotation', ['samples' => $samples, 'gene_id'=>$gene_id, 'current_setting' => $current_setting, 'current_diag' => $diagnosis, 'current_sample' => $sample_id, 'data'=>$data, 'cols'=>$columns, 'col_groups'=>$col_groups, 'hotspot_gene_desc'=>$hotspot_gene_desc, 'hotspot_desc'=>$hotspot_desc]);
	}

	public function checkTargetBySample($sample) {
		$sql = "select distinct a.chr,a.start_pos,a.end_pos,t.type from var_annotation a, var_target t where sampleName='$sample' and a.chr=t.chr and a.start_pos > t.start_pos and a.end_pos < t.end_pos";
		$rows = DB::select($sql);
		$target = array();
		foreach ($rows as $row) {
			$key = $row->chr."_".$row->start_pos."_".$row->end_pos;
			$target[$key][$row->type] = 'Y';
		} 
		return $target;
	}

	public function checkTargetByGene($gene_id) {
		$sql = "select distinct a.chr,a.start_pos,a.end_pos,t.type from var_annotation a, var_target t where gene_refgene='$gene_id' and a.chr=t.chr and a.start_pos > t.start_pos and a.end_pos < t.end_pos";
		$rows = DB::select($sql);
		$target = array();
		foreach ($rows as $row) {
			$key = $row->chr."_".$row->start_pos."_".$row->end_pos;
			$target[$key][$row->type] = 'Y';
		} 
		return $target;

	}

	public function checkTargetByLocus($chr, $start_pos, $end_pos) {
		$sql = "select distinct a.chr,a.start_pos,a.end_pos,t.type from var_annotation a, var_target t where a.chr='$chr' and a.start_pos>='$start_pos' and a.end_pos<='$end_pos' and a.chr=t.chr and a.start_pos > t.start_pos and a.end_pos < t.end_pos";
		$rows = DB::select($sql);
		$target = array();
		foreach ($rows as $row) {
			$key = $row->chr."_".$row->start_pos."_".$row->end_pos;
			$target[$key][$row->type] = 'Y';
		} 
		return $target;

	}

	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function viewVarAnnotationByGene($gene_id) {
		$gene_id = strtoupper($gene_id);
		$samples = $this->getAllSamples();
		$diagnosis = array_keys($samples)[0];
		$sample_id = array_keys($samples[$diagnosis])[0];
		$variance = DB::select("select '&genome=hg19&locus='||chr||':'||start_pos||'-'||end_pos||'&merge=true' as view_igv, 'N' as Hotspots, 'N' as Hotspot_genes, 'N' as Panel, 'N' as Exome, a.* from var_annotation a where gene_refgene='$gene_id'");
		if (count($variance) == 0) {
			return View::make('pages/error', ['message' => 'No results!']);
		}
		$target = $this->checkTargetByGene($gene_id);
		list($hotspot_gene_list, $hotspot_gene_desc) = $this->getHotspotGenes();
		list($hotspot_list, $hotspot_desc) = $this->getHotspots();
		list($data, $columns, $col_groups) = $this->prepareVarData($variance, $target, $hotspot_list, $hotspot_gene_list);
		$current_setting = 'Current gene: <font color="red">'.$gene_id.'</font>';
		return View::make('pages/viewVarAnnotation', ['samples' => $samples, 'gene_id'=>$gene_id, 'current_setting' => $current_setting, 'current_diag' => $diagnosis, 'current_sample' => $sample_id, 'data'=>$data, 'cols'=>$columns, 'col_groups'=>$col_groups, 'hotspot_gene_desc'=>$hotspot_gene_desc, 'hotspot_desc'=>$hotspot_desc]);
	}


	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function viewVarAnnotationByLocus($chr, $start_pos, $end_pos) {
		$samples = $this->getAllSamples();
		$diagnosis = array_keys($samples)[0];
		$sample_id = array_keys($samples[$diagnosis])[0];
		$variance = DB::select("select '&genome=hg19&locus='||chr||':'||start_pos||'-'||end_pos||'&merge=true' as view_igv, 'N' as Hotspots, 'N' as Hotspot_genes, 'N' as Panel, 'N' as Exome, a.* from var_annotation a where chr='$chr' and start_pos>='$start_pos' and end_pos<='$end_pos'");
		if (count($variance) == 0) {
			return View::make('pages/error', ['message' => 'No results!']);
		}
		$target = $this->checkTargetByLocus($chr, $start_pos, $end_pos);
		list($hotspot_gene_list, $hotspot_gene_desc) = $this->getHotspotGenes();
		list($hotspot_list, $hotspot_desc) = $this->getHotspots();
		list($data, $columns, $col_groups) = $this->prepareVarData($variance, $target, $hotspot_list, $hotspot_gene_list);
		$current_setting = 'Current locus: <font color="red">'.$chr."&nbsp;".$start_pos."-".$end_pos.'</font>';
		return View::make('pages/viewVarAnnotation', ['samples' => $samples, 'gene_id'=>'null', 'current_setting' => $current_setting, 'current_diag' => $diagnosis, 'current_sample' => $sample_id, 'data'=>$data, 'cols'=>$columns, 'col_groups'=>$col_groups, 'hotspot_gene_desc'=>$hotspot_gene_desc, 'hotspot_desc'=>$hotspot_desc]);
	}


	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function prepareVarData($variance, $target, $hotspot_list, $hotspot_gene_list) {
		$data = array();
		foreach ($variance as $var) {
			$columns = array();
			$row = array();
			$genes = $var->gene_refgene;
			$genes = explode(',',str_replace('"', '', $genes));
			$var->gene_refgene = "";
			//$var->view_igv = "";
			if (file_exists(public_path().'/data/bams/'.$var->samplename.'.bam')) {
				//$var->view_igv = "<a href=http://localhost:60151/load?file=".url('/data/bams/'.$var->samplename.'.bam').$var->view_igv.">IGV</a>";
				$var->view_igv = "<a href=javascript:launchIGV('".url('/data/bams/'.$var->samplename.'.bam').$var->view_igv."')><img width=25 hight=25 src='".url('images/igv.jpg')."'/></a>";
			}else {
				$var->view_igv = "";
			}
			$key = $var->chr."_".$var->start_pos."_".$var->end_pos;
			
			preg_match('/.*:p\.(.*)/', $var->aachange_refgene, $matches);
			if (count($matches) > 0)
				$var->aachange_refgene = $matches[1];

			if (isset($target[$key]["exome"]))
				$var->exome = 'Y';
			if (isset($target[$key]["v4"]))
				$var->panel = 'Y';			

			foreach ($genes as $gene) {
				//$var->view_igv = "<a target='_blank' href=http://localhost:60151/load?file=".url('/getBam/'.$var->samplename.'.bam').$var->view_igv.">IGV</a>";
				$var->gene_refgene .= "<a target='_blank' href = http://fr-s-ccr-cbio-d.ncifcrf.gov:8080/cbioportal/cross_cancer.do?cancer_study_list=&cancer_study_id=all&data_priority=0&case_ids=&gene_set_choice=user-defined-list&gene_list=".$gene."&clinical_param_selection=null&tab_index=tab_visualize&Action=Submit#crosscancer/overview/0/".$gene."/sarc_mskcc%2Csarc_tcga%2Cacc_tcga%2Cchol_jhu_2013%2Cchol_nccs_2013%2Cchol_nus_2012%2Cblca_bgi%2Cblca_mskcc_solit_2012%2Cblca_mskcc_solit_2014%2Cblca_tcga%2Cblca_tcga_pub%2Cmm_broad%2Ccoadread_genentech%2Ccoadread_mskcc%2Ccoadread_tcga%2Ccoadread_tcga_pub%2Clgg_tcga%2Cpcpg_tcga%2Cbrca_bccrc%2Cbrca_bccrc_xenograft_2014%2Cbrca_broad%2Cbrca_sanger%2Cbrca_tcga%2Cbrca_tcga_pub%2Ccesc_tcga%2Cescc_icgc%2Chnsc_broad%2Chnsc_jhu%2Chnsc_tcga%2Chnsc_tcga_pub%2Cnpc_nusingapore%2Clihc_amc_prv%2Clihc_riken%2Clihc_tcga%2Csclc_clcgp%2Csclc_jhu%2Ccellline_ccle_broad%2Ccellline_nci60%2Cpaad_icgc%2Cpaad_tcga%2Cprad_broad%2Cprad_broad_2013%2Cprad_mich%2Cprad_mskcc%2Cprad_mskcc_2014%2Cprad_mskcc_cheny1_organoids_2014%2Cprad_su2c_2015%2Cprad_tcga%2Cprad_tcga_pub%2Cthca_tcga%2Cthca_tcga_pub%2Cucec_tcga%2Cucec_tcga_pub%2Claml_tcga%2Claml_tcga_pub%2Cmbl_broad_2012%2Cmbl_icgc%2Cmbl_pcgp%2Cmpnst_mskcc%2Cesca_broad%2Cesca_tcga%2Cstad_pfizer_uhongkong%2Cstad_tcga%2Cstad_tcga_pub%2Cstad_uhongkong%2Cstad_utokyo%2Cacyc_mskcc%2Ckirc_bgi%2Ckirc_tcga%2Ckirc_tcga_pub%2Cluad_broad%2Cluad_tcga%2Cluad_tcga_pub%2Cluad_tsp%2Clusc_tcga%2Clusc_tcga_pub%2Cscco_mskcc%2Cskcm_broad%2Cskcm_broad_dfarber%2Cskcm_tcga%2Cskcm_yale%2Cucs_tcga%2Cgbm_tcga%2Cgbm_tcga_pub%2Cgbm_tcga_pub2013%2Ckirp_tcga%2Ckich_tcga%2Ckich_tcga_pub%2Cdlbc_tcga%2Cov_tcga%2Cov_tcga_pub>".$gene."</a>,";
				if (isset($hotspot_gene_list[$gene]))
					$var->hotspot_genes = 'Y';
				if (isset($hotspot_list[$gene][$var->aachange_refgene]))
					$var->hotspots = 'Y';
			}
			$var->gene_refgene = rtrim($var->gene_refgene, ",");

			
			if (isset($var->docm_pmid)) {
				$var->docm_pmid = '<a target="_blank" href="http://docm.genome.wustl.edu/variants/'.$var->docm_pmid.'">DOCM</a>';
			}
			if ($var->mycg_link != "-1") {
				$var->mycg_link = '<a target="_blank" href="http://'.$var->mycg_link.'">My cancer genome</a>';
			}
			if ($var->snp138 != "-1") {
				$var->snp138 = "<a target='_blank' href=http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=".trim($var->snp138, "rs").">".trim($var->snp138, "rs")."</a>";
			}
			if ($var->cosmic70 != "-1" && $var->cosmic70 != "NA") {
				$cosmics = explode(',',str_replace('"', '', $var->cosmic70));
				$cosmic = $cosmics[0];
				$cosmic = str_replace('ID=COSM', "", $cosmic);
				$var->cosmic70 = "<a target='_blank' href=http://grch37-cancer.sanger.ac.uk/cosmic/mutation/overview?id=".$cosmic.">".$cosmic."</a>";
			}
			$var_keys = array_keys((array)$var);
			$var_values = array_values((array)$var);
			$total_freq = 0;
			for ($i=0;$i<count($var_values);$i++) {
				if ($var_values[$i] == "-1" || $var_values[$i] == "NA" || $var_values[$i] == "-" || $var_values[$i] == ".") {
					$var_values[$i] = "";					
				}
				if ($var_keys[$i] == 'grand_total')
					$var_keys[$i] = 'Reported Samples';
				if ($var_keys[$i] == 'acmg_gene')
					$var_keys[$i] = 'ACMG';
				if ($var_keys[$i] == 'docm_pmid')
					$var_keys[$i] = 'DoCM';
				if ($var_keys[$i] == 'mycg_gene')
					$var_keys[$i] = 'MYCG';
				if ($var_keys[$i] == 'match_v3_gene')
					$var_keys[$i] = 'MatchV3';
				if ($var_keys[$i] == 'samplename')
					$var_keys[$i] = 'Sample';
				if ($var_keys[$i] == 'all_1000g2014oct')
					$var_keys[$i] = 'Frequency';
				if ($var_keys[$i] == 'cadd')
					$var_keys[$i] = 'Predictions';
				if ($var_keys[$i] == 'hgmd2014_3_acc_no')
					$var_keys[$i] = 'HGMD2014_3';
				if ($var_keys[$i] == 'func_refgene')
					$var_keys[$i] = 'Function';
				if ($var_keys[$i] == 'exonicfunc_refgene')
					$var_keys[$i] = 'Effect';
				$var_keys[$i] = str_replace('refgene', '', $var_keys[$i]);
								
			}

			for ($i=16;$i<54;$i++) {
				if (is_numeric($var_values[$i])) {
					if ($var_values[$i] != 0) {
						$total_freq++;
					}
				}
			}
			if ($total_freq > 0) {
				$var_values[16] = "<a href=javascript:getDetails('freq','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$total_freq."</a>";
			}
			

			$var_values[134] = "<a href='".url('/viewVarAnnotation/'.$var->samplename."/null")."'>".$var->samplename."</a>&nbsp;&nbsp;<a href=javascript:getDetails('sample_id','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');><img width=25 height=25 src='".url('images/info.png')."'></img></a>"; //sample_id
			$var_values[125] = "<a href=javascript:getDetails('acmg','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$var_values[125].'</a>'; //acmg
			$var_values[122] = "<a href=javascript:getDetails('grand','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$var_values[122].'</a>'; //grand
			$var_values[74] = "<a href=javascript:getDetails('mycg','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$var_values[74].'</a>';   //mycg
			$var_values[69] = "<a href=javascript:getDetails('match','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$var_values[69].'</a>';   //match
			$var_values[63] = "<a href=javascript:getDetails('hgmd','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>".$var_values[63].'</a>';   //hgmd
			//$var_keys[12] = 'Frequency';
			//$var_keys[50] = 'Predictions';
			//$var_keys[118] = 'Reported Samples';

			
			$has_prediction = 0;
			for ($i=54;$i<61;$i++) {
				if ($var_values[$i] != '') {
					$has_prediction = 1;
					$var_values[54] = "<a href=javascript:getDetails('prediction','$var->chr','$var->start_pos','$var->end_pos','$var->ref_base','$var->alt_base','$var->samplename');>YES</a>";   //prediction
					break;
				}
			}
			array_splice($var_keys, 135, 10);
			array_splice($var_values, 135, 10);
			array_splice($var_keys, 132, 2);
			array_splice($var_values, 132, 2);
			array_splice($var_keys, 126, 6);
			array_splice($var_values, 126, 6);
			array_splice($var_keys, 123, 2);
			array_splice($var_values, 123, 2);
			array_splice($var_keys, 86, 36);
			array_splice($var_values, 86, 36);
			array_splice($var_keys, 78, 8);
			array_splice($var_values, 78, 8);
			array_splice($var_keys, 75, 3);
			array_splice($var_values, 75, 3);
			array_splice($var_keys, 70, 3);
			array_splice($var_values, 70, 3);
			array_splice($var_keys, 64, 5);
			array_splice($var_values, 64, 5);
			array_splice($var_values, 55, 6);
			array_splice($var_keys, 55, 6);
			array_splice($var_keys, 17, 37);
			array_splice($var_values, 17, 37);
			for ($i=0;$i<count($var_keys);$i++) {
				$columns[] = array("title" => $this->processColumnName($var_keys[$i]));
				$row[] = $var_values[$i];
			}
			$data[] = $row;

		}
		
		$var_cols = DB::table('var_annotation_col')->get();
		$col_groups = array();
		$col_groups['Default'][] = 0;
		$i = 1;
		foreach ($var_cols as $var_col) {
			$col_groups[$var_col->group_name][] = $i++;
		}

		return array($data, $columns, $col_groups);
	}	



	public function getVarDetailsAnnotation($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $sample_id) {
		$variance = DB::select("select * from var_annotation where chr='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref_base = '$ref_base' and alt_base = '$alt_base' and samplename = '$sample_id'");
		$var = $variance[0];
		$type_fields = array("sample_id" => [127,141], "acmg" => [121,127], "grand" => [81, 120], "cancer" => [81, 117], "mycg" => [70,74], "match" => [65,68], "hgmd" => [59,64], 'freq' => [12, 49], 'prediction' => [49,56]);

		$data = array();
		$columns = array(array("title"=>"key"), array("title"=>"value"));
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
		return json_encode(array("data" => $data, "columns" => $columns));
	}

	public function getVarDetails($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $sample_id) {

		$var_tables = array("grand" => 'var_reported_mutations', "freq" => 'var_frequency', "hgmd" => 'var_hgmd');

		if (!array_key_exists($type, $var_tables))
			return $this->getVarDetailsAnnotation($type, $chr, $start_pos, $end_pos, $ref_base, $alt_base, $sample_id);
		$table_name = $var_tables[$type];
		$rows = DB::select("select * from $table_name where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base'");
		//print ("select * from $table_name where chromosome='$chr' and start_pos='$start_pos' and end_pos = '$end_pos' and ref = '$ref_base' and alt = '$alt_base'");
		$data = array();
		if ($type == "grand")
			$columns = array(array("title"=>"count"), array("title"=>"source"));
		elseif ($type == "freq")
			$columns = array(array("title"=>"type"), array("title"=>"subtype"), array("title"=>"frequency"));
		else
			$columns = array(array("title"=>"key"), array("title"=>"value"));
		foreach ($rows as $row) {
			$array = json_decode(json_encode($row), true);			
			if ($type == "grand" || $type == "freq") {
				$row_value = array();
				foreach ($array as $key=>$value) 
					if (! in_array($key, array("chromosome","start_pos","end_pos","ref","alt"))) {
						if (strtoupper($key) == "STUDY") {
							if (substr($value, 0, 4) == "ICGC") {
								$var_icgc = VarICGC::findVar($chr, $start_pos, $end_pos, $ref_base, $alt_base);
								if ($var_icgc != null) {
									$var_icgc->icgc_id = ltrim($var_icgc->icgc_id, "MU");
									$value = "MU_".$var_icgc->icgc_id;	
								}

							}
							$value = $this->getReportedSampleLink($value);					
						}
						$row_value[] = $value;
					}
				$data[] = $row_value;
			}
			else 
				foreach ($array as $key=>$value) 
					if (! in_array($key, array("chromosome","start_pos","end_pos","ref","alt")))
						$data[] = array($key, $value);			
		}
		return json_encode(array("data" => $data, "columns" => $columns));
	}

	public function getReportedSampleLink($value) {
		preg_match("/(.*)_(.*)/", $value, $matches);
		if (count($matches) == 3) {
			$type = $matches[1];
			$id = $matches[2];
		}
		if ($type == "TCGA")
			return $value;
		if ($type == "MU") {			
			return "<a target='_blanck' href=https://dcc.icgc.org/mutations/MU$id>$value</a>";
		}
		if ($type == "UVM")
			return "<a target='_blanck' href=https://tcga-data.nci.nih.gov/tcga/tcgaCancerDetails.jsp?diseaseType=UVM&diseaseName=Uveal%20Melanoma/$value'>$value</a>";
		return "<a target='_blanck' href=http://www.ncbi.nlm.nih.gov/pubmed/$id>$value</a>";

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

	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function prepareVarDataOld($variance) {
		foreach ($variance as $var) {
			$genes = $var->gene_refgene;
			$genes = explode(',',str_replace('"', '', $genes));
			$var->gene_refgene = "";
			$var->view_igv = "";
			if (file_exists(public_path().'/data/bams/'.$var->samplename.'.bam')) {
				$var->view_igv = "<a href=http://localhost:60151/load?file=".url('/data/bams/'.$var->samplename.'.bam').$var->view_igv.">IGV</a>";
			}
			foreach ($genes as $gene) {
				//$var->view_igv = "<a target='_blank' href=http://localhost:60151/load?file=".url('/getBam/'.$var->samplename.'.bam').$var->view_igv.">IGV</a>";
				$var->gene_refgene .= "<a target='_blank' href = http://fr-s-ccr-cbio-d.ncifcrf.gov:8080/cbioportal/cross_cancer.do?cancer_study_list=&cancer_study_id=all&data_priority=0&case_ids=&gene_set_choice=user-defined-list&gene_list=".$gene."&clinical_param_selection=null&tab_index=tab_visualize&Action=Submit#crosscancer/overview/0/".$gene."/sarc_mskcc%2Csarc_tcga%2Cacc_tcga%2Cchol_jhu_2013%2Cchol_nccs_2013%2Cchol_nus_2012%2Cblca_bgi%2Cblca_mskcc_solit_2012%2Cblca_mskcc_solit_2014%2Cblca_tcga%2Cblca_tcga_pub%2Cmm_broad%2Ccoadread_genentech%2Ccoadread_mskcc%2Ccoadread_tcga%2Ccoadread_tcga_pub%2Clgg_tcga%2Cpcpg_tcga%2Cbrca_bccrc%2Cbrca_bccrc_xenograft_2014%2Cbrca_broad%2Cbrca_sanger%2Cbrca_tcga%2Cbrca_tcga_pub%2Ccesc_tcga%2Cescc_icgc%2Chnsc_broad%2Chnsc_jhu%2Chnsc_tcga%2Chnsc_tcga_pub%2Cnpc_nusingapore%2Clihc_amc_prv%2Clihc_riken%2Clihc_tcga%2Csclc_clcgp%2Csclc_jhu%2Ccellline_ccle_broad%2Ccellline_nci60%2Cpaad_icgc%2Cpaad_tcga%2Cprad_broad%2Cprad_broad_2013%2Cprad_mich%2Cprad_mskcc%2Cprad_mskcc_2014%2Cprad_mskcc_cheny1_organoids_2014%2Cprad_su2c_2015%2Cprad_tcga%2Cprad_tcga_pub%2Cthca_tcga%2Cthca_tcga_pub%2Cucec_tcga%2Cucec_tcga_pub%2Claml_tcga%2Claml_tcga_pub%2Cmbl_broad_2012%2Cmbl_icgc%2Cmbl_pcgp%2Cmpnst_mskcc%2Cesca_broad%2Cesca_tcga%2Cstad_pfizer_uhongkong%2Cstad_tcga%2Cstad_tcga_pub%2Cstad_uhongkong%2Cstad_utokyo%2Cacyc_mskcc%2Ckirc_bgi%2Ckirc_tcga%2Ckirc_tcga_pub%2Cluad_broad%2Cluad_tcga%2Cluad_tcga_pub%2Cluad_tsp%2Clusc_tcga%2Clusc_tcga_pub%2Cscco_mskcc%2Cskcm_broad%2Cskcm_broad_dfarber%2Cskcm_tcga%2Cskcm_yale%2Cucs_tcga%2Cgbm_tcga%2Cgbm_tcga_pub%2Cgbm_tcga_pub2013%2Ckirp_tcga%2Ckich_tcga%2Ckich_tcga_pub%2Cdlbc_tcga%2Cov_tcga%2Cov_tcga_pub>".$gene."</a>,";
			}
			$var->gene_refgene = rtrim($var->gene_refgene, ",");

			if (isset($var->docm_pmid)) {
				$var->docm_pmid = '<a target="_blank" href="http://docm.genome.wustl.edu/variants/'.$var->docm_pmid.'">DOCM</a>';
			}
			if ($var->mycg_link != "-1") {
				$var->mycg_link = '<a target="_blank" href="http://'.$var->mycg_link.'">My cancer genome</a>';
			}
			if ($var->snp138 != "-1") {
				$var->snp138 = "<a target='_blank' href=http://www.ncbi.nlm.nih.gov/SNP/snp_ref.cgi?rs=".trim($var->snp138, "rs").">".trim($var->snp138, "rs")."</a>";
			}
			if ($var->cosmic70 != "-1" && $var->cosmic70 != "NA") {
				$cosmics = explode(',',str_replace('"', '', $var->cosmic70));
				$cosmic = $cosmics[0];
				$cosmic = str_replace('ID=COSM', "", $cosmic);
				$var->cosmic70 = "<a target='_blank' href=http://grch37-cancer.sanger.ac.uk/cosmic/mutation/overview?id=".$cosmic.">".$cosmic."</a>";
			}

		}
		
		$var_cols = DB::table('var_annotation_col')->get();
		$col_groups = array();
		$col_groups['Default'][] = 0;
		$i = 1;
		foreach ($var_cols as $var_col) {
			$col_groups[$var_col->group_name][] = $i++;
		}

		list($columns, $data) = $this->getDataTableJson($variance, array());
		for ($i=0;$i<count($data);$i++) {
			for ($j=0;$j<count($data[$i]);$j++) {
				if ($data[$i][$j] == "-1" || $data[$i][$j] == "NA" || $data[$i][$j] == "-" || $data[$i][$j] == ".") {
					$data[$i][$j] = "";
				}
			}
				
		}
		return array($data, $columns, $col_groups);
	}	
	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function getAllSamples() {
		if (Cache::has('var_samples')) {
			$samples = Cache::get('var_samples');
		} else {
			//$sql = "select distinct diagnosis, samplename, count(samplename) as cnt from var_annotation a, sample_annotation_hiseq b, sample_annotation_biomaterial c where a.sampleName=b.sample_id and b.biomaterial_id=c.biomaterial_id group by diagnosis, samplename";
			$sql = "select distinct diagnosis, samplename, count(samplename) as cnt from var_annotation group by diagnosis, samplename";
			$diag_rows = DB::select($sql);
			$samples = array();
			foreach ($diag_rows as $diag) {
				$samples[$diag->diagnosis][$diag->samplename] = $diag->cnt;
			}
			Cache::forever('var_samples', $samples);
		}
		return $samples;

	}

	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	public function getVarSample($sample_id) {
		if (Cache::has('var_samples')) {
			$samples = Cache::get('var_samples');
		} else {
			$sql = "select distinct diagnosis, samplename, count(samplename) as cnt from var_annotation a, sample_annotation_hiseq b, sample_annotation_biomaterial c where a.sampleName=b.sample_id and b.biomaterial_id=c.biomaterial_id group by diagnosis, samplename";
			$diag_rows = DB::select($sql);
			$samples = array();
			foreach ($diag_rows as $diag) {
				$samples[$diag->diagnosis][$diag->samplename] = $diag->cnt;
			}
			Cache::forever('var_samples', $samples);
		}
		return $samples;

	}

	public function getMutations($sample_id, $gene_id, $type) {
		$var = VarAnnotation::getAnnotationBySampleGene($sample_id, $gene_id);
		return $var->getMutations($type);
	}
	

	public function getPfamDomains($symbol) {
		$gene = Gene::getGene($symbol);
		return $gene->getPfamDomains();
	}

	public function viewMutationPlot($sample_id, $gene_id, $type) {
		$title = "Sample mutations";
		if ($type == "ref") 
			$title = "Reference mutations";
		return View::make('pages/viewMutationPlot', ["sample_id"=>$sample_id, "gene_id"=>$gene_id, "type"=>$type, "title"=>$title]);
	}

	public function getMutationPlotData($sample_id, $gene_id, $type) {
		list($domain, $domain_range) = $this->getPfamDomains($gene_id);
		list($mutations, $mutation_range) = $this->getMutations($sample_id, $gene_id, $type);
		$margin = 50;
		$min_coord = max(min($domain_range["start_pos"], $mutation_range["start_pos"]) - $margin, 0);
		$max_coord = max($domain_range["end_pos"], $mutation_range["end_pos"]) + $margin;
		return json_encode(array("domain"=>$domain, "mutation"=>$mutations, "min_coord" => $min_coord, "max_coord" => $max_coord));
	}
}
