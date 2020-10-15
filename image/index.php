<?php
header('Content-Type: text/plain');

ini_set('display_errors', 1);
require_once '../IIIF.php';

try {
    $iiif = IIIF::Factory()
        ->debug(isset($_GET['debug']))
        ->parseQueryString(@$_GET['request'])
        ->cache()
        ->sendResponse();
} catch (IIIF_Exception $e) {
    $e->sendError();
}
