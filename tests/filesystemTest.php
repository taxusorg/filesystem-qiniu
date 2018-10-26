<?php
namespace Tests;

use Dotenv\Dotenv;
use League\Flysystem\Config;
use League\Flysystem\Util;
use PHPUnit\Framework\TestCase;
use Taxusorg\FilesystemQiniu\FilesystemAdapter;
use Taxusorg\FilesystemQiniu\Qiniu;

class NotifyTest extends TestCase
{
    private $access_key;
    private $secret_key;

    private $bucket = 'cloud';
    private $domain = 'domain.ext';

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

        $this->config = new Config();

        $disk = new Qiniu($this->access_key, $this->secret_key);
        $adapter = new FilesystemAdapter('cloud', $disk);

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
        $path = 'test';

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