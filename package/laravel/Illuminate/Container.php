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

use Closure;
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
  
  //存放实例
  protected static $instance = null;
  //托管外部实例
  protected $instances = [];
  //托管监听器回调
  protected $reBoundCallBack = []; 
  
  //通过bind方法绑定 ['ddd'=>['concrete'=>'']]
  protected $bindings = [];
  //判断类是否被解析过
  protected $resolved = [];

  protected $extends = [];

  //对外暴露类，用于生成实例
  public function make(string $class, array $parameter=[]){
    return $this->resolve($class,$parameter);
  }


  protected function resolve(string $abstract,array $parameter=[]) {
    $abstract = $this->getAlias($abstract);
    //如果是之前就交给容器管理且没有进行上下文绑定，当用instance时传入，若存过则直接返回
    if( isset($this->instances[$abstract]) && is_null($this->getContextualConcrete($abstract)) ){
      return $this->instances[$abstract];
    }
    //获取抽象类对应的实例
    $concrete= $this->getConcrete($abstract);
    $this->with[] = $parameter;
    // 判断是否可实例化 | 接口
    if( $this->isBuildable($abstract,$concrete )  ){
      $obj= $this->build($concrete);
    }else{
      $obj= $this->make($concrete);
    }

    foreach($this->getExtends($abstract) as $closure ){
      //第一个参数是实例
      $obj = $closure($obj,$this);
    }

    if($this->isShared($abstract) ){
      $this->instances[$abstract] = $obj;
    }

    $this->resolved[$abstract] = true;
    array_pop($this->with);
    return $obj;
  }

  protected function getExtends($abstract):array {
    $abstract = $this->getAlias($abstract);
    return $this->extends[$abstract] ?? [];
  }

  protected function isShared(string $abstract){
    return isset($this->instances[$abstract]) || ( 
      isset($this->bindings[$abstract]) && $this->bindings[$abstract]['share']
    );
  }

  protected function isBuildable($abstract,$concrete):bool{
    return $abstract===$concrete || $concrete instanceof Closure;
  }

  /**
   * 但是一般一个类在应用程序中只会实例化一次，所以需要我们把他做成单例
   * 由于PHP是单线程应用，所以是现场安全的
   * 但是如果使用多线程扩展，就需要注意使用double check来获取单例
   */
  public static function getInstance():Container{
    if(is_null(static::$instance)){
      static::$instance = new static;
    }
    return static::$instance;
  } 



  /**
   * 获取对应抽象类型的实例名称
   */
  protected function getConCrete(string $abstract){
    //如果有抽象类/接口 对应的实例的实现类即绑定过上下文关系 则返回该实现类
    if( !is_null( $get=$this->getContextualConcrete($abstract) ) ){
      return $get;
    }
    //如果是通过bind绑定则改为执行回调函数
    if(isset($this->bindings[$abstract])){
      return $this->bindings[$abstract]['concrete'];
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
  //利用反射类构造实例
  protected function build( $class) {
    if( $class instanceof Closure ){
      return $class($this,$this->getLastParameteOverride());
    }
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
      $res = $this->resolveDepenedence($param);
    }catch(BindException $e){
      array_pop($this->buildstack);
      throw $e;
    }
   
    array_pop($this->buildstack);
    return $reflection_class->newInstance(...$res);
  }
  //解析构造函数的依赖
  protected function resolveDepenedence(array $params){
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
      

      $res[] = $this->make( $param->getClass()->name );

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

  /**
   * 外部依赖覆盖，我们这里规定外部依赖是以键值对的形式传入
   *  例如['Name'=>new Name(123)]，所以获取名很重要
   *getInstance("Laravel\\Test\\File",["name"=>$name])
   *但是with也是一颗树
   */
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

  /**
   * 之前都是由容器来实例化类，如果想要让容器管理已经实例化好的类怎么办
   */
  public function instance(string $abstract,$concrete){
    //这里我们约定不适用别名
    $abstract = $this->getAlias($abstract);

    //判断是否曾经被绑定过
    if( $this->bound($abstract) ) {
      //如果被绑定过则执行监听器
      $this->reBound($abstract);
    }
    $this->instances[$abstract] = $concrete;
  }
  //判断 abstract是否被绑定过
  protected function bound(string $abstract):bool{
    return isset($this->instances[$abstract]);
  }
  /**
   * 监听是否被重复绑定
   */
  public function reBinding(string $abstract,Closure $callback){
    $this->reBoundCallBack[$abstract=$this->getAlias($abstract)][] = $callback;
    if( $this->bound($abstract) ){
      return $this->make($abstract);
    }
  }
  //执行重复绑定的监听器，即执行回调函数
  protected function reBound(string $abstract){
    $instance = $this->make($abstract);
    foreach($this->getReBoundCallbacks($abstract) as $callback ){
      call_user_func($callback,$this,$instance);
    }
  }
  //获取对应的所有的回调
  protected function getReBoundCallbacks(string $abstract):array{
    return isset( $this->reBoundCallBack[$abstract] )?$this->reBoundCallBack[$abstract]:[];
  }
  /**
   * 重复绑定监听器执行target的method方法
   * 也就是说额外给Log类的重复绑定时，增加一个回调函数，这个回调函数是其他类的某个方法
   * $container->refresh('ddd',new Test(),'sayTest');
   * (实例，目标实例，目标实例对应的方法)
   */
  public function refresh(string $abstract,$target,$method){
    //这是因为
    $this->reBinding($abstract,function($container,$instance) use($target,$method){
      $target->{$method}($instance);
    });
  }
  
  /**
   * 绑定实例与闭包，并在make时返回闭包执行的结果
   * 如果通过 bind绑定则不能通过 instance绑定
   */
  public function bind(string $abstract,$concrete = null ,bool $share=false){
    $this->dropBinding($abstract);
    if(is_null($concrete)){
       $concrete=$abstract;
    }
    
    if( !$concrete instanceof Closure ){
      $concrete = $this->getClosure($abstract,$concrete);
    }
    //若concrete是一个回调函数则建立键值对
    $this->bindings[$abstract] =compact('concrete','share');
    //判断是否被解析过
    if( $this->resolved($abstract) ){
      $this->reBound($abstract);
    }

  }
  //如果没有被绑定 (instance)过才会被执行并不会被覆盖
  public function bindIf(string $abstract,$concrete=null,bool $share=false):void{
    if( !$this->bound($abstract)  ){
      $this->bind($abstract,$concrete,$share);
    }
  }
  //将abstract绑定到instance，即注入实例到容器里面
  public function singleton(string $abstract,$concrete=null){
    $this->bind($abstract,$concrete,true);
  }

  /**
   * 顾名思义，扩展生成实例，下面是直接暴力替换
   * $container->extends(Log::class,function(){
   * return new Test();
   * });
   * 
   */
  public function extends(string $abstract,Closure $closure){
    $abstract = $this->getAlias($abstract);
    //instance保存的是容器对外保管的实例
    if( isset($this->instances[$abstract]) ){
      //即用回调函数覆盖
      $this->instances[$abstract] = $closure($this->instances[$abstract],$this);
      $this->reBound($abstract);
    }else {
      $this->extends[$abstract][] = $closure;
      //判断是否被解析过，被解析过就意味着又一次绑定
      if( $this->resolved($abstract) ){
        $this->reBound($abstract);
      }
    }
  }

  //判断实例是否被解析
  protected function resolved(string $abstract){
    $abstract = $this->getAlias($abstract);
    return isset($this->resolved[$abstract] ) || isset($this->instances[$abstract] );  
  }
  //解绑
  protected function dropBinding(string $abstract):void {
    //使用bind绑定时不能是instance绑定也不能是别名
    unset( $this->instances[$abstract],$this->aliases[$abstract] );
  }

  protected function getClosure(string $abstract,$concrete):Closure{
    return function(Container $container,$parameters=[]) use ($abstract,$concrete){
      if( $abstract===$concrete ){
        return $this->make($concrete);
      }
      
      $container->resolve($concrete,$parameters);
    };
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
 * 
 * 别名：
 * 1.引入别名，存放在aliases中
 * 
 * 单例
 * 1.引入getInstance实现单例模式
 * 
 * 托管外部实例
 * 1.引入instance让容器能托管外部实例，且在make时将对应的直接返回
 * 2.当重复绑定时，我们使用reBinding将重复绑定发生的行为交给使用者决定
 * 
 * 
 */
