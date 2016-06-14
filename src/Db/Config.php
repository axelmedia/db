<?php
namespace Axelmedia\Db;

class Config implements \ArrayAccess
{
    private $container = array(
        'dsn' => null,
        'scheme' => null,
        'host' => null,
        'user' => null,
        'pass' => null,
        'name' => null,
        'port' => null,
        'path' => null,
        'charset' => null
    );

    public function __construct($dsn, $username = null, $password = null) {
        if (!empty($dsn)) {
            if (is_string($dsn)) {
                $this->parseDSN($dsn);
            } elseif(is_array($dsn)) {
                $this->parseConfig($dsn);
            }

            if (empty($this->container['dsn'])) {
                $dsn = $this->container['scheme'].':';
                $dsn .= 'host='.$this->container['host'].';';
                $dsn .= 'dbname='.$this->container['name'].';';
                if (!empty($this->container['port'])) {
                    $dsn .= 'port='.$this->container['port'].';';
                }
                if (!empty($this->container['charset'])) {
                    $dsn .= 'charset='.$this->container['charset'].';';
                }

                $this->container['dsn'] = trim($dsn, ';');
            }

            if (is_string($username)) {
                $this->container['user'] = $username;
            }

            if (is_string($password)) {
                $this->container['pass'] = $password;
            }
        }
    }

    private function parseDSN($dsn)
    {
        // URI style
        if (strpos($dsn, '://')) {
            $uri = parse_url($dsn);
            if (!empty($uri['scheme'])) {
                $this->container['scheme'] = $uri['scheme'];
            }
            if (!empty($uri['host'])) {
                $this->container['host'] = $uri['host'];
            }
            if (!empty($uri['port'])) {
                $this->container['port'] = $uri['port'];
            }
            if (!empty($uri['user'])) {
                $this->container['user'] = $uri['user'];
            }
            if (!empty($uri['pass'])) {
                $this->container['pass'] = $uri['pass'];
            }
            if (!empty($uri['path'])) {
                $this->container['name'] = trim($uri['path'], '/');
            }
            if (!empty($uri['query'])) {
                parse_str($uri['query'], $query);
                if (!empty($query['charset'])) {
                    $this->container['charset'] = $query['charset'];
                }
            }
        } else {
            $this->container['dsn'] = $dsn;
            $scheme = strtolower(substr($dsn, 0, strpos($dsn, ':')));
            $this->container['scheme'] = $scheme;

            if (preg_match_all('/(\w+)=(\w+)/', $dsn, $m)) {
                foreach($m[1] as $key => $name) {
                    $value = $m[2][$key];
                    if ('host' == $name) {
                        $this->container['host'] = $value;
                    } elseif ('dbname' == $name) {
                        $this->container['name'] = $value;
                    } elseif ('charset' == $name) {
                        $this->container['charset'] = $value;
                    } elseif ('port' == $name) {
                        $this->container['port'] = (int)$value;
                    }
                }
            }
        }

        if ('mysql' == $this->container['scheme'] || 'pgsql' == $this->container['scheme']) {
            if (empty($this->container['host'])) {
                $this->container['host'] = 'localhost';
            }

            if (empty($this->container['charset'])) {
                $this->container['charset'] = 'utf8';
            }

            if (empty($this->container['port'])) {
                $this->container['port'] = ('mysql' == $this->container['scheme'] ? 3306 : 5432);
            }
        }
    }

    private function parseConfig($config)
    {
        foreach($config as $key => $val) {
            if (!is_string($val) && !is_int($val)) {
                continue;
            }

            if (array_key_exists($key, $this->container)) {
                $this->container[$key] = $val;
            } elseif('hostname' == $key || 'host_name' == $key) {
                $this->container['host'] = $config[$key];
            } elseif('database' == $key || 'dbname' == $key || 'db_name' == $key) {
                $this->container['name'] = $config[$key];
            } elseif('username' == $key || 'user_name' == $key) {
                $this->container['user'] = $config[$key];
            } elseif('password' == $key) {
                $this->container['pass'] = $config[$key];
            }
        }
    }

    public function all()
    {
        return $this->container;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function offsetSet($offset, $value)
    {
        if (array_key_exists($offset, $this->container)) {
            $this->container[$offset] = $value;
        } elseif(!empty($offset)) {
            $offset = str_replace(array(
                '_',
                'dbname',
                'hostname',
                'username',
                'password',
            ), array(
                '',
                'name',
                'host',
                'user',
                'pass',
            ), strtolower($offset));

            if (array_key_exists($offset, $this->container)) {
                $this->container[$offset] = $value;
            }
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    public function offsetUnset($offset)
    {
        $this->container[$offset] = null;
    }

    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    public function __debugInfo()
    {
        return $this->container;
    }
}
