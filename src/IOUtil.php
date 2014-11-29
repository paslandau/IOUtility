<?php

namespace paslandau\IOUtility;

use Goodby\CSV\Export\Standard\Exporter;
use Goodby\CSV\Export\Standard\ExporterConfig;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;
use Goodby\CSV\Import\Standard\StreamFilter\ConvertMbstringEncoding;
use paslandau\IOUtility\Csv\CsvRows;
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

        if(self::isAbsolute($back)){
            $filepath = $back;
        }else {
            $filepath = $front . DIRECTORY_SEPARATOR . $back;
        }
        return self::getAbsolutePath($filepath);
    }

    /**
     * Workaround for 'realpath' not being able to resolve non existant files
     * @see http://php.net/manual/en/function.realpath.php#84012
     * @param $path
     * @return string
     */
    private static function getAbsolutePath($path)
    {
        /**
         * Windows style
         */
        if(mb_substr($path,0,strlen(DIRECTORY_SEPARATOR)) === DIRECTORY_SEPARATOR){
            $abs = DIRECTORY_SEPARATOR;
        }

        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $abs = "";
        if(mb_substr($path,0,strlen(DIRECTORY_SEPARATOR)) === DIRECTORY_SEPARATOR){
            $abs = DIRECTORY_SEPARATOR;
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
        return $abs.implode(DIRECTORY_SEPARATOR, $absolutes);
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
     * @param null|string $encoding . [optional]. Default: null.
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
     * @param bool $removeLineEndings . [optional]. Default: true.
     * @param bool $removeEmptyLines . [optional]. Default: true.
     * @param null|string $encoding . [optional]. Default: null.
     * @return string[]
     */
    public static function getFileContentLines($pathToFile, $removeLineEndings = true, $removeEmptyLines = true, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            $url = ConvertMbstringEncoding::getFilterURL($pathToFile, $encoding);
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
     * @param null|string $encoding . [optional]. Default: null.
     */
    public static function writeFileContent($pathToFile, $content, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            $url = ConvertMbstringEncoding::getFilterURL($pathToFile, mb_internal_encoding(), $encoding);
        }
        $file = new SplFileObject($url, "w");
        $file->fwrite($content);
    }

    /**
     * Appends $content to $pathToFile
     * @param string $pathToFile
     * @param string $content
     * @param null|string $encoding . [optional]. Default: null.
     */
    public static function appendFileContent($pathToFile, $content, $encoding = null)
    {
        if ($encoding === null) {
            $url = $pathToFile;
        } else {
            $url = ConvertMbstringEncoding::getFilterURL($pathToFile, mb_internal_encoding(), $encoding);
        }
        $file = new SplFileObject($url, "a");
        $file->fwrite($content);
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
                if (is_dir($file))
                    self::deleteDirectory($file);
                else
                    unlink($file);
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
     * @param string $pathToFile
     * @param CsvRows|string[] $rows
     * @param bool $withHeader [optional]. Default: true. Adds an additional line on top (uses CsvRows->headline OR the keys of the first row of the string[])
     * @param null $encoding [optional]. Default: null.
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: ".
     */
    public static function writeCsvFile($pathToFile, $rows, $withHeader = true, $encoding = null, $delimiter = ",", $enclosure = "\"", $escape = "\"")
    {
        $config = new ExporterConfig();
        $config->setDelimiter($delimiter);
//        $config->setFromCharset(mb_internal_encoding());
        if ($encoding !== null) {
            $config->setToCharset($encoding);
        }
        $config->setEnclosure($enclosure);
        $config->setEscape($escape);
        if ($rows instanceof CsvRows) {
            $rows = $rows->toArray($withHeader);
        }else{ // is a normal array
            if(count($rows) > 0){
                $first = reset($rows);
                $keys = array_keys($first);
                $rows = array_merge([$keys],$rows);
            }
        }
        $exporter = new Exporter($config);
        $exporter->export($pathToFile, $rows);
    }

    /**
     * @param string $pathToFile
     * @param bool $hasHeader [optional]. Default: true.
     * @param null $encoding [optional]. Default: null.
     * @param string $delimiter [optional]. Default: ,.
     * @param string $enclosure [optional]. Default: ".
     * @param string $escape [optional]. Default: ".
     * @return CsvRows
     */
    public static function readCsvFile($pathToFile, $hasHeader = true, $encoding = null, $delimiter = ",", $enclosure = "\"", $escape = "\"")
    {
        $config = new LexerConfig();
        $config->setDelimiter($delimiter);
        if ($encoding !== null) {
            $config->setFromCharset($encoding);
        }
        $config->setToCharset(mb_internal_encoding());
        $config->setEnclosure($enclosure);
        $config->setEscape($escape);
        $config->setIgnoreHeaderLine(false);
        $lexer = new Lexer($config);
        $interpreter = new Interpreter();
        $rows = new CsvRows($hasHeader);
        $first = true;
        $interpreter->addObserver(function (array $row) use (&$rows, &$first) {
            if ($first) {
                $first = false;
                $headline = [];
                if ($rows->hasHeadline()) {
                    foreach ($row as $key => $header) {
                        $headline[$key] = $header;
                    }
                    $rows->setHeadline($headline);
                    return;
                }
                $headline = array_keys($row);
                $rows->setHeadline($headline);
            }
            $rows->addRow($row);
        });
        $lexer->parse($pathToFile, $interpreter);
        return $rows;
    }

}

ConvertMbstringEncoding::register();