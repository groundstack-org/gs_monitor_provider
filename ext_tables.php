<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function() {

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
        'GroundStack.GsMonitorProvider',
        'Provider',
        'Monitor Provider'
    );

    if (TYPO3_MODE === 'BE') {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'GroundStack.GsMonitorProvider', // NameSpace . instead \
            'web', // Make module a submodule of 'web'
            'providermodule', // Submodule key
            '', // Position
            [
                'Backend\ProviderModule' => 'index, savePublicKey',
            ],
            [
                'access' => 'systemMaintainer',
                'icon'   => 'EXT:gs_monitor_provider/Resources/Public/Icons/user_mod_providermodule.svg',
                'labels' => 'LLL:EXT:gs_monitor_provider/Resources/Private/Language/locallang.xlf',
                'navigationComponentId' => '', // hide pagetree
                'inheritNavigationComponentFromMainModule' => false
            ]
        );
    }

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('provider_module', 'Configuration/TypoScript', 'Monitor Provider Module');

    // \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_gsmonitorprovider_domain_model_data');

});
