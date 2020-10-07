<?php


class UserSettingController extends BaseController {


	/**
	 * View user setting.
	 *
	 * @return user setting page
	 */
	public function viewSetting() {
		try {
			$user_id = Sentry::getUser()->id;
		} catch (Exception $e) {
			return Redirect::to('/login');
		}
		$current_user = User::find($user_id);
		$user_name = $current_user->user_profile()->first_name." ".$current_user->user_profile()->last_name;
		if (User::isSuperAdmin())
			$user_gene_list = UserGeneList::orderBy('LIST_NAME')->get();
		else
			$user_gene_list = UserGeneList::where('user_id', '=', $user_id)->orderBy('LIST_NAME')->get();
		$gene_list = array();
		foreach ($user_gene_list as $list) {
			$content = preg_replace("/\s/","\n", $list->gene_list);
			$desc = $list->description;
			$ispublic = $list->ispublic;
			$type = $list->type;
			$user = User::find($list->user_id);
			if ($user == null)
				$user = User::find(1);
			$id = $user->id;
			$name = $user->user_profile()->first_name." ".$user->user_profile()->last_name;
			$gene_list[$list->list_name] = array($content, $desc, $ispublic, $type, $id, $name);
		}
		if (count($gene_list) == 0)
			$gene_list_json = "{}";
		else
			$gene_list_json = json_encode($gene_list);

		$projects = User::getCurrentUserProjects();
		$default_project = UserSetting::getSetting("default_project", false);
		$default_annotation = UserSetting::getSetting("default_annotation", false);
		$high_conf = UserSetting::getSetting("high_conf", true, true);
		Log::info(json_encode($default_project));
		//Log::info($default_annotation);
		return View::make('pages/viewUserSetting', ['high_conf' => $high_conf, 'default_project' => $default_project, 'default_annotation' => $default_annotation, 'projects' => $projects, 'gene_list' => $gene_list_json, 'user_id' => $user_id, 'user_name' => $user_name]);
	}
	public function syncClinomics() {
		Log::info("SYNC CLINOMICS NOW");
		$path=getcwd();
		$path=substr($path, 0, -6);
		$fullpath=$path."app/scripts/backend/syncProcessedResults.sh";
		Log::info($fullpath);
		if (file_exists($fullpath)) {
   			 Log::info( "The file $fullpath exists");
   				exec($fullpath." clinomics all all",$output);
				Log::info($output);
		} else {
    		Log::info( "The file $fullpath does not exists");
    	}


	}
	public function saveSetting($attr_name) {
		$data = Input::all();
		Log::info(json_encode($data));
		UserSetting::saveSetting($attr_name, $data);				
	}

	public function saveSystemSetting($attr_name) {
		$data = Input::all();
		return UserSetting::saveSetting($attr_name, $data, true, true);
	}

	public function saveConfig($attr_name) {
		if (!User::isSuperAdmin())
			return "Not admin";
		$data = Input::all();
		Log::info($attr_name);
		Log::info(json_encode($data));
		Config::set("onco.$attr_name", $data);
		return "Success";		
	}

	public function saveSettingGet($attr_name, $attr_value) {
		UserSetting::saveSetting($attr_name, $attr_value, false);
		return "Success";				
	}

	public function saveGeneList() {
		try {
			$user_id = Sentry::getUser()->id;
		} catch (Exception $e) {
			return "NoUserID";
		}
		$data = Input::all();
		try {
			DB::beginTransaction();
			if (User::isSuperAdmin()) {
				UserGeneList::truncate();
				DB::table('user_gene_list_dtl')->truncate();
			}
			else {
				UserGeneList::where('user_id', '=', $user_id)->delete();
				DB::table('user_gene_list_dtl')->where('user_id', $user_id)->delete();
			}
			foreach ($data as $list_name => $gene_list) {
				$userGeneList = new UserGeneList;
				$userGeneList->user_id = $gene_list[4];
				$userGeneList->list_name = $list_name;				
				$content = preg_replace("/\R/"," ", $gene_list[0]);
				$userGeneList->gene_list = $content;
				$userGeneList->description = $gene_list[1];
				if ($userGeneList->description == "")
					$userGeneList->description = $userGeneList->list_name;
				$userGeneList->ispublic = $gene_list[2];
				$userGeneList->type = $gene_list[3];
				$userGeneList->save();
				$genes = explode(' ', $content);
				foreach ($genes as $gene) {
					if ($gene != "")
						DB::table('user_gene_list_dtl')->insert(['list_name' => $list_name, 'gene' => $gene, 'user_id' => $gene_list[4], 'type' => $gene_list[3]]);
				}
			}
			DB::commit();
			Cache::forget("gene_list");
			return "Success";
		} catch (\PDOException $e) { 
			return $e->getMessage();
			DB::rollBack();           
		}		
	}

	
}
