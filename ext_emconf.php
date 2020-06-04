<?php

/***************************************************************
 * Extension Manager/Repository config file for ext: "gs_monitor_provider"
 *
 *
 * Manual updates:
 * Only the data in the array - anything else is removed by next write.
 * "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['gs_monitor_provider'] = [
    'title' => 'GroundStack - Monitor Provider',
    'description' => 'A TYPO3 extension used to monitor updates for TYPO3 and all installed extensions.',
    'category' => 'plugin',
    'author' => 'Christian Hackl',
    'author_email' => 'info@groundstack.de',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-10.4.9',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'GroundStack\\GsMonitorProvider\\' => 'Classes',
            'ReallySimpleJWT\\' => 'Vendor/ReallySimpleJWT/src'
        ],
    ],
];
