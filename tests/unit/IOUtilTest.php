<?php

use Goodby\CSV\Export\Standard\Exception\StrictViolationException;
use paslandau\ArrayUtility\ArrayUtil;
use paslandau\IOUtility\EncodingStreamFilter;
use paslandau\IOUtility\EncodingStreamSettings;
use paslandau\IOUtility\IOUtil;

class IOUtilTest extends PHPUnit_Framework_TestCase {

    public static function getTmpDirPath(){
        return IOUtil::combinePaths(__DIR__,"tmp");
    }

    public static function setupBeforeClass(){
        mb_internal_encoding("utf-8");

        gc_collect_cycles();
        $dir = self::getTmpDirPath();
        IOUtil::deleteDirectory($dir);
        IOUtil::createDirectoryIfNotExists($dir);
    }

    public static function tearDownAfterClass()
    {
        gc_collect_cycles();
        $dir = self::getTmpDirPath();
        IOUtil::deleteDirectory($dir);
    }

    public function test_ShouldWriteAndReadTxtFile(){

        $dir = self::getTmpDirPath();
        $content = "A sentence with german umlauts like äöüß to check for encoding problems.";

        $encs = [
            "CP1252",
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
            $this->assertEquals($content, $contentOut, "Error at $encoding");
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

        $this->assertEquals(serialize($rows),serialize($rowsOut));
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

    private function getTestDataForWriting($seperator,$quotation){
        $lineEnding = "\n";

        $tests["empty-input"]["input"] = [];
        $tests["empty-input"]["expected"] = "";
        $tests["empty-input"]["expected-headline"] = "";

        $tests["input-one-empty-line"]["input"] = [[]];
        $tests["input-one-empty-line"]["expected"] = "" . $lineEnding;
        $tests["input-one-empty-line"]["expected-headline"] = $lineEnding . $lineEnding;

        $tests["input-one-line-numerical-columns"]["input"] = [[0 => "foo", 1 => "bar"]];
        $tests["input-one-line-numerical-columns"]["expected"] = "foo{$seperator}bar" . $lineEnding;
        $tests["input-one-line-numerical-columns"]["expected-headline"] = "0{$seperator}1{$lineEnding}foo{$seperator}bar" . $lineEnding;

        $tests["input-one-line-text-columns"]["input"] = [["foo" => "foo", "bar" => "bar"]];
        $tests["input-one-line-text-columns"]["expected"] = "foo{$seperator}bar" . $lineEnding;
        $tests["input-one-line-text-columns"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}bar" . $lineEnding;

        $tests["input-one-line-text-columns-encoding-test"]["input"] = [["föäü" => "föäü", "baß" => "baß"]];
        $tests["input-one-line-text-columns-encoding-test"]["expected"] = "föäü{$seperator}baß" . $lineEnding;
        $tests["input-one-line-text-columns-encoding-test"]["expected-headline"] = "föäü{$seperator}baß{$lineEnding}föäü{$seperator}baß" . $lineEnding;

        $tests["input-one-line-with-null"]["input"] = [["foo" => "foo", "bar" => null]];
        $tests["input-one-line-with-null"]["expected"] = "foo{$seperator}" . $lineEnding;
        $tests["input-one-line-with-null"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}" . $lineEnding;

        $tests["input-2-lines"]["input"] = [["foo" => "foo", "bar" => "bar"], ["foo" => "baz", "bar" => "test"]];
        $tests["input-2-lines"]["expected"] = "foo{$seperator}bar{$lineEnding}baz{$seperator}test" . $lineEnding;
        $tests["input-2-lines"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}bar{$lineEnding}baz{$seperator}test" . $lineEnding;

        $tests["input-2-lines-different-column-names"]["input"] = [["foo" => "foo", "bar" => "bar"], ["foo_diff" => "baz", "bar_diff" => "test"]];
        $tests["input-2-lines-different-column-names"]["expected"] = "foo{$seperator}bar{$lineEnding}baz{$seperator}test" . $lineEnding;
        $tests["input-2-lines-different-column-names"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}bar{$lineEnding}baz{$seperator}test" . $lineEnding;

        $tests["input-1-line-quotation"]["input"] = [["foo" => "foo", "bar" => "the escape > $quotation < "]];
        $tests["input-1-line-quotation"]["expected"] = "foo{$seperator}{$quotation}the escape > {$quotation}$quotation < {$quotation}" . $lineEnding;
        $tests["input-1-line-quotation"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the escape > {$quotation}$quotation < {$quotation}" . $lineEnding;

        $tests["input-1-line-delimiter"]["input"] = [["foo" => "foo", "bar" => "the delimiter > $seperator < "]];
        $tests["input-1-line-delimiter"]["expected"] = "foo{$seperator}{$quotation}the delimiter > $seperator < {$quotation}" . $lineEnding;
        $tests["input-1-line-delimiter"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the delimiter > $seperator < {$quotation}" . $lineEnding;

        $tests["input-1-line-crlf"]["input"] = [["foo" => "foo", "bar" => "the crlf > $lineEnding < "]];
        $tests["input-1-line-crlf"]["expected"] = "foo{$seperator}{$quotation}the crlf > $lineEnding < {$quotation}" .$lineEnding;
        $tests["input-1-line-crlf"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the crlf > $lineEnding < {$quotation}" .$lineEnding;

//             https://bugs.php.net/bug.php?id=43225
//             TODO add this test again when bug is fixed
//        $tests["input-1-line-slash-double-quote-bug"]["input"] = [["foo" => "foo", "bar" => "the bug > \\\" < "]];
//        $tests["input-1-line-slash-double-quote-bug"]["expected"] = "foo{$seperator}{$quotation}the bug > \\\"\" < {$quotation}" .$lineEnding;
//        $tests["input-1-line-slash-double-quote-bug"]["expected-headline"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the bug > \\\"\" < {$quotation}" .$lineEnding;

        return $tests;
    }

    private function getTestDataForReading($seperator,$quotation){
        $lineEnding = "\n";

        $tests["empty-input"]["input"] = "";
        $tests["empty-input"]["expected-headline"] = "InvalidArgumentException";
        $tests["empty-input"]["expected"] = [];

        $tests["input-one-empty-line"]["input"] = $lineEnding . $lineEnding;
        $tests["input-one-empty-line"]["expected-headline"] = "InvalidArgumentException";
        $tests["input-one-empty-line"]["expected"] = [[null],[null]];

        $tests["input-one-line-numerical-columns"]["input"] = "0{$seperator}1{$lineEnding}foo{$seperator}bar" . $lineEnding;
        $tests["input-one-line-numerical-columns"]["expected-headline"] = [[0 => "foo", 1 => "bar"]];

        $tests["input-one-line-text-columns"]["expected-headline"] = [["foo" => "foo", "bar" => "bar"]];
        $tests["input-one-line-text-columns"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}bar" . $lineEnding;

        $tests["input-one-line-text-columns-encoding-test"]["expected-headline"] = [["föäü" => "föäü", "baß" => "baß"]];
        $tests["input-one-line-text-columns-encoding-test"]["input"] = "föäü{$seperator}baß{$lineEnding}föäü{$seperator}baß" . $lineEnding;

        $tests["input-one-line-with-null"]["expected-headline"] = [["foo" => "foo", "bar" => ""]];
        $tests["input-one-line-with-null"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}" . $lineEnding;

        $tests["input-2-lines"]["expected-headline"] = [["foo" => "foo", "bar" => "bar"], ["foo" => "baz", "bar" => "test"]];
        $tests["input-2-lines"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}bar{$lineEnding}baz{$seperator}test" . $lineEnding;

        $tests["input-1-line-quotation"]["expected-headline"] = [["foo" => "foo", "bar" => "the escape > $quotation < "]];
        $tests["input-1-line-quotation"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the escape > {$quotation}$quotation < {$quotation}" . $lineEnding;

        $tests["input-1-line-delimiter"]["expected-headline"] = [["foo" => "foo", "bar" => "the delimiter > $seperator < "]];
        $tests["input-1-line-delimiter"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the delimiter > $seperator < {$quotation}" . $lineEnding;

        $tests["input-1-line-crlf"]["expected-headline"] = [["foo" => "foo", "bar" => "the crlf > $lineEnding < "]];
        $tests["input-1-line-crlf"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the crlf > $lineEnding < {$quotation}" .$lineEnding;

//        $tests["input-1-line-slash-double-quote-bug"]["expected-headline"] = [["foo" => "foo", "bar" => "the bug > \\\" < "]];
//        $tests["input-1-line-slash-double-quote-bug"]["input"] = "foo{$seperator}bar{$lineEnding}foo{$seperator}{$quotation}the bug > \\\"\" < {$quotation}" .$lineEnding;

        return $tests;
    }

    private function _writingTest($encoding,$hasHeadline,$seperator,$quotation,$callingMethod){
        $tests = $this->getTestDataForWriting($seperator,$quotation);

        $tmpFolder = $this->getTmpDirPath();
        $errors = [];
        foreach ($tests as $test => $values) {
            $input = $values["input"];
            $expected = $values["expected"];
            if($hasHeadline){
                $expected = $values["expected-headline"];
            }

            $actual = null;

            $excMsg = "";
            try {
                $arr = explode(":",$callingMethod);
                $m = end($arr);
                $path = IOUtil::combinePaths($tmpFolder, $test . "_{$m}_write.csv");
                IOUtil::writeCsvFile($path, $input, $hasHeadline, $encoding, $seperator, $quotation);
                $actual = IOUtil::getFileContent($path, $encoding);

            } catch (\Exception $e) {
                $actual = get_class($e);
                $excMsg = " (".$e->getMessage().")";
            }

            $msg = [
                "Error at $test:",
                "WithHeader: " . ($hasHeadline ? "true" : "false"),
                "Encoding: " . $encoding,
                "Separator: " . $seperator,
                "Quotation: " . $quotation,
                "Input: " . json_encode($input),
                "Excpected: \t" . json_encode($expected),
                "Actual: \t" . json_encode($actual).$excMsg,
            ];
            $msg = implode("\n", $msg);

            // do not use assertions since they terminate the test on error
            if($actual != $expected){
                $errors[] = $msg;
            }
        }
        $this->assertCount(0, $errors, "[".get_called_class()." => ".$callingMethod."] Errors: ".count($errors)."\n\n".implode("\n\n", $errors));
    }

    private function _readingTest($encoding,$hasHeadline,$seperator,$quotation,$callingMethod){
        $tests = $this->getTestDataForReading($seperator,$quotation);

        $tmpFolder = $this->getTmpDirPath();
        $errors = [];
        foreach ($tests as $test => $values) {
            $input = $values["input"];
            $expected = $values["expected-headline"];
            if(!$hasHeadline){
                if(array_key_exists("expected",$values) && is_array($values["expected"])){
                    $expected = $values["expected"];
                }else {
                    $first = null;
                    foreach ($expected as $key => $line) {
                        if ($first === null) {
                            $first = array_keys($line);
                        }
                        $expected[$key] = array_values($line);
                    }
                    if ($first != null) {
                        $expected = array_merge([$first], $expected);
                    }
                }
            }

            $actual = null;

            $excMsg = "";
            try {
                $arr = explode(":",$callingMethod);
                $m = end($arr);
                $path = IOUtil::combinePaths($tmpFolder, $test . "_{$m}_read.csv");
                IOUtil::writeFileContent($path, $input, $encoding);
                $actual = IOUtil::readCsvFile($path, $hasHeadline, $encoding, $seperator, $quotation);
            } catch (\Exception $e) {
                $actual = get_class($e);
                $excMsg = " (".$e->getMessage().")";
            }

            $msg = [
                "Error at $test:",
                "WithHeader: " . ($hasHeadline ? "true" : "false"),
                "Encoding: " . $encoding,
                "Separator: " . $seperator,
                "Quotation: " . $quotation,
                "Input: " . json_encode($input),
                "Excpected: \t" . json_encode($expected),
                "Actual: \t" . json_encode($actual).$excMsg,
            ];
            $msg = implode("\n", $msg);

            // do not use assertions since they terminate the test on error
            if($actual != $expected){
                $errors[] = $msg;
            }
        }
        $this->assertCount(0, $errors, "[".get_called_class()." => ".$callingMethod."] Errors: ".count($errors)."\n\n".implode("\n\n", $errors));
    }

    public function test_ShouldWriteConformingToRfc4180()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = null;
        $hasHeadline = false;

        $this->_writingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldWriteInSpecificEncoding()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = "cp1252";
        $hasHeadline = false;

        $this->_writingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldWriteHeadline()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = null;
        $hasHeadline = true;

        $this->_writingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldWriteWithVaryingParameters()
    {
        $quotation = "#";
        $seperator = ";";
        $encoding = null;
        $hasHeadline = true;

        $this->_writingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldReadConformingToRfc4180()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = null;
        $hasHeadline = false;

        $this->_readingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }


    public function test_ShouldReadSpecificEncoding()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = "cp1252";
        $hasHeadline = false;

        $this->_readingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldReadHeadline()
    {
        $quotation = "\"";
        $seperator = ",";
        $encoding = null;
        $hasHeadline = true;

        $this->_readingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldReadWithVaryingParameters()
    {
        $quotation = "#";
        $seperator = ";";
        $encoding = null;
        $hasHeadline = false;

        $this->_readingTest($encoding,$hasHeadline,$seperator,$quotation,__METHOD__);
    }

    public function test_ShouldReadUtf8Uncorrupted(){
        // prepare problemtic payload:
        // The stream filter will read payload in 8192 byte chunks
        // making the 8192nd character two bytes long (like the "ü" in utf8) will result in a corrupted input string
        // ==> LATIN SMALL LETTER U WITH DIAERESIS => ü => U+00FC @see http://www.utf8-zeichentabelle.de/
        $content = implode("",array_fill(0,8191,"a"))."ü";
        $cp1252 = "cp1252";
        $utf8 = "utf-8";

        // write utf-8 formatted content to file
        $dir = self::getTmpDirPath();
        $url = IOUtil::combinePaths($dir,"csv_corrupted_encoding_test");
        IOUtil::writeFileContent($url,$content); // write without conversion [content is in utf-8]

        // prepare a cp1252 formatted string to compare what we read from the file
        $cp1252content = mb_convert_encoding($content, $cp1252, $utf8);

        //read content / will be converted from utf-8 to cp1252
        $settings = new EncodingStreamSettings($utf8,$cp1252,false);
        $urlToCp1252 = EncodingStreamFilter::getFilterURL($url,$settings);
        $readContent = IOUtil::getFileContent($urlToCp1252);
        // since we did not choose to convert the content "checked" via EncodingStreamSettings, the "ü" will be split in half
        // and cannot be decoded properly
        $this->assertNotEquals($cp1252content,$readContent,"The read content should be corrupted.");

        //read content / will be converted to cp1252
        $settings = new EncodingStreamSettings($utf8,$cp1252,true); // this will work since the conversion is "checked"
        $urlToCp1252 = EncodingStreamFilter::getFilterURL($url,$settings);
        $readContentUncorrupted = IOUtil::getFileContent($urlToCp1252);
        $this->assertEquals($cp1252content,$readContentUncorrupted,"The read content should not be corrupted.");
    }
}
 