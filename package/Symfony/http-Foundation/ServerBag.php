<?php

namespace Symfony\HTTPFoundation;

use Symfony\HTTPFoundation\ParameterBag;

class ServerBag extends ParameterBag
{
  //组装header
  public function getHeaders()
  {
    //header存于$_Server中
    $headers = [];
    foreach ($this->parameters as $key => $value) {
      if (0 == strpos($key, 'HTTP_')) {
        $headers[substr($key, 5)] = $value;
      } elseif (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
        $headers[$key] = $value;
      }
    }
    if (isset($this->parameters['PHP_AUTH_USER'])) {
      $headers['PHP_AUTH_USER'] = $this->parameters['PHP_AUTH_USER'];
      $headers['PHP_AUTH_PW'] = $this->parameters['PHP_AUTH_PW'] ?? '';
    } else {
      //3中auth验证
      $authorizationHeader = null;
      if (isset($this->parameters['HTTP_AUTHORIZATION'])) {
        $authorizationHeader = $this->parameters['HTTP_AUTHORIZATION'];
      } elseif (isset($this->parameters['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authorizationHeader = $this->parameters['REDIRECT_HTTP_AUTHORIZATION'];
      }

      if (null !== $authorizationHeader) {
        if (0 === stripos($authorizationHeader, 'basic ')) {
          //basic 验证 base64_encode
          // Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
          $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
          if (2 == \count($exploded)) {
            [$headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']] = $exploded;
          }
        } elseif (empty($this->parameters['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader, 'digest '))) {
          // In some circumstances PHP_AUTH_DIGEST needs to be set
          //摘要
          $headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
          $this->parameters['PHP_AUTH_DIGEST'] = $authorizationHeader;
        } elseif (0 === stripos($authorizationHeader, 'bearer ')) {
          /*
               * XXX: Since there is no PHP_AUTH_BEARER in PHP predefined variables,
               *      I'll just set $headers['AUTHORIZATION'] here.
               *      https://php.net/reserved.variables.server
               */
          // token jwt
          $headers['AUTHORIZATION'] = $authorizationHeader;
        }
      }
    }

    if (isset($headers['AUTHORIZATION'])) {
      return $headers;
    }

    // PHP_AUTH_USER/PHP_AUTH_PW
    if (isset($headers['PHP_AUTH_USER'])) {
      $headers['AUTHORIZATION'] = 'Basic ' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
    } elseif (isset($headers['PHP_AUTH_DIGEST'])) {
      $headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
    }

    return $headers;
  }
}
