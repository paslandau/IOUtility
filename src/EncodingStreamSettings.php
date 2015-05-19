<?php

namespace paslandau\IOUtility;


class EncodingStreamSettings {
    /**
     * @var string
     */
    private $toEncoding;

    /**
     * @var string
     */
    private $fromEncoding;

    /**
     * @var bool
     */
    private $checked;

    /**
     * @param string|null $fromEncoding
     * @param string|null $toEncoding
     * @param bool $checked [optional]. Default: false.
     */
    function __construct($fromEncoding = null, $toEncoding = null, $checked = null)
    {
        $this->fromEncoding = $fromEncoding;
        $this->toEncoding = $toEncoding;
        if($checked === null) {
            $checked = false;
        }
        $this->checked = $checked;
    }

    /**
     * @return string|null
     */
    public function getToEncoding()
    {
        return $this->toEncoding;
    }

    /**
     * @param string|null $toEncoding
     */
    public function setToEncoding($toEncoding)
    {
        $this->toEncoding = $toEncoding;
    }


    /**
     * @return string|null
     */
    public function getFromEncoding()
    {
        return $this->fromEncoding;
    }

    /**
     * @param string|null $fromEncoding
     */
    public function setFromEncoding($fromEncoding)
    {
        $this->fromEncoding = $fromEncoding;
    }

    /**
     * @return boolean
     */
    public function isChecked()
    {
        return $this->checked;
    }

    /**
     * @param boolean $checked
     */
    public function setChecked($checked)
    {
        $this->checked = $checked;
    }

}