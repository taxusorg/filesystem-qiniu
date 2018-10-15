<?php

namespace Taxusorg\FilesystemQiniu\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;
use InvalidArgumentException;

class ThumbnailUrl extends AbstractPlugin
{
    public function getMethod()
    {
        return 'thumbnailUrl';
    }

    /**
     * Get url.
     * @param string $path
     * @param int $mode
     * @param int $width
     * @param int $height
     * @return string
     * @throws InvalidArgumentException
     */
    public function handle($path, $mode = 2, $width = 144, $height = null)
    {
        $check = strpos($path, '?') ? '&' : '?';

        $mode = (int) $mode;

        if (0 > $mode && $mode > 5 || $width == null && $height == null)
            throw new InvalidArgumentException('Wrong mode, width & height.');

        $mode = "imageView2/$mode";

        if ($width !== null)
            $mode .= '/w/' . (int) $width;

        if ($height !== null)
            $mode .= '/h/' . (int) $height;

        return $this->filesystem->getAdapter()->getDownloadUrl($path) . $check . $mode;
    }
}
