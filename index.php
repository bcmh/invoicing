<?php

require __DIR__ . '/vendor/autoload.php';

use Psr7Middlewares\Middleware\TrailingSlash;
use XeroPHP\Application\PublicApplication;
use XeroPHP\Remote\Request;
use XeroPHP\Remote\URL;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

define('XERO_CONSUMER_KEY', getenv('XERO_CONSUMER_KEY'));
define('XERO_CONSUMER_SECRET', getenv('XERO_CONSUMER_SECRET'));
define('XERO_CALLBACK_URI', getenv('XERO_CALLBACK_URI'));

session_start();

$app = new Slim\App([
    'settings' => [
        'displayErrorDetails' => getenv('SLIM_DISPLAY_ERROR_DETAILS') === "true"
    ]
]);

// Dependencies
$container = $app->getContainer();

$container['view'] = function ($container) {
    $view = new Slim\Views\Twig('views', []);

    $filters = [
        'md' => function ($str) {
            return (new League\CommonMark\CommonMarkConverter)->convertToHtml($str);
        },
        'br' => function ($str) {
            return strlen($str) <= 1 ? $str : $str . '<br/>';
        },
        'nl2br' => function ($str) {
            return nl2br($str);
        },
        'money' => function ($str) {
            return '&pound;' . number_format((float)$str, 2, '.', ',');
        },
        'count' => function ($arr) {
            return count($arr);
        },
        'date' => function ($str) {
            $parts = explode('-', $str);
            return implode('/', $parts);
        }
    ];

    foreach ($filters as $key => $callback) {
        $view->getEnvironment()->addFilter(new Twig_SimpleFilter($key, $callback));
    }

    foreach ([
        "COMPANY_VAT_NUMBER",
        "COMPANY_ACCOUNT_NAME",
        "COMPANY_ACCOUNT_NUMBER",
        "COMPANY_SORT_CODE",
        "COMPANY_IBAN",
        "COMPANY_SWIFT"
    ] as $global ) {
        $view->getEnvironment()->addGlobal( strtolower($global), getenv( $global ) );
    }


    return $view;
};

$container['xero'] = function ($container) {

    return new PublicApplication([
        'oauth' => [
            'callback'          => XERO_CALLBACK_URI,
            'consumer_key'      => XERO_CONSUMER_KEY,
            'consumer_secret'   => XERO_CONSUMER_SECRET
        ],
        'curl' => [
            CURLOPT_CAINFO => __DIR__ .'/certs/ca-bundle.crt',
        ],
    ]);
};

$container['db'] = function ($container) {
    $pdo = new PDO('sqlite:data/db.sqlite');

    // Create the database
    $pdo->exec("CREATE TABLE Tokens (Id INTEGER PRIMARY KEY, accessToken TEXT, expires NUMERIC, refreshToken TEXT)");

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
};

// Middleware
$app->add(new TrailingSlash(true));

$app->add(function($req, $res, $next) {

    if (null === getOAuthSession() && "/login" !== $req->getUri()->getPath()) {
        return $res->withStatus(302)->withHeader('Location', '/login');
    }

    if ("/login" === $req->getUri()->getPath()) {
        session_unset();
        return $next($req, $res);
    }

    $oauth_session = getOAuthSession();

    try {
        $this->xero->getOAuthClient()
                    ->setToken($oauth_session['token'])
                    ->setTokenSecret($oauth_session['token_secret']);
    } catch (Error $e) {
        die();
    }

    return $next($req, $res);
});

// Routes
$app->get('/', function ($req, $res, $args) {

    if (isset($_REQUEST['oauth_verifier'])) {
        $this->xero->getOAuthClient()->setVerifier($_REQUEST['oauth_verifier']);

        $url = new URL($this->xero, URL::OAUTH_ACCESS_TOKEN);
        $req = new Request($this->xero, $url);

        $req->send();
        $oauth_response = $req->getResponse()->getOAuthResponse();

        setOAuthSession(
            $oauth_response['oauth_token'],
            $oauth_response['oauth_token_secret'],
            $oauth_response['oauth_expires_in']
        );

        $uri_parts = explode('?', $_SERVER['REQUEST_URI']);

        // Demo only
        header(
            sprintf(
                'Location: http%s://%s%s',
                (isset($_SERVER['HTTPS']) ? 's' : '' ),
                $_SERVER['HTTP_HOST'],
                $uri_parts[0]
            )
        );
        exit;
    }

    $xero_invoices = $this->xero->load('Accounting\\Invoice')->execute();

    $invoices = [];

    foreach ($xero_invoices as $inv) {
        array_push($invoices, new \BCMH\InvoiceObject($inv));
    }

    return $this->view->render($res, 'index.html', [
        'invoices' => $invoices
    ]);
});

$app->get('/login/', function ($req, $res, $args) {


    $url = new Url($this->xero, URL::OAUTH_REQUEST_TOKEN);
    $request = new Request($this->xero, $url);

    try {
        $request->send();
    } catch (Exception $e) {
        print_r($e);
        if ($request->getResponse()) {
            print_r($request->getResponse()->getOAuthResponse());
        }
    }

    $oauth_response = $request->getResponse()->getOAuthResponse();

    setOAuthSession(
        $oauth_response['oauth_token'],
        $oauth_response['oauth_token_secret']
    );

    return $this->view->render($res, 'login.html', [
        'authorizationUrl' => $this->xero->getAuthorizeURL($oauth_response['oauth_token'])
    ]);
});

$app->get('/invoice/[{id}/]', function ($req, $res, $args) {

    $oauth_session = getOAuthSession();

    $this->xero->getOAuthClient()
               ->setToken($oauth_session['token'])
               ->setTokenSecret($oauth_session['token_secret']);

    $invoice = $this->xero->loadByGUID('Accounting\\Invoice', $args['id']);
    //$company = (new Company($this->freeagent))->getGeneralCompanyInformation()->toArray();

    return $this->view->render($res, 'invoice.html', [
        'invoice' => (new BCMH\InvoiceObject($invoice)),
        'company' => (new BCMH\Company())
    ]);
});


$app->run();


/**
 * Utils
 * TODO: In production this application should use a more robust method of storing OAuth tokens
 */
function setOAuthSession($token, $secret, $expires = null)
{
    if ($expires !== null) {
        $expires = time() + intval($expires);
    }

    $_SESSION['oauth'] = [
        'token' => $token,
        'token_secret' => $secret,
        'expires' => $expires
    ];
}

function getOAuthSession()
{
    if (!isset($_SESSION['oauth']) || ( isset($_SESSION['oauth']) && $_SESSION['oauth']['token'] === null ) || ($_SESSION['oauth']['expires'] !== null && $_SESSION['oauth']['expires'] <= time() )) {
        return null;
    }

    return $_SESSION['oauth'];
}
