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
  protected $sys;
  public function __construct(Sys $log)
  {
    $this->sys = $log;
  }
}

interface Sys{}

class DB implements Sys {

}

$container = new \Laravel\Illuminate\Container();
$db = new Db();
$container->when(Log::class)->needs(Sys::class)->give(DB::class);
// var_dump($container);
var_dump($container->getInstance(Log::class ));
