<?php

namespace paslandau\IOUtility\Csv;


class CsvRows extends \ArrayObject
{

    /**
     * @var bool
     */
    private $hasHeadline;

    /**
     * @var string[]
     */
    private $headline;

    /**
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

    public function addRow(array $row)
    {
        $this->append(new CsvRow($this, $row));
    }

    public function toArray($withHeader = true)
    {
        $arr = [];
        if ($withHeader && $this->hasHeadline()) {
            $arr[] = $this->headlineLookup;
        }
        foreach ($this as $row) {
            $arr[] = $row->toArray();
        }
        return $arr;
    }
}