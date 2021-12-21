<?php 

namespace Laravel\Illuminate;

use Laravel\Illuminate\Abstracts\ContextualBindingBuilderAbstract as AbstractBuilder;
use Laravel\Illuminate\Container;
use Laravel\Illuminate\Support\Arr;

class ContextualBindingBuilder implements AbstractBuilder {
  protected $container;
  protected $concreate;
  protected $needs;
  /**
   * @container 容器
   * @concreate 类名/类名集合
   */
  public function __construct(Container $container, $concreate)
  {
    $this->container = $container;
    $this->concreate = $concreate;
  }
  /**
   * @abstracts 依赖
   */
  public function needs(string $abstracts):ContextualBindingBuilder{
    $this->needs = $abstracts;
    return $this;
  }
  /**
   * @implementation 接口的实现类
   */
  public function give(string $implementation):void{
    foreach( Arr::wrap($this->concreate) as $concreate ){
      $this->container->addContextualBinding($concreate,$this->needs,$implementation);
    }
  }

}