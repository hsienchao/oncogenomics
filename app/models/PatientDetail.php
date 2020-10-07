<?php

class PatientDetail extends Eloquent {
	protected $fillable = [];
	protected $table = 'patient_details';

	static public function updateData ($patient_id, $old_key, $new_key, $value) {
		DB::update('update patient_details set attr_name = ?,  attr_value = ? where patient_id= ? and attr_name = ?', array($new_key, $value, $patient_id, $old_key));
	}

	static public function addData ($patient_id, $key, $value) {
		DB::insert('insert into patient_details values(?,?,?)', array($patient_id, $key, $value));
	}

	static public function deleteData ($patient_id, $key) {
		DB::delete('delete patient_details where patient_id = ? and attr_name = ?', array($patient_id, $key));
	}

	static public function getPatientDetailByProject($project_id) {
		$sql = "select d.* from patient_details d";
		if (strtolower($project_id) != "any" && strtolower($project_id) != "null")
			$sql = "$sql,project_patients p where p.project_id=$project_id and d.patient_id=p.patient_id";
		$rows = DB::select($sql);
		return $rows;
	}

	static public function getPatientDetailByPatientID($patient_id) {
		return DB::select("select * from patient_details where patient_id='$patient_id'");		
	}

}
