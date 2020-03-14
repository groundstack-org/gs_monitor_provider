<?php
declare(strict_types=1);
namespace GroundStack\GsMonitorProvider\Middleware;

use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;

use \UnexpectedValueException;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Server\MiddlewareInterface;
use \Psr\Http\Server\RequestHandlerInterface;
use \TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;

use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \ReallySimpleJWT\Token;
use GroundStack\GsMonitorProvider\Helpers\EnviromentInfoHelper;
use GroundStack\GsMonitorProvider\Domain\Repository\DataRepository;

/**
 * MonitoringMiddleware
 *
 * EXAMPLE:
 * GET or POST - http://www.domain.tld/api/v2/?access_token=testing
 * POST - http://www.domain.tld/api/v2 --- ! set http 'api-key' / 'Bearer authorization' header
 */
class MonitoringMiddleware implements MiddlewareInterface {

    /**
     * response
     *
     * @var Response
     */
    protected $response = [];

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
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $path = $request->getUri()->getPath();
        $this->exPath = explode('/', $path);

        // if($this->exPath[1] === 'TESTINGS') {
        if($this->exPath[1] === 'gs-monitor-api' && $this->exPath[2] === 'v1' && $this->exPath[3] === 'getData') {
            // Remove any output produced until now
            ob_clean();

            /** @var $logger \TYPO3\CMS\Core\Log\Logger */
            $this->logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);

            // config from ext_conf_template.txt
            $this->extensionConfiguration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('gs_monitor_provider');

            $objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
            $this->dataRepository = $objectManager->get('GroundStack\\GsMonitorProvider\\Domain\\Repository\\DataRepository');

            $this->secret = $this->extensionConfiguration['jwt']['secret'];

            // HtmlResponse($content, $status = 200, array $headers = [])
            // JsonResponse($data = [], $status = 200, array $headers = [], $encodingOptions)
            // return new JsonResponse(['test' => 'testing']);

            switch ($request->getMethod()) {
                case 'POST':
                    $this->headers['Content-Type'] = 'application/json; charset=UTF-8';
                    $this->processPostRequest($request);
                    break;
                default:
                    return new HtmlResponse('Method not allowed', 405);
                    break;
            }

            if(empty($this->errors)) {
                return new JsonResponse($this->response, $this->status, $this->headers);
            }

            return new JsonResponse(json_encode($this->errors), $this->status, $this->headers);
        }

        return $handler->handle($request);
    }

    /**
     * processPostRequest
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    public function processPostRequest(ServerRequestInterface $request) {
        switch ($this->extensionConfiguration['accessMethod']) {
            case '1': // jwt-Token

                // check API-Key
                if ($this->authenticate($request)) {
                    $this->headers['Authorization'] = $this->generateToken();
                    break;
                }

                // check JWT-Token
                // don't send the api-key again with the token!
                if($this->checkToken($request)) {
                    $params = json_encode(EnviromentInfoHelper::getVersionData());
                    $data = $this->encryptData($params);

                    if(empty($data) || $data == false) {
                        $this->errors[] = 'API-Key or Publik-Key missmatch!';
                        $this->status = 401;
                        $this->status = 200;
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
     * @param ServerRequestInterface $request
     * @return bool
     *
     */
    protected function authenticate(ServerRequestInterface $request): bool {
        $data = $this->dataRepository->findAll()->getFirst();
        if(!empty($data)) {
            // check api-key
            if (!empty($request->getHeader('api-key')[0])) {
                if($this->checkPassword($request->getHeader('api-key')[0], $data->getApiKey())) {
                    return true;
                } else {
                    $this->logger->error("ERROR: API-Key wrong!");
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
     * @param ServerRequestInterface $request
     * @return boolean
     */
    protected function checkToken(ServerRequestInterface $request): bool {
        // check if jwt-token is given
        if (!empty($request->getHeader('Authorization')[0])) {
            $token = $request->getHeader('Authorization')[0];

            if(strpos($token, 'Bearer ') === 0) {
                // Validate Token
                $rawToken = end(explode('Bearer ', $token));
                // throw new Exception('Throwable TEST01 --- '. $rawToken);

                $this->logger->error(
                    'MonitoringMiddleware - processPostRequest()',
                    [
                        'jwt Token Validate' => Token::validate($rawToken, $this->secret)
                    ]
                );

                return Token::validate($rawToken, $this->secret);
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
            'iss' => $this->extensionConfiguration['jwt']['iss'],
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
            $token = Token::customPayload($this->payload, $this->secret);

            return $token;
        } catch (UnexpectedValueException $e) {
            return $e;
        }
    }

    /**
     * checkAccessToken - url params 'access_token='
     *
     * @param ServerRequestInterface $request
     * @return boolean
     */
    protected function checkAccessToken(ServerRequestInterface $request): bool {
        if (isset($_REQUEST['access_token'])) {
            if ($this->extensionConfiguration['accessToken'] === $_REQUEST['access_token']) {
                return true;
            }
        }

        return false;
    }

    /**
     * encryptData
     *
     * @param mixed $data
     * @return array
     */
    public function encryptData($data): array {
        $DBdata = $this->dataRepository->findAll()->getFirst();
        $publicKey = $DBdata->getPublickey();

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
        $hashFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory::class);

        try {
            $hashInstance = $hashFactory->get($passwordHash, 'FE');
            $validPassword = $hashInstance->checkPassword($password, $passwordHash);
            return $validPassword;
        } catch (InvalidPasswordHashException $invalidPasswordHashException) {
            // Given hash in global configuration is not a valid salted password
            $this->logger->error('gs_monitor_provider: Given hash in global configuration is not a valid salted password');
        }

        return false;
    }
}
