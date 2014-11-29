<?php

use paslandau\IOUtility\IOUtil;

class IOUtilTest extends PHPUnit_Framework_TestCase {

    public static function getTmpDirPath(){
        return __DIR__."/tmp";
    }

    public static function setupBeforeClass(){
        mb_internal_encoding("utf-8");

        $dir = self::getTmpDirPath();
        IOUtil::createDirectoryIfNotExists($dir);
    }

    public static function tearDownAfterClass()
    {
        $dir = self::getTmpDirPath();
        IOUtil::deleteDirectory($dir);
    }

    public function test_ShouldWriteAndReadTxtFile(){

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

        $dir = self::getTmpDirPath();
        $file = IOUtil::combinePaths($dir,"test.csv");
        if(file_exists($file)){
            unlink($file);
        }
        $this->assertTrue(!file_exists($file));

        $rows = [
            ["col1" => "line1", "col2" => "line2", "col3" => "lineü"],
        ];

        $hasHeader = true;
        $encoding = "Cp1252";
        $delimiter = ";";
        $enclosure = "\"";
        $escape = "\"";
        IOUtil::writeCsvFile($file, $rows, $hasHeader,$encoding,$delimiter,$enclosure,$escape);
        $this->assertTrue(file_exists($file));

        $rowsOut = IOUtil::readCsvFile($file, $hasHeader,$encoding,$delimiter,$enclosure,$escape);

        $this->assertEquals(serialize($rows),serialize($rowsOut->toArray(false)));
    }

    public function test_ShouldNotStripTrailingSlash(){

        $s = DIRECTORY_SEPARATOR;

        $tests = [
            "ShouldCombinePathAndFolder" => ["www/public/foo/", "bar.html", "www{$s}public{$s}foo{$s}bar.html"],
            "ShouldNotStripStartingSlash" => ["/www/public/foo/", "bar.html", "{$s}www{$s}public{$s}foo{$s}bar.html"],
            "ShouldRecognizeWindowsStyleAbsolutePath" => ["/www/public/foo/", "bar.html", "{$s}www{$s}public{$s}foo{$s}bar.html"],
            "ShouldWorkWithMixedSeperators" => ["c:\\\\www\\public\\foo\\", "bar.html", "c:{$s}www{$s}public{$s}foo{$s}bar.html"],
            "ShouldIgnoreFrontIfBackIsAbsolute" => ["www/public\\foo\\", "/bar.html", "{$s}bar.html"],
            "ShouldIgnoreFrontIfBackIsAbsoluteWindowsStyle" => ["www/public\\foo\\", "c:\\\\bar.html", "c:{$s}bar.html"],
        ];
        foreach($tests as $name => $vals){
            $expected = $vals[2];
            $actual = IOUtil::combinePaths($vals[0],$vals[1]);
            $this->assertEquals($expected, $actual, "$name failed, front: '{$vals[0]}', back: '{$vals[1]}'");
        }

    }
}
 