<?php 
/*
 * @Author: your name
 * @Date: 2021-12-19 23:43:12
 * @LastEditTime: 2021-12-20 00:05:43
 * @LastEditors: your name
 * @Description: 打开koroFileHeader查看配置 进行设置: https://github.com/OBKoro1/koro1FileHeader/wiki/%E9%85%8D%E7%BD%AE
 * @FilePath: /workpath/laravel-container/index.php
 */
require_once __DIR__."/vendor/autoload.php";



class Log {
  public $num ;
  public function __construct(MyTest $test)
  {
    $this->num =$test;
  }
}

class Test implements MyTest{
  public function sayTest(){echo "hi\n";}
}

interface MyTest{}

$test = new Test();var_dump($test);

$container =  \Laravel\Illuminate\Container::getInstance();
$container->bind('ddd',function() use($test) {
  return $test;
} );
// $container->instance('ddd',$test);

var_dump( $container->make('ddd') );

