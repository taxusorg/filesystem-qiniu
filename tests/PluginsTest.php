<?php
namespace Tests;

use Dotenv\Dotenv;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Taxusorg\FilesystemQiniu\Adapter\QiniuAdapter;
use Taxusorg\FilesystemQiniu\Plugin\GetUrl;
use Taxusorg\FilesystemQiniu\Plugin\ListByExtension;
use Taxusorg\FilesystemQiniu\Plugin\ListImages;
use Taxusorg\FilesystemQiniu\Plugin\ThumbnailUrl;

class PluginsTest extends TestCase
{
    protected $adapter;
    protected $filesystem;

    protected $config;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        date_default_timezone_set('PRC');

        $dotenv = new Dotenv('./');
        $dotenv->load();

        $this->config = new Config();

        $this->adapter = new QiniuAdapter();
        $this->adapter->setAccessKey($_ENV['KEY']);
        $this->adapter->setSecretKey($_ENV['SECRET']);
        $this->adapter->setBucket($_ENV['BUCKET']);
        $this->adapter->setDomain($_ENV['DOMAIN']);

        $this->filesystem = new Filesystem($this->adapter);
        $this->filesystem->addPlugin(new GetUrl());
        $this->filesystem->addPlugin(new ListImages());
        $this->filesystem->addPlugin(new ListByExtension());
        $this->filesystem->addPlugin(new ThumbnailUrl());
    }

    function testGetUrl()
    {
        $url = $this->filesystem->getUrl('dir/file test.ext');
    }

    function testListImages()
    {
        $list = $this->filesystem->listImages('');
    }

    function testListByExtension()
    {
        $list = $this->filesystem->listByExtension('', 'txt');
    }

    function testThumbnailUrl()
    {
        $url = $this->filesystem->thumbnailUrl('file.png');
    }
}