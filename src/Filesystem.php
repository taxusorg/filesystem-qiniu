<?php

namespace Taxusorg\FilesystemQiNiu;

use League\Flysystem\Config;
use League\Flysystem\Util as FlysystemUtil;
use Qiniu\Auth;
use Qiniu\Config as QiNiuConfig;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\FormUploader;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use Taxusorg\FilesystemQiniu\Exceptions\QiniuException;

class Filesystem
{
    /**
     * @var QiNiuConfig
     */
    protected $qiNiuConfig;

    /**
     * @var Auth
     */
    protected $auth;

    protected $uploadManager;
    protected $bucketManager;

    public function __construct(Auth $auth, QiNiuConfig $config = null)
    {
        $this->qiNiuConfig = $config ? $config : new QiNiuConfig();
        $this->auth = $auth;
    }

    /**
     * @param string $bucket
     * @param string $path
     * @param string|resource $contents
     * @param null|array $params
     * @param string $mime
     * @param bool $checkCrc
     * @return array|false false on failure file meta data on success
     */
    public function write($bucket, $path, $contents, $params = null, $mime = 'application/octet-stream', $checkCrc = false)
    {
        $path = Util::normalizePath($path);

        list($result, $error) = $this->getUploadManager()->put(
            $this->auth->uploadToken($bucket, $path),
            $path,
            $contents,
            $params,
            $mime,
            $checkCrc
        );

        if($error !== null)
            throw new QiniuException($error);

        return compact('path');
    }

    /**
     * @param $bucket
     * @param string $path
     * @param resource $resource
     * @param null|array $params
     * @param string $mime
     * @param bool $checkCrc
     * @return array|false false on failure file meta data on success
     * @throws \Exception
     */
    public function writeStream($bucket, $path, $resource, $params = null, $mime = 'application/octet-stream', $checkCrc = false)
    {
        if (is_resource($resource) && !stream_is_local($resource))
            return $this->write($bucket, $path, stream_get_contents($resource), $params, $mime, $checkCrc);

        $path = FlysystemUtil::normalizePath($path);

        $file = $resource;
        $params = UploadManager::trimParams($params);
        $stat = fstat($file);
        $size = $stat['size'];
        $upToken = $this->auth->uploadToken($bucket, $path);

        if ($size <= QiniuConfig::BLOCK_SIZE) {
            $data = fread($file, $size);
            if ($data === false) {
                throw new \Exception("file can not read", 1);
            }
            list($result, $error) = FormUploader::put(
                $upToken,
                $path,
                $data,
                $this->qiNiuConfig,
                $params,
                $mime,
                $checkCrc
            );
        }else{
            $up = new ResumeUploader(
                $upToken,
                $path,
                $file,
                $size,
                $params,
                $mime,
                $this->qiNiuConfig
            );
            list($result, $error) = $up->upload();
        }

        if($error !== null)
            throw new QiniuException($error);

        return compact('path');
    }

    /**
     * @param string $bucket_old
     * @param string $old
     * @param string $bucket_new
     * @param string $new
     * @param bool $force
     * @return bool
     */
    public function rename($bucket_old, $old, $bucket_new, $new, $force = false)
    {
        $old = FlysystemUtil::normalizePath($old);
        $new = FlysystemUtil::normalizePath($new);

        $error = $this->getBucketManager()->move($bucket_old, $old, $bucket_new, $new, $force);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * @param $bucket_old
     * @param string $old
     * @param $bucket_new
     * @param string $new
     * @param bool $force
     * @return bool
     */
    public function copy($bucket_old, $old, $bucket_new, $new, $force = false)
    {
        $old = FlysystemUtil::normalizePath($old);
        $new = FlysystemUtil::normalizePath($new);

        $error = $this->getBucketManager()->copy($bucket_old, $old, $bucket_new, $new, $force);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * @param string $bucket
     * @param string $path
     * @return bool
     */
    public function delete($bucket, $path)
    {
        $path = FlysystemUtil::normalizePath($path);

        $error = $this->getBucketManager()->delete($bucket, $path);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * @param string $bucket
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($bucket, $dirname)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        $files = array_map(function ($array) {
            return $array['path'];
        }, $this->listContentsIncludeKeep($bucket, $dirname, true));

        $ops = $this->getBucketManager()->buildBatchDelete($bucket, $files);
        list($result, $error) = $this->getBucketManager()->batch($ops);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * @param string $bucket
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDir($bucket, $dirname, Config $config)
    {
        return $this->createDirKeep($bucket, $dirname, $config);
    }

    /**
     * @param string $bucket
     * @param string $dirname directory name
     * @return array|false
     */
    public function createDirKeep($bucket, $dirname)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        $path = $this->getKeepPath($dirname);

        $this->write($bucket, $path, '');

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * @param string $bucket
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function deleteDirKeep($bucket, $dirname, Config $config)
    {
        $file = $this->getKeepPath($dirname);

        if($this->has($bucket, $file))
            return $this->delete($bucket, $file);

        return true;
    }

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

    /**
     * @param $bucket
     * @return string
     */
    public function getUploadToken($bucket)
    {
        return $token = $this->auth->uploadToken($bucket);
    }

    /**
     * @return UploadManager
     */
    protected function getUploadManager()
    {
        if ($this->uploadManager instanceof UploadManager) {
            return $this->uploadManager;
        }

        return $this->uploadManager = new UploadManager($this->qiNiuConfig);
    }

    /**
     * @return BucketManager
     */
    protected function getBucketManager()
    {
        if ($this->bucketManager instanceof BucketManager) {
            return $this->bucketManager;
        }

        return $this->bucketManager = new BucketManager($this->auth, $this->qiNiuConfig);
    }

    public function getKeepPath($dirname, $filename = null)
    {
        return Util::normalizePath($dirname . '/' .
            ($filename ? Util::normalizePath($filename) : '.keep'));
    }

    public function isKeep(array $file)
    {
        return ! $this->isNotKeep($file);
    }

    public function isNotKeep(array $file)
    {
        return $file['type'] == 'dir' || $file['basename'] != '.keep';
    }
}
