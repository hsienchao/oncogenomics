<?php

require '/var/www/html/your-project/vendor/autoload.php';
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadTest extends TestCase {
    /**
     *
     * @return void
     */
    public function test()
    {

        $testing=new Tests();
        $get_url=new TestUrl();
//        $login_type = 'nih_login';
//        $loginUrl="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/user/login";
 //       $loginFields =array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type);
 //       $login = $get_url->getUrl($loginUrl, 'post', $loginFields); //login to the site
        $tests_file = file_get_contents("../tests/Unit/json/test_load.json","w");
        $jsonObj = json_decode($tests_file, true);
        foreach ($jsonObj as $field => $value ) {
            $URL=$jsonObj[$field]["URL"];
            $key=$jsonObj[$field]["key"];
            $type=$jsonObj[$field]["type"];
            $threads=$jsonObj[$field]["threads"];
            $out_file="/var/www/html/your-project/tests/Unit/load_results/".$threads."_500ms_threads_".$field;

        

	        $results="";
	        if($type=="Json"){
                $annotation=$jsonObj[$field]["annotation"];
                $index=$jsonObj[$field]["tier"];
                print $annotation."\n";
                if($annotation!="NA" ){
                    $url='https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/saveSettingGet/default_annotation/'.$annotation;
                    $remotePage =$get_url->getUrl($url); //get the remote page
                }
                print $URL."\n";
                $index=$jsonObj[$field]["tier"];
                $results=$testing->testJson($URL,$this,$key,$index);
                file_put_contents($out_file, $results."\n", FILE_APPEND);
	        }
            if($type=="View"){
                $time_pre = microtime(true);
                try{
                    $testing->testContent($URL,$this,$key);
                    $testContent="PASSED";
                }
                
                
                catch(Exception $e) {
                    $testContent= 'FAILED: '.$URL.' does not contain correct content';
                    print 'FAILED: '.$URL.' does not contain correct content';
                }
                $time_post = microtime(true);
                $exec_time = $time_post - $time_pre;
                file_put_contents($out_file, $exec_time."\t".$testContent."\n", FILE_APPEND);

            }

		}
        #	print $response->getStatusCode()."\n";

        #	$this->assertEquals(200, $response->getStatusCode());

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

class Tests extends TestCase {


    
    public function testAssert($route,$test)
    {
        print "IN TESTASSERT ".$route."\n";
        $get_url=new TestUrl();
        $headers = $get_url->getUrl($route,'','',"code");
        print $headers."\n";
        $this->assertEquals(200, $headers);
    }
    public function testContent($route,$test,$key_content)
    {
        $get_url=new TestUrl();
        $content = $get_url->getUrl($route);
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

    public function testJson($route,$test,$file,$tier_index){

        $errors="";
        $string_key = file_get_contents($file);
        print ("string_key");
#        $string_key=str_replace("fr-s-bsg-onc-d.ncifcrf.gov\/clinomics_dev2\/public","localhost",$string_key);
#        $string_key=str_replace("https","http",$string_key);
#        print $string_key;
        $json_key=json_decode($string_key,true);
        print ($route."\n");


        $time_pre = microtime(true);
        $get_url=new TestUrl();
        $string_actual = $get_url->getUrl($route);
        $json_actual=json_decode($string_actual,true);
        $time_post = microtime(true);
        $exec_time = $time_post - $time_pre;
        
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


        return $exec_time."\t".$errors;

    }
    function getData_object($file,$json){
        if(strpos($file, 'QC') !== false)
            $json_obj= $json["qc_data"]["data"];
        else
            $json_obj= $json["data"];
        return $json_obj;
    }
}
