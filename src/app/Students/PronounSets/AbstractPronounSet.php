<?php

namespace App\Students\PronounSets;

abstract class AbstractPronounSet
{
    abstract public function getKey(): string;

    abstract public function getLabel(): string;
}
