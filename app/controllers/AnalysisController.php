<?php

use App;

class AnalysisController extends BaseController {

   
   ############## View Analysis ##########################################################################################
   public function viewAnalysisList()
   {
      #===get data in format
      $jdata    = OncoController::JData('analysis');
      #===get colnames in format
      $jcolnames= OncoController::JColumns('analysis');      

      return View::make('pages/viewtable', ['json'=>$jdata, 'colnames'=>$jcolnames]);
   }

   public function dataTableProcessing() {        

      $start = $_GET['start'];
      $length = $_GET['length'];
      $draw = $_GET['draw'];
      $search_value = strtoupper($_GET['search']['value']);
      $aid = $_GET['aid'];
      
      list($jdata, $row_count) = $this->getData($aid, $start, $length, $search_value);
 
      $json= array("draw"=>$draw, "recordsTotal"=>$row_count, "recordsFiltered"=>$row_count, "data"=>$jdata);

      echo json_encode($json);

   }

   public function viewAnalysis($sid, $aid)
   {
      $analyses = DB::select("select * from analysis where study_id=$sid");
      if ($aid == 0) {
          $aid = $analyses[0]->id;
      }		
      $columns = $this->getAnalysisColumns($aid);
      $col_json = array();
      foreach ($columns as $column) {
         $col_json[] = array("title" => $column);
      }
      return View::make('pages/viewAnalysis', ['colnames'=>json_encode($col_json), 'sid'=>$sid, 'aid'=>$aid, 'analyses'=>$analyses]);
   }

   public function viewAnalysis2($aid)
   {
      #===get data in format

      return View::make('pages/viewanalysis2', []);
   }

   public function getData($aid, $start, $length, $search_value) {
      $analysis = Analyses::find($aid);
      $sid = $analysis->study_id;
      $detailed_field = $analysis->detailed_field;
      $row_count = $analysis->total_rows;      
      if ($search_value != "") {
          $rows = DB::select("select * from analysis_data where aid='$aid' and rid in (select rid from (select a.*, rownum as rn from analysis_data a where aid= $aid and key='$detailed_field' and value like '$search_value%' order by rid) where rn > $start and rn <= ($start + $length)) order by rid");
          $row_count = DB::select("select count(distinct rid) as cnt from analysis_data where aid='$aid' and key='$detailed_field' and value like '$search_value%'")[0]->cnt;
      } else {
          $rows = DB::select("select * from analysis_data where aid='$aid' and rid in (select distinct rid from analysis_data where aid='$aid' and rid > $start and rid <= ($start + $length)) order by rid");
      }
      $json_data = $this->getAnalysisJson($sid, $aid, $rows, $detailed_field);
      return array($json_data, $row_count);
   }

   public function getAnalysisJson($sid, $aid, $rows, $detailed_field) {
      $columns = $this->getAnalysisColumns($aid);
      $json_data = array();
      $old_rid = 0;
      $row_value = array();
      foreach ($rows as $row){         
         if ($old_rid != $row->rid) { 
             if ($old_rid != 0) { 
                 $row_json = $this->getRowJson($old_rid, $columns, $row_value);
                 $json_data[] = $row_json;
                
            }
            $old_rid = $row->rid;
            $row_value = array();
         }
         if ($row->key == $detailed_field) {
               //$row->value = "<a target='_blank' href='".url("/geneDetailUCSC/$sid/".$row->value)."'>".$row->value."</a>";
             $row->value = "<a href='".url("/analysisDetail/$aid/".$row->value."/Boxplot/0/0/0")."'>".$row->value."</a>";
         }
         if (is_numeric($row->value) && !ctype_digit($row->value)) {
             $row->value = number_format($row->value,2);
         }
         $row_value[$row->key] = $row->value;
      }
      //the last row
      $row_json = $this->getRowJson($old_rid, $columns, $row_value);
      $json_data[] = $row_json;
      return $json_data;
   }
   
   public function getRowJson($rid, $columns, $row_value) {
      $row_json = array([$rid]);
      foreach ($columns as $column) {
           if ($column != 'Row ID') {
               if (!isset($row_value[$column])) {
                    $row_json[] = '';
               } else {
                    $row_json[] = $row_value[$column];
               }
           }
      }
      return $row_json;
   }

   public function getAnalysisColumns($aid) {
      $rows = DB::select("select * from analysis_data where aid=$aid and rid=1 order by key");
      $columns = array();
      $columns[] = 'Row ID';
      foreach ($rows as $row) {
          $columns[] = $row->key;
      }
      return $columns;
   }

   public function viewAnalysisDetail($aid, $detailed_value, $plot_type, $x_idx=0, $y_idx=0, $value_idx=0) {
      $analysis = Analyses::find($aid);
      $detailed_field = $analysis->detailed_field;
      $x_fields = explode(',',$analysis->x_fields);
      $y_fields = explode(',',$analysis->y_fields);
      $value_fields = explode(',',$analysis->value_fields);
      $x_field = $x_fields[$x_idx];
      $y_field = $y_fields[$y_idx];
      $x_field_type = $analysis->x_field_type;
      $value_field = $value_fields[$value_idx];
      $rows = DB::select("select * from analysis_data where aid='$aid' and rid in (select distinct rid from analysis_data where aid='$aid' and key = '$detailed_field' and value = '$detailed_value') order by rid");

      if ($plot_type == 'Heatmap') {
          list($plot_json, $width, $height) = $this->getHeatmapJson($rows, $x_field, $y_field, $value_field, $analysis, $x_field_type);
      }
      else {
          list($plot_json, $width, $height) = $this->getBoxplotJson($rows, $x_field, $y_field, $value_field, $analysis);
      }
       
      $columns = $this->getAnalysisColumns($aid);
      
      $analyses = DB::select("select * from analysis a where exists(select * from analysis_data b where a.id = b.aid and key='$detailed_field' and value = '$detailed_value') order by id");
      return View::make('pages/viewAnalysisDetail', ['aid'=>$aid, 'data'=>json_encode($plot_json), 'x_field'=>$x_field,'x_fields'=>$x_fields, 'y_field'=>$y_field,'y_fields'=>$y_fields,'value_fields'=>$value_fields, 'axis_title'=>$value_field, 'title' => $analysis->analysis_name. " - $detailed_value", 'detailed_value' => $detailed_value, 'analyses'=>$analyses, 'plot_type' => $plot_type, 'x_idx' => $x_idx, 'y_idx' => $y_idx, 'value_idx' => $value_idx, 'x_field_type' => $x_field_type, 'plot_width' => $width, 'plot_height' => $height]);
   }

   public function getPlotSize($x_fields, $y_fields) {
      $header = 80;
      $max_x_label_len = max(array_map('strlen', $x_fields));
      $max_y_label_len = max(array_map('strlen', $y_fields));
      $width = $header * 2 + count(array_unique($x_fields)) * 20 + $max_y_label_len * 18;
      $height = $header * 2 + count(array_unique($y_fields)) * 20 + $max_x_label_len * 18;
      return array($width, $height);
   }

   public function getBoxplotJson($rows, $x_field, $y_field, $value_field, $analysis) {
      $raw_data = array();
      foreach ($rows as $row) {
         $raw_data[$row->key][] = $row->value;
      }
      $data_values = $raw_data[$value_field];
      $header = 80;
      $figure_height = 300;
      $x_fields = $raw_data[$x_field];
      $max_x_label_len = max(array_map('strlen', $x_fields));     
      $width = $header * 2 + count(array_unique($x_fields)) * 20 + 5 * 4;
      $height = $header * 2 + $figure_height + $max_x_label_len * 4;      
      $plot_json = array("x"=>$raw_data, "y"=>array('vars'=>array($value_field), 'data'=>array($data_values)), "m"=>array("Name"=>$analysis->analysis_name));
      return array($plot_json, $width, $height);
   }

   public function getHeatmapJson($rows, $x_field, $y_field, $value_field, $analysis, $x_field_type) {
      $y_values = array();
      $data_values = array();
      $raw_data = array();
      $x_field_types = array();
      $old_rid = 0;
      $x_value = '';
      $x_field_type_value = '';
      $y_value = '';
      $data_value = 0;
      foreach ($rows as $row){         
         if ($old_rid != $row->rid) { 
             if ($old_rid != 0) { 
                 $raw_data[$x_value][$y_value] = $data_value;
                 if (isset($x_field_type)) $x_field_types[$x_value] = $x_field_type_value;
             }
             $old_rid = $row->rid;
             $row_value = array();
         }
         if ($row->key == $x_field) $x_value = $row->value;
         if ($row->key == $y_field) { $y_value = $row->value; $y_values[$row->value] = '';};
         if ($row->key == $value_field) $data_value = $row->value;
         if (isset($x_field_type) and $row->key == $x_field_type) $x_field_type_value = $row->value;
      }
      //the last row
      $raw_data[$x_value][$y_value] = $data_value;
      $x_type_json = array();
      if (isset($x_field_type)) {
          $x_field_types[$x_value] = $x_field_type_value;          
      }
      $x_keys = array_keys($raw_data);
      $y_keys = array_keys($y_values);
      foreach ($x_keys as $x_key) {
           $data_row = array();
           foreach ($y_keys as $y_key) {
               $data_row[] = $raw_data[$x_key][$y_key];
           }
           $data_values[] = $data_row;
           if (isset($x_field_type)) $x_type_json[] = $x_field_types[$x_key];
      } 
      $header = 80;
      $max_x_label_len = max(array_map('strlen', $x_keys));
      $max_y_label_len = max(array_map('strlen', $y_keys));
      $width = $header * 2 + count(array_unique($x_keys)) * 20 + $max_y_label_len * 4;
      $height = $header * 2 + count(array_unique($y_keys)) * 20 + $max_x_label_len * 4;
      $plot_json = array("z" => array($x_field_type=> $x_type_json), "x"=>$x_keys, "y"=>array('vars'=>$x_keys, 'smps'=>$y_keys, 'data'=>$data_values, 'desc'=>array($value_field)), "m"=>array("Name"=>$analysis->analysis_name));
      return array($plot_json, $width, $height);
   }


   static public function JData($aid) {
      #===get data from model Sample_Annotation
      $Data = DB::select('select * from analysis_data where aid='.$aid.' and rid in (select distinct rid from analysis_data where aid='.$aid.' and rownum<5000 group by rid)');
      
      #===generate the data in format for samples 
      $jData='[';
      $cflag= 0;
      $ridA = 0;
      $hh   = array();
      $ss   = array();
      foreach($Data as $dd){
         foreach($dd as $key=>$value) {
            if ($key == 'aid'  ) { $aidB = $value; }  
            if ($key == 'rid'  ) { $ridB = $value; }  
            if ($key == 'key'  ) { $keyB = $value; }  
            if ($key == 'value') { $valB = $value; }  
         }
         if ($ridA!=$ridB) { 
            if ($ridA!=0) { 
               $kk = array_keys($ss);
               #===make Table Data
               $jData .='[["'.$aid.'"],["'.$ridA.'"],';
               foreach($kk as $key) {
                  $jData .= '["'.$ss[$key].'"],';
               }
               $jData .='],';
               #===make Table Colnames 
               if (!isset($jColnames)) { 
                  $jColnames ='[{"title":"Analysis_ID"},{"title":"Record_ID"},';
                  foreach($kk as $key) {
                     $jColnames .= '{"title":"'.$key.'"},';
                 }
                  $jColnames .=']';
               }
            }
            $ridA=$ridB;
         }
         $ss[$keyB]=$valB;
      }
      $jData .=']';
      #echo $jColnames;
      #echo $jData;
      #return;

      return array($jColnames, $jData);
   }

   static public function JColumns ($tid) {
      #===get data from model Sample_Annotation
      $Data = DB::select('select * from '.$tid.' where rownum<10');

      #===generate the data in format for column names 
      $jColnames ='[';
      foreach($Data[0] as $key=>$value) {
         $jColnames .= '{"title":"'.$key.'"},';
      }
      return $jColnames;
   }

   ############## View Study ########################################################################################### 
   public function viewStudy()
   {
      #===get data in format
      $jdata    = OncoController::JData('studies');

      #===get colnames in format
      $jcolnames= OncoController::JColumns('studies');      
      return View::make('pages/viewstudy', ['json'=>$jdata, 'colnames'=>$jcolnames]);
   }
   ############## View Samples ########################################################################################### 
   public function viewSamples()
   {
      #===get data in format for Samples
      $jsamples = SampleAnnotation::JSamples();
      #===get data in format for colnames 
      $jcolnames= SampleAnnotation::JColumns();      
      return View::make('pages/viewsamples', ['json'=>$jsamples, 'colnames'=>$jcolnames]);
   }
   ############## View Samples Tip
   public function viewTip($id)
   {
      #===get parameters
      $ss  = explode("=", $id);
      $tt  = explode("|", $ss[1]);  
      $tid = $ss[0];    
      if($tid=="biomaterial_name") { 
         $tid='biomaterial';
      }
      if($tid=="snp_or_array" or $tid=="methylation" or $tid=="iontorrent") { 
         $tid='others';
      }
      $tid = "_".$tid; 
      if($tid=="_person") { 
         $tid='';
      }      

      $bid = $tt[1];
      #===get data in format for Sample Tip
      $jtip = DB::select('select * from sample_annotation'.$tid.' where biomaterial_id='.$bid.'');


      #$jbiomaterial = SampleAnnotationTip::where('biomaterial_id', '=', $bid)->take(50)->get();
      return View::make('pages/viewtip', ['jtip'=>$jtip ]);
   }

   ############## View Samples Biomaterial
   public function viewBioMaterial($bid)
   {
      #===get data in format for Samples
      $jbiomaterial = SampleAnnotationBiomaterial::where('biomaterial_id', '=', $bid)->take(50)->get();
      return View::make('pages/viewbiomaterial', ['jbiomaterial'=>$jbiomaterial->toArray() ]);
   }

   ############## View Samples Tree ########################################################################################### 
   public function viewTree($id)
   {
      return View::make('pages/viewtree2', ['name'=>$id ]);
   }

   public function getTree()
   {
      #===get data from model SampleAnnotation
      $smpls     = SampleAnnotation::all();
      $smpls     = SampleAnnotation::where('rownum','<','200')->get();
      $jsamples  = $smpls->toArray();

      //$jsamples = oncoController::formatSamples($tid);
      $hierarchy = 'person->biomaterial_name->fcid';
      $pathArr = preg_split("/->/",$hierarchy,-1 );

      for($j=0; $j < sizeof($jsamples); $j++) {
         $val = "";
         for($i=0; $i < sizeof($pathArr); $i++) {  
            $res[$i][] = $jsamples[$j][$pathArr[$i]];
            if($i < sizeof($pathArr)-1) {
               if($jsamples[$j][$pathArr[$i+1]] != "") {
                  $res2[$jsamples[$j][$pathArr[$i]]][] = $jsamples[$j][$pathArr[$i+1]];
               }
            }
         }
      }
      
      ksort($res);
      foreach ($res as $k => $v) {
         $levelRes = array_unique($v);
         $uniqLvl[$k] = $levelRes;
      }
      foreach ($res2 as $k => $v) {
         $levelRes = array_unique($v);
         $uniqRes[$k] = $levelRes;
      }

      $treeJson = '{"name": "Person", "children": [';
      oncoController::createTreeJson($uniqLvl[0], $uniqRes, $treeJson);
      $treeJson .= ']}';
      return $treeJson; 
   }

   public function getTree2($id)
   {
      #===get data from model SampleAnnotation
      $idd       = urldecode($id); 
      $ss        = explode("=", $idd);      
      $smpls     = SampleAnnotation::where($ss[0], '=', $ss[1])->get();
      $jsamples  = $smpls->toArray();

      //$jsamples = oncoController::formatSamples($tid);
      if($ss[0]=='diagnosis'){ 
         $hierarchy = 'diagnosis->person->biomaterial_name';
      }
      if($ss[0]=='person'){ 
         $hierarchy = 'diagnosis->person->biomaterial_name->custom_id';
      }
      
      $pathArr = preg_split("/->/",$hierarchy,-1 );

      for($j=0; $j < sizeof($jsamples); $j++) {
         $val = "";
         for($i=0; $i < sizeof($pathArr); $i++) {  
            $res[$i][] = $jsamples[$j][$pathArr[$i]];
            if($i < sizeof($pathArr)-1) {
               if($jsamples[$j][$pathArr[$i+1]] != "") {
                  $res2[$jsamples[$j][$pathArr[$i]]][] = $jsamples[$j][$pathArr[$i+1]];
               }
            }
         }
      }
      
      ksort($res);
      foreach ($res as $k => $v) {
         $levelRes = array_unique($v);
         $uniqLvl[$k] = $levelRes;
      }
      foreach ($res2 as $k => $v) {
         $levelRes = array_unique($v);
         $uniqRes[$k] = $levelRes;
      }

      #$treeJson = '{"name": "Person", "children": [';
      oncoController::createTreeJson($uniqLvl[0], $uniqRes, $treeJson);
      #$treeJson .= ']}';

      return $treeJson; 
   }

   function createTreeJson($elem,$uniqRes, &$treeJson) {

      $i=0;
      foreach ($elem as $k2 => $v2) {
         if (array_key_exists($v2, $uniqRes)) {
            $localElem = $uniqRes[$v2];
         } else {
            $localElem = $v2;
         }
         if($i) { $treeJson .= ","; }
         $treeJson .= "{ \"name\": \"$v2\"";

            $myfile = fopen("hjb.txt", "w"); 
            fwrite($myfile, "\n=========================\n");
            fwrite($myfile, $treeJson);
            fclose($myfile); 

         if(is_array($localElem)) {
            $treeJson .= ", \"children\": [ ";
            #print_r($elem);
            oncoController::createTreeJson($localElem, $uniqRes, $treeJson);
            #echo "after: $treeJson <br>" ;
            $treeJson .= "]";
         }
         else {
            $treeJson .= ", \"size\": 200 ";
         }  
         $treeJson .= "}";
         $i++;
      }
       
   }


   public function showOncoDS()
   {
      $results = OncoDS::all();
      return View::make('pages/showtable', ['tid'=>'Unco_Dataset', 'results'=>$results]);
   }

   public function insertOncoDS()
   {
      $entry = new OncoDS;
      $entry->id = 'Atest'; 
      $entry->name = 'Atest2'; 
      $entry->save();
   }

   public function updateOncoDS()
   {
      $entry = OncoDS::all();
      $entry->id = 'Btest'; 
      $entry->name = 'Btest2'; 
      $entry->save();
   }

   public function deleteOncoDS()
   {
      $affectedRows = OncoDS::where('id', '==', 'Atest')->delete();
   }

}
