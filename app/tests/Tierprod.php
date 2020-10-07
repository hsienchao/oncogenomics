<?php

#require '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/vendor/autoload.php';


class Tierprod  {

    public function test()
    {
        
        $testing=new Tests();
        $passed=0;
        $failed=0;
        $tests_file = file_get_contents("/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/app/tests/tests_tiers.json","w");


        $jsonObj = json_decode($tests_file, true);
        $tests=array();
        $test_string="";

        $get_url=new TestUrl();
        $login_type = 'nih_login';
        $loginUrl="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/user/login";
        $loginFields =array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type);
        $login = $get_url->getUrl($loginUrl, 'post', $loginFields); //login to the site
            
        foreach ($jsonObj as $field => $value ) {
            $URL=$jsonObj[$field]["URL"];
            $pieces = explode("/", $URL);
            $patient_id=$pieces[3];
            $variant=$pieces[6];
            $case_id=$pieces[5];
            $project_id=$pieces[2];
            
            $sample_url='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/getSampleByPatientID/'.$project_id.'/'.$patient_id.'/'.$case_id;
            $string = $get_url->getUrl($sample_url);
            $json=json_decode($string,true);
            $samples=$json['data'];
            #print_r($samples);
           # $URL="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/".$jsonObj[$field]["URL"];
            $results_file=fopen("/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/app/tests/tiers/".$patient_id."_diff.tsv","a");
            foreach ($samples as $data_field=>$data_value) {
                $sample_id=$samples[$data_field][0];
                $exp_type=$samples[$data_field][4];
                print_r($sample_id)."\n";
                if($exp_type=="Exome" ||$exp_type=="Panel"){
                    $URL="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/getVarAnnotation/".$project_id.'/'.$patient_id.'/'.$sample_id.'/'.$case_id.'/'.$variant;

                    $call=$jsonObj[$field]["call"];
                   
                    $test_string="";
                    $url='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/saveSettingGet/default_annotation/avia';
                    $get_url->getUrl($url); //get the remote page
                    print $URL."\n";
                    $avia=$testing->get_json($URL,$call,$this);
                    #print_r($avia);
                    $url='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/saveSettingGet/default_annotation/khanlab';
                    $get_url->getUrl($url); //get the remote page
                    $khanlab=$testing->get_json($URL,$call,$this);


                    $table=$testing->Parse_data($avia,$khanlab,$variant);
                    if($table!=""){
                        fwrite($results_file, $URL."\n");
                        fwrite($results_file,"Chr\tStart\tEnd\tRef\tAlt\tGene\tAvia\tKhanlab\n");
                        fwrite($results_file,$table."\n");
                    }
                }
            }

           # 


        }

    }



}

class Tests {

    
   

    public function Parse_data($avia,$khanlab,$variant)
    {

        if($variant=="germline"){
            $index=54;
            $gene_indx=18;
            $chr_indx=12;
            $start_indx=13;
            $end_indx=14;
            $ref_indx=15;
            $alt_indx=16;
        }
        else{
            $index=53;
            $chr_indx=11;
            $gene_indx=17;
            $start_indx=12;
            $end_indx=13;
            $ref_indx=14;
            $alt_indx=15;
        }
        $Avia_table=array();
        $diff_table="";
        
        foreach ($avia as $data_field=>$data_value) {
            $element= $avia[$data_field];
                if($avia[$data_field][$index]=="")
                    $tier="No tier";
                else{
                    $tier=$avia[$data_field][$index];
                    $tier = $this->GetBetween("<span class='badge'>","</span>", $tier);
                }

                $Avia_chr=$avia[$data_field][$chr_indx];
                $Avia_start=$avia[$data_field][$start_indx];
                $Avia_end=$avia[$data_field][$end_indx];
                $Avia_ref=$avia[$data_field][$ref_indx];
                $Avia_alt=$avia[$data_field][$alt_indx];
                $Avia_gene=$avia[$data_field][$gene_indx];
                if(strpos($Avia_ref,"div")){
                    $pieces = explode("'", $Avia_ref);
                    $Avia_ref=$pieces[1];
                }
                if(strpos($Avia_alt,"div")){
                    $pieces = explode("'", $Avia_alt);
                    $Avia_alt=$pieces[1];
                }
                $pieces = explode(",", $Avia_gene);
                $Avia_gene=$pieces[7];
                $pieces = explode(")", $Avia_gene);
                $Avia_gene=$pieces[0];
                $Avia_table[$Avia_chr."\t".$Avia_start."\t".$Avia_end."\t".$Avia_ref."\t".$Avia_alt."\t".$Avia_gene]=$tier;
                
        }
        if($variant=="germline")
            $index=53;
        else
            $index=52;

        foreach ($khanlab as $data_field=>$data_value) {
            $element= $khanlab[$data_field];
                if($khanlab[$data_field][$index]=="")
                    $tier="No tier";
                else{
                    $tier=$khanlab[$data_field][$index];
                    $tier = $this->GetBetween("<span class='badge'>","</span>", $tier);
                }

                $Khanlab_chr=$khanlab[$data_field][$chr_indx];
                $Khanlab_start=$khanlab[$data_field][$start_indx];
                $Khanlab_end=$khanlab[$data_field][$end_indx];
                $Khanlab_ref=$khanlab[$data_field][$ref_indx];
                $Khanlab_alt=$khanlab[$data_field][$alt_indx];
                $Khanlab_gene=$khanlab[$data_field][$gene_indx];
                if(strpos($Khanlab_ref,"div")){
                    $pieces = explode("'", $Khanlab_ref);
                    $C=$pieces[1];
                }
                if(strpos($Khanlab_alt,"div")){
                    $pieces = explode("'", $Khanlab_alt);
                    $Khanlab_alt=$pieces[1];
                }
                $pieces = explode(",", $Khanlab_gene);
                $Khanlab_gene=$pieces[7];
                $pieces = explode(")", $Khanlab_gene);
                $Khanlab_gene=$pieces[0];
                $key=$Khanlab_chr."\t".$Khanlab_start."\t".$Khanlab_end."\t".$Khanlab_ref."\t".$Khanlab_alt."\t".$Khanlab_gene;
                if(array_key_exists($key, $Avia_table)){
                    if($Avia_table[$key]!=$tier){
                        $diff_table.=$key."\t".$Avia_table[$key]."\t".$tier."\n";
                        print $tier."\n";
                    }
                }
                
                
        }
        print "end\n";
        #print_r($Avia_table);
    return $diff_table;
   

    }

    public function get_json($route,$call,$test){

        


        $testing=new TestUrl();
        $string = $testing->getUrl($route);
        $json=json_decode($string,true);


        $data= $json["data"];
        #print_r($data);
        return $data;

    }

    public function GetBetween($var1="",$var2="",$pool){
    $temp1 = strpos($pool,$var1)+strlen($var1);
    $result = substr($pool,$temp1,strlen($pool));
    $dd=strpos($result,$var2);
    if($dd == 0){
        $dd = strlen($result);
    }

    return substr($result,0,$dd);
}
 
}
class TestUrl{
    function getUrl($url, $method='', $vars='',$test='') {
    $ch = curl_init();
    if ($method == 'post') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, './cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, './cookies.txt');
    $output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    if($test=="code"){
        return $httpcode;
    }
    else{
        return $output;
    }
    }
}

$testing=new Tierprod();
$testing->test();