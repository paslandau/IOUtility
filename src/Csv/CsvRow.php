<?php

namespace paslandau\IOUtility\Csv;

class CsvRow implements \ArrayAccess, \Iterator
{

    /**
     * @var CsvRows
     */
    private $parent;

    /**
     * @var mixed[]
     */
    private $row;

    /**
     * @param CsvRows $parent
     * @param array $row
     */
    public function __construct(CsvRows $parent, array $row)
    {
        $this->parent = $parent;
        $this->row = $row;
    }

    /**
     * @param bool $assoc - if true, tries to uses
     * @return array|\mixed[]
     */
    public function toArray($assoc = true)
    {
        if($assoc && $this->parent->hasHeadline()){
            $headline = $this->parent->getHeadline(false);
            return array_combine($headline,$this->row);
        }
        return $this->row;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        $offset = $this->parent->getColumnIndexByName($offset);
        return array_key_exists($offset, $this->row);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        $offset = $this->parent->getColumnIndexByName($offset);
        return $this->row[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $offset = $this->parent->getColumnIndexByName($offset);
        $this->row[$offset] = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        $offset = $this->parent->getColumnIndexByName($offset);
        unset($this->row[$offset]);
    }

    public function rewind()
    {
        reset($this->row);
    }

    public function current()
    {
        return current($this->row);
    }

    public function key()
    {
        $index= key($this->row);
        return $this->parent->getColumnNameByIndex($index);
    }

    public function next()
    {
        return next($this->row);
    }

    public function valid()
    {
        return false !== current($this->row);
    }
}