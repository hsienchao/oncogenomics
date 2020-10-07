<?php

use App;
use Log;

class ExpressionController extends BaseController {

   
   ############## View Heatmap ###########################################################################################
   public function heatmap($sid)
   {
      $uid       = Sentry::getUser()->id;
      $genelist  = '';
      $smpllist  = '';
      $page      = 1;
      $gnum      = 500;
      $storage   = url();
      return View::make('pages/heatmap', ['sid'=>$sid, 'uid'=>$uid, 'genelist'=>$genelist, 'smpllist'=>$smpllist, 'page'=>$page, 'gnum'=>$gnum, 'storage'=>url()]);
   }

   public function heatmapSettings()
   {
      $genelist  = Input::get('genelist');
      $smpllist  = Input::get('smpllist');
      $page      = Input::get('page');
      $gnum      = Input::get('gnum');
      $sid       = Input::get('sid');
      $uid       = Sentry::getUser()->id;
      $fsettings = fopen(public_path()."/expression/pub/".$uid.".".$sid.".settings", "w");
      fwrite($fsettings, "#uid\n".$uid);
      fwrite($fsettings, "\n#sid\n".$sid);
      fwrite($fsettings, "\n#genes\n".$genelist);
      fwrite($fsettings, "\n#smpls\n".$smpllist);
      $spath = public_path();

      //=====================Prepare the data 
      $fsql = fopen("$spath/expression/pub/$sid.sql", "w");

      //=========columns::samples
      $Dsmpls = [];
      if ($smpllist=='') {
         exec("cp $spath/expression/pub/$sid.cols.tsv $spath/expression/pub/$sid.cols.tsv.$uid");      
         $fp  = fopen("$spath/expression/pub/$sid.cols.tsv.$uid", "r");
         while(!feof($fp)) { 
            $line = fgets($fp);
            $ss   = explode("\t", $line);
            $Dsmpls[$ss[0]]=$ss[0];
         }    
      }
      else{ 
         $fp  = fopen("$spath/expression/pub/$sid.cols.tsv.$uid", "w");
         fwrite($fp , "sample\tgroup\ttissue\tdiagnosis\n");
         $smpls= preg_split('/\s+/', $smpllist);
         foreach($smpls as $smpl) {
            $ss = "
               select sample_id, s.group_name, tissue, a.N_or_T
               from study_groups       s 
               join group_samples      g on s.id=g.group_id and s.study_id=$sid
               join studies            u on u.id=s.study_id
               join sample_annotation_microarray a on a.sample_name=g.sample_id
               where sample_id like '%".$smpl."%'
               ";    
            fwrite($fsql, $ss);  
            $Data = DB::select($ss);
            foreach($Data as $dd){
               $rr='';
               foreach($dd as $key=>$value) {
                  $rr .= "$value\t";
                  if($key=='sample_id') { 
                     $Dsmpls[$value]=$value;
                  }
               }
               $rr=trim($rr, "\t");   
               fwrite($fp , $rr."\n");
            }
         }
         fclose($fp);
      }
      //=========rows::genes  
      $Dgenes=[];          
      if ($genelist=='') {
         exec("cp $spath/expression/pub/$sid.rows.tsv $spath/expression/pub/$sid.rows.tsv.$uid");      
         $fp  = fopen("$spath/expression/pub/$sid.rows.tsv.$uid", "r");
         while(!feof($fp)) { 
            $line = fgets($fp);
            $ss   = explode("\t", $line);
            $Dgenes[$ss[0]]=$ss[0];
         }    
      }
      else{ 
         $fp   = fopen(public_path()."/expression/pub/$sid.rows.tsv.$uid", "w");
         fwrite($fp, "reporter\tsymbol\tchrom:start\n");
         $genes= preg_split('/\s+/', $genelist);
         foreach($genes as $gene) {
            $ss = " 
               select probeset, \"symbol\", \"chrom_start\" 
               from (select distinct probeset from expression_microarray) e 
               join affy_sref s on e.probeset=s.\"affy_id\"
               where \"symbol\" like '%".$gene."%' or probeset like '%".$gene."%'
               ";    
            fwrite($fsql, $ss);  
            $Data = DB::select($ss);
            foreach($Data as $dd){
               $flag=1;
               $rr='';   
               foreach($dd as $key=>$value) {
                  $rr .= "$value\t";
                  if($key=='probeset') { 
                     if(array_key_exists($value, $Dgenes)) { $flag=0;}
                     $Dgenes[$value]=$value;
                  }
               }
               $rr=trim($rr, "\t");   
               if ($flag==1) { 
                  fwrite($fp , $rr."\n");
               }
            }
         }
         fclose($fp);
      }
      //=========data::expressions
      $fp   = fopen(public_path()."/expression/pub/$sid.data.tsv.$uid", "w");
      fwrite($fp, "sample\treporter\tlog2\n");
      $ss = " 
            select sample_name, e.probeset, log2 
            from expression_microarray e
            where probeset in ('".implode("','", $Dgenes)."') and sample_name in ('".implode("','", $Dsmpls)."')
            ";
      fwrite($fsql, $ss);  
      $Data = DB::select($ss);
      foreach($Data as $dd){
         $rr='';
         foreach($dd as $key=>$value) {
            $rr .= "$value\t";
         }
         $rr=trim($rr, "\t");   
         fwrite($fp, $rr."\n");
      }
      fclose($fp);

      fclose($fsql);

      #exec('python '.storage_path().'/expression/heatmap.filter.py -u '.$uid." -s ".$sid );
      return View::make('pages/heatmap', ['sid'=>$sid, 'uid'=>$uid, 'genelist'=>$genelist, 'smpllist'=>$smpllist, 'page'=>$page, 'gnum'=>$gnum, 'storage'=>url()]);
      return Redirect::to('heatmap/'.$sid);
   }

   #==========Prepare Create Study=========================        
   public function prepareCreateStudy()
   {
      
      list($json_tree, $sample_list) = $this->prepareStudy();
      return View::make('pages/editStudy', ['json_tree'=>$json_tree, 'sample_js' => $sample_list, 'study_json' => '', 'mode' => 'create']);

   }

   #==========Prepare Edit Study=========================
   public function prepareEditStudy($sid)
   {
      
      $study = Studies::find($sid);
      $study_groups = Study_group::where('study_id', '=', $sid)->get();
      $study->groups = $study_groups;
      foreach ($study_groups as $study_group) {
             $group_samples = DB::table('group_samples')->where('group_id', '=', $study_group->id)->get();
             $study_group->samples = $group_samples;
      }
      list($json_tree, $sample_list) = $this->prepareStudy();
      $json = json_encode($study);
      return View::make('pages/editStudy', ['json_tree'=>$json_tree, 'sample_js' => $sample_list, 'study_json' => $json, 'mode' => 'edit']);      
   }

   public function prepareStudy() {
      $tree = new stdClass();
      $tree->types = array('default'=>new stdClass(), 'Microarray'=>new stdClass(), 'SOLID' => new stdClass());
      //Uber
      $rs_diag = DB::select("select tissue as diagnosis, N_or_T as tissue_type, SAMPLE_NAME from SAMPLE_ANNOTATION_MICROARRAY order by tissue");
      $tissue_type = [];
      $diags = [];
      foreach ($rs_diag as $row) {
         if (!isset($tissue_type[$row->tissue_type])) {
            $tissue_type[$row->tissue_type] = 0;
         }
         $diags[$row->tissue_type][$row->diagnosis][] = $row->sample_name;
         $tissue_type[$row->tissue_type]++;
      }
      #list($microarray_diag_json, $sample_list_microarray, $diag_idx) = $this->getTreeJSON($tissue_type, $diags, 0, "Uber");
      list($microarray_json, $sample_list_microarray, $diag_idx) = $this->getMicroarryTreeData($tissue_type, $diags, 0, "Uber");
      $tree->types['Uber'] = new stdClass();
      $sample_list = "samples = [];".$sample_list_microarray;

      //Hiseq
      $rs_diag = DB::select("select diagnosis, hiseq_rnaseq_library, tissue_type, library_id, fcid from SAMPLE_ANNOTATION_BIOMATERIAL a, SAMPLE_ANNOTATION_HISEQ b where a.BIOMATERIAL_ID=b.BIOMATERIAL_ID and b.HISEQ_RNASEQ_LIBRARY <> '-' and a.diagnosis <> '-' order by diagnosis");
      $library_type = [];
      $tissue_type = [];      
      $diags = [];
      foreach ($rs_diag as $row) {
         $lib_type = null;
         if ($row->hiseq_rnaseq_library == 'polyA') $lib_type = 'PolyA';
         if ($row->hiseq_rnaseq_library == 'ribozero stranded' || $row->hiseq_rnaseq_library == 'ribominus') $lib_type = 'Ribo';
         if ($lib_type == null) continue;
         if (!isset($library_type[$lib_type])) {
            $library_type[$lib_type] = 0;
         }
         if (!isset($tissue_type[$lib_type][$row->tissue_type])) {
            $tissue_type[$lib_type][$row->tissue_type] = 0;
         }
         $diags[$lib_type][$row->tissue_type][$row->diagnosis][] = $row->library_id."_".$row->fcid;            
         $library_type[$lib_type]++;
         $tissue_type[$lib_type][$row->tissue_type]++;
      }
      
      #list($hiseq_diag_json, $sample_list_hiseq, $diag_idx) = $this->getTreeJSON($tissue_type, $diags, $diag_idx, "Illumina");
      list($hiseq_json, $sample_list_hiseq, $diag_idx) = $this->getIlluminaTreeData($library_type, $tissue_type, $diags, $diag_idx, "Illumina");
      $tree->types['Illumina_PolyA'] = new stdClass();
      $tree->types['Illumina_Ribo'] = new stdClass();
      $sample_list .= $sample_list_hiseq;
      
      $tree->plugins = array('types');
      
      $microarray_tree = new stdClass();
      $microarray_tree->text = 'Microarray';
      $microarray_tree->type = 'Microarray';
      $microarray_tree->state = array('opened'=>true);
      $uber_tree = new stdClass();
      $uber_tree->text = 'Uber';
      $uber_tree->id = 'Uber';
      $uber_tree->type = 'Uber';
      $uber_tree->state = array('opened'=>true);
      $uber_tree->children = $microarray_json;
      $microarray_tree->children = array($uber_tree);      
      $rnaseq_tree = new stdClass();
      $rnaseq_tree->text = 'RNAseq';
      $rnaseq_tree->type = 'RNAseq';
      $rnaseq_tree->state = array('opened'=>true);
      $illumina_tree = new stdClass();
      $illumina_tree->text = 'Illumina';
      $illumina_tree->id = 'Illumina';
      $illumina_tree->type = 'Illumina';
      $illumina_tree->state = array('opened'=>true);
      $illumina_tree->children = $hiseq_json;
      $rnaseq_tree->children = array($illumina_tree);
      $solid_tree = array('text' => 'SOLID', 'id' => 'SOLID', 'type' => 'SOLID');
      $tree_data = array(array('text'=>'All Samples','id'=>'root','state'=>array('opened'=>true),'children' => array($microarray_tree, $rnaseq_tree, $solid_tree)));
      $tree->core = array('data' => $tree_data);
      $json = json_encode($tree);
      return array($json, $sample_list);
   }

   public function getIlluminaTreeData($library_type, $tissue_type, $diags, $diag_idx, $seq_type) {
      $tree_data = array();
      $sample_js = "";
      foreach ($library_type as $library_id=>$library_cnt) {
         $library = new stdClass(); 
         $library_children = array();
         $platform_type = $seq_type."_".$library_id;
         foreach ($tissue_type[$library_id] as $tissue_id=>$tissue_cnt) {
            $tissue = new stdClass(); 
            $tissue_children = array();            
            foreach ($diags[$library_id][$tissue_id] as $diag=>$samples) {
               $diagnosis = new stdClass(); 
               $sample_idx = 0;
               $sample_js .= "samples[$diag_idx] = [];";
               foreach ($samples as $sample) {
                  $sample_js .= "samples[$diag_idx][$sample_idx] = '$sample';";
                  $sample_idx++;
               }
               array_push($tissue_children, array('id'=>"diag_$diag_idx",'type' => $platform_type,'text' => $diag."(".count($samples).")"));
               $diag_idx++;
            }
            $tissue->text = $tissue_id."(".$tissue_cnt.")";
            $tissue->type = $platform_type;
            $tissue->children = $tissue_children;
            array_push($library_children, $tissue);
         }
         $library->id = $platform_type;
         $library->text = $library_id."(".$library_cnt.")";
         $library->type = $platform_type;
         $library->children = $library_children;
         array_push($tree_data, $library);
      }
      return array($tree_data, $sample_js, $diag_idx);
   }

   public function getMicroarryTreeData($tissue_type, $diags, $diag_idx, $platform_type) {
      $tree_data = array();
      $sample_js = "";
      foreach ($tissue_type as $tissue_id=>$tissue_cnt) {
         $tissue = new stdClass(); 
         $tissue_children = array();
         foreach ($diags[$tissue_id] as $diag=>$samples) {
               $diagnosis = new stdClass(); 
               $sample_idx = 0;
               $sample_js .= "samples[$diag_idx] = [];";
               foreach ($samples as $sample) {
                  $sample_js .= "samples[$diag_idx][$sample_idx] = '$sample';";
                  $sample_idx++;
               }
               array_push($tissue_children, array('id'=>"diag_$diag_idx",'type' => $platform_type,'text' => $diag."(".count($samples).")"));
               $diag_idx++;
         }         
         $tissue->text = $tissue_id."(".$tissue_cnt.")";
         $tissue->type = $platform_type;
         $tissue->children = $tissue_children;
         array_push($tree_data, $tissue);
      }
      return array($tree_data, $sample_js, $diag_idx);
   }
  

   public function deleteStudy($sid) {      
      try {
         DB::beginTransaction();
         $study = Studies::find($sid);
         $study->delete();
         $study_groups = Study_group::where('study_id', '=', $sid)->get();
         foreach ($study_groups as $study_group) {
             DB::table('group_samples')->where('group_id', '=', $study_group->id)->delete();
             $study_group->delete();
         }
         DB::table('study_genes')->where('study_id', '=', $sid)->delete();
         DB::commit();
      } catch (\PDOException $e) { 
         return $e->getMessage();
         DB::rollBack();           
      }
      return Redirect::to('/viewStudy');
   }

 #==========save Study=========================        
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
             $study_groups = Study_group::where('study_id', '=', $study->id)->get();
             foreach ($study_groups as $study_group) {
                      DB::table('group_samples')->where('group_id', '=', $study_group->id)->delete();
                      $study_group->delete();
             }
             DB::table('study_genes')->where('study_id', '=', $study->id)->delete();
         }
         if (Sentry::getUser() == null) {
             //return Redirect::to('/login');
         }
         $user_study->user_id = Sentry::getUser()->id;
         $user_study->study_name = $study->name;
         $user_study->study_type = $study->study_type;
         $user_study->study_desc = $study->description;
         $user_study->is_public = ($study->ispublic=="true");
         $user_study->status = 0;
         $user_study->save();      
         $sid=$user_study->id;
         foreach ($study->groups as $group) {
            $study_group = new Study_group;
            $study_group->study_id = $user_study->id;
            $study_group->group_name = $group->id;
            $study_group->group_type = "";
            if (substr($study_group->group_name, 0, 6) == "Tumor_") {
                $study_group->group_type = "Tumor";
            }
            if (substr($study_group->group_name, 0, 7) == "Normal_") {
                $study_group->group_type = "Normal";
            }  
            $study_group->save();
            Log::info("group id: ".$study_group->id);
            foreach ($group->samples as $sample) {
               Log::info("Sample: ".$sample->id);
               $group_sample = new Group_samples;
               $group_sample->group_id = $study_group->id;
               $group_sample->sample_id = $sample->id;
               $group_id = $study_group->id;
               $sample_id = $sample->id;
               DB::table('group_samples')->insert(['group_id' => $group_id, 'sample_id' => $sample_id]); 
               Log::info($sample->id);
            }      
         }
         DB::commit();
      } catch (\PDOException $e) { 
         return $e->getMessage();
         DB::rollBack();           
      }
    
      //=====================Prepare the data 
      $uid  = Sentry::getUser()->id;
      $spath= public_path();
      $Data = DB::select("select study_type from studies where id=$sid");
      foreach($Data as $dd){ foreach($dd as $key=>$value) {$type=$value;}}    
      $fsql = fopen("$spath/data/pub/$sid.sql",       "w");
      $fp   = fopen("$spath/data/pub/$sid.cols",      "w");

      //=====================columns::samples
      if($type=="Uber") {
         $ss = "
            select g.sample_id, s.group_name, a.tissue, a.N_or_T
            from studies                      d
            join study_groups                 s on d.id=s.study_id and d.id=$sid
            join group_samples                g on s.id=g.group_id 
            join sample_annotation_microarray a on a.sample_name=g.sample_id
            ";    
      } elseif (substr($type,0,8) =="Illumina"){
         $ss = "
            select g.sample_id, s.group_name, b.tissue_type, a.diagnosis
            from studies                       d
            join study_groups                  s on d.id=s.study_id and d.id=$sid
            join group_samples                 g on s.id=g.group_id 
            join sample_annotation             a on a.sample_id=g.sample_id
            join sample_annotation_biomaterial b on a.biomaterial_id=b.biomaterial_id
            ";    
      }
      fwrite($fsql, $ss);  
      fwrite($fp , "sample\tgroup\ttissue\tdiagnosis\n");
      $Data = DB::select($ss);
      foreach($Data as $dd){
         $rr='';
         foreach($dd as $key=>$value) {
            $rr .= "$value\t";
            if($key=='sample_id') { 
               $Dsmpls[$value]=$value;
            }
         }
         $rr=trim($rr, "\t");   
         fwrite($fp , $rr."\n");
      }
      fclose($fp);
      //=====================rows::genes 
      echo $type;
      $Dgenes=[];          
      if($type=="Uber") {
         $ss = ' 
            select distinct probeset, symbol, chrom, "start" 
            from expression_microarray e 
            join affy_sref             s on e.probeset=s.affy_id   
            ';
      } elseif (substr($type,0,8) =="Illumina"){
         $ss = ' 
            select distinct a.GENE, symbol, chrom, "start" 
            from expression_rsu_gene e 
            join gene_ensembl        a on e.gene=a.gene   
            ';
      }
      fwrite($fsql, $ss);  
      $fp   = fopen("$spath/data/pub/$sid.rows", "w");
      fwrite($fp, "reporter\tsymbol\tchrom:start\n");
      $Data = DB::select($ss);
      foreach($Data as $dd){
         $rr='';
         foreach($dd as $key=>$value) {
            if($key=="chrom"){ $rr .= "$value:";}
            else {  $rr .= "$value\t"; }
            if($key=='probeset') { 
               $Dgenes[$value]=$value;
            }
         }
         $rr=trim($rr, "\t");   
         fwrite($fp, $rr."\n");
      }
      fclose($fp);

      //=========data::expressions
      $Dgenes=array_slice($Dgenes, 0, 100);
      if($type=="Uber") {
         $ss = " 
            select sample_name, probeset, log2 
            from expression_microarray e
            where sample_name in (
               select sample_id 
               from group_samples g 
               join study_groups  s on s.id=g.group_id and s.study_id=$sid
            )
            ";
      } elseif (substr($type,0,8)=="Illumina"){
         $ss = " 
            select sample_name, gene, log2 
            from expression_rsu_gene e
            where sample_name in (
               select sample_id 
               from group_samples g 
               join study_groups  s on s.id=g.group_id and s.study_id=$sid
            )
            ";
      }
      fwrite($fsql, $ss);  
      $fp   = fopen("$spath/data/pub/$sid.data", "w");
      fwrite($fp, "sample\treporter\tlog2\n");
      if(1==0) { 
         $Data = DB::select($ss);
         foreach($Data as $dd){
            $rr='';
            foreach($dd as $key=>$value) {
               $rr .= "$value\t";
            }
            $rr=trim($rr, "\t");   
            fwrite($fp, $rr."\n");
            echo $rr;
            return;
         }
      }
      fclose($fp);

      fclose($fsql);

       
      #===get data in format
      $jdata    = OncoController::JData('studies');
      #===get colnames in format
      $jcolnames= OncoController::JColumns('studies'); 


      // run background process to prepare the expression file and calculate mean and std
      $db = Config::get("database.default");
      $host = Config::get("database.connections.$db.host");
      $dbname = Config::get("database.connections.$db.database");
      $u = Config::get("database.connections.$db.username");
      $p = Config::get("database.connections.$db.password");
      $cmd = app_path()."/scripts/post_save_study.pl -h $host -i $dbname -u $u -p $p -s $sid -o ".public_path()."/expression &";
      pclose(popen($cmd, "r"));      
     
      return View::make('pages/viewsamples', ['json'=>$jdata, 'colnames'=>$jcolnames]);


   }


   #==========View Gene Details=========================        
   public function viewGeneDetails($sid, $gid) {
      $sql = "select group_name, sample_id from STUDY_GROUPS g, GROUP_SAMPLES s where STUDY_ID=$sid and g.ID=s.GROUP_ID order by GROUP_NAME";
      $rs = DB::select($sql);
      $groups = [];
      foreach ($rs as $row) {
         $groups[$row->group_name][] = $row->sample_id;
      }
 
      $data_file = storage_path()."/expression/rnaseq.27.data.tsv.247X2000";

      $gene_data_str = shell_exec("grep $gid $data_file");
      $gene_data = explode("\n", $gene_data_str);
      $sample_exp = [];
      foreach ($gene_data as $gene_data_entry) {
         $sample_exps = explode("\t", $gene_data_entry);
         if (isset($sample_exps[2])) {
            $sample_exp[$sample_exps[0]] = $sample_exps[2];
         }
      }       
      $xlabels = "";
      $values = "";
      foreach ($groups as $group_name=>$samples) { 
         $xlabels .= "'$group_name',";
         $group_exp = [];
         foreach ($samples as $sample) {
            if (isset($sample_exp[$sample])) {
               $group_exp[] = $sample_exp[$sample];                   
            }
         }
         $values .= "[".min($group_exp).",".$this->get_percentile(25, $group_exp).",".$this->get_percentile(50, $group_exp).",".$this->get_percentile(75, $group_exp).",".max($group_exp)."],";
         #echo $group_name."=>".$values."<BR>";
           
      } 
      rtrim($xlabels, ",");
      rtrim($values, ",");

      $sql = "select * from gene_annotation where ID ='$gid' order by attr_name";
      $anno_rs = DB::select($sql);
      $basic_info;
      $drug_info;
      $ctd_disease_infos;
      $go_bp_infos;
      $go_cc_infos;
      $go_mf_infos;
      $symbol;
      foreach ($anno_rs as $row) {
         if ($row->attr_name == 'CTD Disease Info') {
            $ctd_disease_infos[] = $this->parse_annotation($row->attr_value);
         }
         if ($row->attr_name == 'GAD Disease Info') {
            $gad_disease_infos[] = $this->parse_annotation($row->attr_value);
         }
         if ($row->attr_name == 'GO - Biological Process') {
            $go_bp_infos[] = $this->parse_annotation($row->attr_value);
         }
         if ($row->attr_name == 'GO - Cellular Component') {
            $go_cc_infos[] = $this->parse_annotation($row->attr_value);
         }
         if ($row->attr_name == 'GO - Molecular Function') {
            $go_mf_infos[] = $this->parse_annotation($row->attr_value);
         }
         if ($row->attr_name == 'Gene Symbol') {
            $symbol = $row->attr_value;
         }
      }  
      list($ctd_headers, $ctd_disease_table) = $this->hash2table($ctd_disease_infos);
      list($gad_headers, $gad_disease_table) = $this->hash2table($gad_disease_infos);
      list($go_bp_headers, $go_bp_table) = $this->hash2table($go_bp_infos);
      list($go_cc_headers, $go_cc_table) = $this->hash2table($go_cc_infos);
      list($go_mf_headers, $go_mf_table) = $this->hash2table($go_mf_infos);
      $ctd_json_data = $this->array2jsontable($ctd_disease_table);
      $ctd_json_cols = $this->get_json_columns($ctd_headers);
      $gad_json_data = $this->array2jsontable($gad_disease_table);
      $gad_json_cols = $this->get_json_columns($gad_headers);
      $go_bp_data = $this->array2jsontable($go_bp_table);
      $go_bp_cols = $this->get_json_columns($go_bp_headers);
      $go_cc_data = $this->array2jsontable($go_cc_table);
      $go_cc_cols = $this->get_json_columns($go_cc_headers);
      $go_mf_data = $this->array2jsontable($go_mf_table);
      $go_mf_cols = $this->get_json_columns($go_mf_headers);

      return View::make('pages/gene_detail', ['symbol'=>$symbol, 'xlabels'=>$xlabels, 'values' => $values, 'ctd_disease_data' => $ctd_json_data, 'ctd_disease_cols' => $ctd_json_cols, 'gad_disease_data' => $gad_json_data, 'gad_disease_cols' => $gad_json_cols, 'go_bp_data' => $go_bp_data, 'go_bp_cols' => $go_bp_cols, 'go_cc_data' => $go_cc_data, 'go_cc_cols' => $go_cc_cols, 'go_mf_data' => $go_mf_data, 'go_mf_cols' => $go_mf_cols]);
   }

   function checkStudyExists($study_name) {
      $studies = Studies::where('study_name', '=', $study_name)->get();
      return (count($studies)>0)? 'true':'false';
   }

   function parse_annotation($input) {
      $output = [];
      $output['ID'] = explode(' ', $input)[0];
      preg_match_all('/\\[(.*?)\\]/s', $input, $matches);
      foreach ($matches[1] as $match) {
         $outputs = explode(':', $match);
         $output[$outputs[0]] = $outputs[1];
      }
      return $output;     
   }

   public function get_json_columns ($input) {

      $j_col ='[';
      foreach($input as $header) {
         $j_col .= '{"title":"'.$header.'"},';
      }
      $j_col .=']';
      return $j_col;
   }
   
   function array2jsontable($input) {
      $output ='[';
      foreach ($input as $row) {
         $output .='[';
         foreach ($row as $value) {
             $output .='["'.$value.'"],';
         }
         $output .='],';   
      }
      $output .=']';
      return $output;
   }

   #convert array of hash to array of array (table)
   function hash2table($input) {
      $header_idx = [];
      $headers = [];
      $idx = 0;  
      foreach ($input as $row) {
           foreach ($row as $key => $value) {
               if (!isset($header_idx[$key])) {
                   $headers[$idx] = $key;
                   $header_idx[$key] = $idx++;
               }
           }
      }
  
      for ($i=0;$i<count($input);$i++) {
           for ($j=0;$j<count($header_idx);$j++) {
                $output[$i][$j] = "";
           }
      }

      $i=0;
      foreach ($input as $row) {
           foreach ($row as $key => $value) {
               $j = $header_idx[$key];
               $output[$i][$j] = $value;               
           }
           $i++;  
      } 
      return array($headers, $output);
   }

   function get_percentile($percentile, $array) {
      sort($array);
      $index = ($percentile/100) * count($array);
      if (floor($index) == $index) {
         $result = ($array[$index-1] + $array[$index])/2;
      }
      else {
        $result = $array[floor($index)];
      }
      return $result;
   }    

   public function downloadExampleExpression($type)
   {
      
      if($type=="ENSEMBL")
         $pathToFile=app_path()."/storage/data/gene_ens_upload_example.txt";
      else
         $pathToFile=app_path()."/storage/data/gene_ucsc_upload_example.txt";

      return Response::download($pathToFile);

   }
}

