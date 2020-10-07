<?php

class OncoController extends BaseController {

   /*
   |--------------------------------------------------------------------------
   | Default Home Controller
   |--------------------------------------------------------------------------
   |
   | You may wish to use controllers instead of, or in addition to, Closure
   | based routes. That's great! Here is an example controller method to
   | get you started. To route to this controller, just add the route:
   |
   |   Route::get('/', 'HomeController@showWelcome');
   |
   */

   public function showWelcome()
   {
      return View::make('hello');
   }
        
   public function showTable($tid)
   {
      $results = DB::select('select * from '.$tid);                
      return View::make('pages/showtable', ['tid'=>$tid, 'results'=>$results]);
   }

   ############## View Table ###########################################################################################
   public function viewTable($tid)
   {
      #===get data in format
      $jdata    = OncoController::JData($tid);
      #===get colnames in format
      $jcolnames= OncoController::JColumns($tid);      
      return View::make('pages/viewsamples', ['json'=>$jdata, 'colnames'=>$jcolnames]);
   }

   static public function JData($tid) {
      #===get data from model Sample_Annotation
      $Data = DB::select('select * from '.$tid);

      #===generate the data in format for samples 
      $pps_biom = "\'1430768727551\',\'width=250,height=250,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0\'";
      $pps_vtree= "\'1430768727551\',\'width=1000,height=700,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=1,left=0,top=0\'";
      $jData ='[';
      foreach($Data as $dd){
         $jData .='[';
         foreach($dd as $key=>$value) {
            if($value=='NA') {
               $value=$key.'.'.$value;
            }

            if($value==null) {
               $jData .= '["&nbsp;"],';
            }           
            elseif($key=='biomaterial_id') {
               $jData .= '["<a onclick=\"javascript:void window.open(\''.url("/viewbiomaterial/$value").'\','.$pps_biom.' ); return false;\" >'.$value.'</a>"],';
            }
            elseif($key=='person') {
               $jData .= '["<a onclick=\"javascript:void window.open(\''.url("/viewtree/$value").'\','.$pps_vtree.' ); return false;\" >'.$value.'</a>"],';
            }
            elseif($key=='id') {
               $sid = $value;
               if($tid=="studies") {  
                  $jData .= '["'.$sid.'<a href='.url("/editStudy/".$sid).' ><img src=http://www.iconpng.com/png/mono-general/edit.png style=\"width:8px;height:8px;\"></a> <a href='.url("/deleteStudy/".$sid).' ><img src=http://www.iconpng.com/png/mono-general/delete.png style=\"width:8px;height:8px;\"></a> "],';
               } 
               else{
                  $jData .= '["'.$value.'"],';
               }
            }
            elseif($key=='study_name' or $key=='study_desc' ) {
               $jData .= '["<a href=\"'.url("/heatmap/".$sid).'\" >'.$value.'</a>"],';
            }
            elseif($key=='analysis_name' or $key=='analysis_desc' ) {
               $jData .= '["<a href=\"'.url("/viewAnalysis/".$sid).'\" >'.$value.'</a>"],';
            }
            elseif($key=='analysis_name' or $key=='analysis_desc' ) {
               $jData .= '["<a href=\"'.url("/viewanalysis/".$sid).'\" >'.$value.'</a>"],';
            }
            else {
               $jData .= '["'.$value.'"],';
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
      }
      $jColnames .=']';
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
