<?php

namespace Laravel\Illuminate\Abstracts;

interface ContextualBindingBuilderAbstract {
  public function needs(string $abstract);
  public function give(string $implementation);
}