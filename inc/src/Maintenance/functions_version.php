<?php

/**
 * Functions used for version checks and development information.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use FeedParser;
use InvalidArgumentException;
use MyBB;

const PRODUCT_VERSION_CHECK_JSON_URL = 'https://mybb.com/version_check.json';
const PRODUCT_NEWS_FEED_URL = 'http://feeds.feedburner.com/MyBBDevelopmentBlog';

const DEVELOPMENT_REPOSITORY_API_BASE_URL = 'https://api.github.com';
const DEVELOPMENT_REPOSITORY = 'mybb/mybb';
const DEVELOPMENT_REPOSITORY_BRANCH_PREFIX = 'dev-';
const DEVELOPMENT_REPOSITORY_COMMITS_BASE_URL = 'https://github.com/' . DEVELOPMENT_REPOSITORY . '/commits/';

/**
 * Determines if the current environment indicates development activity.
 */
function hasDevelopmentArtifacts(): bool
{
    return (
        file_exists(MYBB_ROOT . '.git/HEAD') ||
        file_exists(MYBB_ROOT . '.source-commit')
    );
}

/**
 * Returns the commit hash associated with the current installation.
 *
 * Uses the local git repository if the current branch matches the expected development branch name.
 * If no local repository exists, uses the `.source-commit` file.
 */
function getDevelopmentVersionCommitHash(MyBB $mybb): ?string
{
    $branchName = getDevelopmentBranchName($mybb->version);

    $repositoryDetails = readRepositoryDetails(MYBB_ROOT);

    if ($repositoryDetails !== null) {
        if (
            $repositoryDetails['branch'] !== null &&
            $repositoryDetails['branch']['name'] === $branchName
        ) {
            return $repositoryDetails['branch']['latest_commit']['hash'];
        } else {
            // undefined for current branch
            return null;
        }
    } else {
        $generatedCommitHash = readGeneratedDevelopmentVersionCommitHash();

        if ($generatedCommitHash !== null) {
            return $generatedCommitHash;
        }
    }

    return null;
}

/**
 * Returns the hash from the generated `.source-commit` file.
 */
function readGeneratedDevelopmentVersionCommitHash(): ?string
{
    $path = MYBB_ROOT . '.source-commit';

    if (file_exists($path)) {
        $content = file_get_contents($path);

        if ($content !== false) {
            $content = trim($content);

            if (
                in_array(strlen($content), [40, 64]) &&
                ctype_xdigit($content)
            ) {
                return $content;
            }
        }
    }

    return null;
}

/**
 * Returns information about a git repository in the given path.
 *
 * Recognizes repositories in standard location and loose ref files.
 *
 * @param string $path The path to the directory that may contain a `.git` directory, with trailing slash.
 *
 * @return ?array{
 *   branch: ?array{
 *     name: string,
 *     latest_commit: array{
 *       hash: string,
 *     },
 *   },
 * }
 */
function readRepositoryDetails(string $path): ?array
{
    $headFilePath = $path . '.git/HEAD';

    if (
        file_exists($headFilePath) &&
        $headFileContent = file_get_contents($headFilePath)
    ) {
        $results = [
            'branch' => null,
        ];

        if (preg_match('/^ref: refs\/heads\/([a-zA-Z0-9_-]+)/', $headFileContent, $matches)) {
            $branchName = trim($matches[1]);

            $refFilePath = $path . '.git/refs/heads/' . $branchName;

            if (
                file_exists($refFilePath) &&
                $refFileContent = file_get_contents($refFilePath)
            ) {
                $commitHash = trim($refFileContent);

                if (ctype_xdigit($commitHash)) {
                    $results['branch']['name'] = $branchName;
                    $results['branch']['latest_commit']['hash'] = $commitHash;
                }
            }
        }

        return $results;
    }

    return null;
}

function fetchLatestVersionDetails(): ?array
{
    $body = fetch_remote_file(PRODUCT_VERSION_CHECK_JSON_URL);

    if ($body !== false) {
        $data = json_decode($body, true);

        if (isset($data['mybb'])) {
            return $data['mybb'];
        }
    }

    return null;
}

/**
 * Returns latest MyBB news entries.
 *
 * @param non-negative-int $limit
 *
 * @return ?list<array{
 *   title: string,
 *   description: string,
 *   link: string,
 *   author: string,
 *   dateline: int,
 * }>
 */
function fetchLatestNews(int $limit = 3): ?array
{
    require_once MYBB_ROOT . 'inc/class_feedparser.php';

    $parser = new FeedParser();

    $parser->parse_feed(PRODUCT_NEWS_FEED_URL);

    if ($parser->error == '') {
        $results = [];

        $items = array_slice($parser->items, 0, $limit);

        foreach ($items as $item) {
            $results[] = [
                'title' => $item['title'],
                'description' => $item['description'],
                'link' => $item['link'],
                'author' => $item['author'],
                'dateline' => $item['date_timestamp'],
            ];
        }

        return $results;
    }

    return null;
}

/**
 * Returns information about MyBB's development repository activity for the given branch.
 *
 * @return ?array{
 *   latest_commit: array{
 *     hash: string,
 *     date: string,
 *   },
 * }
 */
function fetchDevelopmentBranchDetails(string $branchName): ?array
{
    if (!extension_loaded('curl')) {
        return null;
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL =>
            DEVELOPMENT_REPOSITORY_API_BASE_URL .
            '/repos/' .
            DEVELOPMENT_REPOSITORY .
            '/branches/' .
            $branchName,
        CURLOPT_HTTPHEADER => [
            'User-Agent: MyBB Version Check (https://mybb.com)',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);

    $result = curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($responseCode === 200 && $result !== false) {
        $data = json_decode($result, true);

        if (
            $data !== null &&
            isset(
                $data['commit']['sha'],
                $data['commit']['commit']['committer']['date'],
            )
        ) {
            return [
                'latest_commit' => [
                    'hash' => $data['commit']['sha'],
                    'date' => $data['commit']['commit']['committer']['date'],
                ],
            ];
        }
    }

    return null;
}

/**
 * Returns the expected branch name in MyBB's development repository for the given version.
 *
 * @param string $version A version containing a major and minor component (X.Y).
 */
function getDevelopmentBranchName(string $version): string
{
    if (preg_match('/^(\d+\.\d+)/', $version, $matches)) {
        return DEVELOPMENT_REPOSITORY_BRANCH_PREFIX . $matches[0];
    } else {
        throw new InvalidArgumentException();
    }
}

/**
 * Returns the URL for the listing of recent commits in MyBB's development repository.
 */
function getDevelopmentBranchCommitsUrl(string $branchName): string
{
    return DEVELOPMENT_REPOSITORY_COMMITS_BASE_URL . $branchName;
}
