<?php

require '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/vendor/autoload.php';


class TierTest extends TestCase {

    public function test()
    {
        
        $testing=new Tests();
        $testing->testLogin($this);
        $passed=0;
        $failed=0;
        $tests_file = file_get_contents("./tests/tests_tiers.json","w");


        $jsonObj = json_decode($tests_file, true);
        $tests=array();
        $test_string="";
            
        foreach ($jsonObj as $field => $value ) {
            $URL=$jsonObj[$field]["URL"];
            $pieces = explode("/", $URL);
            $patient_id=$pieces[3];
            $variant=$pieces[6];
            $results_file=fopen("./tests/tiers/".$patient_id."_diff.tsv","a");

            $call=$jsonObj[$field]["call"];
           
            $test_string="";
            $testing->testLogin($this);

            $this->call('GET',"/saveSettingGet/default_annotation/avia");
            print $URL."\n";
            $avia=$testing->get_json($URL,$call,$this);

            $this->call('GET',"/saveSettingGet/default_annotation/khanlab");
            print $URL."\n";
            $khanlab=$testing->get_json($URL,$call,$this);


            $table=$testing->Parse_data($avia,$khanlab,$variant);
            if($table!=""){
                fwrite($results_file, $URL."\n");
                fwrite($results_file,"Chr\tStart\tEnd\tRef\tAlt\tGene\tAvia\tKhanlab\n");
                fwrite($results_file,$table."\n");
            }


        }

    }



}

class Tests extends TestCase {

    public function testLogin($test){
        Session::start();
        $login_type = 'nih_login';
        $test->call('POST', '/user/login',array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type));
    }
    
   

    public function Parse_data($avia,$khanlab,$variant)
    {

        if($variant=="germline"){
            $index=52;
            $gene_indx=16;
            $chr_indx=10;
        }
        else{
            $index=51;
            $chr_indx=9;
            $gene_indx=15;
        }
        $Avia_table=array();
        $diff_table="";
        $start_indx=11;
        $end_indx=12;
        $ref_indx=13;
        $alt_indx=14;
        
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
            $index=51;
        else
            $index=50;

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
        print "end";
        #print_r($Avia_table);
    return $diff_table;
   

    }

    public function get_json($route,$call,$test){

        
        $response = $test->call($call, $route);
        $string= $response->getContent();
        $json=json_decode($string,true);
        
        $data= $json["data"];
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