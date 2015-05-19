<?php

namespace paslandau\IOUtility;

use php_user_filter;
use RuntimeException;

/**
 * Class EncodingStreamFilter
 * Statically called in IOUtil to register the filter
 * @see https://github.com/goodby/csv/blob/master/src/Goodby/CSV/Import/Standard/StreamFilter/ConvertMbstringEncoding.php
 * @package paslandau\IOUtility
 */
class EncodingStreamFilter extends php_user_filter
{
    /**
     * @var string
     */
    const FILTER_NAMESPACE = 'paslandau.convert.encoding.';

    /**
     * @var bool
     */
    private static $hasBeenRegistered = false;

    /**
     * @var string
     */
    private $fromCharset;

    /**
     * @var string
     */
    private $toCharset;

    /**
     * Backup of locale
     * @var
     */
    private $locale;

    /**
     * @var string
     */
    private $data;

    /**
     * @var bool
     */
    private $checked;

    /**
     * @var
     */
    private $bucket;

    private $c;

    /**
     * Return filter name
     * @return string
     */
    public static function getFilterName()
    {
        return self::FILTER_NAMESPACE.'*';
    }

    /**
     * Register this class as a stream filter
     * @throws \RuntimeException
     */
    public static function register()
    {
        if ( self::$hasBeenRegistered === true ) {
            return;
        }

        if ( stream_filter_register(self::getFilterName(), __CLASS__) === false ) {
            throw new RuntimeException('Failed to register stream filter: '.self::getFilterName());
        }

        self::$hasBeenRegistered = true;
    }

    /**
     * Returns a filter in a pattern that will be recognized at $this->onCreate() in the form
     * $fromCharset(:$toCharset)(::checked)
     * Parts in braces are optional, so the following patterns are possible
     * UTF-8:CP1252::checked
     * UTF-8:CP1252
     * UTF-8::checked
     * @param EncodingStreamSettings $encodingSettings. If $encodingSettings->isChecked() is true $this->filterChecked() will be used upon consuming data from the stream
     * @return string
     */
    public static function getFilterWithParameters(EncodingStreamSettings $encodingSettings){
        $fromCharset = mb_internal_encoding();
        if($encodingSettings->getFromEncoding() !== null){
            $fromCharset = $encodingSettings->getFromEncoding();
        }
        $toCharset = $encodingSettings->getToEncoding();
        if ( $toCharset === null ) {
            $filter = sprintf(self::FILTER_NAMESPACE.'%s', $fromCharset);
        } else {
            $toCharset = mb_strtoupper($toCharset);
            $filter = sprintf(self::FILTER_NAMESPACE.'%s:%s', $fromCharset, $toCharset);
        }
        if($encodingSettings->isChecked()){
            $filter .= "::checked";
        }
        return $filter;
    }

    /**
     * Return filter URL
     * @param string $filename
     * @param EncodingStreamSettings $encodingSettings. If $encodingSettings->isChecked() is true $this->filterChecked() will be used upon consuming data from the stream
     * @return string
     */
    public static function getFilterURL($filename, EncodingStreamSettings $encodingSettings)
    {
        $fromCharset = mb_internal_encoding();
        if($encodingSettings->getFromEncoding() !== null){
            $fromCharset = $encodingSettings->getFromEncoding();
        }

        $toCharset = mb_internal_encoding();
        if($encodingSettings->getToEncoding() !== null){
            $toCharset = $encodingSettings->getToEncoding();
        }

        if(mb_strtolower($fromCharset) == mb_strtolower($toCharset)){
            return $filename; // nothing to do
        }
        $filtername = self::getFilterWithParameters($encodingSettings);
        return sprintf('php://filter/%s/resource=%s', $filtername, $filename);
    }

    /**
     * @return bool
     */
    public function onCreate()
    {
//        echo "create\n";
        if ( strpos($this->filtername, self::FILTER_NAMESPACE) !== 0 ) {
            return false;
        }

        $this->data = '';
        $this->c = 0;

        $parameterString = substr($this->filtername, strlen(self::FILTER_NAMESPACE));

        if ( ! preg_match('/^(?P<from>[-\w]+)(:(?P<to>[-\w]+))?(::(?P<checked>checked))?$/', $parameterString, $matches) ) {
            return false;
        }

        $this->fromCharset = isset($matches['from']) &&  $matches['from'] !== "" ? $matches['from'] : 'auto';
        $this->toCharset   = isset($matches['to']) &&  $matches['to'] !== ""  ? $matches['to']   : mb_internal_encoding();
        $this->checked   = isset($matches['checked'])   ? true : false;

        // fixes https://bugs.php.net/bug.php?id=32062
        $this->locale = setlocale(LC_CTYPE, '0'); // backup current locale
        if (stripos($this->locale, 'UTF-8') === false) {
            setlocale(LC_CTYPE, 'en_US.UTF-8');
        }

        return true;
    }

    public function onClose()
    {
//        echo "close\n";
        setlocale(LC_CTYPE, $this->locale); // reset locale
    }

    /**
     * @param string $in
     * @param string $out
     * @param string $consumed
     * @param $closing
     * @return int
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        if($this->checked){
            return $this->filterChecked($in, $out, $consumed, $closing);
        }
        return $this->filterUnchecked($in, $out, $consumed, $closing);
    }

    /**
     * Filters the input stream checked. That means that data is only convertert after the whole stream has been consumed.
     * This will take approx. twice as long as $this->filterUnchecked() but will make sure that no side effect occur.
     * @param $in
     * @param $out
     * @param $consumed
     * @param $closing
     * @return int
     */
    private function filterChecked($in, $out, &$consumed, $closing){

        while ($bucket = stream_bucket_make_writeable($in)) {
            $this->data .= $bucket->data;
            $bucket->data = "";
            $bucket->datalen = 0;
            $this->bucket = $bucket;
//            $this->c += $bucket->datalen;
//            $consumed += $bucket->datalen;
            $consumed = 0;
            stream_bucket_append($out, $bucket);
        }

        if ($closing) {
//            echo "checked\n";
            $consumed = strlen($this->data);
            $this->data = mb_convert_encoding($this->data, $this->toCharset, $this->fromCharset);
            $this->bucket->data = $this->data;
            $this->bucket->datalen = strlen($this->data);
            stream_bucket_append($out, $this->bucket);
            //clear data
            $this->data = "";
            $this->c = 0;
            $this->bucket = null;
            return PSFS_PASS_ON;
        }

        return PSFS_PASS_ON;
    }

    /**
     * Filters the input stream unchecked. That means that data of a bucket can contain only "half" of a multibyte character
     * so that the output is corrputed.
     * @see http://etutorials.org/Server+Administration/upgrading+php+5/Chapter+8.+Streams+Wrappers+and+Filters/8.6+Creating+Filters/ for a similar example
     * or use $content = implode("",array_fill(0,8191,"a"))."ü"; to generate an input that will be corrupted
     * @param $in
     * @param $out
     * @param $consumed
     * @param $closing
     * @return int
     */
    private function filterUnchecked($in, $out, &$consumed, $closing){
        while ( $bucket = stream_bucket_make_writeable($in) ) {
            $bucket->data = mb_convert_encoding($bucket->data, $this->toCharset, $this->fromCharset);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

//        if ($closing) {
//            echo "unchecked\n";
//        }

        return PSFS_PASS_ON;
    }
}
