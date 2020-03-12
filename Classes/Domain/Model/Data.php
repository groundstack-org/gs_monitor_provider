<?php
namespace GroundStack\GsMonitorProvider\Domain\Model;

// use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \TYPO3\CMS\Extbase\Domain\Model\FileReference;
use \TYPO3\CMS\Extbase\Persistence\ObjectStorage;

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
 * Data
 */
class Data extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

    /**
     * apikey
     * @var string
     */
    protected $apikey;

    /**
     * publickey
     * @var string
     */
    protected $publickey;

    /**
     * __construct
     */
    public function __construct()
    {
        //Do not remove the next line: It would break the functionality
        // $this->initStorageObjects();
    }

    /**
     * Initializes all ObjectStorage properties
     * Do not modify this method!
     * It will be rewritten on each save in the extension builder
     * You may modify the constructor of this class instead
     *
     * @return void
     */
    protected function initStorageObjects()
    {
    }

    /**
     * sets the apikey attribute
     *
     * @param string $apikey
     */
    public function setApikey($apikey)
    {
        $this->apikey = $apikey;
    }

    /**
     * returns the apikey attribute
     *
     * @return string
     */
    public function getApikey()
    {
        return $this->apikey;
    }

    /**
     * sets the publickey attribute
     *
     * @param string $publickey
     */
    public function setPublickey($publickey)
    {
        $this->publickey = $publickey;
    }

    /**
     * returns the publickey attribute
     *
     * @return string
     */
    public function getPublickey()
    {
        return $this->publickey;
    }
}
