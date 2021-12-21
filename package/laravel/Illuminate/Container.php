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

use Exception;
use ReflectionClass;
use ReflectionException;
use Laravel\Illuminate\Support\Arr;
use Laravel\Illuminate\ContextualBindingBuilder;
use Laravel\Illuminate\Exception\BindException;
use Laravel\Illuminate\Exception\LogicException;
use ReflectionParameter;

class Container {
  //构建栈
  protected $buildstack= [];
  
  //外部依赖队列
  protected $with= [];
  
  //映射关系 别名 => 别名/真实类名
  protected $aliases = [];

  //绑定上下文映射关系 ['Log'=>['Sys接口类'=>'DB接口实现类']]
  protected $contextual = [];
  //对外暴露类，用于生成实例
  public function getInstance(string $class,array $parameter=[]) {
    $class = $this->getAlias($class);
    $concrete= $this->getConcrete($class);
    $this->with[] = $parameter;
    return $this->resolve($concrete);
  }

  // protected function isBuildable(string $concrete,string $abstract):bool{
  //   return $concrete===$abstract;
  // }

  /**
   * 获取对应抽象类型的实例名称
   */
  protected function getConCrete(string $abstract):string{
    //如果有抽象类/接口 对应的实例的实现类 则返回该实现类
    if( !is_null( $get=$this->getContextualConcrete($abstract) ) ){
      return $get;
    }
    return $abstract;
  }

  protected function getContextualConcrete(string $abstract){
    if( !is_null($find = $this->findContextualBindings($abstract)) ){
      return $find;
    }
    return null;
  }

  protected function findContextualBindings(string $abstract){
    return $this->contextual[end($this->buildstack)][$abstract] ?? null;
  }


  /**
   * 1.传入的依赖想要被外部覆盖怎么办？ 
   * getInstance("Laravel\\Test\\File",["Laravel\\Test\\Name"=>$name])
   * getInstance("Laravel\\Test\\File",["name"=>$name])
   * 2.当传入的不是依赖而是基础类型怎么办？__construct(string $a)即参数不是一个依赖
   */
  //利用反射类生成实例
  protected function resolve(string $class) {
    //**判断是否可实例化**
    //如果类实例化出错
    try{
      $reflection_class= new ReflectionClass($class);
    }catch(ReflectionException $e ){
      throw new BindException("类[$class]不存在",0,$e);
    }
    array_push($this->buildstack,$class);
    //如果类不能被实例化怎么办？A：这里是抛出异常并一起报告所有的不能被实例化的类
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

    // 抛出异常，并将其移除构建栈
    //以为构造函数的依赖(interface/abstract/private __construct)不能被实例化，该类也不能被实例化
    try{
      $res = $this->depenedence($param);
    }catch(BindException $e){
      array_pop($this->buildstack);
      throw $e;
    }
   
    array_pop($this->buildstack);
    return $reflection_class->newInstance(...$res);
  }
  //解析构造函数的依赖
  protected function depenedence(array $params){
    $res = [];
    foreach($params as $param) {
      // param Instanceof ReflectionParameter
      //这里进行外部依赖的覆盖
      if(  $this->hasParameterOver($param) ){
        //如果有外部依赖则直接替换
        $res[] = $this->getParameterOverride($param);
        continue;
      }
      //对构造函数是否是有默认值或不是依赖即param->getClass is NULL
      //没有依赖覆盖的肯定使用了默认值
      if( !$param->getClass() ){
        $res[] =  $this->resolvePrimitive($param);
        continue;
      }
      

      $res[] = $this->getInstance( $param->getClass()->name );

    }
    return $res;
  }
  //设置别名
  public function setAliase(string $abstract,string $alias){
    if($abstract == $alias){
      throw new LogicException("[$abstract] is [$alias] itself");
    }
    $this->aliases[$alias] = $abstract;
  }
  //递归获取真实类名
  protected function getAlias(string $abstract){
    if(!isset($this->aliases[$abstract])){
      return $abstract;
    }
    return $this->getAlias( $this->aliases[$abstract] );
  } 

  //外部依赖覆盖，我们这里规定外部依赖是以键值对的形式传入
  // 例如['Name'=>new Name(123)]，所以获取名很重要
  //getInstance("Laravel\\Test\\File",["name"=>$name])
  //但是with也是一颗树
  protected function hasParameterOver(ReflectionParameter $parameter):bool {
    return array_key_exists(
      $parameter->getName(),$this->getLastParameteOverride()
    );
  }
  //获取当前传入实例的依赖数组
  protected function getLastParameteOverride():array {
    return count($this->with) ? end($this->with) : [];
  }
  //获取依赖的覆盖参数,即返回数组的键值
  protected function getParameterOverride(ReflectionParameter $parameter) {
    return ($this->getLastParameteOverride())[$parameter->getName()];
  }
  /**
   * 解决基础类型参数，若没有进行外部依赖覆盖，则必有参数，否则抛出异常
   */
  protected function resolvePrimitive(ReflectionParameter $parameter ){
    if( $parameter->isDefaultValueAvailable() ){
      return $parameter->getDefaultValue();
    }
    $this->unresolvePrimitive($parameter);
  }
  //todo抛出异常，基础类型参数没有默认值
  protected function unresolvePrimitive(ReflectionParameter $parameter):Exception{
    // var_dump( $parameter->getDeclaringClass()->getName() );die; string(17) "Laravel\Test\File"
    $message = "parameter:".$parameter->getName()." in class".$parameter->getDeclaringClass()->getName();
    throw new BindException($message);
  }
  //判断实例是否可实例化
  protected function noInstantiable($class){
    if(!empty($this->buildstack)){
      $class = implode(",",$this->buildstack);
    }
    throw new BindException("类[$class]不能被实例化",0);
  }
  //绑定接口到实现类的映射 container->when(LOG)->needs(Sys)->give(DB)
  public function when(string $class):ContextualBindingBuilder{
    $aliases = [];
    foreach(Arr::wrap($class) as $c){
      $aliases[] = $this->getAlias($c);
    }
    //构建上下文实例
    return new ContextualBindingBuilder($this,$aliases);
  }
  //绑定上下文映射关系
  //['Log'=>['Sys(interface)'=>'DB'(interface的实现)]]
  /**
   * @concrete 类名
   * @abstracts 依赖 interface
   * @implementation 接口的实现类
   */
  public function addContextualBinding($concrete,$abstracts,$implemention):void{
    $this->contextual[$concrete][$this->getAlias($abstracts)] = $implemention;
  }
}

/**
 * 解析依赖遇到的问题：
 * 1.如果传入的依赖没有约束，那么使用构造函数获取构造参数时，会把参数变量名作为类的名称去实例化抛出异常，
 * 我们引入参数with，来覆盖未作约定的参数
 * 2.如果传入的是员数据类型或其他类型，我们引入resolvePrimitive，如果形参有默认值则赋予默认值，否则抛出异常
 * 3.当外部依赖为接口时，实例化会报出无法被实例化的异常，此时我们对外提供when方法，
 * 引入外部类ContextualBindingBuilder提供needs和give接口来绑定interface和实例化类的映射关系，
 * 当我们实例化类时会先判断该抽象类有没有实例化的类来解决问题
 * 
 * 别名：
 * 1.引入别名，存放在aliases中
 * 
 */
