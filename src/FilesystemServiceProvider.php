<?php
namespace Taxusorg\FilesystemQiniu;

use Illuminate\Filesystem\Cache;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory as MemoryStore;
use League\Flysystem\Filesystem;
use Taxusorg\FilesystemQiniu\Adapter\QiniuAdapter;
use Taxusorg\FilesystemQiniu\Plugin\GetUrl;
use Taxusorg\FilesystemQiniu\Plugin\GetThumbnailUrl;

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
                        $use_cache = isset($config['cache']) ? (bool) $config['cache'] : false;

                        $adapter = new QiniuAdapter($config);
                        if ($use_cache) {
                            if ($use_cache === true) {
                                $cache = new MemoryStore;
                            } else {
                                $cache = new Cache(
                                    $this->app['cache']->store($config['cache']['store']),
                                    isset($config['cache']['prefix']) ? $config['cache']['prefix'] : 'filesystem_qiniu',
                                    isset($config['cache']['expire']) ? $config['cache']['expire'] : null
                                );
                            }

                            $adapter = new CachedAdapter($adapter, $cache);
                        }

                        $filesystem = new Filesystem($adapter);

                        $filesystem->addPlugin(new GetUrl());
                        $filesystem->addPlugin(new GetThumbnailUrl());

                        return $filesystem;
                    });

                    return $this->app->make('filesystem.disk.qiniu.driver');
                });
            });
        });
    }
}
