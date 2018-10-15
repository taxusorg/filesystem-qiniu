<?php
namespace Taxusorg\FilesystemQiniu\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;

class GetUrl extends AbstractPlugin
{
    public function getMethod()
    {
        return 'getUrl';
    }

    /**
     * Get url.
     * @param string $path
     * @return string
     */
    public function handle($path)
    {
        return $this->filesystem->getAdapter()->getDownloadUrl($path);
    }
}