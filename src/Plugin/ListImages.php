<?php
namespace Taxusorg\FilesystemQiniu\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;

class ListImages extends AbstractPlugin
{
    public function getMethod()
    {
        return 'listImages';
    }

    public function handle($directory = '', $recursive = false)
    {
        $contents = $this->filesystem->listContents($directory, $recursive);

        $filter = function ($object) {
            return $object['type'] === 'file' && substr($object['mimeType'], 0, 5) === 'image';
        };

        return array_values(array_filter($contents, $filter));
    }
}