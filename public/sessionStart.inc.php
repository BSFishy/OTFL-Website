<?php

$host2 = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == 'localhost:8000' ? 'C:\Users\Matt\www\OTFL Website' : __DIR__);

$sessionName = "SID" . md5('/');
if ($_SERVER['HTTP_HOST'] <> 'localhost')
    @ini_set("session.save_path", $host2 . '\sessions');
else
    @ini_set("session.save_path", $host2 . '\sessions');
$sessionDomain = "/";

session_name($sessionName);
@ini_set("session.cookie_path", $sessionDomain);
session_start();