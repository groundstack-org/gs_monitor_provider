<?php
declare(strict_types=1);
namespace GroundStack\GsMonitorProvider\Eid;

use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;

use \UnexpectedValueException;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Server\RequestHandlerInterface;
use \TYPO3\CMS\Core\Http\Response;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Database\ConnectionPool;

use \ReallySimpleJWT\Token;
use GroundStack\GsMonitorProvider\Helpers\JsonResponse;
use GroundStack\GsMonitorProvider\Helpers\EnvironmentInfoHelper;
use GroundStack\GsMonitorProvider\Helpers\DatabaseInfoHelper;
use GroundStack\GsMonitorProvider\Domain\Repository\DataRepository;

/**
 * Monitoring
 *
 * EXAMPLE:
 * GET or POST - http://www.domain.tld/api/v2/?access_token=testing
 * POST - http://www.domain.tld/api/v2 --- ! set http 'api-key' / 'Bearer authorization' header
 */
class Monitoring {

    /**
     * response
     *
     * @var Response
     */
    protected $response;

    /**
     * errors
     *
     * @var array $errors
     */
    protected $errors = [];

    /**
     * status
     *
     * @var int $status
     */
    protected $status = 200;

    /**
     * headers
     *
     * @var int $headers
     */
    protected $headers = [];

    /**
     * DataRepository
     *
     * @var DataRepository
     */
    protected $dataRepository = null;

    /**
     * expath
     *
     * @var array
     */
    protected $expath = [];

    /**
     * ext_conf_template config
     *
     * @var array $extensionConfiguration
     */
    protected $extensionConfiguration = [];

    /**
     * accessToken or jwtToken
     *
     * @var string $secret
     */
    private $secret = '';

    /**
     * payload
     *
     * @var array $payload
     */
    private $payload = [];

    public function __construct() {
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return void
     */
    public function process(ServerRequestInterface $request, ResponseInterface $handler) {
        // Remove any output produced until now
        ob_clean();

        /** @var $logger \TYPO3\CMS\Core\Log\Logger */
        $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);

        // config from ext_conf_template.txt
        $objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

        /** @var \TYPO3\CMS\Extensionmanager\Utility\ConfigurationUtility $configurationUtility */
        $configurationUtility = $objectManager->get('TYPO3\CMS\Extensionmanager\Utility\ConfigurationUtility');
        $this->extensionConfiguration = $configurationUtility->getCurrentConfiguration('gs_monitor_provider');

        $this->dataRepository = $objectManager->get('GroundStack\\GsMonitorProvider\\Domain\\Repository\\DataRepository');

        $this->secret = $this->extensionConfiguration['jwt.secret'];

        // HtmlResponse($content, $status = 200, array $headers = [])
        // JsonResponse($data = [], $status = 200, array $headers = [], $encodingOptions)
        // return new JsonResponse(['test' => 'testing']);
        switch ($request->getMethod()) {
            case 'GET':
                header('Content-Type: application/json; charset=UTF-8');
                $this->processPostRequest($request);
                break;
            case 'POST':
                header('Content-Type: application/json; charset=UTF-8');
                $this->processPostRequest($request);
                break;
            default:
                return new HtmlResponse('Method not allowed', 405);
                break;
        }

        // TODO error handling überarbeiten - bzw. abfrage api-key und token
        // aktuell werden beide nach einander abgefragt = nicht gut!
        // if(empty($this->errors)) {
            // header('Authorization: asdfadf');

            // $data = $this->dataRepository->findAll()->getFirst();
            // $apiKey = $data->getApikey();
            // $apiKeyPlain = $request->getHeader('api-key')[0];
            // var_dump($data);
            // var_dump($apiKey);
            // echo "<br><br>";
            // var_dump($apiKeyPlain);
            // var_dump($this->checkPassword($apiKey, $apiKeyPlain));
            // var_dump($data = $this->dataRepository->findAll()->getFirst());
            // var_dump($this->response);
            // var_dump($this->status);
            // var_dump($this->headers);
            // var_dump($this->createJsonResponse());
            // var_dump($this->authenticate($request));
            // var_dump($request->getHeaders());
            // die();

            // return $this->createJsonResponse($this->response);
            return new JsonResponse($this->response, $this->status, $this->headers);
        // }
        // return $this->response;
        // print_r($this->createJsonResponse($this->response));
        // die();
        // return $this->createJsonResponse($this->response);

        // return $this->createJsonResponse($this->errors);
        // return new JsonResponse(json_encode($this->errors), $this->status, $this->headers);
    }

    /**
     * processPostRequest
     *
     * @param array $request
     * @return void
     */
    public function processPostRequest($request) {

        switch ($this->extensionConfiguration['accessMethod']['value']) {
            case '1': // jwt-Token
                // check API-Key

                if ($this->authenticate($request)) {
                    header( 'Authorization: '.$this->generateToken() );
                    break;
                }

                // check JWT-Token
                // don't send the api-key again with the token!
                if($this->checkToken($request)) {
                    $environmentInfo = EnvironmentInfoHelper::getVersionData();
                    $databaseInfo = DatabaseInfoHelper::getDatabaseVersion();

                    if (!empty($databaseInfo)) {
                        $environmentInfo['runtime']['database']['version'] = $databaseInfo;
                    }

                    $params = [
                        'environment' => $environmentInfo,
                        'time' => $this->getTimeStamp()
                    ];
                    $data = $this->encryptData(json_encode($params));

                    if(empty($data) || $data == false) {
                        $this->errors[] = 'API-Key or Publik-Key missmatch!';
                        $this->status = 401;
                        break;
                    }

                    $this->response['secretInfo'] = base64_encode($data['secretInfo']);
                    $this->response['encryptedData'] = base64_encode($data['encryptedData']);
                    break;
                }

                $this->status = 401;
                break;

            default:
                $this->status = 401;
                break;
        }
    }

    /**
     * authenticate
     *
     * @param $request
     * @return bool
     *
     */
    protected function authenticate($request): bool {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_gsmonitorprovider_domain_model_data');
        $apiKey = $queryBuilder->select('apikey')->from('tx_gsmonitorprovider_domain_model_data')->execute()->fetchColumn(0);

        if(!empty($apiKey)) {
            // check api-key
            $requestHeaders = getallheaders();
            if (!empty($requestHeaders['Api-Key'])) {
                if(!empty($apiKey)) {
                    if($this->checkPassword($requestHeaders['Api-Key'], $apiKey)) {
                        return true;
                    } else {
                        $this->logger->error("ERROR: API-Key wrong!");
                    }
                }
            } else {
                $this->logger->error("ERROR: request->getHeader(api-key) is a empty array.");
            }
        }

        $this->logger->error("ERROR: authenticate - no DB entry!");

        return false;
    }

    /**
     * checkToken - check / validate given Token
     *
     * @param $request
     * @return boolean
     */
    protected function checkToken($request): bool {

        // check if jwt-token is given
        $requestHeaders = getallheaders();

        if (!empty($requestHeaders['Authorization'])) {
            $token = $requestHeaders['Authorization'];
            if(strpos($token, 'Bearer ') === 0) {
                // Validate Token
                $rawToken = end(explode('Bearer ', $token));

                return Token::validate($rawToken, $this->secret['value']);
            }
        }

        // TODO: Log if no Authorization header is given

        return false;
    }

    /**
     * generateToken - generate jwt token
     *
     * @return string
     */
    protected function generateToken(): string {
        $this->payload = [
            'iss' => $this->extensionConfiguration['jwt.iss']['value'],
            'aud' => 'http://client.com',
            'iat' => time(),
            // 'nbf' => time(),
            'exp' => time() + 600,
            'data' => [ // custom values
                'id' => 11,
                'email' => 'test@test.de'
            ]
        ];
        try {
            $token = Token::customPayload($this->payload, $this->secret['value']);
            return $token;
        } catch (UnexpectedValueException $e) {
            return $e;
        }
    }

    /**
     * encryptData
     *
     * @param mixed $data
     * @return array
     */
    public function encryptData($data): array {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_gsmonitorprovider_domain_model_data');
        $publicKey = $queryBuilder->select('publickey')->from('tx_gsmonitorprovider_domain_model_data')->execute()->fetchColumn(0);

        if(!empty($publicKey)) {
            // Encrypt using the public key
            // Not working for larger strings: https://stackoverflow.com/questions/23013039/openssl-public-encrypt-and-json
            // openssl_public_encrypt($data, $encryptedData, $publicKey);

            $cipher = 'aes-256-cbc';
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);

            // Encrypt password and cipher using the public key
            $randomPassword = $this->randomPassword(20, 1, 'lower_case,upper_case,numbers,special_symbols')[0];
            $dataForHeader = [
                'cipher' => $cipher,
                'password' => $randomPassword,
                'iv' => base64_encode($iv)
            ];
            $cleartext = json_encode($dataForHeader);
            openssl_public_encrypt($cleartext, $secretInfo, $publicKey);

            // Encrpyt the main data
            $encryptedData = openssl_encrypt($data, $cipher, $randomPassword, 0, $iv);

            $returnArray = [
                'secretInfo' => $secretInfo,
                'encryptedData' => $encryptedData
            ];

            return $returnArray;
        }

        return [];
    }

    /**
     * randomPassword
     *
     * @param int $length - password char length
     * @param count $count - how many passwords to generate
     * @param string $characters - multi value of lower_case, upper_case, numbers, special_symbols comma separeted e. g. 'upper_case,numbers'
     * @return array
     */
    function randomPassword(int $length, int $count, $characters): array {
        // define variables used within the function
        $symbols = [];
        $passwords = [];
        $used_symbols = '';
        $pass = '';

        // an array of different character types
        $symbols['lower_case'] = 'abcdefghijklmnopqrstuvwxyz';
        $symbols['upper_case'] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $symbols['numbers'] = '1234567890';
        $symbols['special_symbols'] = '!?~@#-_+<>[]{}';

        $characters = explode(',', $characters); // get characters types to be used for the passsword
        foreach ($characters as $key => $value) {
            $used_symbols .= $symbols[$value]; // build a string with all characters
        }
        $symbols_length = strlen($used_symbols) - 1; //strlen starts from 0 so to get number of characters deduct 1

        for ($p = 0; $p < $count; $p++) {
            $pass = '';
            $i = 0;
            for ($i; $i < $length; $i++) {
                $n = rand(0, $symbols_length); // get a random character from the string with all characters
                $pass .= $used_symbols[$n]; // add the character to the password string
            }
            $passwords[] = $pass;
        }

        return $passwords; // return the generated password
    }

    /**
     * checkPassword
     *
     * @param string $password - clear text password
     * @param string $passwordHash - The stored password hash from database or file
     * @return bool
     */
    public function checkPassword(string $password, string $passwordHash): bool  {

        if (\TYPO3\CMS\Saltedpasswords\Utility\SaltedPasswordsUtility::isUsageEnabled('FE')) {
            $objSalt = \TYPO3\CMS\Saltedpasswords\Salt\SaltFactory::getSaltingInstance($passwordHash);
            if (is_object($objSalt)) {
                if ($objSalt->checkPassword($password, $passwordHash)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns current timestamp and timezone setting
     * Example:
     *
     * @return string
     */
    public function getTimeStamp() {
        $date = new \DateTime();
        return $date->getTimestamp();
    }

    /**
     * @param array|null $configuration
     * @param int $statusCode
     * @return Response
     */
    public function createJsonResponse($content): Response {
        $response = (new Response())
            ->withStatus($this->status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');

        if (!empty($content)) {
            $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
            $response->getBody()->write(json_encode($content ?: null, $options));
            $response->getBody()->rewind();
        }

        return $response;
    }
}
