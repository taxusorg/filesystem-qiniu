<?php
namespace Taxusorg\FilesystemQiniu\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;

class ListByExtension extends AbstractPlugin
{
    public function getMethod()
    {
        return 'listByExtension';
    }

    /**
     * @param string $directory
     * @param string $extension
     * @param bool $recursive
     * @return array|false
     */
    public function handle($directory = '', $extension, $recursive = false)
    {
        $contents = $this->filesystem->listContents($directory, $recursive);

        $filter = function ($object) use ($extension) {
            return $object['type'] === 'file' && $object['extension'] === $extension;
        };

        return array_values(array_filter($contents, $filter));
    }
}