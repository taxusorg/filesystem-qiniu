<?php

namespace Taxusorg\FilesystemQiniu;

use Taxusorg\FilesystemQiniu\Exceptions\QiniuException;

trait FilesystemReadTrait
{
    /**
     * Get stat
     *
     * @param string $bucket
     * @param string $path
     * @return array|false
     */
    public function stat($bucket, $path)
    {
        $path = Util::normalizePath($path);

        list($result, $error) = $this->getBucketManager()->stat($bucket, $path);

        // todo: 如果文件不存在返回什么结果
        if ($error !== null)
            throw new QiniuException($error);

        $result['key'] = $path;

        return Util::mapFileInfo($result);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $bucket
     * @param string $path
     * @return array|bool|null
     */
    public function has($bucket, $path)
    {
        if ($this->stat($bucket, $path))
            return true;

        $result = $this->listContentsPart($bucket, $path);

        return $result && ! empty($result);
    }

    /**
     * @param string $bucket
     * @param string $directory
     * @param int $limit
     * @return bool|array
     */
    protected function listContentsPart($bucket, $directory = '', $limit = 1)
    {
        $directory = Util::normalizePath($directory);

        $result = $this->getContentsFromBucket($bucket, $directory, null, $limit);

        if ($result === false)
            return false;

        return $result['items'];
    }

    /**
     * @param string $bucket
     * @param string $directory
     * @param bool $recursive
     * @return array
     */
    public function listContents($bucket, $directory = '', $recursive = false)
    {
        $data = $this->listContentsIncludeKeep($bucket, $directory, $recursive);

        return array_filter($data, [$this, 'isNotKeep']);
    }

    /**
     * @param string $bucket
     * @param string $directory
     * @param bool $recursive
     * @return array
     */
    public function listContentsIncludeKeep($bucket, $directory = '', $recursive = false)
    {
        $directory = Util::normalizePath($directory);

        $prefix = $directory;
        $marker = null;
        $limit = 1000;
        $delimiter = null; // todo: delimiter able

        $_data = [];
        do {
            $result = $this->getContentsFromBucket($bucket, $prefix, $marker, $limit, $delimiter);
            if ($result === false) {
                break;
            } else {
                $_data = array_merge($_data, $result['items']);
            }
        } while (array_key_exists('marker', $result) && $marker = $result['marker']);

        $_data = array_map([Util::class, 'mapFileInfo'], $_data);
        $_data = Util::formatListing($directory, $_data);
        $_data = array_merge($_data, Util::extractDirsWithFilesPath($_data));

        return Util::formatListing($directory, $_data, $recursive);
    }

    protected function getContentsFromBucket($bucket, $prefix, $marker = null, $limit = 1000, $delimiter = null)
    {
        list($result, $error) = $this->getBucketManager()->listFiles($bucket, $prefix, $marker, $limit, $delimiter);
        if ($error !== null) throw new QiniuException($error);

        return $result;
    }

}
