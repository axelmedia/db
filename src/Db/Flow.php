<?php
namespace Axelmedia\Db;

class Flow
{
    private $conn;
    private $sql;
    private $binds = array();
    private $formats = array();
    private $callbacks = array();
    private $errors = array();

    private $runned = false;
    private $query;

    public function __construct($conn)
    {
        if (!($conn instanceof \Axelmedia\DB\Connection)) {
            throw new \Exception('Database not connected.');
        }

        $this->conn = $conn;
    }

    public function sql($sql)
    {
        if(empty($sql)) {
            throw new \Exception('SQL sentence is Required.');
        } elseif(!is_string($sql)) {
            throw new \TypeError('Argument must be of the type string, '.gettype($sql).' given, called');
        }

        $this->sql = $sql;
        return $this;
    }

    public function bind($binds)
    {
        if (is_array($binds)) {
            $this->binds = $binds;
        } elseif(isset($binds)) {
            $this->binds = func_get_args();
        }

        $this->errors = array();

        return $this;
    }

    public function format($callback = null)
    {
        if (is_callable($callback)) {
            $callback = $callback->bindTo($this->conn);
            $this->callbacks[__FUNCTION__] = $callback;
        }

        return $this;
    }

    public function validate($callback = null)
    {
        if (is_callable($callback)) {
            $callback = $callback->bindTo($this->conn);
            $this->callbacks[__FUNCTION__] = $callback;
        }

        return $this;
    }

    public function success($callback = null)
    {
        if (is_callable($callback)) {
            $this->callbacks[__FUNCTION__] = $callback;
        }

        if (!empty($this->query)) {
            $binds = (!empty($this->formats) ? $this->formats : $this->binds);
            $callback = $this->callbacks[__FUNCTION__];
            $callback = $callback->bindTo($this->query);
            $callback($binds);
            $this->query = null;
        }

        return $this;
    }

    public function error($callback = null)
    {
        if (is_callable($callback)) {
            $callback = $callback->bindTo($this->conn);
            $this->callbacks[__FUNCTION__] = $callback;
        }

        if ($this->runned && !empty($this->errors)) {
            $this->callbacks[__FUNCTION__]($this->errors);
            $this->runned = false;
        }

        return $this;
    }

    public function check()
    {
        static $errors = array();
        if (!empty($errors) && !empty($this->errors)) {
            return $errors;
        }

        if (
            !empty($this->binds) &&
            empty($this->formats) &&
            !empty($this->callbacks['format'])
        ) {
            $this->formats = $this->callbacks['format']($this->binds);
        }

        if (
            (!empty($this->binds) || !empty($this->formats)) &&
            !empty($this->callbacks['validate'])
        ) {
            try {
                if (!empty($this->formats)) {
                    $results = $this->callbacks['validate']($this->formats);
                } else {
                    $results = $this->callbacks['validate']($this->binds);
                }

                if (is_array($results)) {
                    foreach($results as $val) {
                        if (is_string($val)) {
                            $this->errors[] = new \Exception($val);
                        } elseif($val instanceof \Exception) {
                            $this->errors[] = $val;
                        }
                    }
                } elseif (is_string($results)) {
                    $errors[] = new \Exception($results);
                } elseif($results instanceof \Exception) {
                    $errors[] = $results;
                }
            } catch(\Exception $e) {
                $this->errors[] = $e;
            } catch(\TypeError $e) {
                $this->errors[] = $e;
            }
        }

        if (!empty($errors)) {
            return $this->errors = array_merge($this->errors, $errors);
        }
    }

    public function run()
    {
        $this->check();

        $this->runned = true;

        if (!empty($this->errors)) {
            if (!empty($this->callbacks['error'])) {
                $this->callbacks['error']($this->errors);
            }
            return false;
        }

        $binds = (!empty($this->formats) ? $this->formats : $this->binds);
        if (!empty($binds)) {
            $sql = $this->sql;
            if (!preg_match('/:[a-zA-Z0-9_]+/i', $sql) && false !== strpos($sql, '?')) {
                $bindValues = array_values($binds);
            } else {
                $bindValues = $binds;
            }

            try {
                $stmt = $this->conn->prepare($this->sql);
                if ($stmt->execute($bindValues)) {
                    $this->query = $stmt;
                    if (!empty($this->callbacks['success'])) {
                        $callback = $this->callbacks['success'];
                        $callback = $callback->bindTo($stmt);
                        $callback($binds);
                    }
                }
                return true;
            } catch(\PDOException $e) {
                $this->errors[] = $e;
                if (!empty($this->callbacks['error'])) {
                    $this->callbacks['error']($this->errors);
                }
            }
        } else {
            try {
                $query = $this->conn->query($this->sql);
                $this->query = $query;
                if (!empty($this->callbacks['success'])) {
                    $callback = $this->callbacks['success'];
                    $callback = $callback->bindTo($query);
                    $callback();
                }
                return true;
            } catch(\PDOException $e) {
                $this->errors[] = $e;
                if (!empty($this->callbacks['error'])) {
                    $this->callbacks['error']($this->errors);
                }
            }
        }

        return false;
    }
}
