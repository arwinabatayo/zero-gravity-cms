<?php

namespace ZeroGravity\Cms\Path\Resolver;

use Cocur\Slugify\SlugifyInterface;
use Psr\SimpleCache\CacheInterface;
use ZeroGravity\Cms\Content\File;
use ZeroGravity\Cms\Path\Path;

class CachingResolver extends AbstractResolver implements MultiPathResolver
{
    use MultiPathFindOneTrait;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var SinglePathResolver
     */
    protected $wrappedResolver;

    /**
     * @var SlugifyInterface
     */
    protected $slugify;

    /**
     * @param CacheInterface     $cache
     * @param SinglePathResolver $wrappedResolver
     * @param SlugifyInterface   $slugify
     */
    public function __construct(CacheInterface $cache, SinglePathResolver $wrappedResolver, SlugifyInterface $slugify)
    {
        $this->cache = $cache;
        $this->wrappedResolver = $wrappedResolver;
        $this->slugify = $slugify;
    }

    /**
     * Resolve the given path or glob pattern and find the matching files.
     *
     * @param Path      $path
     * @param Path|null $parentPath
     *
     * @return File[]
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function find(Path $path, Path $parentPath = null): array
    {
        if (!$this->wrappedResolver instanceof MultiPathResolver) {
            return [];
        }
        $key = $this->generateCacheKey('find', $path, $parentPath);
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $result = $this->wrappedResolver->find($path, $parentPath);
        $this->cache->set($key, $result);

        return $result;
    }

    /**
     * Resolve the given file name and path.
     *
     * @param Path      $path
     * @param Path|null $parentPath
     *
     * @return null|File
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(Path $path, Path $parentPath = null): ? File
    {
        $key = $this->generateCacheKey('get', $path, $parentPath);
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $result = $this->wrappedResolver->get($path, $parentPath);
        $this->cache->set($key, $result);

        return $result;
    }

    /**
     * @param string $method
     * @param Path   $path
     * @param Path   $parentPath
     *
     * @return string
     */
    protected function generateCacheKey(string $method, Path $path, Path $parentPath = null)
    {
        if (null === $parentPath) {
            $parentPath = '';
        }

        $signature = sprintf('%s::%s::%s', $method, $path, $parentPath);

        return $this->slugify->slugify($signature).'_'.sha1($signature);
    }
}
