<?php
namespace Tests;

use Dotenv\Dotenv;
use League\Flysystem\Config;
use PHPUnit\Framework\TestCase;
use Taxusorg\FilesystemQiniu\FilesystemAdapter;
use Taxusorg\FilesystemQiniu\Qiniu;

class filesystemTest extends TestCase
{
    private $access_key;
    private $secret_key;

    private $bucket;
    private $domain;

    protected $adapter;

    protected $config;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        date_default_timezone_set('PRC');

        $dotenv = new Dotenv('../');
        $dotenv->load();

        $this->access_key = $_ENV['QINIU_KEY'];
        $this->secret_key = $_ENV['QINIU_SECRET'];

        $this->bucket = $_ENV['QINIU_BUCKET'];
        $this->domain = $_ENV['QINIU_DOMAIN'];

        $this->config = new Config();

        $disk = new Qiniu($this->access_key, $this->secret_key);
        $adapter = new FilesystemAdapter('cloud', $disk, ['domain' => $this->domain]);

        $this->adapter = $adapter;
    }

    public function testGetUploadToken()
    {
        $token = $this->adapter->getUploadToken();

        $this->assertStringStartsWith($this->access_key, $token);
    }

    public function testGetDownloadUrl()
    {
        $path = 'dir/file name.ext';

        $this->adapter->setDomain('domain.ext');
        $this->assertEquals($this->adapter->getDownloadUrl($path), 'http://domain.ext/' . $path);

        $this->adapter->setDomain('https://domain.ext');
        $this->assertEquals($this->adapter->getDownloadUrl($path), 'https://domain.ext/' . $path);

        $this->adapter->setDomain('ftp://domain.ext');
        $this->assertEquals($this->adapter->getDownloadUrl($path), 'ftp://domain.ext/' . $path);
    }

    public function testWriteAndDelete()
    {
        $path = 'test/testWrite.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $result = $this->adapter->write($path, $content, $this->config);

        $this->assertEquals($result['path'], $path);
        $this->assertEquals($content, $this->adapter->read($path)['contents']);

        $result2 = $this->adapter->delete($path);

        $content2 = $this->adapter->read($path)['contents'];

        $this->assertTrue($result2);
    }

    public function testUpdate()
    {
        $path = 'test/testUpdate.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $result = $this->adapter->update($path, $content, $this->config);

        $this->assertEquals($result['path'], $path);

        $this->assertEquals($content, $this->adapter->read($path)['contents']);
    }

    public function testWriteStream()
    {
        $path = 'test/testWriteStream.txt';
        $path2 = 'test/testWriteStream2.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $result = $this->adapter->write($path, $content, $this->config);

        $stream = $this->adapter->readStream($path)['stream'];

        $this->assertTrue(is_resource($stream));

        $result2 = $this->adapter->writeStream($path2, $stream, $this->config);

        $this->assertEquals($result['path'], $path);
        $this->assertEquals($result2['path'], $path2);

        $this->assertEquals($this->adapter->read($path)['contents'], $this->adapter->read($path2)['contents']);
    }

    public function testRename()
    {
        $path = 'test/testRename.' . date("Y-m-d-H-i-s") . '.txt';
        $path2 = 'test/testRenameNew.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);
        $result = $this->adapter->rename($path, $path2);

        $this->assertTrue($result);

        $this->assertEquals($content, $this->adapter->read($path2)['contents']);
    }

    public function testCopy()
    {
        $path = 'test/testCopy.' . date("Y-m-d-H-i-s") . '.txt';
        $path2 = 'test/testCopyNew.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);
        $result = $this->adapter->copy($path, $path2);

        $this->assertTrue($result);

        $this->assertEquals($this->adapter->read($path)['contents'], $this->adapter->read($path2)['contents']);
    }

    public function testListContents()
    {
        $dir = 'test';
        $path = 'test/testWrite.' . date("Y-m-d-H-i-s") . '.txt';
        $content = 'file content. Date:' . date("Y-m-d H:i:s");

        $this->adapter->write($path, $content, $this->config);

        $files = $this->adapter->listContents($dir, false);
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file['path'];
        }

        $this->assertTrue(in_array($path, $paths));
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