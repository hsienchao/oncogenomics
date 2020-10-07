<?php

require '/mnt/webrepo/fr-s-bsg-onc-d/htdocs/clinomics_dev2/vendor/autoload.php';


class Create_keysTest extends TestCase {

    public function test()
    {
        
        Session::start();
        $login_type = 'nih_login';
        $this->call('POST', '/user/login',array('loginID' => "goldwebersa", 'password' => "AGMBOsu@2020",'login_type'=>$login_type));
        $keys_file = file_get_contents("./tests/json_keys.json","w");
        $jsonObj = json_decode($keys_file, true);
        foreach ($jsonObj as $field => $value ) {

            $URL=$jsonObj[$field]["URL"];
            $call=$jsonObj[$field]["call"];
            $file=$jsonObj[$field]["key"];

            $annotation=$jsonObj[$field]["annotation"];
            print $annotation."\n";
            print $URL."\n";
            if($annotation!="NA" )
                $this->call('GET',"/saveSettingGet/default_annotation/".$annotation);
#                $this->call('GET',"/saveSettingGet/default_annotation/avia");
            $response = $this->call($call, $URL);
            $json_string= $response->getContent();
            $json_file=fopen($file,"w");
            fwrite($json_file,$json_string);
 
        }

        
    }



}