<?php
namespace Tests;

use Dotenv\Dotenv;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;
use League\Flysystem\Util;
use PHPUnit\Framework\TestCase;
use Taxusorg\FilesystemQiniu\Adapter\QiniuAdapter;
use Taxusorg\FilesystemQiniu\Thumbnail;

class NotifyTest extends TestCase
{
    protected $adapter;

    protected $config;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        date_default_timezone_set('PRC');

        $dotenv = new Dotenv('../');
        $dotenv->load();

        $this->config = new Config();

        $this->adapter = new QiniuAdapter([], new Thumbnail(['mode' => 2, 'width' => 144]));
        $this->adapter->setAccessKey($_ENV['KEY']);
        $this->adapter->setSecretKey($_ENV['SECRET']);
        $this->adapter->setBucket($_ENV['BUCKET']);
        $this->adapter->setDomain($_ENV['DOMAIN']);

    }

    public function testThumbnailQueries()
    {
        $thumbnail = new Thumbnail(['width' => 30, 'height' => 120]);

        $queries = $thumbnail->getUrl('testing?a', ['mode' => 2]);

        $this->assertEquals($queries, 'testing?a&imageView2/2/w/30/h/120');
    }

    public function testGetUploadToken()
    {
        $token = $this->adapter->getUploadToken();

        $this->assertStringStartsWith($_ENV['KEY'], $token);
    }

    public function testDomain()
    {
        print_r($this->adapter->getDomain('http') . "\n");
    }

    public function testGetDownloadUrl()
    {
        $path = 'dir/file name.ext';

        print_r($this->adapter->getDownloadUrl($path) . "\n");
    }

    public function testWrite()
    {
        $path = 'test/testWrite.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $result = $this->adapter->write($path, $content, $this->config);

        $this->assertEquals($result['path'], $path);
    }

    public function testUpdate()
    {
        $path = 'test/testUpdate.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $result = $this->adapter->update($path, $content, $this->config);

        $this->assertEquals($result['path'], $path);
    }

    public function testWriteStream()
    {
        $stream = fopen('./tests/test.png', 'rb');
        $path = 'test/testWriteStream.png';

        $result = $this->adapter->writeStream($path, $stream, $this->config);

    }

/*    public function testWriteStream2()
    {
        $stream = fopen('./tests/bigTest.zip', 'rb');
        $path = 'test/testWriteStreamBig.zip';

        $result = $this->adapter->writeStream($path, $stream, $this->config);

    }*/

    public function testRename()
    {
        $path = 'test/testRename.' . date("Y-m-d-H-i-s") . '.txt';
        $path2 = 'test/testRenameNew.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);
        $result = $this->adapter->rename($path, $path2);

        $this->assertTrue($result);
    }

    public function testCopy()
    {
        $path = 'test/testCopy.' . date("Y-m-d-H-i-s") . '.txt';
        $path2 = 'test/testCopyNew.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);
        $result = $this->adapter->copy($path, $path2);

        $this->assertTrue($result);
    }

    public function testNormalizePath()
    {
        $paths = [];
        $paths[] = '';
        $paths[] = '/';
        $paths[] = 'dir';
        $paths[] = '/dir';
        $paths[] = 'dir/';
        $paths[] = '/dir/';
        $paths[] = 'dir/dir/';
        $paths[] = 'dir/file.txt';
        $paths[] = '/dir/file.txt';

        foreach ($paths as $path) {
            print_r('path:'.Util::normalizePath($path) . "\n");
        }
    }

    public function testListContents()
    {
        $path = '/';

        $files = $this->adapter->listContents($path, false);

        $this->assertTrue(is_array($files));
    }

    public function testRead()
    {
        $path = 'test/testRead.' . date("Y-m-d-H-i-s") . '.txt';
        $contents = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $contents, $this->config);

        $result = $this->adapter->read($path);

        $this->assertEquals($contents, $result['contents']);
    }

    public function testReadStream()
    {
        $path = 'test/testRead.' . date("Y-m-d-H-i-s") . '.txt';
        $contents = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $contents, $this->config);

        $result = $this->adapter->readStream($path);

        $this->assertEquals($contents, stream_get_contents($result['stream']));
    }

    public function testDelete()
    {
        $path = 'test/testDelete.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);

        $result = $this->adapter->delete($path);

        $this->assertTrue($result);
    }

    public function testDeleteDir()
    {
        $path = 'test/testDeleteDir.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);

        $dir = 'test';

        $result = $this->adapter->deleteDir($dir);

        $this->assertTrue($result);
    }

}