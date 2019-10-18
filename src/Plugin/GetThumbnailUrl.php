<?php

namespace Taxusorg\FilesystemQiniu\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;

class GetThumbnailUrl extends AbstractPlugin
{
    public function getMethod()
    {
        return 'getThumbnailUrl';
    }

    /**
     * Get url.
     * @param string $path
     * @param array $config
     * @return string|false
     */
    public function handle($path, array $config = [])
    {
        return $this->filesystem->getAdapter()->getThumbnailUrl($path, $config);
    }
}
