<?php

namespace Taxusorg\FilesystemQiniu;

class Thumbnail
{
    const VIEW = 'imageView2';

    private $mode = 0;

    private $queries = [];

    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function setConfig(array $config = [])
    {
        isset($config['mode']) && $this->setMode($config['mode']);
        isset($config['width']) && $this->setWidth($config['width']);
        isset($config['height']) && $this->setHeight($config['height']);
        isset($config['format']) && $this->setFormat($config['format']);
        isset($config['interlace']) && $this->setInterlace($config['interlace']);
        isset($config['quality']) && $this->setQuality($config['quality']);
        isset($config['colors']) && $this->setColors($config['colors']);
        isset($config['ignoreError']) && $this->setIgnoreError($config['ignoreError']);

        return $this;
    }

    /**
     * @param $var
     * @return bool
     */
    protected function queriesFilter($var)
    {
        if (is_string($var) && strlen($var))
            return true;

        return is_numeric($var) || is_bool($var);
    }

    public function getBuiltQueries(array $config = [])
    {
        if (! empty($config))
            return (clone $this)->setConfig($config)->getBuiltQueries();

        $query_str = http_build_query(array_filter($this->queries, [$this, 'queriesFilter']), null, '/');

        return self::VIEW . '/' . $this->mode . '/' . str_replace('=', '/', $query_str);
    }

    public function getUrl($url, array $config = [])
    {
        if ($pos = strripos($url, '?')) {
            if ($pos !== strlen($url) - 1)
                $url .= '&';

            return $url . $this->getBuiltQueries($config);
        } else {
            return $url . '?' . $this->getBuiltQueries($config);
        }
    }

    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function setWidth($width)
    {
        $this->queries['w'] = $width;

        return $this;
    }

    public function getWidth()
    {
        return $this->queries['w'];
    }

    public function setHeight($height)
    {
        $this->queries['h'] = $height;

        return $this;
    }

    public function getHeight()
    {
        return $this->queries['h'];
    }

    public function setFormat($format)
    {
        $this->queries['format'] = $format;

        return $this;
    }

    public function getFormat()
    {
        return $this->queries['format'];
    }

    public function setInterlace($interlace)
    {
        $this->queries['interlace'] = $interlace;

        return $this;
    }

    public function getInterlace()
    {
        return $this->queries['interlace'];
    }

    public function setQuality($quality)
    {
        $this->queries['q'] = $quality;

        return $this;
    }

    public function getQuality()
    {
        return $this->queries['q'];
    }

    public function setColors($colors)
    {
        $this->queries['colors'] = $colors;

        return $this;
    }

    public function getColors()
    {
        return $this->queries['colors'];
    }

    public function setIgnoreError($ignoreError)
    {
        $this->queries['ignore-error'] = $ignoreError;

        return $this;
    }

    public function getIgnoreError()
    {
        return $this->queries['ignore-error'];
    }


}
