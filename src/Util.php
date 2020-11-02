<?php
namespace Taxusorg\FilesystemQiniu;

use League\Flysystem\Util as FlysystemUtil;

class Util
{
    /**
     * Supplement info.
     *
     * @param array $file
     * @return array
     */
    public static function mapFileInfo(array $file)
    {
        $file['type'] = 'file';
        $file['path'] = $file['key'];
        $file['timestamp'] = $file['putTime'];
        $file['size'] = $file['fsize'];
        $file['mimetype'] = $file['mimeType'];

        return $file;
    }

    /**
     * The dir of the records all appeared.
     *
     * @param $contents
     *
     * @return array
     */
    public static function extractDirsWithFilesPath($contents)
    {
        $dirs = [];
        foreach ($contents as $key=>$content) {
            if(!$content['dirname'] || key_exists($content['dirname'], $dirs))
                continue;
            $directory = '';
            foreach (explode("/",$content['dirname']) as $value) {
                $directory = FlysystemUtil::normalizePath($directory . '/' . $value);
                $dirs[$directory]['path'] = $directory;
                $dirs[$directory]['type'] = 'dir';
            }
        }
        return $dirs;
    }

    public static function isNotKeep($file)
    {
        return $file['type'] == 'dir' || $file['basename'] != '.keep';
    }

    public static function dirname($path)
    {
        return dirname(FlysystemUtil::normalizePath($path));
    }

    public static function isImage(array $file)
    {
        if (isset($file['mimetype']) && strpos($file['mimetype'], 'image') === 0)
            return true;

        return false;
    }

    /**
     * @param string $domain
     * @return array
     */
    public static function normalizeDomain($domain)
    {
        $parsed_url = parse_url(trim($domain));
        $scheme     = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'http';
        $path       = isset($parsed_url['path']) ? FlysystemUtil::normalizePath($parsed_url['path']) : '';
        $host       = isset($parsed_url['host']) ? $parsed_url['host']. ($path ? '/'.$path : null) : $path;

        return [$host, $scheme];
    }

    public static function normalizeScheme($protocol = null, $default = 'http')
    {
        if (is_string($protocol) && in_array($protocol, ['http', 'https'])) {
            return $protocol;
        }

        if (true === $protocol) {
            return 'https';
        }

        if (false === $protocol) {
            return 'http';
        }

        if (null === $protocol) {
            return self::normalizeScheme($default, 'http');
        }

        throw new \Error('scheme mast in [true, false, "http", "https"]');
    }
}
