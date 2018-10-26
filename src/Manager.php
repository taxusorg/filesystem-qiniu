<?php

namespace Taxusorg\FilesystemQiniu;

class Manager
{
    private $config = [];

    private $disks = [];

    private $buckets = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function get($disk = null, $bucket = null)
    {
        if (null == $disk)
            $disk = $this->getDefaultDisk();

        if (null == $bucket)
            $bucket = $this->getDefaultBucket($disk);

        if (! isset($this->buckets[$disk][$bucket]))
            $this->buckets[$disk][$bucket] = $this->resolveBucket($disk, $bucket);

        return $this->buckets[$disk][$bucket];
    }

    protected function resolveBucket($disk_name, $name)
    {
        $this->checkDiskBucket($disk_name, $name);

        $disk = $this->getDisk($disk_name);

        $config = $this->getBucketConfig($disk_name, $name);

        $domain = isset($config['domain']) ? $config['domain'] : null;

        $bucket = new FilesystemAdapter($name, $disk, $domain);

        return $bucket;
    }

    public function getDisk($name)
    {
        if (! isset($this->disks[$name]))
            $this->disks[$name] = $this->resolveDisk($name);

        return $this->disks[$name];
    }

    protected function resolveDisk($name)
    {
        $config = $this->getConfig($name);

        $disk = new Qiniu($config['access_key'], $config['secret_key']);

        return $disk;
    }

    protected function getConfig($name)
    {
        return $this->config['disks'][$name];
    }

    protected function getBucketConfig($name, $bucket)
    {
        return $this->config['disks'][$name]['buckets'][$bucket];
    }

    protected function checkDiskBucket($name, $bucket)
    {
        $config = $this->getConfig($name);

        if (isset($config['buckets']) && is_array($config['buckets'])) {
            return in_array($bucket, array_keys($config['buckets']));
        }

        throw new \InvalidArgumentException("Bucket [{$bucket}] in Driver [{$name}] is not supported.");
    }

    protected function getDefaultDisk()
    {
        return $this->config['default'];
    }

    protected function getDefaultBucket($name)
    {
        $config = $this->getConfig($name);

        if (isset($config['default']))
            return $config['default'];

        throw new \InvalidArgumentException("Default Bucket not found in Driver [{$name}].");
    }
}
