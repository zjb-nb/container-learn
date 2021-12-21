<?php  

namespace Laravel\Illuminate\Support;

class Arr {
  //包装成数组
  public static function wrap($value):array {
    if( is_null($value) ) return [];
    return is_array($value)?$value:[$value];
  }
}
