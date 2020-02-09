<?php

namespace Neskodi\SSHCommander\Traits\ArrayBehavior;

trait ProvidesCount
{
    protected $items = [];

    public function count()
    {
        return count($this->items);
    }
}
