<?php

declare(strict_types=1);

namespace MyBB\Http\Controllers\Maintenance;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use MyBB;

class MaintenanceController
{
    public function __construct(Request $request, \MyLanguage $lang)
    {
        $lang->load('maintenance');

        // global permissions

        if (
            \MyBB\Maintenance\lockFileExists() &&
            !\MyBB\Maintenance\developmentEnvironment()
        ) {
            \MyBB\Maintenance\httpOutputError(
                $lang->locked_title,
                $lang->sprintf($lang->locked, 'lock'),
            );
        }

        // routing

        $action = $request->get('action') ?? 'index';

        switch ($action) {
            case 'get_latest_version':
                $controller = 'MaintenanceController::getLatestVersion';
                break;

            case 'get_deferred_default_values':
                $controller = 'ProcessController@getDeferredDefaultValues';
                break;
            case 'get_parameter_feedback':
                $controller = 'ProcessController@getParameterFeedback';
                break;
            case 'run_operation':
                $controller = 'ProcessController@runOperation';
                break;
            case 'index':
                $controller = 'ProcessController@index';
                $parameters = [
                    'languages' => $lang->get_languages(),
                ];
                break;
        }

        if (isset($controller)) {
            $results = Container::getInstance()->call(
                'MyBB\Http\Controllers\Maintenance\\' . $controller,
                $parameters ?? [],
            );
        } else {
            $results = null;
        }

        // output

        \MyBB\Maintenance\httpSendStandardHeaders();

        if (is_array($results)) {
            header('Content-type: application/json');
            echo json_encode($results);
        } else {
            echo $results;
        }
    }

    public static function getLatestVersion(MyBB $mybb): ?array
    {
        $results = null;

        $latestVersionDetails = \MyBB\Maintenance\getLatestVersionDetails();

        if (isset($latestVersionDetails['latest_version'])) {
            $results = [
                'latest_version' => $latestVersionDetails['latest_version'],
                'upToDate' => version_compare($mybb->version, $latestVersionDetails['latest_version']) >= 0,
            ];
        }

        return $results;
    }
}
