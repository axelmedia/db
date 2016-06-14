<?php
namespace Axelmedia\Db;

class BulkInsert
{
    private $conn;
    private $table;
    private $columns;

    private $updates;
    private $keys = array();
    private $stack = array();

    private $size = 0;
    private $ignore = false;
    private $last_query;

    public function __construct($conn, $table)
    {
        $this->conn = $conn;
        $this->table = $table;

        if (false !== strpos($table, '.')) {
            list($dbname, $table) = explode('.', $table);
            $this->conn->exec("USE `{$dbname}`");
            $this->table = $table;
        }

        try {
            $columns = array();
            $sql = 'SHOW FULL COLUMNS FROM '.$table.';';
            $query = $this->conn->query($sql);
            while ($res = $query->fetch(\PDO::FETCH_ASSOC)) {
                $columns[$res['Field']] = $res['Field'];
            }
            $this->columns = $columns;
        } catch (\PDOException $e) {
        }

        $max = $this->conn->query("SHOW VARIABLES LIKE 'max_allowed_packet';")->fetch();
        $this->max = (int) end($max);
    }

    public function setIgnore($bool)
    {
        $this->ignore = (!empty($bool));
    }

    public function setUpdates()
    {
        $args = func_get_args();
        $arr = null;
        if (is_array($args[0])) {
            $arr = $args[0];
        } elseif (is_string($args[0])) {
            $arr = $args;
        }

        if (empty($arr)) {
            return;
        }

        $updates = array();
        foreach ($arr as $val) {
            if (!empty($this->columns[$val])) {
                $updates[$val] = "`{$val}`=VALUES(`$val`)";
            }
        }

        $this->updates = implode(','.PHP_EOL, $updates);
    }

    public function setData($data)
    {
        $arr = array();
        foreach ($data as $key => $val) {
            if (!empty($this->columns[$key]) && is_scalar($val)) {
                $this->keys[$key] = $key;
                $arr[$key] = $val;
            }
        }

        if (!empty($arr)) {
            $size = strlen(bin2hex(implode('', $arr)));
            if ($this->size + $size > $this->max) {
                $this->flush();
            }
            $this->stack[] = $arr;
        }
    }

    public function flush()
    {
        if (empty($this->stack)) {
            return;
        }

        $value = '('.implode(',', array_fill(0, count($this->keys), '?')).')';
        $values = implode(','.PHP_EOL, array_fill(0, count($this->stack), $value));

        $sql  = 'INSERT '.($this->ignore ? 'IGNORE' : 'INTO')." `{$this->table}`".PHP_EOL;
        $sql .= '(`'.implode('`,`', $this->keys).'`)'.PHP_EOL;
        $sql .= "VALUES {$values}".PHP_EOL;
        if (!empty($this->updates)) {
            $sql .= 'ON DUPLICATE KEY UPDATE'.PHP_EOL."{$this->updates}".PHP_EOL;
        }
        $sql = trim($sql).';';
        $this->last_query = $sql;

        $binds = array();
        foreach ($this->stack as $data) {
            foreach ($this->keys as $val) {
                if (array_key_exists($val, $data)) {
                    $binds[] = $data[$val];
                } else {
                    $binds[] = null;
                }
            }
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare($sql);
            if ($stmt->execute($binds)) {
                $this->conn->commit();
                $this->stack  = array();
            } else {
                $this->conn->rollback();
            }
        } catch (\PDOException $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}
