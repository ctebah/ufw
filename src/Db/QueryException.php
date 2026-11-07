<?php
namespace Ufw\Db;

/**
 * SQL failure raised by {@see Db} when {@see Db::setThrowOnError()} is on.
 * The numeric code is the MySQL errno.
 */
class QueryException extends \RuntimeException
{
    /**
     * @var string
     */
    protected $query;

    /**
     * @var string
     */
    protected $connIdent;

    /**
     * @param string $message
     * @param int $errno
     * @param string $query
     * @param string $connIdent
     */
    public function __construct($message, $errno, $query, $connIdent)
    {
        parent::__construct($message, (int) $errno);
        $this->query = (string) $query;
        $this->connIdent = (string) $connIdent;
    }

    /**
     * @return int
     */
    public function getErrno()
    {
        return (int) $this->getCode();
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return string
     */
    public function getConnIdent()
    {
        return $this->connIdent;
    }
}
