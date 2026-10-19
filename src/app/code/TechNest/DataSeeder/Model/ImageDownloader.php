<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Downloads product images into pub/media/import/technest/ (kept as a local cache, never hotlinked).
 * WebP is converted to PNG because the Magento media gallery only accepts jpg/jpeg/gif/png.
 */
class ImageDownloader
{
    private const IMPORT_DIR = 'import/technest/';
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const TIMEOUT = 30;
    private const ATTEMPTS = 2;

    /**
     * @param CurlFactory $curlFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Download (or reuse) every image; files are named <url_key>-<n>.<ext>.
     *
     * @param string $urlKey
     * @param string[] $urls
     * @return string[] Absolute file paths, in the same order as $urls
     * @throws LocalizedException
     */
    public function download(string $urlKey, array $urls): array
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $media->create(self::IMPORT_DIR);
        $files = [];
        foreach (array_values($urls) as $i => $url) {
            $ext = preg_match('/\.([A-Za-z0-9]+)(?:[?#].*)?$/', $url, $match) ? strtolower($match[1]) : '';
            if (!in_array($ext, self::ALLOWED, true)) {
                continue;
            }
            $target = self::IMPORT_DIR . $urlKey . '-' . ($i + 1) . '.' . ($ext === 'webp' ? 'png' : $ext);
            if (!$media->isFile($target)) {
                $body = $this->fetch($url);
                if ($ext === 'webp') {
                    $this->saveWebpAsPng($body, $media->getAbsolutePath($target));
                } else {
                    $media->writeFile($target, $body);
                }
            }
            $files[] = $media->getAbsolutePath($target);
        }
        return $files;
    }

    /**
     * HTTP GET returning the body; retried once on network errors.
     *
     * @param string $url
     * @return string
     * @throws LocalizedException
     */
    private function fetch(string $url): string
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        for ($attempt = 1;; $attempt++) {
            try {
                $curl->get($url);
                break;
            } catch (\Exception $e) {
                if ($attempt >= self::ATTEMPTS) {
                    throw new LocalizedException(__('Image %1: %2', $url, $e->getMessage()), $e);
                }
            }
        }
        if ($curl->getStatus() !== 200 || $curl->getBody() === '') {
            throw new LocalizedException(__('Image %1: HTTP %2', $url, $curl->getStatus()));
        }
        return $curl->getBody();
    }

    /**
     * Decode WebP with GD and write a PNG that keeps transparency.
     *
     * @param string $webp
     * @param string $absolutePath
     * @return void
     * @throws LocalizedException
     */
    private function saveWebpAsPng(string $webp, string $absolutePath): void
    {
        // GD is used directly: Magento's image adapters cannot read WebP.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $image = imagecreatefromstring($webp);
        if ($image === false) {
            throw new LocalizedException(__('Could not decode WebP image for %1', $absolutePath));
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        imagesavealpha($image, true);
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        imagepng($image, $absolutePath);
    }
}
