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

// var_dump(new Laravel\Test\File( new \Laravel\Test\Name() ));die;

interface A {
  
}

$name = new Laravel\Test\Name();
$name->name = 'zjb';
var_dump($name);

$container = new Laravel\Illuminate\Contaner();
$obj = $container->getInstance("Laravel\\Test\\File");
var_dump($obj);
$obj = $container->getInstance("Laravel\\Test\\File",["name"=>'123'  ]);
var_dump($obj);
