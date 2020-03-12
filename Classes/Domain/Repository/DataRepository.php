<?php
namespace GroundStack\GsMonitorProvider\Domain\Repository;

// use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \Doctrine\DBAL\Connection;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Database\ConnectionPool;

/***
 *
 * This file is part of the "gs_monitor_provider" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/
/**
 * The repository for Data
 */
class DataRepository extends \TYPO3\CMS\Extbase\Persistence\Repository {

    /**
     * @var array
     */
    protected $defaultOrderings = [
        'sorting' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING
    ];

    public function initializeObject() {
        /**
         * @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings
         */
        // $querySettings = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Typo3QuerySettings');

        /** @var \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings $querySettings */
        $querySettings = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(FALSE);
        $querySettings->setRespectSysLanguage(TRUE);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * removeCompletely
     *
     * @param int $uid
     * @return void
     */
    public function removeCompletely(int $uid) {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_gsmonitorprovider_domain_model_data');
        $affectedRows = $queryBuilder
            ->delete('tx_gsmonitorprovider_domain_model_data')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid))
            )
            ->execute();

        return $affectedRows;
    }
}
