<?php

use paslandau\IOUtility\Csv\CsvRow;
use paslandau\IOUtility\Csv\CsvRows;

class CsvRowsTest extends PHPUnit_Framework_TestCase
{

    private function getCsvRowsWithLines($headline, $lines = []){
        $csvRows = new CsvRows();
        $csvRows->setHeadline($headline);
       foreach($lines as $line) {
           $csvRows->addRow($line);
       }
        return $csvRows;
    }

    public function test_typicalFlow()
    {
        $headline = ["col1", "col2", "col3"];
        $csvRows = new CsvRows();

        $this->assertSame(null, $csvRows->getHeadline(), "Headline should not be set");

        $csvRows->setHeadline($headline);

        $this->assertSame($headline, $csvRows->getHeadline(), "Headline should be set");


        $this->assertEquals(0, $csvRows->count(), "I just added a line - count should be one");

        $row = ["val1", "val2", "val3"];
        $csvRows->addRow($row);

        $this->assertEquals(1, $csvRows->count(), "I just added a line - count should be one");

        $row2 = ["val11", "val12", "val13"];
        $csvRows->addRow($row2);

        $this->assertEquals(2, $csvRows->count(), "I just added a second line - count should be one");

        $expected = [
            array_combine($headline, $row),
            array_combine($headline, $row2),
        ];
        $actual = $csvRows->toArray();
        $this->assertEquals($expected, $actual, "toArray() has wrong output with default values");

        $expected = [
            $headline,
            array_combine($headline, $row),
            array_combine($headline, $row2),
        ];
        $actual = $csvRows->toArray(true, true);
        $this->assertEquals($expected, $actual, "toArray(true,true) has wrong output");

        $expected = [
            $headline,
            $row,
            $row2,
        ];
        $actual = $csvRows->toArray(true, false);
        $this->assertEquals($expected, $actual, "toArray(true,false) has wrong output");
    }

    public function test_arrayFunctions(){
        $this->setExpectedException(PHPUnit_Framework_Error_Warning::class);
        $headline = ["col1", "col2", "col3"];
        $csvRows = $this->getCsvRowsWithLines($headline);

        // should fail
        array_merge([],$csvRows);
    }

    public function test_getAndSetViaArrayAccess(){
        $headline = ["col1", "col2", "col3"];
        $csvRows = $this->getCsvRowsWithLines($headline);
        $row = ["foo","bar","baz"];
        $csvRow = new CsvRow($csvRows,$row);
        $csvRows["foo"] = $csvRow;
        $actual = $csvRows["foo"];
        $this->assertEquals($csvRow,$actual);
    }
}