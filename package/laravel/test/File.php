<?php
namespace Laravel\Test;
use Laravel\Test\Name;
class File {
  private $name;
  public function __construct(Name $name)
  {
    $this->name=$name;
  }

}