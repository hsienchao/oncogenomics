<?php


$unit_tests_file=file_get_contents("/var/www/html/your-project/tests/Unit/json/tests.json","w");
$test_load_file="/var/www/html/your-project/tests/Unit/json/test_load.json";
        $get_url=new TestUrl();

$jsonObj = json_decode($unit_tests_file, true);
$login_type = 'nih_login';
$loginUrl="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public/user/login";
$loginFields =array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type);
$login = $get_url->getUrl($loginUrl, 'post', $loginFields); //login to the site
$threads=[1,5,10,15,20,30,40,50];
foreach ($jsonObj as $field => $value ) {
    $URL="https://fr-s-bsg-onc-d.ncifcrf.gov/clinomics_dev2/public".$jsonObj[$field]["URL"];
    $call=$jsonObj[$field]["call"];
	$type=$jsonObj[$field]["type"];
	$key=$jsonObj[$field]["key"];

    if(isset([$field]["tier"])){
       $tier=$jsonObj[$field]["tier"];
    }
   else{
    $tier="";
   }

    if(isset([$field]["annotation"])){
       $annotation=$jsonObj[$field]["annotation"];
    }
   else{
    $annotation="";
   }

    foreach($threads as $thread) {
	    $t[$field]=array('URL'=> $URL, 'call'=> $call,"threads"=>$thread,"type"=>$type,"key"=>$key,"tier"=>$tier,"annotation"=>$annotation);
	    $fp = fopen($test_load_file, 'w');
	    print $field." ".$thread."\n";
	    file_put_contents($test_load_file, json_encode($t));
	    unset($t);
	    fclose($fp);
    
    	exec("perl /var/www/html/your-project/tests/Unit/Load_test.pl -t ".$thread);
    	if($thread>=5||$thread<=15){
    		sleep(5);
    	}
    	if($thread>=20||$thread<=30){
    		sleep(60);
    	}
    	if($thread==40){
    		sleep(90);
    	}
    	if($thread==50){
    		sleep(120);
    	}
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
