<?php
namespace Laravel\Test;
use Laravel\Test\Name;
class File {
  private $name;
  private $names;
  public function __construct(string $name='hhh',Name $names)
  {
    $this->name=$name;
    $this->names=$names;
  }

}