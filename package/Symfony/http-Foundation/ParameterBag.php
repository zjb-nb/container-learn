<?php 
namespace Symfony\HTTPFoundation;

use ArrayIterator;
use Traversable;

//主要用于管理全局变量
class ParameterBag implements \IteratorAggregate,\Countable
{
  protected $parameters;
  public function __construct($parameters)
  {
    $this->parameters = $parameters;
  }
  
  public function all()
  {
    return $this->parameters;
  }

  public function keys()
  {
    return array_keys($this->parameters);
  }

  public function get($key,$default='')
  {
    return $this->parameters[$key] ?? $default;
  }

  public function set($key,$value){
    $this->parameters[$key]= $value;
  }

  public function add($parameters)
  {
    $this->parameters = array_replace($this->parameters,$parameters);
  }

  public function remove($key)
  {
    unset($this->parameters[$key]);
  }

  public function has($key):bool
  {
    return isset($this->parameters[$key]);
  }

  public function replace($parameters)
  {
    $this->parameters = $parameters;
  }
  /**
   * 返回key对应的所有字母 'name'=>'123abc' 返回 abc
   */
  public function getAlpha($key, $default = '')
  {
      return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default));
  }
  
  /**
   * 返回所有字母加数字
   */
  public function getAlnum($key, $default = '')
  {
      return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default));
  }
  //过滤加减号
  public function getDigits($key, $default = '')
  {
      return str_replace(['-', '+'], '', $this->filter($key, $default, \FILTER_SANITIZE_NUMBER_INT));
  }
  /**
   * filter_var用法 https://www.w3school.com.cn/php/func_filter_var.asp
   */
  public function filter($key, $default = null, $filter = \FILTER_DEFAULT, $options = [])
  {
      $value = $this->get($key, $default);

      // Always turn $options into an array - this allows filter_var option shortcuts.
      if (!\is_array($options) && $options) {
          $options = ['flags' => $options];
      }

      // Add a convenience check for arrays.
      if (\is_array($value) && !isset($options['flags'])) {
          $options['flags'] = \FILTER_REQUIRE_ARRAY;
      }

      return filter_var($value, $filter, $options);
  }
  
  //实现让类实现迭代器接口，当foreach该实例时返回
  public function getIterator(): Traversable
  {
    return new ArrayIterator($this->parameters); 
  }
  //实现Countable接口
  public function count():int{
    return count($this->parameters);
  }
}