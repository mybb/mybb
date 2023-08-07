<?php

declare(strict_types=1);

namespace MyBB\Http\Controllers;

use DB_Base;
use MyBB;
use MyBB\Stopwatch\Stopwatch;
use MyLanguage;

use function MyBB\Maintenance\template;

class DebugController
{
    public function __construct(
        private MyBB $mybb,
        private DB_Base $db,
        private MyLanguage $lang,
        private Stopwatch $stopwatch,
    ) {}

    public function index(): never
    {
        require_once MYBB_ROOT . 'inc/src/Maintenance/functions_http.php';

        echo template('debug/index.twig', [
            'data' => $this->getData(),
        ]);

        exit;
    }

    private function getData(): array
    {
        global $templates, $templatelist;

        $data = [];

        $eventDuration = [
            'main' => $this->stopwatch->getEvent('main')?->getDuration(),
            'core.init.bootstrap' => $this->stopwatch->getEvent('core.init.bootstrap')?->getDuration(),
            'core.init' => $this->stopwatch->getEvent('core.init')?->getDuration(),
            'core.global' => $this->stopwatch->getEvent('core.global')?->getDuration(),
        ];

        $stageDuration = [
            'bootstrap' => $eventDuration['core.init.bootstrap'],
            'init' => max(0, $eventDuration['core.init'] - $eventDuration['core.init.bootstrap']),
            'global' => $eventDuration['core.global'],
            'controller' => max(0, $eventDuration['main'] - $eventDuration['core.global'] - $eventDuration['core.init']),
        ];

        $timeDb = $this->db->query_time;
        $timePhp = $eventDuration['main'] - $timeDb;

        $percentDb = number_format((($timeDb / $eventDuration['main']) * 100), 2);
        $percentPhp = number_format((($timePhp / $eventDuration['main']) * 100), 2);

        $memoryUsage = get_memory_usage();
        if (!$memoryUsage) {
            $memoryUsage = $this->lang->unknown;
        } else {
            $memoryUsage = get_friendly_size($memoryUsage)." ({$memoryUsage} bytes)";
        }

        // opcache
        $opcacheQueryable = function_exists('opcache_get_status') && opcache_get_status() !== false;

        if ($opcacheQueryable) {
            $opcacheConfig = opcache_get_configuration();
            $opcache = opcache_get_status();

            $opcacheUsedMemoryRatio =
                $opcache['memory_usage']['used_memory'] /
                ($opcache['memory_usage']['used_memory'] + $opcache['memory_usage']['free_memory'])
            ;
            $opcacheCachedKeysRatio =
                $opcache['opcache_statistics']['num_cached_keys'] /
                $opcache['opcache_statistics']['max_cached_keys']
            ;

            $data['opcache'] = [
                'jit' => $opcacheConfig['directives']['opcache.jit'],
                'revalidate_freq' => $opcacheConfig['directives']['opcache.revalidate_freq'] . ' s',
                'enable_file_override' => $opcacheConfig['directives']['opcache.enable_file_override'],
                'optimization_level' => $opcacheConfig['directives']['opcache.optimization_level'],
                'preload' => $opcacheConfig['directives']['opcache.preload'],
                'file_update_protection' => $opcacheConfig['directives']['opcache.file_update_protection'],
                'Cached Scripts' => $opcache['opcache_statistics']['num_cached_scripts'],
                'Cached Keys' => $opcache['opcache_statistics']['num_cached_keys'] . ' (' . round($opcacheCachedKeysRatio * 100, 2) . '%)',
                'Used Memory' => get_friendly_size($opcache['memory_usage']['used_memory']) . ' (' . round($opcacheUsedMemoryRatio * 100, 2) . '%)',
                'Interned Strings' => $opcache['interned_strings_usage']['number_of_strings'] . ' — ' . get_friendly_size($opcache['interned_strings_usage']['used_memory']),
                'Hits' => $opcache['opcache_statistics']['hits'] . ' — ' . round($opcache['opcache_statistics']['opcache_hit_rate'], 2) . '%',
                'Misses' => $opcache['opcache_statistics']['misses'],
            ];
        }

        // definition lists
        $data['timing'] = [
            'Page Generation Time' => format_time_duration($eventDuration['main']),
            'PHP Processing Time' => format_time_duration($timePhp) . ' (' . $percentPhp . '%)',
            'DB Processing Time' => format_time_duration($this->db->query_time) . ' (' . $percentDb . '%)',
        ];
        $data['stagesTiming'] = [
            '<abbr title="inc/src/bootstrap.php">Bootstrapping</abbr>' => $stageDuration['bootstrap'],
            '<abbr title="inc/init.php">General Initialization</abbr>' => $stageDuration['init'],
            '<abbr title="global.php">Front-end Initialization</abbr>' => $stageDuration['global'],
            'Controller' => $stageDuration['controller'],
        ];
        $data['resources'] = [
            'No. Included Files' => count(get_included_files()),
            'No. DB Queries' => $this->db->query_count,
            'No. DB Templates Used' => count($templates->cache)." (".count(explode(",", $templatelist ?? ''))." Cached / ".count($templates->uncached_templates)." Manually Loaded)",
            'Memory Usage' => $memoryUsage,
            'Server Load' => get_server_load(),
        ];
        $data['status'] = [
            'MyBB Version' => $this->mybb->version,
            'PHP Version' => PHP_VERSION,
            'Database Extension' => $this->mybb->config['database']['type'],
            'GZip Encoding' => $this->mybb->settings['gzipoutput'] != 0 ? "Enabled" : "Disabled",
            'OPcache' => $opcacheQueryable && $opcache['opcache_enabled']
                ? $opcacheConfig['version']['opcache_product_name'] . ' ' . $opcacheConfig['version']['version']
                : 'Disabled'
            ,
            'Memory Limit' => @ini_get("memory_limit"),
        ];

        // cache
        $data['cache'] = $this->mybb->cache->calllist;

        // database
        $data['dbConnections'] = $this->db->connections;
        $data['dbQueries'] = $this->db->querylist;

        // processing
        $data['parserParsingEvents'] = $this->stopwatch->getEvents('core.parser.parse');
        $data['parserValidationEvents'] = $this->stopwatch->getEvents('core.parser.validate');

        // view
        $data['dbTemplates'] = array_keys($templates->cache);
        $data['dbTemplatesUncached'] = $templates->uncached_templates;

        $data['twigTemplateEvents'] = $this->stopwatch->getEvents('core.view.template');
        $data['assetPublishEvents'] = $this->stopwatch->getEvents('core.view.asset.publish');

        return $data;
    }
}
