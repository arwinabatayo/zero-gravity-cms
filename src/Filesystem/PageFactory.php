<?php

namespace ZeroGravity\Cms\Filesystem;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ZeroGravity\Cms\Content\Page;
use ZeroGravity\Cms\Exception\FilesystemException;
use ZeroGravity\Cms\Filesystem\Event\AfterPageCreate;
use ZeroGravity\Cms\Filesystem\Event\BeforePageCreate;

class PageFactory
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Directory[]
     */
    private $directories = [];

    /**
     * @param LoggerInterface          $logger
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Create Page from directory content.
     *
     * @param Directory $directory
     * @param bool      $convertMarkdown
     * @param array     $defaultSettings
     * @param Page|null $parentPage
     *
     * @return null|Page
     */
    public function createPage(
        Directory $directory,
        bool $convertMarkdown,
        array $defaultSettings,
        Page $parentPage = null
    ) {
        $directory->validateFiles();
        if (Directory::CONTENT_STRATEGY_NONE === $directory->getContentStrategy()) {
            return null;
        }

        if (null !== $parentPage) {
            $defaultSettings = $this->mergeSettings($defaultSettings, $parentPage->getChildDefaults());
        }
        $settings = $this->buildPageSettings($defaultSettings, $directory, $parentPage);

        /* @var $handledBeforeCreatePage BeforePageCreate */
        $handledBeforeCreatePage = $this->eventDispatcher->dispatch(
            BeforePageCreate::BEFORE_PAGE_CREATE,
            new BeforePageCreate($directory, $settings, $parentPage)
        );
        $page = new Page($directory->getName(), $handledBeforeCreatePage->getSettings(), $parentPage);
        $page->setContent($directory->fetchPageContent($convertMarkdown));

        $files = $directory->getFiles();
        foreach ($directory->getDirectories() as $subDirectory) {
            $subPage = $this->createPage($subDirectory, $convertMarkdown, $defaultSettings, $page);
            if (null === $subPage) {
                foreach ($subDirectory->getFilesRecursively() as $path => $file) {
                    $files[$subDirectory->getName().'/'.$path] = $file;
                }
            }
        }
        $page->setFiles($files);
        $this->eventDispatcher->dispatch(
            AfterPageCreate::AFTER_PAGE_CREATE,
            new AfterPageCreate($page)
        );
        $this->directories[$page->getPath()->toString()] = $directory;

        return $page;
    }

    /**
     * @param array     $defaultSettings
     * @param Directory $directory
     * @param Page      $parentPage
     *
     * @return array
     */
    private function buildPageSettings(array $defaultSettings, Directory $directory, Page $parentPage = null): array
    {
        $settings = $directory->fetchPageSettings();
        $defaultTemplate = $directory->getDefaultBasenameTwigFile();

        if (null !== $defaultTemplate && !isset($settings['content_template'])) {
            $parentPath = isset($parentPage) ? rtrim($parentPage->getFilesystemPath(), '/') : '';
            $settings['content_template'] = sprintf('@ZeroGravity%s/%s/%s',
                $parentPath,
                $directory->getName(),
                $defaultTemplate->getFilename()
            );
        }

        $settings = $this->mergeSettings([
            'slug' => $directory->getSlug(),
            'visible' => $directory->hasSortingPrefix() && !$directory->hasUnderscorePrefix(),
            'module' => $directory->hasUnderscorePrefix(),
        ],
            $defaultSettings,
            $settings
        );

        return $settings;
    }

    /**
     * Merge 2 or more arrays, deep merging array values while replacing scalar values.
     *
     * @param array[] $params 1 or more arrays to merge
     *
     * @return array
     */
    private function mergeSettings(array ...$params): array
    {
        $settings = [];
        foreach ($params as $param) {
            foreach ($param as $key => $value) {
                if (isset($settings[$key]) && is_array($settings[$key]) && is_array($value)) {
                    $settings[$key] = $this->mergeSettings($settings[$key], $value);
                } else {
                    $settings[$key] = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Get the directory for a previously created page instance.
     *
     * @param Page $page
     *
     * @return Directory
     */
    public function getDirectory(Page $page): Directory
    {
        if (!isset($this->directories[$page->getPath()->toString()])) {
            throw FilesystemException::cannotFindDirectoryForPage($page);
        }

        return $this->directories[$page->getPath()->toString()];
    }
}
