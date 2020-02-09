<?php

namespace Neskodi\SSHCommander\Traits\ArrayBehavior;

trait ProvidesArrayAccess
{
    protected $items = [];

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->items)) {
            return $this->items[$offset];
        }

        return null;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }
}
