<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;
use Illuminate\Database\Eloquent\Model;

class SampleAnnotationBiomaterial extends Model {
   protected $table       = 'sample_annotation_biomaterial';
   protected $primary_key = NULL;
   public    $timestamps  = false;
   public function SampleAnnotation () {
      return $this->belongsToMany('App\Sample_Annotation');
   }
}
