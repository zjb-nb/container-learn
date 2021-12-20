<?php 
/*
 * @Author: your name
 * @Date: 2021-12-19 23:41:55
 * @LastEditTime: 2021-12-20 00:01:20
 * @LastEditors: your name
 * @Description: 打开koroFileHeader查看配置 进行设置: https://github.com/OBKoro1/koro1FileHeader/wiki/%E9%85%8D%E7%BD%AE
 * @FilePath: /workpath/laravel-container/package/laravel/Illuminate/Container.php
 */
namespace Laravel\Illuminate;

use ReflectionClass;
use ReflectionException;
use Laravel\Illuminate\Exception\BindException;
class Contaner {
  protected $buildstack= [];
  //对外暴露类，用于生成实例
  public function getInstance(string $class) {
    return $this->resolve($class);
  }
  
  //利用反射类生成实例
  protected function resolve(string $class) {
    //如果类实例化出错
    try{
      $reflection_class= new ReflectionClass($class);
    }catch(ReflectionException $e ){
      throw new BindException("类[$class]不存在",0,$e);
    }
    array_push($this->buildstack,$class);
    //如果类不能被实例化
    if( !$reflection_class->isInstantiable() ){
      $this->noInstantiable($class);
    }
    $class_construct= $reflection_class->getConstructor();
    if (is_null($class_construct)){
      array_pop($this->buildstack);
      return $reflection_class->newInstance();
    }
    //解析构造函数中的参数依赖
    $param = $class_construct->getParameters();
    // var_dump($param);die;
    $res = $this->depenedence($param);
    // var_dump($res);die;
    array_pop($this->buildstack);
    return $reflection_class->newInstance(...$res);
  }

  protected function depenedence(array $params){
    $res = [];
    foreach($params as $param) {
      $res[] = $this->resolve( $param->getClass()->name );
    }
    return $res;
  }

  protected function noInstantiable($class){
    if(!empty($this->buildstack)){
      $class = implode(",",$this->buildstack);
    }
    throw new BindException("类[$class]不能被实例化",0);
  }
}


