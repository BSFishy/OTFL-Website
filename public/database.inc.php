<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as Model;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Dflydev\FigCookies\FigResponseCookies as ResCookies;
use Dflydev\FigCookies\SetCookie;

function createDatabase() {
    $capsule = new Capsule;

    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => __DIR__ . '/../otfl.sqlite',
        'prefix' => ''
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
}

class OtflDatabase {

    private $user;
    private $db;
    private static $instance = null;

    public function __construct() {
        $this->db = createDatabase();
        if (OtflDatabase::$instance == null)
            OtflDatabase::$instance = $this;
    }

    public static function instance() {
        if (OtflDatabase::$instance == null) {
            $this->__construct();
        }
        return OtflDatabase::$instance;
    }

    public function getUser() {
        return $this->user;
    }

    public function init() {
        if (isset($_SESSION['id'])) {
            $usr = Capsule::select('SELECT * FROM users WHERE `id`=' . $_SESSION['id']);

            $this->user = new OtflUser($usr[0]->id, $usr[0]->username, $usr[0]->authlvl);
        }
    }

    public function loggedIn() {
        return $this->user != null;
    }

    public function pageInit(Request $request, Response $response) {
        $usernameCookie = ResCookies::get($response, 'username');
        $passwordCookie = ResCookies::get($response, 'password');

        if ($usernameCookie != null && $passwordCookie != null) {
            $login = $this->login($usernameCookie, $passwordCookie, $request, $response, true);

            if (!is_array($login)) {
                return $response;
            } else {
                return false;
            }
        } elseif (($usernameCookie == null && $passwordCookie != null) || ($usernameCookie != null && $passwordCookie == null)) {
            $response = $this->removeCookies($response);
        }

        return $response;
    }

    public function createSidebar($that) {
        $return = array();
        if ($this->loggedIn()) {
            $return[] = '<li><a href="' . $that->router->pathFor('logout') . '">Logout</a></li>';
        } else {
            $return[] = '<li><a href="' . $that->router->pathFor('login') . '">Login</a></li>';
        }

        $returnString = '';
        foreach ($return as $k => $v) {
            $returnString .= $v;
        }

        return $returnString;
    }

    public function login($username, $password, Request $request, Response $response, $remember = false) {
        $errors = array();
        $uname = $username;
        $pass = md5($password);

        if ($remember && $request == null) {
            $errors[] = 'If you want to remember the username and password, you must specify a request.';
        } elseif (!$remember) {
            $response = $this->removeCookies($response);
        } else {
            $usernameCookie = ResCookies::set($response, SetCookie::create('username')
                                    ->withValue($uname)
                                    ->withDomain($_SERVER['HTTP_HOST'])
                                    ->withPath('/')
            );
            $passwordCookie = ResCookies::set($response, SetCookie::create('password')
                                    ->withValue($pass)
                                    ->withDomain($_SERVER['HTTP_HOST'])
                                    ->withPath('/')
            );
        }

        $select = Capsule::select('SELECT * FROM users WHERE `username`=' . OtflModel::mySQLSafe($uname) . ' AND `password`=' . OtflModel::mySQLSafe($pass));

        if (empty($select)) {
            $errors[] = 'Your username or password was incorrect';
            $usernameCookie = ResCookies::get($response, 'username');
            $passwordCookie = ResCookies::get($response, 'password');

            if ($usernameCookie != null && $passwordCookie != null) {
                $response = $this->removeCookies($response);
            }
        } else {
            $_SESSION['id'] = $select[0]->id;
            $response = $response->withHeader('Location', '/home');
        }

        return empty($errors) ? $response : $errors;
    }

    public function removeCookies(Response $response) {
        $response = ResCookies::remove($response, 'username');
        $response = ResCookies::remove($response, 'password');

        return $response;
    }

}

class OtflAuthHandle {

    private $db;
    private $accesslvl;

    public function __construct(OtflDatabase $db, int $accesslvl) {
        $this->db = $db;
        $this->accesslvl = $accesslvl;
    }

    public function __invoke($request, $response, $next) {
        if ($this->db->getUser() == null) {
            return $response->withStatus(503)->withHeader('Location', '/login');
        }

        if ($this->db->getUser()->getAuthlvl() < $this->accesslvl) {
            return $response->withStatus(503)->withHeader('Location', '/503');
        }

        return $next($request, $response);
    }

}

class OtflModel extends Model {

    public function insert(string $tableName, array $data) {
        if (!is_array($data))
            return;

        $count = 0;
        foreach ($data as $key => $val) {
            if ($count == 0) {
                $fields = "`" . $key . "`";
                $values = $val;
            } else {
                $fields .= ", " . "`" . $key . "`";
                $values .= ", " . $val;
            }
            $count++;
        }

        $query = "INSERT INTO " . $tableName . " (" . $fields . ") VALUES (" . $values . ")";
        $ret = Capsule::insert($query);

        if ($ret > 0)
            return true;
        else
            return false;
    }

    public function select(string $query) {
        return Capsule::select($query);
    }

    public static function mySQLSafe($value, $quote = "'") {

        // strip quotes if already in
        $value = str_replace(array("\'", "'"), "&#39;", $value);

        // Stripslashes 
        if (get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }
        $value = OtflModel::mysql_escape_mimic($value);
        $value = $quote . trim($value) . $quote;

        return $value;
    }

    public static function mysql_escape_mimic($inp) {
        if (is_array($inp))
            return array_map(__METHOD__, $inp);

        if (!empty($inp) && is_string($inp)) {
            return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
        }

        return $inp;
    }

}

class OtflUser extends OtflModel {

    private $id;
    private $username;
    private $authlvl;

    public function __construct($id, $username, $authlvl) {
        parent::__construct();
        $this->id = $id;
        $this->username = $username;
        $this->authlvl = $authlvl;
    }

    public static function setupUser(string $username, string $password, $authlvl = 0) {
        if ($this->insert('users', array('username' => $username, 'password' => $password, 'authlvl' => $authlvl))) {
            $usr = $this->select("SELECT * FROM users WHERE `username`=" . $this->mySQLSafe($username));

            return new OtflUser($usr[0]['id'], $usr[0]['username'], $usr[0]['authlvl']);
        } else {
            return false;
        }
    }

    public function getAuthlvl() {
        return $this->authlvl;
    }

}
