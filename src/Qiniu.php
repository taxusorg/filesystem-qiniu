<?php
namespace Taxusorg\FilesystemQiniu;

use League\Flysystem\Config;
use League\Flysystem\Util as FlysystemUtil;
use League\Flysystem\Util\ContentListingFormatter;
use Qiniu\Auth;
use Qiniu\Config as QiniuConfig;
use Qiniu\Processing\Operation;
use Qiniu\Processing\PersistentFop;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

class Qiniu implements QiniuInterface
{
    private $access_key = '';
    private $secret_key = '';

    private $keep_name = '.keep';

    private $auth;
    private $uploadManager;
    private $bucketManager;
    private $persistentFop;
    private $qiniuConfig;

    public function __construct($access_key, $secret_key, $keep_name = null)
    {
        $this->access_key = (string) $access_key;
        $this->secret_key = (string) $secret_key;

        $this->keep_name = $keep_name == null ? $this->keep_name : FlysystemUtil::normalizePath($keep_name);

        $this->qiniuConfig = new QiniuConfig();
    }

    /**
     * Write a new file.
     *
     * @param string $bucket
     * @param string $path
     * @param string|resource $contents
     * @param Config $config   Config object
     * @return array|false false on failure file meta data on success
     */
    public function write($bucket, $path, $contents, Config $config)
    {
        list($result, $error) = $this->getUploadManager()->put(
            $this->getAuth()->uploadToken($bucket, $path),
            $path,
            $contents,
            $config->get('params', null),
            $config->get('mime', 'application/octet-stream'),
            $config->get('checkCrc', false)
        );

        if($error !== null)
            return false;
        // todo: throw
        return compact('path');
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $bucket
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     * @return array|false false on failure file meta data on success
     * @throws
     */
    public function writeStream($bucket, $path, $resource, Config $config)
    {
        if (is_resource($resource) && !stream_is_local($resource))
            return $this->write($bucket, $path, stream_get_contents($resource), $config); // todo: when ! stream_is_local ?

        $file = $resource;
        $stat = fstat($file);
        $size = $stat['size'];

        if ($size <= QiniuConfig::BLOCK_SIZE) {
            return $this->write($bucket, $path, stream_get_contents($resource), $config);
        }

        $params = UploadManager::trimParams($config->get('params'));
        $mime = $config->get('mime', 'application/octet-stream');
        $upToken = $this->getAuth()->uploadToken($bucket, $path);

        $up = new ResumeUploader(
            $upToken,
            $path,
            $file,
            $size,
            $params,
            $mime,
            $this->qiniuConfig
        );
        list($result, $error) = $up->upload(basename($path));

        if($error !== null)
            return false;
        // todo: throw
        return compact('path');
    }

    /**
     * Update a file.
     *
     * @param string $bucket
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     * @return array|false false on failure file meta data on success
     */
    public function update($bucket, $path, $contents, Config $config)
    {
        return $this->write($bucket, $path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $bucket
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     * @return array|false false on failure file meta data on success
     * @throws
     */
    public function updateStream($bucket, $path, $resource, Config $config)
    {
        return $this->writeStream($bucket, $path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $old_bucket
     * @param string $old
     * @param string $new_bucket
     * @param string $new
     * @return bool
     */
    public function rename($old_bucket, $old, $new_bucket, $new)
    {
        $error = $this->getBucketManager()->move($old_bucket, $old, $new_bucket, $new, false);

        if ($error !== null)
            return false;
        // todo: throw
        return true;
    }

    /**
     * Copy a file.
     *
     * @param string $old_bucket
     * @param string $old
     * @param string $new_bucket
     * @param string $new
     * @return bool
     */
    public function copy($old_bucket, $old, $new_bucket, $new)
    {
        $error = $this->getBucketManager()->copy($old_bucket, $old, $new_bucket, $new, false);

        if ($error !== null)
            return false;
        // todo: throw
        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $bucket
     * @param string $path
     * @return bool
     */
    public function delete($bucket, $path)
    {
        $error = $this->getBucketManager()->delete($bucket, $path);

        if ($error !== null)
            return false;
        // todo: throw
        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $bucket
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($bucket, $dirname)
    {
        $files = array_map(function ($array) {
            return $array['path'];
        }, $this->listContentsIncludeKeep($dirname, true));

        $ops = $this->getBucketManager()->buildBatchDelete($bucket, $files);
        list($result, $error) = $this->getBucketManager()->batch($ops);

        if ($error !== null)
            return false;
        // todo: throw
        return true;
    }

    /**
     * Create a directory.
     *
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
     * Create a directory keep.
     *
     * @param string $bucket
     * @param string $dirname directory name
     * @param Config $config
     * @return array|false
     */
    public function createDirKeep($bucket, $dirname, Config $config)
    {
        $file = $this->getKeepPath($dirname);

        $this->write($bucket, $file, '', $config);

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * Delete a directory keep.
     *
     * @param string $bucket
     * @param string $dirname directory name
     * @return array|false
     */
    public function deleteDirKeep($bucket, $dirname)
    {
        $file = $this->getKeepPath($dirname);

        if($this->has($bucket, $file))
            return $this->delete($bucket, $file);

        return true;
    }

    /**
     * Read a file.
     *
     * @param string $url
     * @return array|false
     */
    public function read($url)
    {
        $location = $this->getPrivateDownloadUrl($url);
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
     * @param string $url
     * @return array|false
     */
    public function readStream($url)
    {
        $location = $this->getPrivateDownloadUrl($url);
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
     * @param string $bucket
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContents($bucket, $directory = '', $recursive = false)
    {
        $_data = $this->listContentsIncludeKeep($bucket, $directory, $recursive);

        return array_filter($_data, [$this, 'isNotKeep']);
    }

    /**
     * List contents of a directory.
     *
     * @param string $bucket
     * @param string $directory
     * @param bool   $recursive
     * @return array
     */
    public function listContentsIncludeKeep($bucket, $directory = '', $recursive = false)
    {
        $prefix = $directory;
        $marker = null;
        $limit = 1000;
        $delimiter = null; // todo: delimiter able
        $_data = [];
        do {
            list($result, $error) = $this->getBucketManager()->listFiles($bucket, $prefix, $marker, $limit, $delimiter);
            if ($error !== null) return false; // todo: throw
            $_data = array_merge($_data, $result['items']);
        } while (array_key_exists('marker', $result) && $marker = $result['marker']);
        $_data = array_map([static::class, 'mapFileInfo'], $_data);
        $_data = (new ContentListingFormatter($directory, true))->formatListing($_data);
        $_data = array_merge($_data, static::extractDirsWithFilesPath($_data));

        return (new ContentListingFormatter($directory, $recursive))->formatListing($_data);
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
        list($result, $error) = $this->getBucketManager()->stat($bucket, $path);

        if ($error !== null)
            return false; // todo: false or throw

        $result['key'] = $path;

        return static::mapFileInfo($result);
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
        return $this->stat($bucket, $path) ? true : false;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $bucket
     * @param string $path
     * @return array|false
     */
    public function getMetadata($bucket, $path)
    {
        return $this->stat($bucket, $path);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $bucket
     * @param string $path
     * @return array|false
     */
    public function getSize($bucket, $path)
    {
        return $this->stat($bucket, $path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $bucket
     * @param string $path
     * @return array|false
     */
    public function getMimetype($bucket, $path)
    {
        return $this->stat($bucket, $path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $bucket
     * @param string $path
     * @return array|false
     */
    public function getTimestamp($bucket, $path)
    {
        return $this->stat($bucket, $path);
    }

    /**
     * Get url for Read.
     *
     * @param string $url
     * @return string
     * @throws
     */
    public function getPrivateDownloadUrl($url)
    {
        $location = $this->getAuth()->privateDownloadUrl($url);
        // todo: if file not exists.
        return $location;
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
//        list($result, $error) = $this->getOperation()->execute($path, $fops);
//
//        if ($error !== null)
//            return false; // todo: throw
//
//        return $result;
    }

    /**
     * Execute PersistentFop.
     *
     * @param string $bucket
     * @param string $path
     * @param string|array $fops
     * @param string|null $pipeline
     * @param string|null $notify_url
     * @param bool $force
     * @return integer|bool
     */
    public function persistentFopExecute($bucket, $path, $fops, $pipeline = null, $notify_url = null, $force = false)
    {
        list($id, $error) = $this->getPersistentFop()->execute($bucket, $path, $fops, $pipeline, $notify_url, $force);

        if ($error !== null)
            return false; // todo: throw

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
            return false; // todo: throw

        return $json;
    }

    /**
     * Get upload token.
     *
     * @param string $bucket
     * @return string
     */
    public function getUploadToken($bucket)
    {
        return $token = $this->getAuth()->uploadToken($bucket);
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

//    /**
//     * Get Operation.
//     *
//     * @return Operation
//     */
//    protected function getOperation()
//    {
//        if ($this->operation instanceof Operation) {
//            return $this->operation;
//        }
//
//        return $this->operation = new Operation($this->getDomain(), $this->getAuth());
//    }

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

        return $this->auth = new Auth($this->access_key, $this->secret_key);
    }

    public function getKeepPath($dirname, $filename = null)
    {
        return FlysystemUtil::normalizePath($dirname . '/' .
            ($filename ? FlysystemUtil::normalizePath($filename) : $this->keep_name));
    }

    public function isNotKeep($file)
    {
        return $file['type'] == 'dir' || $file['basename'] != $this->keep_name;
    }

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
}
