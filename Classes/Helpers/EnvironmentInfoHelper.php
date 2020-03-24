<?php
declare(strict_types=1);
namespace GroundStack\GsMonitorProvider\Helpers;

// use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Utility\VersionNumberUtility;

class EnvironmentInfoHelper {
    /**
     * Gets the version data as array. The array has a key `runtime` containing data about the
     * platform and framework, as well as a key `modules` where the installed extensions, including
     * their installed and newest version number, are listed.
     *
     * @return array
     */
    static function getVersionData(): array {
        $objectManager = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
        $extensionListUtility = $objectManager->get('TYPO3\CMS\Extensionmanager\Utility\ListUtility');

        $result = [];
        $extensions = $extensionListUtility->getAvailableExtensions();
        $extensionTer = $extensionListUtility->enrichExtensionsWithEmConfAndTerInformation($extensions);
        $t3Versions = self::getT3Versions();
        $useComposer = self::usesComposer();

        // Platform data
        $result['runtime'] = [
            'platform' => 'php',
            'platform_version' => phpversion(),
            'framework' => 'typo3',
            'framework_installed_version' => $t3Versions[0],
            'framework_newest_version' => $t3Versions[1],
            'composer' => $useComposer
        ];

        // Extension data
        $result['modules'] = [];

        foreach ($extensionTer as $extensionKey => $extension) {
            if($extension['type'] === 'Local') {
                $result['modules'][] = [
                    'name' => $extension['key'],
                    'installed_version' => $extension['version'],
                    'newest_version' => isset($extension['updateToVersion']) ? $extension['updateToVersion']->getVersion() : $extension['version'],
                ];
            }
        }

        return $result;
    }

    /**
     * Gets the currently installed and the newest available version of TYPO3. Returns an
     * array with these versions, where index 0 is the current version and index 1 is the
     * newest available version.
     *
     * @return array<string>
     */
    static function getT3Versions(): array {
        $versionInformationUrl = 'https://get.typo3.org/json';
        $versionInformationResult = GeneralUtility::getUrl($versionInformationUrl);

        // if fetching the release data failed, just return the installed
        // version and version 0.0.0 as latest.
        if (!$versionInformationResult) {
            return [TYPO3_version, '0.0.0'];
        }

        $versionInformation = @json_decode($versionInformationResult, true);
        $latestStable = explode('.', $versionInformation['latest_stable']);
        $latestLts = explode('.', $versionInformation['latest_lts']);
        $latest = $versionInformation['latest_stable'];

        // for some wired reason the latest LTS version is greater than
        // the latest stable version. as we are interested in the most recent version
        // we check which of the twe, lts or stable, is the greatest version number, and return
        // this version.
        foreach ($latestStable as $key => $part) {
            if ($latestLts[$key] > $latestStable[$key]) {
                $latest = $versionInformation['latest_lts'];
                break;
            }
        }

        return [
            TYPO3_version,
            $latest,
        ];
    }

    /**
     * Will return true for an instance that uses composer or false if it isn't.
     * We do a version comparison because versions older than TYPO3 v7 are not installable via Composer.
     *
     * @return bool
     */
    public static function usesComposer(): bool {
        $currentTYPO3Version = VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
        $composer = false;

        if ($currentTYPO3Version >= VersionNumberUtility::convertVersionNumberToInteger('7.0.0')) {
            $composer = \TYPO3\CMS\Core\Core\Bootstrap::usesComposerClassLoading();
        }

        return $composer;
    }

    /**
     * currentVersionHigherThan
     *
     * @param string $version - e. g. 9.3 or 8.7
     * @return bool
     */
    public static function currentVersionHigherThan(string $version): bool {
        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= VersionNumberUtility::convertVersionNumberToInteger($version)) {
            return true;
        }

        return false;
    }
}
