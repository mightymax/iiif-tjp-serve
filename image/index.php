<?php
header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');
header('Access-Control-Expose-Headers: Content-Length,Content-Range');
header('Content-Type: text/plain');


ini_set('display_errors', 1);
require_once '../IIIF.php';

$basepath = is_dir('/home/mlindeman/') ? '/home/mlindeman/' : null;
try {
    $iiif = IIIF::Factory()
        ->setBasepath($basepath)
        ->parseQueryString(@$_GET['request'])
        ->cache()
        ->sendResponse();
} catch (IIIF_Exception $e) {
    $e->sendError();
}
