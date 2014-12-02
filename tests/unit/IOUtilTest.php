<?php

use Goodby\CSV\Export\Standard\Exception\StrictViolationException;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\IOUtility\IOUtil;

class IOUtilTest extends PHPUnit_Framework_TestCase {

    public static function getTmpDirPath(){
        return IOUtil::combinePaths(__DIR__,"tmp");
    }

    public static function setupBeforeClass(){
        mb_internal_encoding("utf-8");

        $dir = self::getTmpDirPath();
        IOUtil::deleteDirectory($dir);
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

    public function test_copyDirectory(){
        $temp = self::getTmpDirPath();

        $parent = IOUtil::combinePaths($temp,"test_copy/");
        IOUtil::createDirectoryIfNotExists($parent);
        $files = [
            "foo.txt" => "foo",
            "bar" => "bar",
        ];
        foreach($files as $file => $content){
            $path = IOUtil::combinePaths($parent,$file);
            IOUtil::writeFileContent($path,$content);
        }

        $pathToTarget = IOUtil::combinePaths($temp,"temp2/foo/");
        if(file_exists($pathToTarget)){
            IOUtil::deleteDirectory($pathToTarget);
        }
        IOUtil::copyDirectory($parent,$pathToTarget);

        $exists = file_exists($pathToTarget);
        $this->assertTrue($exists,"'$pathToTarget' should exist, but it doesn't!");

        $resfiles = IOUtil::getFiles($pathToTarget,true,true,true);
        foreach($resfiles as $file){
            $content = IOUtil::getFileContent($file);
            $cleanedFile = str_replace($pathToTarget, "",$file);
            $this->assertArrayHasKey($cleanedFile,$files,"File $cleanedFile not expected!");
            $this->assertEquals($files[$cleanedFile],$content,"Content does match between $cleanedFile and $file");
        }
    }

    public function test_rename(){
        $temp = self::getTmpDirPath();

        $parent = IOUtil::combinePaths($temp,"test_rename/");
        IOUtil::createDirectoryIfNotExists($parent);
        $files = [
            "foo.txt" => "foo",
            "bar" => "bar",
        ];
        foreach($files as $file => $content){
            $path = IOUtil::combinePaths($parent,$file);
            IOUtil::writeFileContent($path,$content);
        }

        $pathToTarget = IOUtil::combinePaths($temp,"temp2/");

        /**
         * CAUTION: $pathToTarget must not exist  but the parent folder must exist!
         */
        IOUtil::deleteDirectory($pathToTarget);
        rename($parent,$pathToTarget);

        $exists = file_exists($pathToTarget);
        $this->assertTrue($exists,"'$pathToTarget' should exist, but it doesn't!");

        $resfiles = IOUtil::getFiles($pathToTarget,true,true,true);
        foreach($resfiles as $file){
            $content = IOUtil::getFileContent($file);
            $cleanedFile = str_replace($pathToTarget, "",$file);
            $this->assertArrayHasKey($cleanedFile,$files,"File $cleanedFile not expected!");
            $this->assertEquals($files[$cleanedFile],$content,"Content does match between $cleanedFile and $file");
        }
    }

    public function test_writeCsvFile_readCsvFile()
    {
        $temp = self::getTmpDirPath();
        $parent = IOUtil::combinePaths($temp, "test_writeCsvFile_readCsvFile/");
        IOUtil::createDirectoryIfNotExists($parent);

        $encoding = mb_internal_encoding();
        $escape = "\"";
        $enclosure = "\"";
        $delimiter = ",";
        $withHeader = true;
        $tests = [
            "empty-array" => [
                "input" => [],
            ],
            "array-one-empty-line" => [
                "input" => [[]],
                "expected" => [],
            ],
            "array-one-line-numerical-columns" => [
                "input" => [[0 => "foo", 1 => "bar"]],
            ],
            "array-one-line-text-columns" => [
                "input" => [["foo" => "foo", "bar" => "bar"]],
            ],
            "array-one-line-with-null" => [
                "input" => [["foo" => "foo", "bar" => null]],
                "expected" => [["foo" => "foo", "bar" => ""]],
            ],
            "array-2-lines" => [
                "input" => [["foo" => "foo", "bar" => "bar"], ["foo" => "baz", "bar" => "test"],]
            ],
            "array-2-lines-different-column-names" => [
                "input" => [["foo" => "foo", "bar" => "bar"], ["foo_diff" => "baz", "bar_diff" => "test"],],
                "expected" => [["foo" => "foo", "bar" => "bar"], ["foo" => "baz", "bar" => "test"],]
            ],
            "array-2-lines-different-column-number-first" => [
                "input" => [["foo" => "foo", "bar" => "bar", "baz" => "boom"], ["foo" => "baz", "bar" => "test"],],
                "expected" => StrictViolationException::class
            ],
            "array-2-lines-different-column-number-second" => [
                "input" => [["foo" => "foo", "bar" => "bar"], ["foo" => "baz", "bar" => "test", "baz" => "boom"],],
                "expected" => StrictViolationException::class
            ],
            "array-1-line-escape" => [
                "input" => [["foo" => "foo", "bar" => "the escape > $escape < "]]
            ],
            "array-1-line-enclosure" => [
                "input" => [["foo" => "foo", "bar" => "the enclosure > $enclosure < "]]
            ],
            "array-1-line-delimiter" => [
                "input" => [["foo" => "foo", "bar" => "the delimiter > $delimiter < "]]
            ],
            "array-1-line-crlf" => [
                "input" => [["foo" => "foo", "bar" => "the crlf > \r\n < "]]
            ],
            // https://bugs.php.net/bug.php?id=43225
            // TODO add this test again when bug is fixed
//            "array-1-line-slash-double-quote-bug" => [
//                "input" => [["foo" => "foo", "bar" => "the bug > \\\" < "]]
//            ],
        ];

        foreach ($tests as $test => $values) {
            $input = $values["input"];
            $expected = $input;
            if (array_key_exists("expected", $values)) {
                $expected = $values["expected"];
            }

            $actual = null;
            try {
                $path = IOUtil::combinePaths($parent, $test . ".csv");
                IOUtil::writeCsvFile($path, $input, $withHeader, $encoding, $delimiter, $enclosure, $escape);
                $actualCsv = IOUtil::readCsvFile($path, $withHeader, $encoding, $delimiter, $enclosure, $escape);
                $actual = $actualCsv->toArray(false, true);

            } catch (Exception $e) {
                $actual = get_class($e);
            }

            $msg = [
                "Error at $test:",
                "WithHeader: " . ($withHeader ? "true" : "false"),
                "Encoding: " . $encoding,
                "Delimiter: " . $delimiter,
                "Enclosure: " . $enclosure,
                "Escape: " . $escape,
                "Input: " . json_encode($input),
                "Excpected: " . json_encode($expected),
                "Actual: " . json_encode($actual),
            ];
            $msg = implode("\n", $msg);
//                echo $msg . "\n\n\n";
            if (is_array($expected)) {
                $this->assertTrue(ArrayUtil::equals($actual, $expected, true, true, true), $msg);
            } else {
                $this->assertEquals($expected, $actual, $msg);
            }
        }
        echo "";
    }
}
 