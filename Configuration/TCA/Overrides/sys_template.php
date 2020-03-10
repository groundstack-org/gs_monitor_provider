<?php
defined('TYPO3_MODE') || die();

call_user_func(function() {

    $extensionKey = 'gs_monitor_provider';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
        $extensionKey,
        'Configuration/TypoScript',
        'Hauer-Heinrich - Montitor TS'
    );
});
