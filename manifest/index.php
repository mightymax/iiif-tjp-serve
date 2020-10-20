<?php
header('Content-Type: text/plain');

ini_set('display_errors', 1);
require_once '../IIIF.php';

try {
    $iiif = IIIF::Factory()
        ->setMode(IIIF::MODE_MANIFEST)
        ->parseQueryString(@$_GET['request'])
        ->cache()
        ->sendResponse();
} catch (IIIF_Exception $e) {
    $e->sendError();
}

return;
?>

SELECT dam_file.folder, dam_storage.filepath ||  '/' || dam_fileversion.dsn || dam_fileversion.filename
FROM ams.dam_fileversion 
JOIN ams.dam_file ON dam_file.uuid=dam_fileversion.file
JOIN ams.dam_storage ON dam_file.storage=dam_storage.uuid
WHERE file IN
(
	SELECT file 
	FROM ams.col_media
	WHERE col_entiteit = '09c7ff50-70a6-11e4-a16c-d31c81183655'
)
LIMIT 10

http://lab.picturae.pro/rkd/tjp-iiif/image/?request=/klanten/ams/ams_mrx_bld/topview/01/ams/19/CD_010097_4618_TIF//010097011665.tjp/full/255,/0/default.jpg
http://lab.picturae.pro/rkd/tjp-iiif/image/?request=/klanten/ams/ams_mrx_bld/topview/01/ams/19/CD_010097_4618_TIF/010097011672.tjp/info.json