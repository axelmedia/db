<?php
namespace Axelmedia\Db;

class Column implements \ArrayAccess, \IteratorAggregate
{
    private $table;

    private $raw = array();
    private $data = array();

    private $formatter;
    private $validator;

    public function __construct($table, $raw)
    {
        if(!($table instanceof \Axelmedia\DB\Table)) {
            throw new \Exception('Table is not object.');
        }

        $this->table = $table;

        if (is_array($raw)) {
            $this->raw = $raw;
        }

        $requires = $primaries = $indicies = array();

        $data = array(
            'name' => $raw['Field'],
            'comment' => (!empty($raw['Comment']) ? $raw['Comment'] : null),
            'type' => $this->correctType($raw['Type']),
            'length' => $this->correctLength($raw['Type']),
            'unsigned' => $this->correctUnsigned($raw['Type']),
            'default' => $this->correctDefault($raw['Default'], $raw['Extra']),
            'charset' => $this->correctCharset($raw['Collation']),
            'require' => $this->correctRequire($raw['Null']),
            'primary' => $this->correctPrimary($raw['Key']),
            'index' => $this->correctIndex($raw['Key']),
            'auto_increment' => $this->correctAutoIncrement($raw['Extra']),
        );

        if (!empty($data['comment']) && !empty($data['charset'])) {
            $charset = str_replace('mb4', '', $data['charset']);
            $data['comment'] = mb_convert_encoding(
                $data['comment'],
                mb_internal_encoding(),
                $charset
            );
        }

        $this->data = $data;
    }

    public function get($key = null)
    {
        if (empty($key)) {
            return $this->data;
        } else {
            return $this->offsetGet($key);
        }
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function getName()
    {
        return $this->data['name'];
    }

    public function getSiblings($name = null)
    {
        if (!empty($name)) {
            return $this->table->getColumns($name);
        } else {
            $columns = $this->table->getColumns($name);
            unset($columns[$this->getName()]);
            return $columns;
        }
    }

    public function setValue($value)
    {
        if (is_array($value)) {
            $this->value = new \Axelmedia\DB\Values($value);
        } else {
            $this->value = $value;
        }
    }

    public function getValue()
    {
        if (!$this->hasValue()) {
            return;
        }

        if (!empty($this->formatter)) {
            $callback = $this->formatter;
            return $callback($this->value);
        } else {
            return $this->value;
        }
    }

    public function hasValue()
    {
        return property_exists($this, 'value');
    }

    public function removeValue()
    {
        unset($this->value);
        return $this;
    }

    public function setFormatter($callback)
    {
        if (!($callback instanceof \Closure)) {
            throw new \TypeError('Argument must be of the type closure, '.gettype($callback).' given.');
        }

        $callback = $callback->bindTo($this);
        $this->formatter = $callback;
    }

    public function hasFormatter()
    {
        return (!empty($this->formatter) && is_callable($this->formatter));
    }

    public function removeFormatter()
    {
        $this->formatter = null;
        return $this;
    }

    public function setValidator($callback)
    {
        if (!($callback instanceof \Closure)) {
            throw new \TypeError('Argument must be of the type closure, '.gettype($callback).' given.');
        }

        $callback = $callback->bindTo($this);
        $this->validator = $callback;
    }

    public function hasValidator()
    {
        return (!empty($this->validator) && is_callable($this->validator));
    }

    public function removeValidator()
    {
        $this->validator = null;
        return $this;
    }

    public function isValid()
    {
        if (!$this->hasValidator()) {
            return true;
        }

        $args = func_get_args();
        if (count($args) > 0) {
            $this->setValue($args[0]);
        }

        $callback = $this->validator;
        return $callback($this->getValue());
    }


    // Aliases

    public function table()
    {
        return $this->getTable();
    }

    public function raw()
    {
        return $this->getRaw();
    }

    public function name()
    {
        return $this->getName();
    }

    public function siblings($name = null)
    {
        return $this->getSiblings($name);
    }

    public function value($value = null)
    {
        $this->setValue($value);
        return $this->getValue();
    }


    // Corrector

    private function correctType($value)
    {
        $value = strtolower($value);

        if (
            (0 === strpos($value, 'tinyint') && false !== strpos($value, '(1)')) ||
            0 === strpos($value, 'bool') ||
            0 === strpos($value, 'bit')
        ) {
            return 'bool';
        } elseif (
            false !== strpos($value, 'int') ||
            0 === strpos($value, 'year')
        ) {
            return 'integer';
        } elseif (
            false !== strpos($value, 'float') ||
            false !== strpos($value, 'decimal') ||
            false !== strpos($value, 'double') ||
            0 === strpos($value, 'dec')
        ) {
            return 'float';
        } elseif (
            false !== strpos($value, 'char') ||
            false !== strpos($value, 'text')
        ) {
            return 'string';
        } elseif (
            0 === strpos($value, 'timestamp') ||
            0 === strpos($value, 'datetime')
        ) {
            return 'datetime';
        } elseif (0 === strpos($value, 'date')) {
            return 'date';
        } elseif (0 === strpos($value, 'time')) {
            return 'time';
        } elseif (false !== strpos($value, 'binary')) {
            return 'binary';
        } elseif (false !== strpos($value, 'blob')) {
            return 'blob';
        } elseif (
            false !== strpos($value, 'enum') ||
            0 === strpos($value, 'set')
        ) {
            return 'set';
        } elseif (
            false !== strpos($value, 'geometry') ||
            false !== strpos($value, 'point') ||
            false !== strpos($value, 'polygon') ||
            false !== strpos($value, 'linestring')
        ) {
            return 'geometry';
        } elseif (false !== strpos($value, 'json')) {
            return 'json';
        } else {
            return 'unknown';
        }
    }

    private function correctLength($value)
    {
        if (preg_match('/\((\d+)\)/', $value, $match)) {
            return (int)$match[1];
        }
    }

    private function correctUnsigned($value)
    {
        return (false !== strpos(strtolower($value), 'unsigned'));
    }

    private function correctRequire($value)
    {
        if (empty($value)) {
            return false;
        }

        return ('no' == strtolower($value));
    }

    private function correctDefault($value, $extra)
    {
        if (preg_match('/CURRENT_(TIME|DATE)/i', $value) || preg_match('/CURRENT_(TIME|DATE)/i', $extra)) {
            return 'current_timestamp';
        } else {
            return $value;
        }
    }

    private function correctCharset($value)
    {
        if (!empty($value)) {
            $value = strtolower($value);
            $charset = substr($value, 0, strpos($value, '_'));
            $charset = str_replace('mb4', '', $charset);
            return $charset;
        }
    }

    private function correctPrimary($value)
    {
        if (empty($value)) {
            return false;
        }

        return (0 === strpos(strtolower($value), 'pri'));
    }

    private function correctIndex($value)
    {
        if (empty($value)) {
            return false;
        }

        return (preg_match('/\A(pri|key|mul)/i', strtolower($value)) >= 0);
    }

    private function correctAutoIncrement($value)
    {
        if (empty($value)) {
            return false;
        }

        return (0 === strpos(strtolower($value), 'auto_incr'));
    }


    // Implements

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function offsetExists($offset)
    {
        if (!is_string($offset)) {
            throw new \TypeError('Argument must be of the type string, '.gettype($offset).' given, called');
        }

        $offset = strtolower($offset);
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        if (!is_string($offset)) {
            throw new \TypeError('Argument must be of the type string, '.gettype($offset).' given, called');
        }

        $offset = strtolower($offset);
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        }
    }

    public function offsetSet($offset, $value) {
        if (!is_string($offset)) {
            throw new \TypeError('Argument must be of the type string, '.gettype($offset).' given, called');
        }

        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        if (!is_string($offset)) {
            throw new \TypeError('Argument must be of the type string, '.gettype($offset).' given, called');
        }

        unset($this->data[$offset]);
    }

    function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    public function __debugInfo()
    {
        return $this->data;
    }
}
