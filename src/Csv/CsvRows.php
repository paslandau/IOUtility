<?php

namespace paslandau\IOUtility\Csv;


class CsvRows extends \ArrayObject
{

    /**
     * @var bool
     */
    private $hasHeadline;

    /**
     * Column names are keys
     * @var string[]
     */
    private $headline;

    /**
     * Column indexes are keys
     * @var string[]
     */
    private $headlineLookup;

    public function __construct($hasHeadline = true)
    {
        $this->hasHeadline = $hasHeadline;
    }

    public function hasHeadline()
    {
        return $this->hasHeadline;
    }

    /**
     * @param bool $assoc [optional]. Default: false. If true, the result will have the column names as keys (and the indexes as values) instead of numeric indexes and column names as keys.
     * @return string[]|int[]
     */
    public function getHeadline($assoc = false)
    {
        if($assoc){
            return $this->headline;
        }
        return $this->headlineLookup;
    }

    public function setHeadline(array $headline)
    {
        $this->headline = array_flip($headline);
        $this->headlineLookup = $headline;
    }

    public function getColumnIndexByName($name)
    {
        $arr = $this->headline;
        if (!array_key_exists($name, $arr)) {
            return $name;
        }
        return $this->headline[$name];
    }

    public function getColumnNameByIndex($index)
    {
        return $this->headlineLookup[$index];
    }

    /**
     * @param string[]|CsvRow $row
     */
    public function addRow($row)
    {
        if(!$row instanceof CsvRow){
            $row = new CsvRow($this, $row);
        }
        $this->append($row);
    }

    /**
     * @param bool $withHeader - if true, an additional line with the header fields is added on top
     * @param bool $assoc [optional]. Default: true. If true the keys of the rows will be associative instead of numeric (if a headline was provided)
     * @return array
     */
    public function toArray($withHeader = true, $assoc = true)
    {
        $arr = [];
        if ($withHeader && $this->hasHeadline()) {
            $arr[] = $this->headlineLookup;
        }
        /** @var CsvRow $row */
        foreach ($this as $row) {
            $arr[] = $row->toArray($assoc);
        }
        return $arr;
    }
}