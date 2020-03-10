<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function() {
    $version9 = \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= \TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('9.3');
    // if TYPO3 version 9 or higher:
    if($version9) {
        // TYPO3 >= 9 uses middleware instead of old eID
    } else {
        $TYPO3_CONF_VARS['FE']['eID_include']['anxapi/v1/up'] = 'EXT:gs_monitor_provider/Resources/Private/Eid/Up.php';
        $TYPO3_CONF_VARS['FE']['eID_include']['anxapi/v1/modules'] = 'EXT:gs_monitor_provider/Resources/Private/Eid/Modules.php';
    }
});
