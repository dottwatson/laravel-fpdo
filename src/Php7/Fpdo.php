<?php
namespace Fpdo\Php7;

use PDO;
use Vimeo\MysqlEngine\FakePdoInterface;
use Fpdo\FpdoTrait;

class Fpdo extends PDO implements FakePdoInterface
{
    use FpdoTrait;

    /**
     * @param string $statement
     * @param array $options
     * @return FakePdoStatement
     */
    public function prepare($statement, $options = [])
    {
        $stmt = new FpdoStatement($this, $statement, $this->real);
        if ($this->defaultFetchMode) {
            $stmt->setFetchMode($this->defaultFetchMode);
        }
        return $stmt;
    }

    /**
     * @param string $statement
     * @param int $mode
     * @param null $arg3
     * @param array $ctorargs
     * @return FakePdoStatement
     */
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
    {
        $sth = $this->prepare($statement);
        $sth->execute();
        return $sth;
    }

    /**
     * save tables based on its output definitio
     * 
     * @param string $table If null or not set all tables with output defined will be exported
    */
    public function save(string $table = null)
    {
        return $this->getServer()->saveOutputTable($this,$table);
    }
}
