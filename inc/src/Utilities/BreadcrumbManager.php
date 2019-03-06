<?php

namespace MyBB\Utilities;

use Traversable;

/**
 * The breadcrumb manager manages breadcrumb navigation.
 */
class BreadcrumbManager implements \IteratorAggregate, \ArrayAccess, \Countable
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

        $boardUrl = rtrim($boardUrl, '/');

        $this->breadcrumbs = [
            [
                'name' => $boardName,
                'url' => $boardUrl .'/index.php',
            ],
        ];

        $this->forumCache = [];
    }

    /**
     * Get the raw breadcrumb items.
     *
     * The breadcrumbs are returned by reference, allowing them to be manually edited by plugins and elsewhere.
     *
     * @return array
     */
    public function &getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
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
            // NOTE: The below call to `cache_forums` will not rebuild the cache if it already exists -
            //  it is simply used here to ensure it exists.
            $forumCache = cache_forums();

            foreach ($forumCache as $key => $val) {
                $this->forumCache[$val['fid']][$val['pid']] = $val;
            }
        }

        if (is_array($this->forumCache[$fid])) {
            foreach ($this->forumCache[$fid] as $key => $forumNav) {
                if ($fid == $forumNav['fid']) {
                    if (!empty($this->forumCache[$forumNav['pid']])) {
                        $this->buildForumBreadcrumb((int) $forumNav['pid']);
                    }

                    $newEntry = [
                        'name' => preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $forumNav['name']),
                    ];

                    if (!empty($multiPage)) {
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
     * Get the number of items in the breadcrumb list.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->breadcrumbs);
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

    /**
     * Whether a offset exists
     *
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->breadcrumbs[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     *
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return isset($this->breadcrumbs[$offset]) ? $this->breadcrumbs[$offset] : null;
    }

    /**
     * Offset to set
     *
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     *
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->breadcrumbs[] = $value;
        } else {
            $this->breadcrumbs[$offset] = $value;
        }
    }

    /**
     * Offset to unset
     *
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     *
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        unset($this->breadcrumbs[$offset]);
    }
}
