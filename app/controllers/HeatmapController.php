<?php

use App;
use Log;
use App\Models;
use Input;
use Validator;
use Redirect;
use Request;
use Session;

class HeatmapController extends BaseController {

   
   ############## Studies page ###########################################################
   public function studies()
   {

      ###########################################################
      #################  Studies Info  ##########################
      ###########################################################
      #--- get data in format
      $jStudies = HeatmapController::JData('studies');
      #--- get colnames in format
      $jStudiesCols= HeatmapController::JColumns('studies');   

      return View::make('pages/studies', ['studies'=>$jStudies, 'studiescols'=>$jStudiesCols]);

   }

   static public function JData($tid) {
      #===get data from model Sample_Annotation
      $Data = DB::select('select * from '.$tid);

      #===generate the data in format for samples 
      $jData ='[';
      foreach($Data as $dd){
         $jData .='[';
         foreach($dd as $key=>$value) {
            if($value=='NA') {
               $value=$key.'.'.$value;
            }

            if($key=='id') {
               $jData .= '"'.$value.'",';
               $sid = $value;
               $jData .= '"<a href='.url("/editStudy/".$sid).'  ><img src=http://www.iconpng.com/png/mono-general/edit.png   style=\"width:8px;height:8px;\"></a>",'; 
               $jData .= '"<a href='.url("/deleteStudy/".$sid).'><img src=http://www.iconpng.com/png/mono-general/delete.png style=\"width:8px;height:8px;\"></a>",';
            }
            elseif($key=='study_desc') {
               $jData .= '"'.$value.'",';
               $jData .= '"<a href=# onclick=\"addTab(\'jH:'.$sid.'\',\''.url("/jHeatmap/".$sid).'\')\">jH</a> <a href=# onclick=\"addTab(\'cH:'.$sid.'\',\''.url("/canvasHeatmap/".$sid).'\')\">cH</a>",';
               $jData .= '"<a href=# onclick=\"addTab(\'GSEA:'.$sid.'\',\''.url("/gsea/".$sid).'\')\">GSEA</a>",';
            }
            else {
               $jData .= '"'.$value.'",';
            }
         }
         $jData .='],';
      }
      $jData .=']';

      return $jData;
   }

   static public function JColumns ($tid) {
      #===get data from model Sample_Annotation
      $Data = DB::select('select * from '.$tid);

      #===generate the data in format for column names 
      $jColnames ='[';
      foreach($Data[0] as $key=>$value) {
         $jColnames .= '{"title":"'.$key.'"},';
         if($key=='id') {
            $jColnames .= '{"title":"Edit"},';
            $jColnames .= '{"title":"Delete"},'; 
         }
         elseif($key=='study_desc') {
            $jColnames .= '{"title":"Heatmap"},';
            $jColnames .= '{"title":"GSEA"},';
         }
      }
      $jColnames .=']';
      return $jColnames;
   }



   ############## View Heatmap ###########################################################
   public function jHeatmap($sid)
   {

      ###########################################################
      #################  Studies Info  ##########################
      ###########################################################
      #--- get data in format
      $jStudies = OncoController::JData('studies');
      #--- get colnames in format
      $jStudiesCols= OncoController::JColumns('studies');   

    
      ###########################################################
      #################  heatmap Info  ##########################
      ###########################################################
      //=====================prepare settings================================ 
      //------initiate settings
      $uid                   = Sentry::getUser()->id;
      $spath                 = public_path();
      $settings              = [];
      $settings['genelist' ] = '';
      $settings['smpllist' ] = '';
      $settings['maxgene'  ] = '50';
      $settings['threshold'] = '5';
       
      //------read settings from file
      if(file_exists("$spath/data/pub/$sid.$uid.settings" )) {
         $fsettings = fopen("$spath/data/pub/$sid.$uid.settings", "r");
         $id =''; 
         $ss ='';
         while(($line=fgets($fsettings))!=false) { 
            if($line[0]=="#") {
               $settings[$id] = trim($ss, "\x00..\x1F #"); 
               $id=trim($line,"\x00..\x1F #");
               $ss='';
            }      
            else{
               $ss.=' '.trim($line,"\x00..\x1F #");
            }
         }
         fclose($fsettings);
      }
      $genelist  = $settings['genelist' ] ;
      $smpllist  = $settings['smpllist' ] ;
      $maxgene   = $settings['maxgene'  ] ;
      $threshold = $settings['threshold'] ;

      //------read settings from page
      if(Input::get('genelist' )) { $genelist  = Input::get('genelist' );}
      if(Input::get('smpllist' )) { $smpllist  = Input::get('smpllist' );}
      if(Input::get('maxgene'  )) { $maxgene   = Input::get('maxgene'  );}
      if(Input::get('threshold')) { $threshold = Input::get('threshold');}
      $flag=0; 
      if($genelist  != $settings['genelist' ] or 
         $smpllist  != $settings['smpllist' ] or 
         $threshold != $settings['threshold'] or 
         $maxgene   != $settings['maxgene'  ]
        ) { 
         $flag=1;
      }

      //------write settings to file
      $fsettings = fopen("$spath/data/pub/$sid.$uid.settings", "w");
      fwrite($fsettings, "#uid         \n".$uid        );
      fwrite($fsettings, "\n#sid       \n".$sid        );
      fwrite($fsettings, "\n#genelist  \n".$genelist   );
      fwrite($fsettings, "\n#smpllist  \n".$smpllist   );
      fwrite($fsettings, "\n#maxgene   \n".$maxgene    );
      fwrite($fsettings, "\n#threshold \n".$threshold  );
      fclose($fsettings);

      if(1==1) { 
         //=====================db table settings================================ 
         $Data = DB::select("select study_type from studies where id=$sid");
         foreach($Data as $dd){ foreach($dd as $key=>$value) {$type=$value;}}    
         if($type=="Uber") {
            $exprdb="expression_microarray";
            $annodb="affy_sref";
         } elseif ($type=="Illumina"){
            $exprdb="expression_rsu_gene";
            $annodb="anno_rsu_gene";
         }
         
         //=====================columns::samples=================================
         //-----get sample list-----
         
         $where =[];
         if ($smpllist==' ' or $smpllist=='') {
            $where[] = "rownum<1000";
         }
         else{ 
            $smpls= preg_split('/\s+/', $smpllist);
            foreach($smpls as $smpl) {
               if ($smpl!='') { $where[]= " sample_id like '%".$smpl."%' "; }
            }
         }
         $ss = "
            select distinct sample_id
            from study_groups       s 
            join group_samples      g on s.id=g.group_id and s.study_id=$sid
            join studies            u on u.id=s.study_id                     
            where exists(select sample_name from $exprdb e where g.sample_id=e.sample_name) 
            and (".implode(" or ", $where).")";
         $Data = DB::select($ss);
         $Dsmpls = [];
         foreach($Data as $dd){
            foreach($dd as $key=>$value) {
               $Dsmpls[$value]=$value;
            }
         }
         
         //-----get sample annotation-----
         if($type=="Uber") {
            $ss = "
               select g.sample_id, s.group_name, a.tissue, a.N_or_T
               from studies                      d
               join study_groups                 s on d.id=s.study_id and d.id=$sid
               join group_samples                g on s.id=g.group_id 
               join sample_annotation_microarray a on a.sample_name=g.sample_id
               where g.sample_id in ('".implode("','", $Dsmpls)."') 
               order by a.N_or_T, a.tissue, s.group_name, g.sample_id            
               ";    
         } elseif ($type=="Illumina"){
            $ss = "
               select g.sample_id, s.group_name, b.tissue_type, a.diagnosis
               from studies                       d
               join study_groups                  s on d.id=s.study_id and d.id=$sid
               join group_samples                 g on s.id=g.group_id 
               join sample_annotation             a on a.sample_id=g.sample_id
               join sample_annotation_biomaterial b on a.biomaterial_id=b.biomaterial_id
               where g.sample_id in ('".implode("','", $Dsmpls)."') 
               order by a.diagnosis, b.tissue_type, s.group_name, g.sample_id
               ";    
         }
         $Data = DB::select($ss);
         
         //-----output to column file-----
         $fp  = fopen("$spath/data/pub/$sid.$uid.cols", "w");
         fwrite($fp , "sample\tgroup\ttissue\tdiagnosis\n");
         foreach($Data as $dd){
            $ss='';
            foreach($dd as $key=>$value) {
               $ss .= "$value\t";
            }
            $ss=trim($ss, "\t");   
            fwrite($fp , $ss."\n");
         }
         fclose($fp);
         
         //=====================rows::genes=================================
         //-----get gene list-----
         $where =[];
         if (trim($genelist,"\x00..\x1F #")=='') {
            $where[] = "1=1";
         }
         else{ 
            $genes= preg_split('/\s+/', $genelist);
            foreach($genes as $gene) {
               if ($gene!='') { $where[]= " a.symbol like '%".$gene."%' "; }
            }
         }
         if($type=="Uber") {
            $ss = "
               select distinct a.affy_id
               from $annodb a 
               where exists(select e.probeset from $exprdb e where e.probeset=a.affy_id)
               and (".implode(" or ", $where).")
               ";
         } elseif ($type=="Illumina"){
            $ss = " 
               select distinct a.gene
               from $annodb a 
               where exists(select e.gene     from $exprdb e where e.gene=a.gene ) and 
               (".implode(" or ", $where).")
               ";
         }
         $Data = DB::select($ss);
         $Dgenes=[];          
         foreach($Data as $dd){
            foreach($dd as $key=>$value) {
               $Dgenes[$value]=$value;
            }
         }
         $Dgenes=array_slice($Dgenes, 0, $maxgene);
           
         //-----get gene annotation-----
         if($type=="Uber") {
            $ss = "
               select distinct e.probeset, a.symbol, a.chrom, a.\"start\" 
               from $exprdb e 
               left join $annodb a on a.affy_id=e.probeset
               where e.probeset in ('".implode("','", $Dgenes)."') 
               and length(chrom)<3
               order by a.chrom, a.\"start\", a.symbol, e.probeset 
               ";
         } elseif ($type=="Illumina"){
            $ss = " 
               select distinct e.gene, a.symbol, chrom, \"start\" 
               from $exprdb e
               left join $annodb a on e.gene=a.gene
               where e.gene in ('".implode("','", $Dgenes)."') 
               and length(chrom)<3
               order by chrom, \"start\", a.symbol, e.gene 
               ";
         }
         $Data = DB::select($ss);
         
         //-----output to row file-----
         $fp   = fopen("$spath/data/pub/$sid.$uid.rows", "w");
         fwrite($fp, "reporter\tsymbol\tchrom:start\n");
         foreach($Data as $dd){
            $rr='';
            foreach($dd as $key=>$value) {
               if($key=="chrom"){ $rr .= "$value:";}
               else {  $rr .= "$value\t"; }
            }
            $rr=trim($rr, "\t");   
            fwrite($fp, $rr."\n");
         }
         fclose($fp);
         
         //=====================data::expressions=================================
         //-----get expression data-----
         if($type=="Uber") {
            $ss = " 
               select sample_name, e.probeset, log2 
               from $exprdb e
               where sample_name in ('".implode("','", $Dsmpls)."') and probeset in ('".implode("','", $Dgenes)."')  
               ";
         } elseif ($type=="Illumina"){
            $ss = " 
               select sample_name, gene, log2 
               from $exprdb e
               where sample_name in ('".implode("','", $Dsmpls)."') and gene in ('".implode("','", $Dgenes)."')  
               ";
         }
         
         //-----output to data file-----
         $fp   = fopen("$spath/data/pub/$sid.$uid.data", "w");
         fwrite($fp, "sample\treporter\tlog2\n");
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
      }

      return View::make('pages/heatmap', ['studies'=>$jStudies, 'studiescols'=>$jStudiesCols, 'sid'=>$sid, 'uid'=>$uid, 'genelist'=>$genelist, 'smpllist'=>$smpllist, 'storage'=>url(), 'threshold'=>$threshold, 'maxgene'=>$maxgene ]);

   }

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

   ############## View Heatmap ###########################################################
   public function canvasHeatmap($sid)
   {

      ###########################################################
      #################  heatmap Info  ##########################
      ###########################################################
      //=====================prepare settings================================ 
      //------initiate settings
      $uid                   = Sentry::getUser()->id;
      $spath                 = public_path();
      $settings              = [];
      $settings['genelist' ] = '';
      $settings['smpllist' ] = '';
      $settings['maxgene'  ] = '20';
      $settings['threshold'] = '5';
       
      //------read settings from file
      //if(file_exists("$spath/data/pub/$sid.$uid.settings" )) {
      if (0) {
	   $fsettings = fopen("$spath/data/pub/$sid.$uid.settings", "r");
         $id =''; 
         $ss ='';
         while(($line=fgets($fsettings))!=false) { 
            if($line[0]=="#") {
               $settings[$id] = trim($ss, "\x00..\x1F #"); 
               $id=trim($line,"\x00..\x1F #");
               $ss='';
            }      
            else{
               $ss.=' '.trim($line,"\x00..\x1F #");
            }
         }
         fclose($fsettings);
      }
      $genelist  = $settings['genelist' ] ;
      $smpllist  = $settings['smpllist' ] ;
      $maxgene   = $settings['maxgene'  ] ;
      $threshold = $settings['threshold'] ;

      //------read settings from page
	$smpllist  = "";
      if(Input::get('genelist' )) { $genelist  = Input::get('genelist' );}
      if(Input::get('smpllist' )) { $smpllist  = Input::get('smpllist' );}
      if(Input::get('maxgene'  )) { $maxgene   = Input::get('maxgene'  );}
      if(Input::get('threshold')) { $threshold = Input::get('threshold');}

      $flag=0; 
      if($genelist  != $settings['genelist' ] or 
         $smpllist  != $settings['smpllist' ] or 
         $maxgene   != $settings['maxgene'  ] or
         $threshold != $settings['threshold']  
        ){  
         $flag=1; 
         //echo "flag";
         //return;
      }
	$genelist = str_replace(array("\n", "\t"), '', $genelist);
	$genelist = strtoupper($genelist);	
      //------write settings to file
      $fsettings = fopen("$spath/data/pub/$sid.$uid.settings", "w");
      fwrite($fsettings, "#uid         \n".$uid        );
      fwrite($fsettings, "\n#sid       \n".$sid        );
      fwrite($fsettings, "\n#genelist  \n".$genelist   );
      fwrite($fsettings, "\n#smpllist  \n".$smpllist   );
      fwrite($fsettings, "\n#maxgene   \n".$maxgene    );
      fwrite($fsettings, "\n#threshold \n".$threshold  );
      fclose($fsettings);

      $study = null;
      if(1==1) { 
         //=====================db table settings================================ 
         $study = Studies::find($sid);
         $exp_type = $study->exp_type;
	 $status = $study->status;
         $exprdb="expr";
         //=====================columns::samples=================================
         //-----get sample list-----
         
         $where =[];
         if ($smpllist==' ' or $smpllist=='') {
            $where[] = "rownum<1000";
         }
         else{ 
            $smpls= preg_split('/\s+/', $smpllist);
            foreach($smpls as $smpl) {
               if ($smpl!='') { $where[]= " sample_id like '".$smpl."%' "; }
            }
         }
         $ss = "
            select distinct sample_id
            from study_samples       s 
            where s.study_id=$sid and exists(select sample_id from $exprdb e where s.sample_id=e.sample_id) 
            and (".implode(" or ", $where).")";		
         $Data = DB::select($ss);
         $Dsmpls = [];
         foreach($Data as $dd){
            foreach($dd as $key=>$value) {
               $Dsmpls[$value]=$value;
            }
         }         
         
         //=====================rows::genes=================================
         //-----get gene list-----
         $where =[];
         if (trim($genelist,"\x00..\x1F #")=='') {
            $where[] = "1=1";
         }
         else{ 
            $genes= preg_split('/\s+/', $genelist);
            foreach($genes as $gene) {
               if ($gene!='') { $where[]= " a.symbol like '".$gene."' "; }
            }
         }
         if($exp_type=="Array") {
            $ss = "
               select distinct a.affy_id
               from affy_sref a 
               where exists(select e.probeset from $exprdb e where e.probeset=a.affy_id)
               and (".implode(" or ", $where).")
               ";
         } elseif ($exp_type=="RNAseq"){
            $ss = " 
               select distinct a.gene
               from anno_rsu_gene a 
               where exists(select e.gene     from $exprdb e where e.gene=a.gene ) and 
               (".implode(" or ", $where).")
               ";
         }
         $Data = DB::select($ss);
         $Dgenes=[];          
         foreach($Data as $dd){
            foreach($dd as $key=>$value) {
               $Dgenes[$value]=$value;
            }
         }
         $Dgenes=array_slice($Dgenes, 0, $maxgene);
         
        
      }
	//$this->getExpr($sid, $Dsmpls, $Dgenes);
      $heatmap_json = $this->getHeatmapJson($sid, $Dsmpls, $Dgenes, $status); 

      return View::make('pages/canvasHeatmap', ['sid'=>$sid, 'uid'=>$uid, 'genelist'=>$genelist, 'smpllist'=>$smpllist, 'storage'=>url(), 'threshold'=>$threshold, 'maxgene'=>$maxgene, 'heatmap_data'=>json_encode($heatmap_json), 'heatmap_title' => "Expression - $sid", 'status'=>$status]);      
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


   ############## run GSEA ###########################################################
   public function gsea($sid)
   {

      #-----parameters------
      $path      = public_path();
      $gpath     = "$path/data/gsea";
      $uid       = Sentry::getUser()->id;
      $email     = Sentry::getUser()->email;   
      $geneset   = Input::get ('geneset'   );
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

         $Data = DB::select($ss);
         $smpls='';
         foreach($Data as $dd){
            foreach($dd as $key=>$value) {
               $smpls .= "<option value=$value>$value</option>\n";
            }
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
                           $gsealist .= ' <a target="gseaiframe" href='.asset("data/gsea/pub/$dir/pub/$log").'><img title=log style=\"width:12px;height:12px;\" src=http://help.sap.com/static/saphelp_470/en/35/3d9f3cad1e3251e10000000a114084/Image185.gif></a>';                              
                        }
                     }
                  }
                  $gsealist .='</td>'; 
                  $gsealist .= '<td><a target="gseaiframe" href='.asset('data/gsea/pub/'.$dir.'.tar'       ).'><img title=Download style=\"width:16px;height:16px;\" src=http://www.naadsm.org/naadsm/files/common/smallZipFileIcon.png></a></td>';  
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
                           $gsealist .= ' <a target="gseaiframe" href='.asset("data/gsea/pub/$dir/pub/$log").'><img title=log style=\"width:12px;height:12px;\" src=http://help.sap.com/static/saphelp_470/en/35/3d9f3cad1e3251e10000000a114084/Image185.gif></a>';                              
                        }
                     }
                  }
                  $gsealist .='</td>'; 
                  $status="<img title=failed style=\"width:16px;height:16px;\" src=https://build.spring.io/images/iconsv4/icon-build-failed.png>"; 
                  $logs = scandir("$gpath/pub/$dir/pub/");
                  foreach($logs as $log) { if ((time()-filectime("$gpath/pub/$dir/pub/$log")) < 300) { $status="<A title=\"running/refresh\" HREF=\"javascript:history.go(0)\"><img  style=\"width:16px;height:16px;\" src=http://icons.iconarchive.com/icons/fatcow/farm-fresh/16/arrow-refresh-icon.png></A>"; } }
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
        $cmd = "/opt/nasapps/production/java/java-1.8.0-oraclejdk/bin/java -cp gsea2-2.0.10.jar -Xmx4192m xtools.gsea.GseaPreranked -gmx '$gmx' -collapse false -mode Max_probe -norm meandiv -nperm 1000 -rnk pub/$fn/$fn.rnk -scoring_scheme weighted -rpt_label my_analysis -chip gseaftp.broadinstitute.org://pub/gsea/annotations/GENE_SYMBOL.chip -include_only_symbols true -make_sets true -plot_top_x 20 -rnd_seed timestamp -set_max 500 -set_min 15 -zip_report false -out pub/$fn -gui false ";
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
        $host = "http://fr-s-bsg-onc-d.ncifcrf.gov/onco.sandbox2/public";
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
        return Redirect::to("/gsea/$sid");
        return View::make('pages/gsea', ['user'=>"$uid\t$email", 'study'=>"$sid", 'data'=>"$data"]);
        
   }

   ############## delete run data ###########################################################
   public function gseadel($target)
   {
       $path = public_path();
       $ss   = explode('=', $target);
       #if (file_exists("$path/data/gsea/pub/$dir")) { unlink ("$path/data/gsea/pub/$dir"); }
       exec("rm -rf $path/data/gsea/pub/".$ss[1]);

       return Redirect::to("/gsea/".$ss[0]);
   }

}
