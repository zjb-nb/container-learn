<?php

namespace Symfony\HTTPFoundation;

use Symfony\HTTPFoundation\ParameterBag;
use Symfony\HTTPFoundation\ServerBag;
use Symfony\HTTPFoundation\FileBag;
use Symfony\HTTPFoundation\HeaderBag;

class Request
{
  public $query;  //get
  public $request; // post
  public $cookie;  // cookie
  public $server;  // server
  public $files;   // files
  public $content; //body
  public $header;

  //全局变量是一个数组
  public function __construct(
    $query = [],
    $request = [],
    $cookie = [],
    $server = [],
    $files = [],
    $content = []
  ) {
    $this->initialize($query, $request, $cookie, $server, $files, $content);
  }

  /**
   * 初始化
   */
  protected function initialize(
    $query = [],
    $request = [],
    $cookie = [],
    $server = [],
    $files = [],
    $content = []
  ) {
    $this->query = new ParameterBag($query);
    $this->request = new ParameterBag($request);
    $this->cookie = new ParameterBag($cookie);
    $this->server = new ServerBag($server);
    $this->files = new FileBag($files);
    $this->content = new ParameterBag($content);
    $this->header = new HeaderBag($this->server->getHeaders());
  }

  /**
   * 使用全局变量创建request实例
   */
  public static function createFromGlobals()
  {
    //用默认值生成request实例
    $request = self::createRequestFromFactory(
      $_GET,
      $_POST,
      $_COOKIE,
      $_SERVER,
      $_FILES
    );
    return $request;
  }

  private static function createRequestFromFactory($query, $request, $cookie, $server, $files, $content = [])
  {
    return new static($query, $request, $cookie, $server, $files, $content);
  }

  /**
   * 对已经实例化好的request对象重新赋值
   */
  protected function duplicate(
    $query = [],
    $request = [],
    $cookie = [],
    $server = [],
    $files = [],
    $content = []
  ) {
    $requestObj = clone $this;
    //进行参数覆盖
    if ($query !== []) {
      $requestObj->query = new ParameterBag($query);
    }
    if ($request !== []) {
      $requestObj->request = new ParameterBag($request);
    }
    if ($cookie !== []) {
      $requestObj->cookie = new ParameterBag($cookie);
    }
    if ($server !== []) {
      $requestObj->server = new ServerBag($server);
    }
    if ($files !== []) {
      $requestObj->files = new FileBag($files);
    }
    if ($content !== []) {
      $requestObj->query = new ParameterBag($content);
    }
    return $requestObj;
  }

  public function __clone()
  {
  }

  /**
   * 创建一个基于URI的请求配置实例，主要对server的变量改写
   */
  public static function create($uri, $method = 'GET', $parameters = [], $cookie = [], $server = [], $files = [], $content = null)
  {
    $server = array_replace([
      'SERVER_NAME' => 'localhost',
      'SERVER_PORT' => 80,
      'HTTP_HOST' => 'localhost',
      'HTTP_USER_AGENT' => 'Symfony',
      'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
      'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
      'REMOTE_ADDR' => '127.0.0.1',
      'SCRIPT_NAME' => '',
      'SCRIPT_FILENAME' => '',
      'SERVER_PROTOCOL' => 'HTTP/1.1',
      'REQUEST_TIME' => time(),
      'REQUEST_TIME_FLOAT' => microtime(true),
    ], $server);
    $server['PATH_INFO'] = '';
    $server['REQUEST_METHOD'] = strtoupper($method);
    /**
     * parse_url("http://www.baidu.com/web_path?a=1")
     * array(4) {
     * ["scheme"]=>
     *    string(4) "http"
     * ["host"]=>
     *    string(13) "www.baidu.com"
     * ["path"]=>
     *    string(9) "/web_path"
     * ["query"]=>
     *    string(3) "a=1"
     * }
     */
    $components = parse_url($uri);
    if (isset($components['host'])) {
      $server['SERVER_NAME'] = $components['host'];
      $server['HTTP_HOST'] = $components['host'];
    }

    if (isset($components['scheme'])) {
      if ('https' === $components['scheme']) {
        $server['HTTPS'] = 'on';
        $server['SERVER_PORT'] = 443;
      } else {
        unset($server['HTTPS']);
        $server['SERVER_PORT'] = 80;
      }
    }

    if (isset($components['port'])) {
      $server['SERVER_PORT'] = $components['port'];
      $server['HTTP_HOST'] .= ':' . $components['port'];
    }
    // http://user:pass@hostname/path
    if (isset($components['user'])) {
      $server['PHP_AUTH_USER'] = $components['user'];
    }

    if (isset($components['pass'])) {
      $server['PHP_AUTH_PW'] = $components['pass'];
    }

    if (!isset($components['path'])) {
      $components['path'] = '/';
    }

    //对方法进行处理
    switch (strtoupper($method)) {
      case 'POST':
      case 'PUT':
      case 'DELETE':
          if (!isset($server['CONTENT_TYPE'])) {
              $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
          }
          // no break
      case 'PATCH':
          $request = $parameters;
          $query = [];
          break;
      default:
          $request = [];
          $query = $parameters;
          break;
    }
    //url参数拼接 ?a=1&b=3
    $queryString = '';
    if (isset($components['query'])) {
        parse_str(html_entity_decode($components['query']), $qs);

        if ($query) {
            $query = array_replace($qs, $query);
            $queryString = http_build_query($query, '', '&');
        } else {
            $query = $qs;
            $queryString = $components['query'];
        }
    } elseif ($query) {
        $queryString = http_build_query($query, '', '&');
    }
    $server['REQUEST_URI'] = $components['path'].('' !== $queryString ? '?'.$queryString : '');
    $server['QUERY_STRING'] = $queryString;
    return self::createRequestFromFactory($query,$request,$cookie,$server,$files,$content);
  }
}


/**
 * 获取request实例的方法:
 * 1.new
 * 2.createFromGlobals
 * 3.利用clone机制 duplicate
 */
