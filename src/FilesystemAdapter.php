<?php

namespace Taxusorg\FilesystemQiniu;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util as FlysystemUtil;

class FilesystemAdapter implements AdapterInterface
{
    protected $bucket;
    protected $domain = [];

    protected $filesystem;

    public function __construct($bucket, $filesystem, $config = [])
    {
        $this->bucket = $bucket;

        if (! $filesystem instanceof QiniuInterface) {
            if (! (isset($filesystem['access_key']) && isset($filesystem['secret_key'])))
                throw new \InvalidArgumentException('Wring filesystem'); // todo: throw

            $filesystem = new Qiniu($filesystem['access_key'], $filesystem['secret_key']);
        }

        $this->filesystem = $filesystem;

        if (isset($config['domain']))
            $this->setDomain($config['domain']);
    }
    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->write($this->bucket, $path, $contents, $config);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     * @throws
     */
    public function writeStream($path, $resource, Config $config)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->writeStream($this->bucket, $path, $resource, $config);
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->update($this->bucket, $path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->updateStream($this->bucket, $path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $path = FlysystemUtil::normalizePath($path);
        $newpath = FlysystemUtil::normalizePath($newpath);

        return $this->filesystem->rename($this->bucket, $path, $this->bucket, $newpath); // todo: same bucket
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $path = FlysystemUtil::normalizePath($path);
        $newpath = FlysystemUtil::normalizePath($newpath);

        return $this->filesystem->copy($this->bucket, $path, $this->bucket, $newpath); // todo: same bucket
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->delete($this->bucket, $path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        return $this->filesystem->deleteDir($this->bucket, $dirname);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        $dirname = FlysystemUtil::normalizePath($dirname);

        return $this->filesystem->createDir($this->bucket, $dirname, $config);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        $path = FlysystemUtil::normalizePath($path);

        return []; // todo: visibility
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     */
    public function has($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->has($this->bucket, $path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->read($this->getDownloadUrl($path));
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        return $this->filesystem->readStream($this->getDownloadUrl($path));
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = FlysystemUtil::normalizePath($directory);

        return $this->filesystem->listContents($this->bucket, $directory, $recursive);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->getMetadata($this->bucket, $path);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->getSize($this->bucket, $path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->getMimetype($this->bucket, $path);
    }

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->filesystem->getTimestamp($this->bucket, $path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return ['visibility' => static::VISIBILITY_PUBLIC]; // todo: visibility
    }

    /**
     * @param $path
     * @return string
     */
    public function getDownloadUrl($path)
    {
        $path = FlysystemUtil::normalizePath($path);

        return $this->getDomain() . '/' . $path;
    }

    /**
     * @param string $path
     * @return string
     */
    public function getUrl($path)
    {
        return $this->getDownloadUrl($path);
    }

    /**
     * @param string $path
     * @return string
     */
    public function getPrivateDownloadUrl($path)
    {
        return $this->filesystem->getPrivateDownloadUrl($this->getDownloadUrl($path));
    }

    /**
     * @param string $url
     */
    public function setDomain($url)
    {
        $domain = parse_url($url);

        if ($domain == false) {
            throw new \InvalidArgumentException('Wrong Url.');
        }

        if (! isset($domain['host'])) {
            $domain = parse_url('//' . ltrim($url, '/'));
        }

        if ($domain == false) {
            throw new \InvalidArgumentException('Wrong Url.');
        }

        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        if (! isset($this->domain['host'])) {
            throw new \InvalidArgumentException('No host. '); // todo: exception
        }

        $url = isset($this->domain['scheme']) ? $this->domain['scheme'] : 'http';
        $url .= '://';
        $url .= isset($this->domain['user']) ? $this->domain['user'] : null;
        $url .= isset($this->domain['pass']) ? ':' . $this->domain['pass'] : null;
        $url .= (isset($this->domain['user']) || isset($this->domain['pass'])) ? '@' : null;
        $url .= $this->domain['host'];
        $url .= isset($this->domain['port']) ? ':' . $this->domain['port'] : null;

        return $url;
    }
}
