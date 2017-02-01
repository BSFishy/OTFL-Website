<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as Model;

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

    public function __construct() {
        $this->db = createDatabase();
    }
    
    public function __0construct(Capsule $db) {
        $this->db = $db;
    }

    public function getUser() {
        return $this->user;
    }

    public function init() {
        if(isset($_SESSION['id'])){
            $usr = Capsule::select('SELECT * FROM users WHERE `id`=' . $_SESSION['id']);
            
            $this->user = new OtflUser($usr[0]['id'], $usr[0]['username'], $usr[0]['authlvl']);
        }
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

        $query = "INSERT INTO " . $tablename . " (" . $fields . ") VALUES (" . $values . ")";
        $ret = Capsule::insert($query);

        if ($ret > 0)
            return true;
        else
            return false;
    }

    public function select(string $query) {
        return Capsule::select($query);
    }

    function mySQLSafe($value, $quote = "'") {

        // strip quotes if already in
        $value = str_replace(array("\'", "'"), "&#39;", $value);

        // Stripslashes 
        if (get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }
        // Quote value
        if (version_compare(phpversion(), "4.3.0") == "-1") {
            $value = $this->db->escape_string($value);
        } else {
            $value = $this->db->real_escape_string($value);
        }
        $value = $quote . trim($value) . $quote;

        return $value;
    }

}

class OtflUser extends OtflModel {

    private $id;
    private $username;
    private $authlvl;

    public function __construct(int $id, string $username, int $authlvl) {
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
