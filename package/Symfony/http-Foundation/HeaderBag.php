<?php

namespace Symfony\HTTPFoundation;

use ArrayIterator;
use Symfony\HTTPFoundation\HeaderUtils;
use Traversable;

class HeaderBag implements \IteratorAggregate,\Countable
{
  protected const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  protected const LOWER = '-abcdefghijklmnopqrstuvwxyz';
  //由serverBag的getheader获取到header头部
  protected $headers = [];
  protected $cacheControl = [];

  public function __construct(array $headers = [])
  {
    foreach ($headers as $key => $values) {
      $this->set($key, $values);
    }
  }

  public function set($key, $values, $replace = true)
  {
    //将eky里面的所有字符转为小写
    //检查value里面是否有新值，有就新增或替换
    $key = strtr($key, self::UPPER, self::LOWER);
    if (\is_array($values)) {
      $values = array_values($values);
      if (true === $replace || !isset($this->headers[$key])) {
        $this->headers[$key] = $values;
      } else {
        $this->headers[$key] = array_merge($this->headers[$key], $values);
      }
    } else {
      if (true === $replace || !isset($this->headers[$key])) {
        $this->headers[$key] = [$values];
      } else {
        $this->headers[$key][] = $values;
      }
    }
    //cache-control用来配置本地资源是否缓存 和缓存过期时间
    //request: no-cache,no-store,max-age
    if ('cache-control' === $key) {
      $this->cacheControl = $this->parseCacheControl(implode(', ', $this->headers[$key]));
    }
  }
  
  public function count():int
  {
    return \count($this->headers);
  }

  public function getIterator():Traversable{
    return new ArrayIterator($this->headers);
  }


  /**
   * 调用工具类
   * 解析缓存配置
   */
  protected function parseCacheControl($header)
  {
    /**
     * $_SERVER['HTTP_cache_CONTROL']= 'max-age=300 ,public';
     * [
     * [max-age=>300],
     * [public]
     * ]
     */
    //按规律分割
    $parts = HeaderUtils::split($header, ',=');
    //重新组装
    /**
     * [max-age=>300,public=>true]
     */
    return HeaderUtils::combine($parts);
  }
  //当echo 时执行魔术方法，将header里面的参数全部转为字符串输出
  public function __toString()
  {
    if (!$headers = $this->all()) {
      return '';
    }

    ksort($headers);
    $max = max(array_map('strlen', array_keys($headers))) + 1;
    $content = '';
    foreach ($headers as $name => $values) {
      $name = ucwords($name, '-');
      foreach ($values as $value) {
        $content .= sprintf("%-{$max}s %s\r\n", $name . ':', $value);
      }
    }

    return $content;
  }

  /**
   * 获取header数组并转为小写
   */
  public function all()
  {
    //如果传值且第一个参数不为null，则返回单个 
    if (1 <= \func_num_args() && null !== $key = func_get_arg(0)) {
      return $this->headers[strtr($key, self::UPPER, self::LOWER)] ?? [];
    }
    return $this->headers;
  }
  public function keys()
  {
    return array_keys($this->all());
  }
  public function replace(array $parameter)
  {
    //不能是简单的替换还要修改大小写
    $this->headers = [];
    $this->add($parameter);
  }
  public function add(array $parameter)
  {
    foreach ($parameter as $k => $v) {
      $this->set($k, $v);
    }
  }

  public function get($key, $default = null)
  {
    $headers = $this->all((string) $key);

    if (!$headers) {
      return $default;
    }

    if (null === $headers[0]) {
      return null;
    }

    return (string) $headers[0];
  }
  //判断是否含有某个参数
  public function has($key)
  {
      return \array_key_exists(strtr($key, self::UPPER, self::LOWER), $this->all());
  }
  //判断是否有对应的值
  public function contains($key, $value)
  {
      return \in_array($value, $this->all((string) $key));
  }

  public function remove($key)
  {
      $key = strtr($key, self::UPPER, self::LOWER);

      unset($this->headers[$key]);

      if ('cache-control' === $key) {
          $this->cacheControl = [];
      }
  }
  //将header常见的日期格式化
  public function getDate($key, \DateTime $default = null)
  {
      if (null === $value = $this->get($key)) {
          return $default;
      }

      if (false === $date = \DateTime::createFromFormat(\DATE_RFC2822, $value)) {
          throw new \RuntimeException(sprintf('The "%s" HTTP header is not parseable (%s).', $key, $value));
      }

      return $date;
  }

  public function addCacheControlDirective($key, $value = true)
  {
      $this->cacheControl[$key] = $value;

      $this->set('Cache-Control', $this->getCacheControlHeader());
  }

  protected function getCacheControlHeader()
  {
      ksort($this->cacheControl);

      return HeaderUtils::toString($this->cacheControl, ',');
  }
}
