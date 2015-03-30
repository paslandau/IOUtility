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

    public static function getFilterWithParameters($fromCharset, $toCharset = null){
        if ( $toCharset === null ) {
            return sprintf(self::FILTER_NAMESPACE.'%s', $fromCharset);
        } else {
            return sprintf(self::FILTER_NAMESPACE.'%s:%s', $fromCharset, $toCharset);
        }
    }

    /**
     * Return filter URL
     * @param string $filename
     * @param string $fromCharset
     * @param string $toCharset
     * @return string
     */
    public static function getFilterURL($filename, $fromCharset, $toCharset = null)
    {
        $filtername = self::getFilterWithParameters($fromCharset, $toCharset);
        return sprintf('php://filter/%s/resource=%s', $filtername, $filename);
    }

    /**
     * @return bool
     */
    public function onCreate()
    {
        if ( strpos($this->filtername, self::FILTER_NAMESPACE) !== 0 ) {
            return false;
        }

        $parameterString = substr($this->filtername, strlen(self::FILTER_NAMESPACE));

        if ( ! preg_match('/^(?P<from>[-\w]+)(:(?P<to>[-\w]+))?$/', $parameterString, $matches) ) {
            return false;
        }

        $this->fromCharset = isset($matches['from']) ? $matches['from'] : 'auto';
        $this->toCharset   = isset($matches['to'])   ? $matches['to']   : mb_internal_encoding();

        return true;
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
        while ( $bucket = stream_bucket_make_writeable($in) ) {
            $bucket->data = mb_convert_encoding($bucket->data, $this->toCharset, $this->fromCharset);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
