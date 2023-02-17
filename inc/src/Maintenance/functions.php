<?php
/**
 * Miscellaneous helper functions.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use MyBB;

#region lock file
function lockFileExists(string $type = null): bool
{
    $filename = 'lock';

    if ($type !== null) {
        $filename .= '_' . $type;
    }

    return file_exists(INSTALL_ROOT . $filename);
}

/**
 * @throws \Exception
 */
function createLockFile(string $type = null): void
{
    $filename = 'lock';

    if ($type !== null) {
        $filename .= '_' . $type;
    }

    if (is_writable(INSTALL_ROOT)) {
        $handle = @fopen(INSTALL_ROOT . $filename, 'w');
        $result = @fwrite($handle, '1');
        @fclose($handle);

        if ($result === false || $result === 0) {
            throw new \Exception('Failed to create lock file');
        }
    } else {
        throw new \Exception('Lock file directory not writable');
    }
}
#endregion

#region network
/**
 * @psalm-pure
 */
function hostnameResolvesToLoopbackAddress(string $hostname): ?bool
{
    $dotAddress = gethostbyname($hostname);

    if (filter_var($dotAddress, FILTER_VALIDATE_IP)) {
        return ipAddressIsLoopback($dotAddress);
    } else {
        return null;
    }
}

/**
 * @psalm-pure
 */
function ipAddressIsLoopback(string $dotAddress): bool
{
    $cidrRanges = [
        '127.0.0.0/8',
        '::1/128',
    ];

    $packedAddress = my_inet_pton($dotAddress);

    foreach ($cidrRanges as $cidrRange) {
        [$min, $max] = fetch_ip_range($cidrRange);

        if (strcmp($min, $packedAddress) <= 0 && strcmp($max, $packedAddress) >= 0) {
            return true;
        }
    }

    return false;
}
#endregion

#region XML
function getXmlTreeFromFile(string $filePath): array
{
    $parser = create_xml_parser(
        file_get_contents($filePath)
    );

    $parser->collapse_dups = 0;

    return $parser->get_tree();
}

function xmlTreeToArray(array $tree): array
{
    $results = [];

    $ignoredFields = ['attributes', 'tag', 'value'];

    $a = $tree[array_key_first($tree)][0];

    $childrenTagName = current(
        array_diff(
            array_keys($a),
            $ignoredFields,
        )
    );

    foreach ($a[$childrenTagName] as $row) {
        $result = [];

        foreach ($row as $field => $value) {
            if (in_array($field, $ignoredFields)) {
                continue;
            }

            $result[$field] = $value[0]['value'];
        }

        $results[] = $result;
    }

    return $results;
}
#endregion
