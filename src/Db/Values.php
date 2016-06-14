<?php
namespace Axelmedia\Db;

class Values extends \ArrayObject implements \JsonSerializable {
    public function __construct(
        $input = null,
        $flags = self::ARRAY_AS_PROPS,
        $iterator_class = 'ArrayIterator'
    )
    {
        if (is_array($input)) {
            foreach($input as $key => $val) {
                $this->__set($key, $val);
            }
        }

        return $this;
    }

    public function __set($name, $value)
    {
        if (is_array($value) || is_object($value)) {
            $object = new self($value);
            $this->offsetSet($name, $object);
        } else {
            $this->offsetSet($name, $value);
        }
    }

    public function __get($name)
    {
        if ($this->offsetExists($name)) {
            return $this->offsetGet($name);
        } elseif (array_key_exists($name, $this)) {
            return $this[$name];
        } else {
            throw new \InvalidArgumentException(sprintf('$this have not prop `%s`', $name));
        }
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this);
    }

    public function __unset($name)
    {
        unset($this[$name]);
    }

    public function __toString()
    {
        $items = array();
        foreach($this as $val) {
            if (!is_array($val) && !is_object($val)) {
                $items[] = $val;
            }
        }

        if (!empty($items)) {
            return implode(',', $items);
        }
    }

    public function jsonSerialize() {
        return $this;
    }
}
