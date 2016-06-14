<?php
namespace Axelmedia\Db;

class History
{
    private $historyMax = 100;
    private $history = array();

    public function set($sql, $binds = null)
    {
        $this->history[] = array(
            'sql' => $sql,
            'binds' => (!empty($binds) ? $binds : null)
        );

        if (($count = count($this->history)) > $this->historyMax) {
            $this->history = array_slice($this->history, -$this->historyMax, $this->historyMax);
        }
    }

    public function get()
    {
        return $this->history;
    }

    public function __debugInfo()
    {
        return $this->history;
    }
}
