<?php
namespace Axelmedia\Db;

/**
 * DB/Statement
 *
 * PDO Statement wrapper. (extended PDOStatement class)
 */
class Statement extends \PDOStatement
{
    /**
     * History object
     *
     * @access private
     * @var History
     */
    private $history;

    /**
     * Binded data buffer
     *
     * @access private
     * @var array
     */
    private $binds = array();

    /**
     * Create new PDOStatement object
     *
     * @param string $history Pass History object
     */
    protected function __construct($history = null)
    {
        if ($history instanceof \Axelmedia\DB\History) {
            $this->history = $history;
        }
    }

    /**
     * PDOStatement bindParam() wrapper
     *
     * Buffering bind datas
     *
     * @param string $param   Parameter identifier
     * @param string $value   Variable to bind to the SQL statement parameter
     * @param string $type    Explicit data type for the parameter using the PDO::PARAM_* constants
     * @param string $length  Length of the data type
     * @param string $options Driver options
     */
    public function bindParam($param, &$value, $type = \PDO::PARAM_STR, $length = null, $options = null)
    {
        $this->binds[] = $value;
        parent::bindParam($param, $value, $type, $length, $options);
    }

    /**
     * PDOStatement bindValue() wrapper
     *
     * Buffering bind datas
     *
     * @param string $param Parameter identifier
     * @param string $value Variable to bind to the SQL statement parameter
     * @param string $type  Explicit data type for the parameter using the PDO::PARAM_* constants
     */
    public function bindValue($param, $value, $type = \PDO::PARAM_STR)
    {
        $this->binds[] = $value;
        return parent::bindValue($param, $value, $type);
    }

    /**
     * PDOStatement execute() extend history and callback
     *
     * @param  array    $values   Variable to bind to the SQL statement parameter
     * @param  callable $callback callback function binded PDO object
     * @param  bool     $noLog    If true, History not add
     * @return object   PDOStatement object
     */
    public function execute($values = array(), $callback = null, $noLog = false)
    {
        $binds = $this->binds;
        $this->binds = array();

        if (!empty($values)) {
            if (!$noLog) {
                $this->history->set($this->queryString, $values);
            }

            $result =  parent::execute($values);
        } else {
            if (!$noLog) {
                $this->history->set($this->queryString, $binds);
            }

            $result =  parent::execute();
        }

        if ($callback instanceof \Closure) {
            $callback = $callback->bindTo($result);
            $callback($result);
        }

        return $result;
    }
}
