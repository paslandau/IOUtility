<?php

namespace paslandau\IOUtility;

use League\Csv\Reader;
use League\Csv\Writer;
use paslandau\IOUtility\Exceptions\IOException;
use SplFileObject;

class IOUtil
{

    /**
     * Concatenates the two given path parts using predefined const DIRECTORY_SEPARATOR.
     * @param $front
     * @param $back
     * @return string
     */
    public static function combinePaths($front, $back)
    {
        if (self::isAbsolute($back)) {
            $filepath = $back;
        } else {
            $filepath = $front . DIRECTORY_SEPARATOR . $back;
        }
        return self::getAbsolutePath($filepath);
    }

    /**
     * Checks if the given filesystem $path is absolute.
     * DO NOT USE FOR URLS!
     * @see http://oik-plugins.eu/woocommerce-a2z/oik_api/path_is_absolute/
     * @param string $path
     * @return bool - true if path is absolute
     */
    public static function isAbsolute($path)
    {

        // Windows allows absolute paths like this.
        if (preg_match('#^[a-zA-Z]:\\\\#', $path)) {
            return true;
        }

        /*
           * This is definitive if true but fails if $path does not exist or contains
           * a symbolic link.
           */
        if (realpath($path) == $path) {
            return true;
        }

        if (strlen($path) == 0 || $path[0] == '.') {
            return false;
        }

        // A path starting with / or \ is absolute; anything else is relative.
        return ($path[0] == '/' || $path[0] == '\\');
    }

    /**
     * Gets the full content of the given $pathToFile
     * @param string $pathToFile
     * @param null|string|EncodingStreamSettings $encoding [optional]. Default: null.
     * @return string
     */
    public static function getFileContent($pathToFile, $encoding = null)
    {
        $lines = self::getFileContentLines($pathToFile, false, false, $encoding);
        $text = implode("", $lines);
        return $text;
    }

    /**
     *  Gets the full content of the given $pathToFile as array of lines in that file
     * @param string $pathToFile
     * @param bool $removeLineEndings [optional]. Default: true.
     * @param bool $removeEmptyLines [optional]. Default: true.
     * @param null|string|EncodingStreamSettings $encoding [optional]. Default: null.
     * @return string[]
     */
    public static function getFileContentLines($pathToFile, $removeLineEndings = true, $removeEmptyLines = true, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            if (!($encoding instanceof EncodingStreamSettings)) {
                $encoding = new EncodingStreamSettings($encoding, mb_internal_encoding(), false);
            }
            $url = EncodingStreamFilter::getFilterURL($pathToFile, $encoding);
        }

        $file = new SplFileObject($url, "r");
        $lines = array();
        foreach ($file as $line) {
            if ($removeLineEndings) {
                $line = str_replace("\r\n", "\n", $line);
                $line = str_replace("\n", "", $line);
            }
            if ($removeEmptyLines && empty($line)) {
                continue;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * Writes $content to $pathToFile
     * @param string $pathToFile
     * @param string $content
     * @param null|string|EncodingStreamSettings $encoding [optional]. Default: null.
     */
    public static function writeFileContent($pathToFile, $content, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            if (!($encoding instanceof EncodingStreamSettings)) {
                $encoding = new EncodingStreamSettings(mb_internal_encoding(), $encoding, false);
            }
            $url = EncodingStreamFilter::getFilterURL($pathToFile, $encoding);
        }
        $file = new SplFileObject($url, "w");
        $file->fwrite($content);
        $file = null; // kill pointer
    }

    /**
     * Appends $content to $pathToFile
     * @param string $pathToFile
     * @param string $content
     * @param null|string|EncodingStreamSettings $encoding [optional]. Default: null.
     */
    public static function appendFileContent($pathToFile, $content, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            if (!($encoding instanceof EncodingStreamSettings)) {
                $encoding = new EncodingStreamSettings(mb_internal_encoding(), $encoding, false);
            }
            $url = EncodingStreamFilter::getFilterURL($pathToFile, $encoding);
        }
        $file = new SplFileObject($url, "a");
        $file->fwrite($content);
        $file = null; // kill pointer
    }

    /**
     * Get an array that represents directory tree. Files contain the full path.
     * @see http://www.php.net/manual/de/function.scandir.php#109140
     * @param string $directory . \Directory path
     * @param bool $recursive [optional]. Default: true. Include sub directories
     * @param bool $listDirs [optional]. Default: false. Include directories on listing
     * @param bool $listFiles [optional]. Default: true. Include files on listing
     * @param string $exclude [optional]. Default: "". Exclude paths that matches this regex
     * @param string $include [optional]. Default: "". Include only paths that matches this regex
     * @return string[]
     */
    public static function getFiles($directory, $recursive = true, $listDirs = false, $listFiles = true, $exclude = '', $include = '')
    {
        $arrayItems = array();
        $skipByExclude = false;
        $skipByInclude = false;
        $handle = opendir($directory);
        if ($handle) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^[\\.]{1,2}$/", $file)) {
                    continue;
                }
                if ($exclude !== null && !empty($exclude)) {
                    $skipByExclude = preg_match($exclude, $file) > 0;
                }
                if ($include !== null && !empty($include)) {
                    $skipByInclude = preg_match($include, $file) == 0;
                }
                $pathToFile = self::combinePaths($directory, $file);// $directory . DIRECTORY_SEPARATOR . $file;
                if (!$skipByExclude && !$skipByInclude) {
                    if (is_dir($pathToFile) && $recursive) {
                        $arrayItems = array_merge($arrayItems, self::GetFiles($pathToFile, $recursive, $listDirs, $listFiles, $exclude, $include));
                    }
                    if (is_dir($pathToFile)) {
                        if ($listDirs) {
                            $arrayItems [] = $pathToFile;
                        }
                    } else {
                        if ($listFiles) {
                            $arrayItems [] = $pathToFile;
                        }
                    }
                }
            }
            closedir($handle);
        }
        return $arrayItems;
    }

    /**
     * Deletes the directory at $pathToDir recursively!
     * @param string $pathToDir
     * @throws IOException
     */
    public static function deleteDirectory($pathToDir)
    {
        if ($pathToDir === null || empty($pathToDir)) {
            throw new IOException ("pathToDir must not be empty!");
        }
        if (!file_exists($pathToDir)) {
            return;
        }
        if (!is_dir($pathToDir)) {
            throw new IOException ("pathToDir '$pathToDir' is not a directory!");
        }

        $objects = scandir($pathToDir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $file = self::combinePaths($pathToDir, $object);
                if (is_dir($file)) {
                    self::deleteDirectory($file);
                } else {
                    unlink($file);
                }
            }
        }
        rmdir($pathToDir);
    }

    /**
     * Creates a new directory at $pathToDir if it doesn't exist
     * @param string $pathToDir
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public static function createDirectoryIfNotExists($pathToDir, $mode = 0777, $recursive = false)
    {
        if (!file_exists($pathToDir)) {
            return mkdir($pathToDir, $mode, $recursive);
        }
        return true;
    }

    /**
     * Copies the content of the entire directory from $pathToSourceDir to $pathToTargetDir
     * @param string $pathToSourceDir
     * @param string $pathToTargetDir
     * @see http://stackoverflow.com/a/7775949/413531
     */
    public static function copyDirectory($pathToSourceDir, $pathToTargetDir)
    {
        if (!is_dir($pathToSourceDir)) {
            throw new \UnexpectedValueException("pathToSourceDir '$pathToSourceDir' must be a directory!");
        }
        self::createDirectoryIfNotExists($pathToTargetDir, 0777, true);
        if (!is_dir($pathToTargetDir)) {
            throw new \UnexpectedValueException("pathToTargetDir '$pathToTargetDir' must be a directory!");
        }
        $iterator = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator ($pathToSourceDir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $new = self::combinePaths($pathToTargetDir, $iterator->getSubPathName());
            if ($item->isDir() && !file_exists($new)) {
                mkdir($new);
            } else {
                copy($item, $new);
            }
        }
    }

    /**
     * @param string $pathToFile
     * @param string[][] $rows
     * @param bool $withHeader [optional]. Default: true. Adds an additional line on top (uses CsvRows->headline OR the keys of the first row of the string[])
     * @param string|EncodingStreamSettings|null $encoding [optional]. Default: null (null: no conversion is performed - file as read "as is").
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: ".
     */
    public static function writeCsvFile($pathToFile, $rows, $withHeader = true, $encoding = null, $delimiter = null, $enclosure = null, $escape = null)
    {
        if ($delimiter === null) {
            $delimiter = ",";
        }
        if ($enclosure === null) {
            $enclosure = "\"";
        }
        if ($escape === null) {
            $escape = "\\";
        }

        if (!($encoding instanceof EncodingStreamSettings)) {
            $encoding = new EncodingStreamSettings(mb_internal_encoding(), $encoding, false);
        }

        $url = EncodingStreamFilter::getFilterURL($pathToFile, $encoding);
        $obj = new SplFileObject($url, "w");
        $writer = Writer::createFromFileObject($obj);
        $writer->setDelimiter($delimiter);
        $writer->setEnclosure($enclosure);
        $writer->setEscape($escape);
        if (count($rows) > 0 && $withHeader) {
            $first = reset($rows);
            $keys = array_keys($first);
            $rows = array_merge([$keys], $rows);
        }
        $writer->insertAll($rows);
    }


    /**
     * Applies $callback to a chunk read from the CSVfile
     * @param callable $callback
     * @param $chunkSize
     * @param string $pathToFile
     * @param bool $hasHeader [optional]. Default: true.
     * @param string|EncodingStreamSettings|null $encoding [optional]. Default: null (null: no conversion is performed - file as read "as is").
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: \.
     * @param null|callable $fn [optional]. Default: null. Callable, that will be applied to each row. See Reader::fetchAssoc() / Reader::fetchAll()
     * @param int $offset [optional]. Default: 0.
     * @param int $limit [optional]. Default: -1.
     * @return int - number of total rows
     */
    public static function walkCsvFile(Callable $callback, $chunkSize, $pathToFile, $hasHeader = null, $encoding = null, $delimiter = null, $enclosure = null, $escape = null, $fn = null, $offset = null, $limit = null)
    {
        $reader = self::getCsvReader($pathToFile, $encoding, $delimiter, $enclosure, $escape, $offset, $limit);
        if ($hasHeader) {
            $iter = $reader->fetchAssoc(0, $fn);
        } else {
            $iter = $reader->fetch($fn);
        }

        $buffer = [];
        $count = 0;
        foreach($iter as $key => $line){
            $count++;
            $buffer[] = $line;
            if (count($buffer) % $chunkSize === 0) {
                $callback($buffer);
                $buffer = [];
            }
        }
        if (count($buffer) > 0) {
            $callback($buffer);
        }
        return $count;
    }
//    public static function walkCsvFile($callback, $chunkSize, $pathToFile, $hasHeader = null, $encoding = null, $delimiter = null, $enclosure = null, $escape = null, $fn = null, $offset = null, $limit = null)
//    {
//        $buffer = [];
//        $walkerFn = function ($line) use (&$buffer, $callback, $chunkSize, $fn) {
//            /**
//             * Apply the given line-callback
//             * @var Callable $fn ;
//             */
//            if ($fn !== null) {
//                $line = $fn($line);
//            }
//            $buffer[] = $line;
//            if (count($buffer) % $chunkSize === 0) {
//                $callback($buffer);
//                $buffer = [];
//            }
//            return $line;
////            return false; // make sure we don't waste memory by saving the lines somewhere else in addtion to $buffer
//        };
//        self::readCsvFile($pathToFile, $hasHeader, $encoding, $delimiter, $enclosure, $escape, $walkerFn, $offset, $limit);
//        if (count($buffer) > 0) {
//            $callback($buffer);
//        }
//    }

    /**
     * @param string $pathToFile
     * @param string|EncodingStreamSettings|null $encoding [optional]. Default: null (null: no conversion is performed - file as read "as is").
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: \.
     * @param int $offset [optional]. Default: 0.
     * @param int $limit [optional]. Default: -1.
     * @return Reader
     * @todo TEST
     */
    public static function getCsvReader($pathToFile, $encoding = null, $delimiter = null, $enclosure = null, $escape = null, $offset = null, $limit = null)
    {
        if ($limit === null) {
            $limit = -1;
        }
        if ($offset === null) {
            $offset = 0;
        }
        if ($delimiter === null) {
            $delimiter = ",";
        }
        if ($enclosure === null) {
            $enclosure = "\"";
        }
        if ($escape === null) {
            $escape = "\\";
        }

        if (!($encoding instanceof EncodingStreamSettings)) {
            $encoding = new EncodingStreamSettings($encoding, mb_internal_encoding(), false);
        }

        $url = $pathToFile;
        if ($encoding !== null) {
            $url = EncodingStreamFilter::getFilterURL($pathToFile, $encoding);
        }

        $obj = new SplFileObject($url, "r");
//        $reader = Reader::createFromPath($pathToFile);
        $reader = Reader::createFromFileObject($obj);
//        if($encoding !== null) {
//            $reader->prependStreamFilter(EncodingStreamFilter::getFilterWithParameters($encoding));
//        }
        $reader->setDelimiter($delimiter);
        $reader->setEnclosure($enclosure);
        $reader->setEscape($escape);
        $reader->setOffset($offset);
        $reader->setLimit($limit);
//        $reader->setNewline("\r\n");
//            $reader->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
//        $reader->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        return $reader;
    }

    /**
     * @param string $pathToFile
     * @param bool $hasHeader [optional]. Default: true.
     * @param string|EncodingStreamSettings|null $encoding [optional]. Default: null (null: no conversion is performed - file as read "as is").
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: \.
     * @param null|callable $fn [optional]. Default: null. Callable, that will be applied to each row. See Reader::fetchAssoc() / Reader::fetchAll()
     * @param int $offset [optional]. Default: 0.
     * @param int $limit [optional]. Default: -1.
     * @return \string[][]
     */
    public static function readCsvFile($pathToFile, $hasHeader = null, $encoding = null, $delimiter = null, $enclosure = null, $escape = null, $fn = null, $offset = null, $limit = null)
    {
        $reader = self::getCsvReader($pathToFile, $encoding, $delimiter, $enclosure, $escape, $offset, $limit);
        if ($hasHeader) {
            $iter = $reader->fetchAssoc(0, $fn);
            $result = iterator_to_array($iter, false);
        } else {
            $result = $reader->fetchAll($fn);
        }
        return $result;
    }

    /**
     * Workaround for 'realpath' not being able to resolve non existant files
     * @see http://php.net/manual/en/function.realpath.php#84012
     * @param $path
     * @return string
     */
    private static function getAbsolutePath($path)
    {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $abs = "";
        if (mb_substr($path, 0, strlen(DIRECTORY_SEPARATOR)) === DIRECTORY_SEPARATOR) {
            $abs = DIRECTORY_SEPARATOR;
        }
        $end = "";
        if (mb_substr($path, -strlen(DIRECTORY_SEPARATOR)) === DIRECTORY_SEPARATOR) {
            $end = DIRECTORY_SEPARATOR;
        }
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part) continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return $abs . implode(DIRECTORY_SEPARATOR, $absolutes) . $end;
    }

    /**
     * Returns the path of a temporary file. Don't forget to unlink() it after usage!
     * @return string
     * @todo TEST
     */
    public static function getPathToTempFile()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), "TMP");
        return $tmpFile;
    }

    /**
     * PHP implementation of unix's "tail"
     * @see http://stackoverflow.com/a/15025877/413531
     * @see http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @param $filename
     * @param int $lines
     * @param int $buffer
     * @return string
     * @todo TEST
     */
    public static function tail($filename, $lines = 10, $buffer = 4096)
    {
        // Open the file
        $f = fopen($filename, "rb");

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
        return $output;
    }
}

EncodingStreamFilter::register();