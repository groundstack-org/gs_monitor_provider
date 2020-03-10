<?php
return [
    'frontend' => [
        'GroundStack/frontend/monitor' => [
            'target' => \GroundStack\GsMonitorProvider\Middleware\MonitoringMiddleware::class,
            'before' => [
                'typo3/cms-frontend/eid',
            ],
            'after' => [
                'typo3/cms-frontend/preprocessing',
            ],
        ],
    ]
];
