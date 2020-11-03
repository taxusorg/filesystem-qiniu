<?php
namespace Taxusorg\FilesystemQiniu\Adapter;

use Qiniu\Processing\Operation;
use Qiniu\Processing\PersistentFop;
use Taxusorg\FilesystemQiniu\Exceptions\QiniuException;
use Taxusorg\FilesystemQiniu\Thumbnail;
use Taxusorg\FilesystemQiniu\Util;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util as FlysystemUtil;
use League\Flysystem\Util\ContentListingFormatter;
use Qiniu\Config as QiniuConfig;
use Qiniu\Auth;
use Qiniu\Storage\FormUploader;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

class QiniuAdapter implements AdapterInterface
{
    private $accessKey = '';
    private $secretKey = '';
    private $bucket = '';
    private $domain = '';
    private $protocol;
    private $private_protocol;
    private $notify_url;

    private $auth;
    private $uploadManager;
    private $bucketManager;
    private $operation;
    private $persistentFop;
    private $qiniuConfig;

    private $thumbnail;

    public function __construct($config = [], Thumbnail $thumbnail = null)
    {
        isset($config['key']) && $this->setAccessKey($config['key']);
        isset($config['secret']) && $this->setSecretKey($config['secret']);
        isset($config['bucket']) && $this->setBucket($config['bucket']);
        isset($config['domain']) && $this->setDomain($config['domain']);
        isset($config['protocol']) && $this->setProtocol($config['protocol']);
        isset($config['private_protocol']) && $this->setPrivateProtocol($config['private_protocol']);

        $this->qiniuConfig = new QiniuConfig();

        if ($thumbnail == null && isset($config['thumbnail']) && is_array($config['thumbnail'])) {
            $this->thumbnail = new Thumbnail($config['thumbnail']);
        } else {
            $this->thumbnail = $thumbnail;
        }
    }

    public function setThumbnail(Thumbnail $thumbnail)
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     *
     * @return Thumbnail
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string|resource $contents
     * @param Config $config   Config object
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $path = FlysystemUtil::normalizePath($path);

        list($result, $error) = $this->getUploadManager()->put(
            $this->getAuth()->uploadToken($this->bucket, $path),
            $path,
            $contents,
            $config->get('params', null),
            $config->get('mime', 'application/octet-stream'),
            $config->get('checkCrc', false)
        );

        if($error !== null)
            throw new QiniuException($error);

        return compact('path');
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     * @return array|false false on failure file meta data on success
     * @throws
     */
    public function writeStream($path, $resource, Config $config)
    {
        $key = FlysystemUtil::normalizePath($path);

        if (is_resource($resource) && !stream_is_local($resource))
            return $this->write($key, stream_get_contents($resource), $config);

        $file = $resource;
        $params = UploadManager::trimParams($config->get('params'));
        $stat = fstat($file);
        $size = $stat['size'];
        $mime = $config->get('mime', 'application/octet-stream');
        $upToken = $this->getAuth()->uploadToken($this->bucket, $key);

        if ($size <= QiniuConfig::BLOCK_SIZE) {
            $data = fread($file, $size);
            if ($data === false) {
                throw new \Exception("file can not read", 1);
            }
            list($result, $error) = FormUploader::put(
                $upToken,
                $key,
                $data,
                $this->qiniuConfig,
                $params,
                $mime,
                $config->get('checkCrc')
            );
        }else{
            $up = new ResumeUploader(
                $upToken,
                $key,
                $file,
                $size,
                $params,
                $mime,
                $this->qiniuConfig
            );
            list($result, $error) = $up->upload();
        }

        if($error !== null)
            throw new QiniuException($error);

        return compact('path');
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     * @return array|false false on failure file meta data on success
     * @throws
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $old
     * @param string $new
     * @return bool
     */
    public function rename($old, $new)
    {
        $old = FlysystemUtil::normalizePath($old);
        $new = FlysystemUtil::normalizePath($new);

        $error = $this->getBucketManager()->move($this->bucket, $old, $this->bucket, $new, false);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * Copy a file.
     *
     * @param string $old
     * @param string $new
     * @return bool
     */
    public function copy($old, $new)
    {
        $old = FlysystemUtil::normalizePath($old);
        $new = FlysystemUtil::normalizePath($new);

        $error = $this->getBucketManager()->copy($this->bucket, $old, $this->bucket, $new, false);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        $error = $this->getBucketManager()->delete($this->bucket, $path);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        $files = array_map(function ($array) {
            return $array['path'];
        }, $this->listContentsIncludeKeep($dirname, true));

        $ops = $this->getBucketManager()->buildBatchDelete($this->bucket, $files);
        list($result, $error) = $this->getBucketManager()->batch($ops);

        if ($error !== null)
            throw new QiniuException($error);

        return true;
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        return $this->createDirKeep($dirname, $config);
    }

    /**
     * Create a directory keep.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDirKeep($dirname, Config $config)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        $file = $this->getKeepPath($dirname);

        $this->write($file, '', $config);

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Delete a directory keep.
     *
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function deleteDirKeep($dirname, Config $config)
    {
        $file = $this->getKeepPath($dirname);

        if($this->has($file))
            return $this->delete($file);

        return true;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }

    /**
     * Read a file.
     *
     * @param string $path
     * @return array|false
     */
    public function read($path)
    {
        $location = $this->getPrivateDownloadUrl($path);
        // todo: if file not exists.
        $contents = file_get_contents($location);

        if ($contents === false) {
            return false;
        }

        return compact('contents', 'path');
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     * @return array|false
     */
    public function readStream($path)
    {
        $location = $this->getPrivateDownloadUrl($path);
        // todo: if file not exists.
        // todo: about http stream
        $context = stream_context_create([
            'http'=>[
                'method'=>"GET",
                /*'header'=>"Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n"*/
            ]
        ]);

        /* Sends an http request to www.example.com
            with additional headers shown above */
        $stream = fopen($location, 'r', false, $context);

        return compact('stream', 'path');
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $_data = $this->listContentsIncludeKeep($directory, $recursive);

        return array_filter($_data, [Util::class, 'isNotKeep']);
    }

    /**
     * @param $prefix
     * @param null $marker
     * @param int $limit
     * @param null $delimiter
     * @return bool|array
     */
    protected function getContentsFromBucket($prefix, $marker = null, $limit = 1000, $delimiter = null)
    {
        list($result, $error) = $this->getBucketManager()->listFiles($this->bucket, $prefix, $marker, $limit, $delimiter);
        if ($error !== null) throw new QiniuException($error);

        return $result;
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContentsIncludeKeep($directory = '', $recursive = false)
    {
        $directory = FlysystemUtil::normalizePath($directory);

        $prefix = $directory;
        $marker = null;
        $limit = 1000;
        $delimiter = null; // todo: delimiter able
        $_data = [];
        do {
            $result = $this->getContentsFromBucket($prefix, $marker, $limit, $delimiter);
            if ($result === false) {
                return false; // todo: throw
            } else {
                $_data = array_merge($_data, $result['items']);
            }
        } while (array_key_exists('marker', $result) && $marker = $result['marker']);
        $_data = array_map([Util::class, 'mapFileInfo'], $_data);
        $_data = (new ContentListingFormatter($directory, true))->formatListing($_data);
        $_data = array_merge($_data, Util::extractDirsWithFilesPath($_data));

        return (new ContentListingFormatter($directory, $recursive))->formatListing($_data);
    }

    /**
     * @param string $directory
     * @param int $limit
     * @return bool|array
     */
    protected function listContentsPart($directory = '', $limit = 1)
    {
        $directory = FlysystemUtil::normalizePath($directory);

        $result = $this->getContentsFromBucket($directory, null, $limit);

        if ($result === false)
            return false;

        return $result['items'];
    }

    public function canThumbnail($mimeType)
    {
        return in_array(strtolower($mimeType), [
//            'psd',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/tiff',
            'image/bmp',
        ]);
    }

    /**
     * Get stat
     *
     * @param string $path
     * @return array|false
     */
    public function stat($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        list($result, $error) = $this->getBucketManager()->stat($this->bucket, $path);

        if ($error !== null)
            throw new QiniuException($error);

        $result['key'] = $path;

        return Util::mapFileInfo($result);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     * @return array|bool|null
     */
    public function has($path)
    {
        if ($this->stat($path))
            return true;

        $result = $this->listContentsPart($path);

        return $result && ! empty($result);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     */
    public function getMetadata($path)
    {
        return $this->stat($path);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->stat($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->stat($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->stat($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getVisibility($path)
    {
        return ['visibility' => static::VISIBILITY_PUBLIC];
    }

    public function getDownloadUrl($path, $protocol = null)
    {
        $path = FlysystemUtil::normalizePath($path);

        $protocol = Util::normalizeScheme($protocol, $this->protocol);

        $baseUrl = $protocol.'://'.$this->getDomain().'/'.str_replace(" ","%20",$path);

        return $baseUrl;
    }

    public function getPrivateDownloadUrl($path, $protocol = null)
    {
        if (! $protocol) $protocol = $this->private_protocol;

        $url = $this->getDownloadUrl($path, $protocol);

        return $this->getAuth()->privateDownloadUrl($url);
    }

    public function getThumbnailUrl($path, array $config = [], $protocol = null)
    {
        if ($this->thumbnail)
            return $this->thumbnail->getUrl($this->getDownloadUrl($path, $protocol), $config);

        return null;
    }

    public function getPrivateThumbnailUrl($path, array $config = [], $protocol = null)
    {
        if (! $protocol) $protocol = $this->private_protocol;

        $url = $this->getThumbnailUrl($path, $config, $protocol);

        if (! $url) return $url;

        return $this->getAuth()->privateDownloadUrl($url);
    }

    public function thumbnailUrl($path, array $config = [], $protocol = null)
    {
        return $this->getThumbnailUrl($path, $config, $protocol);
    }

    /**
     * For Illuminate\filesystem\FilesystemAdapter.
     *
     * @param string $path
     * @param string|null $protocol
     * @return string
     */
    public function getUrl($path, $protocol = null)
    {
        return $this->getDownloadUrl($path, $protocol);
    }

    public function url($path, $protocol = null)
    {
        return $this->getUrl($path, $protocol);
    }

    /**
     * Run Operation.
     *
     * @param $path
     * @param $fops
     * @return bool
     */
    public function operationExecute($path, $fops)
    {
        list($result, $error) = $this->getOperation()->execute($path, $fops);

        if ($error !== null)
            throw new QiniuException($error);

        return $result;
    }

    /**
     * Execute PersistentFop.
     *
     * @param string $path
     * @param string|array $fops
     * @param string|null $pipeline
     * @param string|null $notify_url
     * @param bool $force
     * @return integer|bool
     */
    public function persistentFopExecute($path, $fops, $pipeline = null, $notify_url = null, $force = false)
    {
        if (!$notify_url) $notify_url = $this->notify_url;

        list($id, $error) = $this->getPersistentFop()->execute($this->bucket, $path, $fops, $pipeline, $notify_url, $force);

        if ($error !== null)
            throw new QiniuException($error);

        return $id;
    }

    /**
     * Get status.
     *
     * @param $id
     * @return bool|string json
     */
    public function persistentFopStatus($id)
    {
        list($json, $error) = $this->getPersistentFop()->status($id);

        if ($error !== null)
            throw new QiniuException($error);

        return $json;
    }

    /**
     * Set Qiniu Access key.
     *
     * @param string $accessKey
     * @return $this
     */
    public function setAccessKey($accessKey)
    {
        $this->accessKey = strval($accessKey);

        return $this;
    }

    /**
     * Set Qiniu Secret key.
     *
     * @param string $secretKey
     * @return $this
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = strval($secretKey);

        return $this;
    }

    /**
     * Set your Bucket.
     *
     * @param string $bucket
     * @return $this
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;

        return $this;
    }

    /**
     * Set your Domain.
     * Example 'domain.xxx', 'https://domain.xxx'. Default Protocol is 'http'.
     *
     * @param string $domain
     * @return $this
     */
    public function setDomain($domain)
    {
        list($domain, $protocol) = Util::normalizeDomain($domain);

        $this->domain = $domain;

        $this->protocol = $protocol;

        return $this;
    }

    /**
     * Get Domain.
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set default Protocol.
     *
     * @param string $protocol
     * @return $this
     * @throws
     */
    public function setProtocol($protocol)
    {
        $this->protocol = Util::normalizeScheme($protocol);

        return $this;
    }

    /**
     * Get default Protocol.
     *
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * Set private Protocol.
     *
     * @param string $protocol
     * @return $this
     * @throws
     */
    public function setPrivateProtocol($protocol)
    {
        $this->private_protocol = Util::normalizeScheme($protocol);

        return $this;
    }

    /**
     * Get private Protocol.
     *
     * @return string
     */
    public function getPrivateProtocol()
    {
        return $this->private_protocol ?: $this->protocol;
    }

    /**
     * Get upload token.
     *
     * @return string
     */
    public function getUploadToken()
    {
        return $token = $this->getAuth()->uploadToken($this->bucket);
    }

    /**
     * Get UploadManager.
     *
     * @return UploadManager
     */
    protected function getUploadManager()
    {
        if ($this->uploadManager instanceof UploadManager) {
            return $this->uploadManager;
        }

        return $this->uploadManager = new UploadManager($this->getQiniuConfig());
    }

    /**
     * Get BucketManager.
     *
     * @return BucketManager
     */
    protected function getBucketManager()
    {
        if ($this->bucketManager instanceof BucketManager) {
            return $this->bucketManager;
        }

        return $this->bucketManager = new BucketManager($this->getAuth(), $this->getQiniuConfig());
    }

    /**
     * Get Operation.
     *
     * @return Operation
     */
    protected function getOperation()
    {
        if ($this->operation instanceof Operation) {
            return $this->operation;
        }

        return $this->operation = new Operation($this->getDomain(), $this->getAuth());
    }

    /**
     * Get PersistentFop.
     *
     * @return PersistentFop
     */
    protected function getPersistentFop()
    {
        if ($this->persistentFop instanceof PersistentFop) {
            return $this->persistentFop;
        }

        return $this->persistentFop = new PersistentFop($this->getAuth(), $this->getQiniuConfig());
    }

    /**
     * Get Config Qiniu.
     *
     * @return QiniuConfig
     */
    protected function getQiniuConfig()
    {
        if ($this->qiniuConfig instanceof QiniuConfig) {
            return $this->qiniuConfig;
        }

        return $this->qiniuConfig = new QiniuConfig();
    }

    /**
     * Get Auth.
     *
     * @return Auth
     */
    protected function getAuth()
    {
        if ($this->auth instanceof Auth) {
            return $this->auth;
        }

        return $this->auth = new Auth($this->accessKey, $this->secretKey);
    }

    public function getKeepPath($dirname, $filename = null)
    {
        return FlysystemUtil::normalizePath($dirname . '/' .
            ($filename ? FlysystemUtil::normalizePath($filename) : '.keep'));
    }
}
