<?php
namespace GroundStack\GsMonitorProvider\Controller\Backend;

use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use \TYPO3\CMS\Extbase\Annotation\Inject;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Messaging\FlashMessage;

use \TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use \TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use GroundStack\GsMonitorProvider\Domain\Repository\DataRepository;

/***
 *
 * This file is part of the "gs_monitor_observer" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

class ProviderModuleController extends ActionController {

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @Inject
     */
    protected $persistenceManager;

    /**
     * @var DataRepository
     */
    protected $dataRepository = null;

    /**
     * extensionKey
     *
     * @var string $extensionKey
     */
    protected $extensionKey = '';

    /**
     * extensionConfiguration
     *
     * @var [type]
     */
    protected $extensionConfiguration;

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view) {
        $this->extensionKey = 'gs_monitor_provider';
        // Typo3 extension manager gearwheel icon (ext_conf_template.txt)
        $this->extensionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][$this->extensionKey];

        $this->view->assign('script', 'T3_THIS_LOCATION = ' . GeneralUtility::quoteJSvalue(rawurlencode(GeneralUtility::getIndpEnv('REQUEST_URI'))) . ";");
    }

    public function __construct() {
    }

    /**
     * @param DataRepository $dataRepository
     */
    public function injectDataRepository(DataRepository $dataRepository) {
        $this->dataRepository = $dataRepository;
    }

    /**
     * action show
     *
     * @return void
     */
    public function indexAction() {
        $data = $this->dataRepository->findAll()->getFirst();

        $this->view->assignMultiple([
            'data' => $data
        ]);
    }

    /**
     * addNewDataAction
     *
     * @param \GroundStack\GsMonitorProvider\Domain\Model\Data $newData
     * @return void
     */
    public function addNewDataAction(\GroundStack\GsMonitorProvider\Domain\Model\Data $newData = null) {
        $errors = [];
        if($newData !== null) {
            if(!empty($newData->getUid()) && !empty($newData->getPid()) ) {
                $this->forward(
                    'updateData',
                    NULL,
                    NULL,
                    [
                        'data' => $newData
                    ]
                );
            }
        }

        // Allow only one dataset
        $dataSet = $this->dataRepository->countAll();
        if($dataSet === 0) {
            if(!empty($newData)) {
                if(!empty($newData->getApikey())) {
                    $newApiKey = trim($newData->getApikey());

                    // create passwordHash
                    $apiKeyHash = $this->createPassword($newApiKey);
                    $newData->setApikey($apiKeyHash);
                }

                if(!empty($newData->getPublickey())) {
                    $newPublicKey = trim($newData->getPublickey());
                }

                $this->dataRepository->add($newData);
                $this->persistenceManager->persistAll();
                // $this->dataRepository->update($newData);
            }
        }

        $dataSet2 = $this->dataRepository->countAll();
        if($dataSet2 > 0) {
            $errors[] = 'Database entry exists already!';
        } else {
            $this->view->assign("showForm", true);
        }

        foreach ($errors as $key => $value) {
            $this->addFlashMessage($value, '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        }

        $this->view->assignMultiple([
            'menuIndex' => 'addNewData'
        ]);
    }

    /**
     * updateDataAction
     *
     * @param \GroundStack\GsMonitorProvider\Domain\Model\Data $updateData
     * @return void
     */
    public function updateDataAction(\GroundStack\GsMonitorProvider\Domain\Model\Data $updateData) {

        if(empty($updateData->getApikey())) {
            $updateData->setApikey($updateData->_getCleanProperty('apikey'));
        } else {
            $updateData->setApikey($this->createPassword($updateData->getApikey()));
        }

        if(empty($updateData->getPublickey())) {
            $updateData->setPublickey($updateData->_getCleanProperty('publickey'));
        }

        $this->dataRepository->update($updateData);
        $this->persistenceManager->persistAll();

        $this->redirect(
            'index',
            NULL,
            NULL,
            []
        );
    }

    /**
     * deleteDataAction
     *
     * @param \GroundStack\GsMonitorProvider\Domain\Model\Data $deleteData
     * @return void
     */
    public function deleteDataAction(\GroundStack\GsMonitorProvider\Domain\Model\Data $deleteData) {
        $this->dataRepository->remove($deleteData);
        $affectedRows = $this->dataRepository->removeCompletely($deleteData->getUid());

        $this->addFlashMessage('Database entry deleted!', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);

        $this->redirect(
            'index',
            NULL,
            NULL,
            []
        );
    }

    /**
     * createPassword from string, returns hashed password
     *
     * @param string $password - clear text password
     * @return string
     */
    public function createPassword(string $password): string {
        $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');

        return $hashInstance->getHashedPassword($password);
    }
}
