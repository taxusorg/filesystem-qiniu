<?php
namespace Taxusorg\FilesystemQiniu;

use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Taxusorg\FilesystemQiniu\Adapter\QiniuAdapter;

class FilesystemServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }
    
    public function register()
    {
        $this->app->booting(function () {
            $this->app->extend('filesystem', function ($filesystem, $app) {
                return $filesystem->extend('qiniu', function ($app, $config) {
                    $this->app->singleton('filesystem.disk.qiniu.driver', function () use ($config) {
                        return new Filesystem(new QiniuAdapter($config));
                    });

                    return $this->app->make('filesystem.disk.qiniu.driver');
                });
            });
        });
    }
}