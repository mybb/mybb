<?php

namespace MyBB\Utilities;

use Traversable;

/**
 * The breadcrumb manager manages breadcrumb navigation.
 */
class BreadcrumbManager implements \IteratorAggregate
{
    /**
     * The root URL to the site.
     *
     * @var string $boardUrl
     */
    protected $boardUrl;

    /**
     * The current trail of breadcrumbs.
     *
     * @var array $breadcrumbs
     */
    protected $breadcrumbs;

    /**
     * A cache of forum details.
     *
     * @var array $forumCache
     */
    protected $forumCache;

    /**
     * Create a new breadcrumb manager with the given site name and index URL.
     *
     * The site name and index URL will be used as the first elements in the breadcrumb trail.
     *
     * @param string $boardName The name of the site.
     * @param string $boardUrl The URL to the index of the site.
     */
    public function __construct(string $boardName, string $boardUrl)
    {
        $this->boardUrl = $boardUrl;

        $this->breadcrumbs = [
            [
                'name' => $boardName,
                'url' => $boardUrl .'/index.php',
            ],
        ];

        $this->forumCache = [];
    }

    /**
     * Add a breadcrumb menu item to the list.
     *
     * @param string $name The name of the item to add.
     * @param string $url The URL of the item to add.
     */
    public function addBreadcrumb(string $name, string $url = '')
    {
        $this->breadcrumbs[] = [
            'name' => $name,
            'url' => $url,
        ];
    }

    /**
     * Reset the breadcrumb navigation to the first item, and clear all other entries.
     */
    public function reset()
    {
        $this->breadcrumbs = array_slice($this->breadcrumbs, 0, 1);
    }

    /**
     * Build the forum breadcrumb navigation (the navigation to a specific forum including all parent forums).
     *
     * @param int $fid The forum ID to build the navigation for.
     * @param array $multiPage The multi-page drop down array of information.
     */
    public function buildForumBreadcrumb(int $fid, array $multiPage = [])
    {
        if (empty($this->forumCache)) {
            // TODO: We want to eradicate globals eventually, but for now this is the best way to do this
            // NOTE: The below call to `cache_forums` will not rebuild the cache if it already exists -
            //  it is simply used here to ensure it exists.
            cache_forums();
            $forumCache = $GLOBALS['forum_cache'];

            foreach ($forumCache as $key => $val) {
                $this->forumCache[$val['fid']][$val['pid']] = $val;
            }
        }

        if (is_array($this->forumCache[$fid])) {
            foreach ($this->forumCache[$fid] as $key => $forumNav) {
                if ($fid == $forumNav['fid']) {
                    if (!empty($this->forumCache[$forumNav['pid']])) {
                        build_forum_breadcrumb($forumNav['pid']);
                    }

                    $newEntry = [
                        'name' => preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forumNav['name']),
                    ];

                    if (defined("IN_ARCHIVE")) {
                        // Set up link to forum in breadcrumb.
                        if ($this->forumCache[$fid][$forumNav['pid']]['type'] == 'f' ||
                            $this->forumCache[$fid][$forumNav['pid']]['type'] == 'c') {
                            $newEntry['url'] = "forum-{$forumNav['fid']}.html";
                        } else {
                            $newEntry['url'] = $this->boardUrl.'/archive/index.php';
                        }
                    } elseif (!empty($multiPage)) {
                        $newEntry['url'] = get_forum_link($forumNav['fid'], $multiPage['current_page']);
                        $newEntry['multipage'] = $multiPage;
                        $newEntry['multipage']['url'] = str_replace('{fid}', $forumNav['fid'], FORUM_URL_PAGED);
                    } else {
                        $newEntry['url'] = get_forum_link($forumNav['fid']);
                    }

                    $this->breadcrumbs[] = $newEntry;
                }
            }
        }
    }

    /**
     * Retrieve an external iterator
     *
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->breadcrumbs);
    }
}