<?php
namespace Taxusorg\FilesystemQiniu;

use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Taxusorg\FilesystemQiniu\Adapter\QiniuAdapter;

class QiniuServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }
    
    public function register()
    {
        $this->app->singleton(Manager::class, function () {
            return new Manager($this->getConfig());
        });

        $this->app->extend('filesystem', function ($filesystem, $app) {
            return $filesystem->extend('qiniu', function ($app, $config) {
                $disk = isset($config['disk']) ? $config['disk'] : null;
                $bucket = isset($config['bucket']) ? $config['bucket'] : null;

                return $this->app->make(Manager::class)->get($disk, $bucket);
            });
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/qiniu.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'qiniu.php',
            ]);
        }
    }

    protected function getConfig()
    {
        return $this->app['config']['qiniu'];
    }
}
