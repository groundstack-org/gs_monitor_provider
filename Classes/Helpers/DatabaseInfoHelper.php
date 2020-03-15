<?php
declare(strict_types=1);
namespace GroundStack\GsMonitorProvider\Helpers;

// use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Utility\VersionNumberUtility;
use \TYPO3\CMS\Core\Database\ConnectionPool;

class DatabaseInfoHelper {

    /**
     * For getting the database version we have to deal with different methods.
     * Since TYPO3 v8 we get the version via an abstraction layer which is
     * more future prove.
     * For v6 we need to perform a plain query and for v7 we have do have a method.
     *
     * @return string
     */
    public static function getDatabaseVersion(): string {
        $currentTYPO3Version = VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version);
        $version = 'n/a';

        if ($currentTYPO3Version <= VersionNumberUtility::convertVersionNumberToInteger('7.0.0')) {
            // TYPO3 < v7
            $version = $GLOBALS['TYPO3_DB']->sql_fetch_assoc(
                $GLOBALS['TYPO3_DB']->sql_query('SELECT @@version')
            )['@@version'];
        } elseif ($currentTYPO3Version <= VersionNumberUtility::convertVersionNumberToInteger('8.0.0')) {
            // TYPO3 < v8
            $version = $GLOBALS['TYPO3_DB']->getServerVersion();
        } else {
            // TYPO3 >= v8
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionByName(
                // We only use the first connection, because we usually don't use more than
                // one database. In the future or when we build a bigger website with more
                // than one database we can update this to a more generic method.
                $connectionPool->getConnectionNames()[0]
            );
            $version = $connection->getServerVersion();
        }

        return $version;
    }

}