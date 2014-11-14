<?php

use paslandau\IOUtility\IOUtil;

class IOUtilTest extends PHPUnit_Framework_TestCase {

    public static function getTmpDirPath(){
        return __DIR__."/tmp";
    }

    public static function setupBeforeClass(){
        $dir = self::getTmpDirPath();
        IOUtil::createDirectoryIfNotExists($dir);
    }

    public static function tearDownAfterClass()
    {
        $dir = self::getTmpDirPath();
        IOUtil::deleteDirectory($dir);
    }

    public function test_ShouldWriteAndReadTxtFile(){
        mb_internal_encoding("utf-8");

        $dir = self::getTmpDirPath();
        $content = "A sentence with german umlauts like äöüß to check for encoding problems.";

        $encs = [
            "Cp1252",
            "ISO-8859-1",
            "UTF-8"
        ];
        foreach($encs as $encoding) {
            $file = IOUtil::combinePaths($dir,"test-$encoding.txt");
            if(file_exists($file)){
                unlink($file);
            }
            $this->assertTrue(!file_exists($file));
            IOUtil::writeFileContent($file, $content, $encoding);
            $this->assertTrue(file_exists($file));

            $contentOut = IOUtil::getFileContent($file, $encoding);
            $this->assertEquals($content, $contentOut);
        }
    }

    public function test_ShouldWriteAndReadCsvFile(){
        mb_internal_encoding("utf-8");

        $dir = self::getTmpDirPath();
        $file = IOUtil::combinePaths($dir,"test.csv");
        if(file_exists($file)){
            unlink($file);
        }
        $this->assertTrue(!file_exists($file));

        $rows = [
            ["col1","col2","colü"],
            ["line1","line2","lineü"],
        ];

        $hasHeader = true;
        $encoding = "Cp1252";
        $delimiter = ";";
        $enclosure = "\"";
        $escape = "\"";
        IOUtil::writeCsvFile($file, $rows, $hasHeader,$encoding,$delimiter,$enclosure,$escape);
        $this->assertTrue(file_exists($file));

        $rowsOut = IOUtil::readCsvFile($file, $hasHeader,$encoding,$delimiter,$enclosure,$escape);

        $this->assertEquals(serialize($rows),serialize($rowsOut->toArray()));
    }
}
 