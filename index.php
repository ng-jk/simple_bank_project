<?php 
print_r("test");
exit;
require_once 'vendor/autoload.php';

// database
$mysqli = new mysqli("sql207.infinityfree.com", "if0_40197402", "Zk4Ivol6RiynT", "if0_40197402_system_database");

// https request handler
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];
$request_host = $_SERVER['HTTP_HOST'];

// middleware

// middleware here for database connection
include "backend/service/database_mysql_service.php"; // basic database class
include "backend/service/database.php"; // database dataclass
$database = new database("sql207.infinityfree.com", "if0_40197402","Zk4Ivol6RiynT", "if0_40197402_system_database");
$database_mysql_service = new database_mysql_service($database);
$result = $database_mysql_service->middleware_check();
if($result){
    $mysqli = $database_mysql_service->get_database_conn(); // setup global variable mysqli
}

// middleware here for config
include "backend/service/database_mysql_service.php"; // basic database class
include "backend/service/database.php"; // database dataclass
$database = new database("sql207.infinityfree.com", "if0_40197402","Zk4Ivol6RiynT", "if0_40197402_system_database");
$database_mysql_service = new database_mysql_service($database);
$result = $database_mysql_service->middleware_check();
if($result){
    $mysqli = $database_mysql_service->get_database_conn(); // setup global variable mysqli
}

// middleware here to initialize jwt token
include "backend/service/jwt.php"; // basic jwt class
include "backend/service/jwt_verify_service.php"; // jwt dataclass
$jwt = new jwt("sql207.infinityfree.com", "if0_40197402","Zk4Ivol6RiynT", "if0_40197402_system_database");
$database_mysql_service = new database_mysql_service($database);
$result = $database_mysql_service->middleware_check();
if($result){
    $mysqli = $database_mysql_service->get_database_conn(); // setup global variable mysqli
}

// second middleware for status init and check login 
include "backend/service/status_basic_service.php"; // basic status class
include "backend/service/status.php"; // status dataclass
$status = new status($permission, $is_login,$request_uri, $request_method, $user_info);
$status_basic_serivce = new status_basic_service($status);
$result = $status_basic_service->middleware_check();
if($result){
    $status = $status_basic_service->get_status(); // setup global variable status
}


// third middleware here for new CSRF and JWT

// router
include "backend/router.php";

?>