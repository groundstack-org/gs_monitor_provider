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
     * @var RequestFactory
     */
    private $requestFactory;

    protected $extensionKey;
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

    protected $extConf;

    public function __construct() {
    }

    /**
     * action show
     *
     * @return void
     */
    public function indexAction() {
        $keyArray = [
            'public' => $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gs_monitor_provider']['publicKey']
        ];

        $this->view->assignMultiple([
            'keyArray' => $keyArray
        ]);
    }

    /**
     * savePublicKeyAction
     *
     * @param string $publickey
     * @return void
     */
    public function savePublicKeyAction(string $publickey = '') {
        $publicKey = trim($publickey);

        // Write to LocalConfiguration.php
        $objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $configurationManager = $objectManager->get('TYPO3\\CMS\\Core\\Configuration\\ConfigurationManager');
        $configurationManager->updateLocalConfiguration([
            'EXTENSIONS'=> [
                'gs_monitor_provider' => [
                    'publicKey' => $publicKey
                ]
            ]
        ]);

        $this->redirect(
            'index',
            NULL,
            NULL,
            []
        );
    }
}
