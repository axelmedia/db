<?php
namespace Axelmedia\Db;

/**
 * DB/Connection
 *
 * PDO Connection wrapper. (extended PDO class)
 */
class Connection extends \PDO
{
    /**
     * PDO Options
     *
     * @access private
     * @var array
     */
    private $options = array(
        \PDO::ATTR_TIMEOUT => 10,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    );

    /**
     * Config object
     *
     * @access private
     * @var Config
     */
    private $config;

    /**
     * History object
     *
     * @access private
     * @var History
     */
    private $history;

    /**
     * Create new PDO object
     *
     * @param  string       $dsn      Data Source Name or database config array
     * @param  string       $username Database Username
     * @param  string       $password Database Password
     * @param  string       $options  PDO driver option
     * @param  string       $config   Pass Config object
     * @param  string       $history  Pass History object
     * @throws Exception    when passed Config argument error
     * @throws PDOException when Database connect failed
     */
    public function __construct(
        $dsn,
        $user = null,
        $pass = null,
        $options = null,
        $config = null,
        $history = null
    ) {
        if (empty($options) || !is_array($options)) {
            $options = $this->options;
        }

        if (!($config instanceof \Axelmedia\DB\Config)) {
            throw new \Exception('Argument 5 must be instance of Config.');
        }

        $this->config = $config;

        if ($history instanceof \Axelmedia\DB\History) {
            $this->history = $history;
        }

        parent::__construct($dsn, $user, $pass, $options);
    }

    /**
     * Config getter
     *
     * @param  string $key Config array key
     * @return mixed  If pass $key return one, else all config data
     */
    public function getConfig($key = null)
    {
        if (isset($key)) {
            if ($this->config->offsetExists($key)) {
                return $this->config->offsetGet($key);
            }
        } else {
            return $this->config->all();
        }
    }

    /**
     * PDO exec() wrapper extend history and callback
     *
     * @param  string   $query    SQL sentence
     * @param  callable $callback callback function binded PDO object
     * @param  bool     $noLog    If true, History not add
     * @return object   PDO query object
     */
    public function exec($query, $callback = null, $noLog = false)
    {
        if (!$noLog && isset($this->history)) {
            $this->history->set($query);
        }

        $result =  parent::exec($query);

        if ($callback instanceof \Closure) {
            $callback = $callback->bindTo($result);
            $callback($result);
        }

        return $result;
    }

    /**
     * PDO query() extend history and callback
     *
     * @param  string   $query    SQL sentence
     * @param  callable $callback callback function binded PDO object
     * @param  bool     $noLog    If true, History not add
     * @return object   PDO query object
     */
    public function query($query, $callback = null, $noLog = false)
    {
        if (!$noLog && isset($this->history)) {
            $this->history->set($query);
        }

        $result =  parent::query($query);

        if ($callback instanceof \Closure) {
            $callback = $callback->bindTo($result);
            $callback($result);
        }

        return $result;
    }
}
