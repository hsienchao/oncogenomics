<?php

class UserSetting extends Eloquent {
	public $timestamps = false;
	public $incrementing = false;
	protected $fillable = [];
	protected $table = 'user_setting';
	protected $primaryKey = null;	

	static public function getSetting($attr_name, $to_obj=true, $system=false) {
		if ($system)
			$logged_user_id = 1;
		else {
			$logged_user = User::getCurrentUser();
			if ($logged_user == null)
				return null;
			$logged_user_id = $logged_user->id;
		}
		$settings = UserSetting::where('user_id', $logged_user_id)->where('attr_name', $attr_name)->get();		
		foreach($settings as $setting) {
			if ($setting->attr_value == null || $setting->attr_value == "null")
				break;
			if ($to_obj)
				return json_decode($setting->attr_value);
			return $setting->attr_value;
		}
		$setting = Config::get("onco.$attr_name");
		if ($setting != null) {
			if ($to_obj)
				return (object)$setting;
			return $setting;
		}
		return null;
	}

	static public function getDescriptions($type) {
		$user_filter_list = array();
		try {
			$user_id = Sentry::getUser()->id;
			if ($type == "rnaseq")
				$type = "rnaseq','somatic','germline";
			$user_gene_list = DB::select("select list_name, description from user_gene_list where (user_id=$user_id or ispublic='Y') and type in ('$type','all')");
		} catch (Exception $e) {
			$user_gene_list = DB::select("select list_name, description from user_gene_list where ispublic='Y' and type in ('$type','all')");
		}
		
		foreach ($user_gene_list as $list) {
			$user_filter_list[$list->list_name] = $list->description;
		}
		return $user_filter_list;
	}

	static public function saveSetting($attr_name, $data, $json=true, $system=false) {
		if ($system)
			$logged_user_id = 1;
		else {
			$logged_user = User::getCurrentUser();
			if ($logged_user == null)
				return null;
			$logged_user_id = $logged_user->id;
		}
		
		try {				
				DB::beginTransaction();
				UserSetting::where('user_id', '=', $logged_user_id)->where('attr_name', '=', $attr_name)->delete();
				$setting = new UserSetting;
				$setting->user_id = $logged_user_id;
				$setting->attr_name = $attr_name;
				if ($json)
					$setting->attr_value = json_encode($data, JSON_NUMERIC_CHECK);
				else
					$setting->attr_value = $data;
				$setting->save();
				DB::commit();
				return "Success";
		} catch (\PDOException $e) { 
				return $e->getMessage();
				DB::rollBack();           
		}
		
	}
}
