<?php
declare(strict_types=1);
namespace GroundStack\GsMonitorProvider\Middleware;

// mb_internal_encoding("UTF-8");

use \TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use UnexpectedValueException;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Server\MiddlewareInterface;
use \Psr\Http\Server\RequestHandlerInterface;
use \TYPO3\CMS\Core\Http\Response;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \ReallySimpleJWT\Token;
use GroundStack\GsMonitorProvider\Helpers\EnviromentInfoHelper;

use Exception;

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
     */
    protected $response;

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

    public function __construct(Type $var = null) {
        /** @var $logger \TYPO3\CMS\Core\Log\Logger */
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
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

        if($this->exPath[1] === 'test') {
            $params = json_encode(EnviromentInfoHelper::getVersionData());
            echo $params;
            die();
        }

        // currently you can only use domain.tld/api/v1/access_token (it must be a '/' before the access_token!)
        if($this->exPath[1] === 'gs-monitor-api' && $this->exPath[2] === 'v1' && $this->exPath[3] === 'data') {
            // Remove any output produced until now
            ob_clean();

            // config from ext_conf_template.txt
            $this->extensionConfiguration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('gs_monitor_provider');

            $response = GeneralUtility::makeInstance(Response::class);

            switch ($request->getMethod()) {
                case 'POST':
                    return $this->processPostRequest($request);
                    break;
                default:
                    $response->withStatus(405, 'Method not allowed');
                    return $response;
            }
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
        $response = GeneralUtility::makeInstance(Response::class);
        $this->secret = $this->extensionConfiguration['jwt']['secret'];

        // check witch Accessmethod to use
        switch ($this->extensionConfiguration['accessMethod']) {
            case '1': // jwt-Token
                // check API-Key
                if ($this->authenticate($request)) {
                    $response = $response
                        ->withHeader('Content-Type', 'application/json; charset=UTF-8')
                        ->withStatus(200)
                        ->withAddedHeader('Authorization', $this->generateToken());
                    return $response;
                }

                // check JWT-Token
                // don't send the api-key again with the token!
                if($this->checkToken($request)) {
                    $params = json_encode(EnviromentInfoHelper::getVersionData());
                    $data = $this->encryptData($params);

                    $response = $response
                        ->withHeader('Content-Type', 'application/json; charset=UTF-8')
                        ->withStatus(200);
                    $responseArray = [
                        'secretInfo' => base64_encode($data['secretInfo']),
                        'encryptedData' => base64_encode($data['encryptedData'])
                        // 'encryptedData' => base64_encode($params)
                    ];

                    $response->getBody()->write(json_encode($responseArray));

                    return $response;
                } else {
                    $response->withStatus(401, 'Authenticate-Token')->withHeader('Authenticate-Token', '');
                    return $response;
                }

                $response->withStatus(401, 'WWW-Authenticate')->withHeader('WWW-Authenticate', '');

                return $response;
                break;

            default:
                $response->withStatus(401, 'WWW-Authenticate')->withHeader('WWW-Authenticate', '');
                return $response;
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
    protected function authenticate(ServerRequestInterface $request):bool {
        // check api-key
        if (is_array($request->getHeader('api-key'))) {
            foreach ($request->getHeader('api-key') as $key => $value) {
                if($value === $this->extensionConfiguration['jwt']['apiKey']) {
                    return true;
                }
            }
        } else {
            // $this->logger->error("ERROR: request->getHeader(api-key) is a empty array.");
        }

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
        if (is_array($request->getHeader('Authorization'))) {
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

        $public_key = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['gs_monitor_provider']['publicKey'];

        if(!empty($public_key)) {
            // Encrypt using the public key
            // Not working for larger strings: https://stackoverflow.com/questions/23013039/openssl-public-encrypt-and-json
            // openssl_public_encrypt($data, $encryptedData, $public_key);
            $publicKeyId = openssl_pkey_get_public($public_key);
            $encKeys = [];

            $cipher = 'aes-256-cbc';
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);

            // Encrypt password and cipher using the public key
            $randomPassword = $this->randomPassword();
            $dataForHeader = [
                'cipher' => $cipher,
                'password' => $randomPassword,
                'iv' => base64_encode($iv)
            ];
            $cleartext = json_encode($dataForHeader);
            openssl_public_encrypt($cleartext, $secretInfo, $public_key);

            // Encrpyt the main data
            $encryptedData = openssl_encrypt($data, $cipher, $randomPassword, 0, $iv);

            $returnArray = [
                'secretInfo' => $secretInfo,
                'encryptedData' => $encryptedData
            ];

            return $returnArray;
        }

        return false;
    }

    public function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 16; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}
