<?php

require '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/vendor/autoload.php';


class AllTest extends TestCase {

    public function test()
    {
        
        $testing=new Tests();
        $testing->testLogin($this);
        $passed=0;
        $failed=0;
        $tests_file = file_get_contents("./tests/tests.json","w");
        $jsonObj = json_decode($tests_file, true);
        $tests=array();
        $tests_header="URL\texecution_time\ttestAssert\ttestContent\ttest_json\n";
        $test_string="";
         $test_log_file_json=fopen("./tests/test_log.json","w");
            $test_log_file_tsv=fopen("./tests/test_log_tsv.tsv","w");
            $today = getdate();
            $d=$today['mday'];
            $m=$today['mon'];
            $y=$today['year'];
            $h=$today['hours'];
            $mi=$today['minutes'];
            $date=$m."_".$d."_".$y." at ".$h.":".$mi;
        $table=$date;
        $table.= "<html><style>table, th, td {border: 1px solid black;}</style><body><table>";
        $table.="<tr><th>URL</th><th>execution_time</th><th>testAssert</th><th>testContent</th><th>test_json</th></tr>"; 

        $tests_header="URL\texecution_time\ttestAssert\ttestContent\ttest_json\n";
        foreach ($jsonObj as $field => $value ) {
            $URL=$jsonObj[$field]["URL"];
            $call=$jsonObj[$field]["call"];
            $key=$jsonObj[$field]["key"];
            $type=$jsonObj[$field]["type"];
           
        $test_string="";
            $testing->testLogin($this);
            if($type=="View"){
                $time_pre = microtime(true);
                try{
                    $testing->testAssert($URL,$call,$this);
                    $testAssert="PASSED";
                    $passed+=1;
                }
                
                catch(Exception $e) {
                    $testAssert ='FAILED: '.$URL.' failed to assert 200';
                    $failed+=1;
                }

                try{
                    $testing->testContent($URL,$call,$this,$key);
                    $testConent="PASSED";
                    $passed+=1;
                }
                
                catch(Exception $e) {
                    $testConent= 'FAILED: '.$URL.' does not contain correct content';
                    $failed+=1;
                }
                $time_post = microtime(true);
                $exec_time = $time_post - $time_pre;
                $test = array('testAssert'=>$testAssert,"testContent"=>$testConent,"execution_time"=>$exec_time);
                $table.="<tr><td>".$URL."</td><td>".$exec_time."</td><td>".$testAssert."</td><td>".$testConent."</td><td>NA</td></tr>";
                print "FIELD ".$field."\n";
                $tests[$field]=$test;

            }
            if($type=="Json"){
                $annotation=$jsonObj[$field]["annotation"];
                print $annotation."\n";
                if($annotation!="NA" )
                    $this->call('GET',"/saveSettingGet/default_annotation/".$annotation);
#                $this->call('GET',"/saveSettingGet/default_annotation/avia");
                $time_pre = microtime(true);
                print $URL."\n";
                $index=$jsonObj[$field]["tier"];
                $results=$testing->testJson($URL,$call,$this,$key,$index);
                if($results=="PASSED"){
                    $passed+=1;
                }
                else{
                    $failed+=1;
                }
            $time_post = microtime(true);
            $exec_time = $time_post - $time_pre;
            $test = array('test_json'=>$results,"execution_time"=>$exec_time);
            $test_string.=$URL."\tNA\tNA\tNA\t".$results."\n";
            $table.="<tr><td>".$URL."</td><td>NA</td><td>NA</td><td>NA</td><td>".$results."</td></tr>";
            $tests[$field]=$test;
 
            }

        }
        $table.="</table></body></html>";
        $test_data = json_encode(array('date'=>$date,'passed'=>$passed,'failed'=>$failed,"tests"=>$tests),JSON_PRETTY_PRINT);
        fwrite($test_log_file_json,$test_data);

        fwrite($test_log_file_tsv,$date."\n");
        fwrite($test_log_file_tsv,"passed:".$passed."\n");
        fwrite($test_log_file_tsv,"failed:".$failed."\n");

        fwrite($test_log_file_tsv,$tests_header);
        fwrite($test_log_file_tsv,$test_string);
#        $msg = $date."\n"."passed:".$passed."\n"."failed:".$failed."\n".$tests_header.$test_string;
        $msg=$table."passed:".$passed."\n"."failed:".$failed."\n";
        print $msg;
        $msg = wordwrap($msg,500);
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        mail("scott.goldweber@nih.gov","Oncogenomics Unit Test",$msg,$headers);



    }



}

class Tests extends TestCase {

    public function testLogin($test){
        Session::start();
        $login_type = 'nih_login';
        $test->call('POST', '/user/login',array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type));
    }
    
    public function testRoute($route,$test)
    {

    }

    public function testAssert($route,$call,$test)
    {
        print "IN TESTASSERT ".$route."\n";
        $response = $test->call($call, $route);
        print $response->getStatusCode()."\n";
        $this->assertEquals(200, $response->getStatusCode());
    }
    public function testContent($route,$call,$test,$key_content)
    {
        $response = $test->call($call, $route);
        $content= $response->getContent();
        $this->assertContains($key_content,$content);
        print "IN TESTCONTENT ".$route."\n";
    }

    public function Parse_data($data_key,$data_actual,$index)
    {

        
        $errors="FAILED: ";
        foreach ($data_key as $data_field=>$data_value) {
        $element= $data_key[$data_field];
        foreach ($element as $element_field=>$element_value) {
            if($data_actual[$data_field][$element_field]!=$element[$element_field]){
    #                    print "ACTUAL: ".$data_actual[$data_field][$element_field]."   KEY:".$element[$element_field]."\n";
                if($index!="NA"){
                    if($data_actual[$data_field][$index]=="")
                        $tier="No tier";
                    else
                        $tier=$data_actual[$data_field][$index];
                    $errors.= "at element ".$data_field." with indescrincy ".$data_actual[$data_field][$element_field]." at tier ".$tier."\n";
                }
                else
                    $errors.= "at element ".$data_field." with indescrincy ".$data_actual[$data_field][$element_field]."\n";
            }
            
        }
    }
    if($errors=="FAILED: ")
        $errors="PASSED";
    return $errors;


        

    }

    public function testJson($route,$call,$test,$file,$tier_index){

        $errors="";
        $string_key = file_get_contents($file);
        $string_key=str_replace("fr-s-bsg-onc-d.ncifcrf.gov\/clinomics_dev2\/public","localhost",$string_key);
        $string_key=str_replace("https","http",$string_key);
#        print $string_key;
        $json_key=json_decode($string_key,true);
        print ($route."\n");

        $response = $test->call($call, $route);
        $string_actual= $response->getContent();
        $json_actual=json_decode($string_actual,true);
        
        
        try{
            if($json_actual[0]!='NA')
                print ($route.":is more than one\n");
            foreach ($json_key as $index=>$data_value){
                $data_actual=$json_actual[$index];
                $data_key=$json_key[$index];
                $data_key= $this->getData_object($file,$data_key);
                $data_actual= $this->getData_object($file,$data_actual);
                print $index."\n";
                $errors.=$this->Parse_data($data_key,$data_actual,$tier_index);
                if($errors=="PASSED")
                    $errors="";
            }
            if($errors=="")
                $errors="PASSED";
        }
        catch(Exception $e){
            print ($route.":is not more than one\n");
            $data_key= $this->getData_object($file,$json_key);
            $data_actual= $this->getData_object($file,$json_actual);

            $errors=$this->Parse_data($data_key,$data_actual,$tier_index);
        }


        return $errors;

    }
    function getData_object($file,$json){
        if(strpos($file, 'QC') !== false)
            $json_obj= $json["qc_data"]["data"];
        else
            $json_obj= $json["data"];
        return $json_obj;
    }
}