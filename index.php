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


use Symfony\HTTPFoundation\Request;

$uri ="http://www.baidu.com:8080/web_path?a=1";
$_SERVER['HTTP-cache-CONTROL']= 'max-age=300 ,public';

$request = new Request($_GET,$_POST,$_COOKIE,$_SERVER,$_FILES);
echo $request->header;
die;
$request = Request::create($uri,'GET',['name'=>'zjb']);
var_dump($request->server->getHeaders());

