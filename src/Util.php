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

    public static function dirname($path)
    {
        return dirname(FlysystemUtil::normalizePath($path));
    }
}
