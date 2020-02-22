<?php

include_once "vendor/autoload.php";

use App\RestApi;

$projects = get_projects_from_post();

$rest = new RestApi($projects);

$rest->send_all();


echo PHP_EOL."Good".PHP_EOL;

function get_projects_from_post(){ 
    if ($_SERVER["REQUEST_METHOD"] != "POST"){
        include "app/page_404.php";
    }
    
    $json = file_get_contents('php://input');
    $json_dec = json_decode($json, true);
    
    if (!array_key_exists("projects", $json_dec)){
        include "app/page_404.php";
    }
    
    $projects = $json_dec["projects"];
    
    if(is_null($projects)){
        include "app/page_404.php";
    }
    return $projects;
}