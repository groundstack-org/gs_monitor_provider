<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function() {
    $versionBiggerThan9 = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('9.3');
    // if TYPO3 version 9 or higher:
    if($versionBiggerThan9) {
        // TYPO3 >= 9 uses middleware instead of old eID
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['gs_monitor_provider'] = \GroundStack\GsMonitorProvider\Eid\Monitoring::class . '::process';
        // $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['gs_monitor_provider'] = 'EXT:gs_monitor_provider/Classes/Eid/Monitoring.php';
    }
});
