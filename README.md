# TJP IIIF server

Framework to deliver IIIF Image API (v2.0 and v3.0) and IIIF Presentaion API (v.2.0)
from TJP images.

##config
If a config.php is present in the root of this folder, it is possible to config:

- basepath: Root filepath to which all requested images ar relative
- maxwidth, maxheight: limit the size of output images (*note*: defining both results in square images)

config.php should loke like this:

``<?php
return [
    'basepath' => '/home/mlindeman',
    'maxwidth' => 800
];``
