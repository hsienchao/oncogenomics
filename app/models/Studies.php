<?php

class Studies extends Eloquent {
	protected $fillable = [];
    protected $table = 'studies';
	private $normal_num = 0;
	private $tumor_num = 0;
	/**
	 * View all studies.
	 *
	 * @return editStudy view page
	 */
	static public function getAllStudies() {
		$studies = null;
		if (Sentry::getUser() != null) {
			$user_id = Sentry::getUser()->id;
			$studies = Studies::where('user_id', '=', $user_id)->orWhere('is_public', '=', 1)->get();
		} else {
			$studies = Studies::where('is_public', '=', 1)->get();
		}
		return $studies;
	}

	/*	
	function __construct() {
		foreach ($this->samples as $sample) {
			if ($sample->tissue_cat == 'normal') $normal_num++;
			if ($sample->tissue_cat == 'tumor') $tumor_num++;
		}
	}
	*/

	public function sample_num() {
		//return count($this->samples);
		$res = DB::select("select count(*) as cnt from study_samples where study_id = ".$this->id);
		return $res[0]->cnt;
	}

	public function normal_sample_num() {
		$res = DB::select("select count(*) as cnt from study_samples where tissue_cat='normal' and study_id = ".$this->id);
		return $res[0]->cnt;
	}	

/*
	public function analyses() {
		return $this->hasMany('Analyses', 'study_id');
        }
*/
	public function analysis_num () {
		$res = DB::select("select count(*) as cnt from analysis where study_id = ".$this->id);
		return $res[0]->cnt;
	}


	public function kaplan_meier () {
		$res = DB::select("select count(*) as cnt from study_samples s1, survival s2 where s1.sample_id=s2.sample_id and s1.study_id = ".$this->id);
		return ($res[0]->cnt > 10)?'Y':'N';
	}

}
