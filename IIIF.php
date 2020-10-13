<?php
class IIIF
{
    protected $basePath = '/';
    protected $mode;
    protected $region, $size, $rotation, $quality, $format;
    protected $getpic;
    
    protected $isManifest = false;
    
    const MODE_INFO = 'info';
    const MODE_IMG = 'image';
    const MODE_MANIFEST = 'manifest';
    
    protected $regex_pattern = array(
        'region' => '(full|square|(?:(?:pct\:)?\d+(?:\.\d+)?\,){3}\d+(?:\.\d+)?)\/',
        'size' => '(\^?max|\^?\d+\,|\^?\,\d+|\^?pct\:\d+|\^?!?\d+\,\d+)\/',
        'rotation' => '(!?\d+(?:\.\d+)?)\/',
        'quality' => '(default|color|gray|bitonal|native)\.',
        'format' => '(jpg|png)'
    );

    public function __construct()
    {
    }
    
    public static function Factory () {
        return new self();
    }
    
    public function setBasepath($basePath)
    {
        $this->basePath = rtrim($basePath, '/') . '/';
        return $this;
    }
    
    public function parseQueryString($queryString)
    {
        if (!$queryString) throw new IIIF_Exception("Empty queryString");
        
        if ($this->mode === self::MODE_MANIFEST) {
            return $this->setFile($queryString);
        }
        
        $matches = array();
        // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
        // {scheme}://{server}{/prefix}/{identifier}/info.json
        $pattern = '/\/'.implode($this->regex_pattern, '') . '/';
        if (preg_match('/(.+)\/info.json$/', $queryString, $matches)) {
            $this->setMode(self::MODE_INFO)->setFile($matches[1]);
        } elseif (preg_match($pattern, $queryString, $matches)) {
            list(,$region, $size, $rotation, $quality, $format) = $matches;
            $this->setFile(implode('/', explode('/', $queryString, -1 * (count($matches)-2))));
            $this->setMode(self::MODE_IMG)->setRegion($region)->setSize($size)->setRotation($rotation)->setQuality($quality)->setFormat($format);
        } else {
            throw new IIIF_Exception("Wrong queryString: {$queryString}\npattern: {$pattern}");
        }
        return $this;
    }
    
    public function setFile($file)
    {
        $this->file = $file;
        $this->getpic = new IIIF_Getpic($this->basePath . ltrim($file, '/'));
        return $this;
    }
    
    public function getFile($fullPath = true)
    {
        if (!$this->getpic && true === $fullPath) return;
        
        return false === $fullPath ? $this->file : $this->getpic->getFile();
    }
    
    protected function setParam ($key, $val)
    {
        if (!isset($this->regex_pattern[$key])) throw new IIIF_Exception("Unkown parameter: {$key}");
        $pattern = rtrim($this->regex_pattern[$key], '\/.');
        if (!preg_match('/^'.$pattern.'$/', $val)) {
            throw new IIIF_Exception("Wrong pattern for {$key}: {$val}");
        }
        $this->$key = $val;
        return $this;
    }
    
    public function setRegion($region)
    {
        return $this->setParam('region', $region);
    }
    
    public function setSize($size)
    {
        return $this->setParam('size', $size);
    }
    
    public function setRotation($rotation)
    {
        return $this->setParam('rotation', $rotation);
    }
    
    public function setQuality($quality)
    {
        return $this->setParam('quality', $quality);
    }
    
    public function setFormat($format)
    {
        return $this->setParam('format', $format);
    }
    
    public function setMode($mode)
    {
        if ($mode === self::MODE_IMG || $mode === self::MODE_INFO || $mode === self::MODE_MANIFEST) {
            $this->mode = $mode;
            return $this;
        } else {
            throw new IIIF_Exception("Unkown mode: {$mode}");
        }
    }
    
    protected function info()
    {
        $getpic_info =  $this->getpic->info();
        $info= array(
            "@context" => "http://iiif.io/api/image/2/context.json",
            "@id" => "http://home.lindeman.nu/iiif/image/?request=dam-ams/0dbda810-4099-ad94-278b-2e6c3f8f7d62.tjp",
            "protocol" => "http://iiif.io/api/image",
            'profile' => array(
                'supports'  => array("canonicalLinkHeader", "profileLinkHeader", "mirroring", "rotationArbitrary"),
                "qualities" => array("default", "bitonal", "gray", "color"), 
                "formats"   => array("jpg") //, "png", "gif", "webp")
            ),
            'tiles' => $getpic_info->getTiles(),
            'sizes' => $getpic_info->getSizes(),
            "width" => $getpic_info->width,
            "height" => $getpic_info->height
        );
        header('Content-Type: application/ld+json');
        echo json_encode($info);
    }
    
    public function image()
    {
        $size = '(\^?max|\^?\d+\,|\^?\,\d+|\^?pct\:\d+|\^?!?\d+\,\d+)\/';
        $region = '(full|square|(?:(?:pct\:)?\d+(?:\.\d+)?\,){3}\d+(?:\.\d+)?)\/';
        
        $getpic_info =  $this->getpic->info()->setSizes();
        $tiles = $getpic_info->getTiles();
        $sizes = $getpic_info->getSizes(true);
        
        
         if(preg_match('/^(?:(?:pct\:)?\d+(?:\.\d+)?\,){3}\d+(?:\.\d+)?$/', $this->region, $match)) {
        
             //HACKED: Only serve tiles
             $regions = explode(',', $this->region);
             list($x, $y, $w, $h) = explode(',', $this->region);
             $x = (int)$x; $y=(int)$y; $w=(int)$w; $h=(int)$h;
             $debugTxt = "x:{$x}\ny:{$y}\nw:$w\nh:$h\nx/tw: " . ($x / $getpic_info->tilewidth);
             $debugTxt .= "\nsize: {$this->size}\n";
             // $x = 0; $y = 0; $w = 272; $h= 204;
     
             // echo("x, y, w, h = $x, $y, $w, $h\n");
             if ($w % $getpic_info->tilewidth) {
                 $w = $h;
             }
             if ($h % $getpic_info->tileheight) {
                 $h = $w;
             }

             $l = 0;
             for ($i = 0 ; $i < $getpic_info->layers; $i++) {
                 $l = $i+1;
                 $scale = pow($getpic_info->ratio, $l);
                 // echo "$getpic_info->width / $scale <= $w && $getpic_info->height / $scale <= $h\n";
         
                 if ($getpic_info->width / $scale <= $w && $getpic_info->height / $scale <= $h) {
                     break;
                 }
             }
             $l = $l+1;
             if ($l > $getpic_info->layers) $l = $getpic_info->layers;
             $layer = @$sizes[$l];
     
     
             $scale = pow($getpic_info->ratio, $getpic_info->layers - $l);
             $x = ($x / $scale);
             $y = ($y / $scale);
             $col = ($x - ($x % $getpic_info->tilewidth)) / $getpic_info->tilewidth ;
             $row = ($y - ($y % $getpic_info->tileheight)) / $getpic_info->tileheight;
     
             $offset = ($row) * @$layer->cols + $col;
             $tile = @$layer->starttile + $offset;
     
             if (1) {
                 header("Content-Type: image/jpeg");
                 echo $this->getpic->getTile($tile, false);
             } else {
                 header("Content-Type: image/jpeg");
                 $im = new Imagick();
                 $im->newImage(256, 256, '#000');
                 $im->BorderImage(new ImagickPixel("red") , 2,2);
                 $text_draw = new ImagickDraw();
                 $text_draw->setFontSize( 10 );
                 $text_draw->setFillColor('#ffffff');
                 // $text_draw->setGravity( Imagick::ALIGN_CENTER );
                 $im->annotateImage( $text_draw, 10, 20, 0, "C/R {$col}/{$row}\nT{$tile} / L{$l}\nST/O " . (@$layer->starttile) . "/{$offset}\n{$debugTxt}");
                 $im->setImageFormat( "jpeg" );
                 echo $im;
             }
             exit;
             
             //This code is not working anymore:
             $pct = 0 === strpos($this->region, 'pct:');
             list($x, $y, $w, $h) = explode(',', str_replace('pct:', '', $this->region));
             if ($pct) {
                 $x = round(($x/100) * $getpic_info->width);
                 $y = round(($y/100) * $getpic_info->height);
                 $w = round(($w/100) * $getpic_info->width);
                 $h = round(($h/100) * $getpic_info->height);
             }
             if ($w <= 0 || $h <= 0) throw new IIIF_Exception("Requested region’s height or width is zero", 400);
             if ($x > $getpic_info->width || $y > $getpic_info->height) 
                 throw new IIIF_Exception("Requested region is entirely outside the bounds of the reported dimensions", 400);
             $image->cropImage($w, $h, $x, $y);
         } else {
             $layer = 3;
             $image = $this->getpic->getLayer($layer, $this->quality);
             $w = $getpic_info->width;
             $h = $getpic_info->height;
             if ($this->region === 'square') {
                  if ($getpic_info->width > $getpic_info->height) {
                      $x = abs(($getpic_info->width - $getpic_info->height) / 2);
                      $image->cropImage($getpic_info->height, $getpic_info->height, $x, 0);
                  } else {
                      $y = abs(($getpic_info->height - $getpic_info->width) / 2);
                      $image->cropImage($getpic_info->width, $getpic_info->width, 0, $y);
                  }
              }
         }
         
         
         if (preg_match('/^(\^?)(\!?)(\d*)?\,(\d*)?$/', $this->size, $match)) {
             $upscale = $match[1] == '^';
             $bestfit = $match[2] == '!';
             $rw = (int)$match[3];
             $rh = (int)$match[4];
             
             if ($upscale && $bestfit) {
                 throw new IIIF_Exception("Upscaling and Bestfit of regions is not yet implemented.", 501);
             }
             if ($upscale && ($rw > $w || $rh > $h)) {
                 throw new IIIF_Exception("Upscaling of regions is not yet implemented.", 501);
             }
             if ($rw > $w) {
                 throw new IIIF_Exception("The value of w must not be greater than the width of the extracted region.", 400);
             }
             if ($rh > $h) {
                 throw new IIIF_Exception("The value of h must not be greater than the height of the extracted region.", 400);
             }
             $image->adaptiveResizeImage($rw, $rh, $bestfit);
         } elseif (preg_match('/^(\^?)pct:(\d+)$/', $this->size, $match)) {
             $upscale = $match[1] == '^';
             $pct = (int)$match[2];
             if ($upscale && $pct > 100) {
                 throw new IIIF_Exception("Upscaling of regions (pct>100) is not yet implemented.", 501);
             }
             $rw = ($pct/100) * $getpic_info->width;
             $image->adaptiveResizeImage($rw, 0);
         }
         
         if ($this->rotation && $this->rotation != 360) {
             $mirror = 0 === strpos($this->rotation, '!');
             $rotation = (float)ltrim($this->rotation, '!');
             if ($mirror) {
                 $image->flopImage();
             }
             $image->rotateImage(new ImagickPixel('#ffffff'), $rotation);
         }
         
         if ($this->quality == 'gray') {
             $image->setImageType(Imagick::IMGTYPE_GRAYSCALEMATTE);
         } elseif ($this->quality == 'bitonal') {
             $image->setImageType(Imagick::IMGTYPE_BILEVEL);
         }
        header("Content-Type: image/jpeg");
        echo $image;
        exit;
    }
    
    public function manifest()
    {
        $getpic_info =  $this->getpic->info();
        $baseUrl = 'http' . ($_SERVER['SERVER_PORT'] == 80 ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['DOCUMENT_URI'];
        $iiifUrl = 'http' . ($_SERVER['SERVER_PORT'] == 80 ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . str_replace('/manifest', '/image', $_SERVER['DOCUMENT_URI']);
        $iiifUrl .= '?request=' . $this->file;
        $id = $baseUrl . '/' . md5($iiifUrl);
        $manifest = [
          "@context" => "http://iiif.io/api/presentation/3/context.json",
          "@id" => $id,
          "@type" => "sc:Manifest",
          "thumbnail" => "{$iiifUrl}/full/250,/0/default.jpg",
          "sequences" => [
              [
              "@context" => "http://iiif.io/api/presentation/3/context.json",
              "@id" => "{$id}/sequence/normal",
              "@type" => "sc:Sequence",
              "label" => "Img 1",
              "viewingHint" => "individuals",
              "canvases" => [
                  [
                  "@id" => "{$id}/canvas",
                  "@type" => "sc:Canvas",
                  "label" => "Img 1",
                  "width" => $getpic_info->width,
                  "height" => $getpic_info->height,
                  "images" => [
                    [
                      "resource" => [
                        "service" => [
                          "@id" => $iiifUrl,
                          "@context" => "http://iiif.io/api/image/3/context.json",
                          "profile" => "http://iiif.io/api/image/3/level0.json"
                        ],
                        "format" => "image/jpeg",
                        "height" => $getpic_info->width,
                        "width" => $getpic_info->height,
                        "@id" => "$iiifUrl/full/max/0/default.jpg",
                        "@type" => "dcTypes:Image"
                      ],
                      "on" => "{$id}/canvas",
                      "motivation" => "sc:painting",
                      "@id" => "h{$id}/annotation",
                      "@type" => "oa:Annotation"
                    ]
                  ]
                ]
              ]
            ]
          ]
      ];

        header('Content-Type: application/json');
        echo json_encode($manifest);
    }
    
    public function sendResponse()
    {
        if (!$this->mode) throw new IIIF_Exception("Unkown mode, please set one first");
        if (!$this->getpic) throw new IIIF_Exception("No file set");
        
        if ($this->mode == self::MODE_IMG) {
            if (null === $this->region || null === $this->size || null === $this->rotation || null === $this->quality || null === $this->format) {
                throw new IIIF_Exception("Missing one of the required Image params (region|size|rotation|quality|format)");
            } else {
                $this->image();
            }
        } elseif($this->mode == self::MODE_MANIFEST) {
            $this->manifest();
        } else {
            $this->info();
        }
        
    }
    
    public function cache()
    {
        $file = $this->getFile();
        $timestamp = filemtime($file);
        $tsstring = gmdate('D, d M Y H:i:s ', $timestamp) . 'GMT';
        $etag = md5($file.$timestamp);

        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : false;
        if ((($if_none_match && $if_none_match == $etag) || (!$if_none_match)) &&
            ($if_modified_since && $if_modified_since == $tsstring))
        {
            header('HTTP/1.1 304 Not Modified');
            exit();
        }
        else
        {
            header("Last-Modified: $tsstring");
            header("ETag: \"{$etag}\"");
        }
        return $this;
    }
}

class IIIF_Getpic
{
    protected $file, $info;
    public static $getpic_bin, $sizes_bin;
    
    public function __construct($file = null)
    {
        if (null === self::$getpic_bin) {
            $path = `which getpic`;
            $this->setGepticBin($path ? trim($path) : __DIR__ .'/bin/getpic');
        }

        if (null === self::$sizes_bin) {
            $path = `which sizes`;
            $this->setSizesBin($path ? trim($path) :__DIR__ .'/bin/sizes');
        }

        if ($file) {
            $this->setFile($file);
        }
    }
    
    public function getTile($tile)
    {
        $output = array();
        $err = array();
        $cmd = self::$getpic_bin . ' ' . intval($tile) . ' ' . escapeshellarg($this->file);
        ob_start();
        passthru($cmd);
        $blob = ob_get_contents();
        ob_end_clean();
        if (!$blob) throw new IIIF_Exception("getpic cmd `{$cmd}` failed", 500);

        $tile = new Imagick();
        $tile->readImageBlob($blob);
        return $tile;
    }
    
    public function getLayer($layer)
    {
        $size = $this->info()->setSizes()->sizes[$layer];
        
        $image = new Imagick();
        $image->newImage($size->width, $size->height, 'none');
        
        $tileNo = $size->starttile;
        for ($row = 0; $row < $size->rows; $row++) {
            for ($col = 0; $col < $size->cols; $col ++) {
                $tile = $this->getTile($tileNo);
                $image->compositeImage($tile, imagick::COMPOSITE_COPY, $col * $this->info()->tilewidth, $row * $this->info()->tileheight);
                $tileNo ++;
            }
        }
        $image->setImageFormat ("jpeg");
        return $image;
    }
    
    public function setFile($file)
    {
        if (!file_exists($file)) {
            throw new IIIF_Exception("File `{$file}` not found", 404);
        }
        
        if (!is_file($file)) {
            throw new IIIF_Exception("File `{$file}` found but that ain't no file", 404);
        }
        
        if (!is_readable($file)) {
            throw new IIIF_Exception("File `{$file}` not readable", 404);
        }
        
        $this->info = null;
        $this->file = $file;
        $this->info();
    }
    
    public function getFile()
    {
        return $this->file;
    }
    
    public function setGepticBin($fullPathToGetpic)
    {
        if (file_exists($fullPathToGetpic) && is_executable($fullPathToGetpic)) {
            self::$getpic_bin = escapeshellarg($fullPathToGetpic);
            return $this;
        }
        throw new IIIF_Exception("getpic executable `{$fullPathToGetpic}` not found or non executable");
    }
    
    public function setSizesBin($fullPathToSizes)
    {
        if (file_exists($fullPathToSizes) && is_executable($fullPathToSizes)) {
            self::$sizes_bin = escapeshellarg($fullPathToSizes);
            return $this;
        }
        throw new IIIF_Exception("sizes executable `{$fullPathToSizes}` not found or non executable");
    }
    
    public function info()
    {
        if ($this->info) return $this->info;
        $output = array();
        $err = 0;
        $cmd = self::$getpic_bin. ' -info ' . escapeshellarg($this->file);
        exec($cmd, $output, $err);
        if ($err) {
            throw new IIIF_Exception("getpic cmd `{$cmd}` failed", 500);
        }
        $info = new IIIF_Getpic_info();
        foreach ($output as $val) {
            list($key, $val) = explode('=', $val, 2);
            if (preg_match('/^\d+$/', $val)) $val = (int)$val;
            $info->$key = $val;
        }
        $info->setSizes();
        $this->info = $info;
        return $info;
    }
}

class IIIF_Getpic_info
{
    public $width, $height, $layers, $tilewidth, $tileheight, $ratio, $numfiles, $mimeType = "image/jpeg";
    public $sizes = array();
    
    public function setSizes()
    {
        if (count($this->sizes)) return $this;
        for ($i = 1; $i <= $this->layers; $i++) {
            $err = 0;
            $output = array();
            $cmd = IIIF_Getpic::$sizes_bin . " -layer {$i} -wh {$this->width} {$this->height} {$this->tilewidth} {$this->tileheight} {$this->layers} {$this->ratio}";
            exec($cmd, $output, $err);
            if ($err) {
                throw new IIIF_Exception("sizes cmd (`{$cmd}`) failed", 500);
            }
            $size = (object)array(
                'layer' => $i,
                'starttile' => null, 
                'cols' => null, 
                'rows' => null
            );
            list($size->starttile, $size->cols, $size->rows) = explode(' ', $output[0]);
            $size->starttile = (int)$size->starttile;
            $size->cols = (int)$size->cols;
            $size->rows = (int)$size->rows;
            $scale = pow($this->ratio, $this->layers - $i);
            $size->width = floor($this->width / $scale);
            $size->height = floor($this->height / $scale);
            $this->sizes[$i] = $size; 
        }
        return $this;
    }
    
    public function getSizes($native = false)
    {
        if ($native) return $this->sizes;
        $sizes = array();
        foreach ($this->sizes as $size) {
            $sizes[] = array('width' => $size->width, 'height' => $size->height);
        }
        return $sizes;
    }
    
    public function getTiles() {
        $scalefactors = array();
        for ($i = 1; $i <= $this->layers; $i++) {
            $scalefactors[] = pow($this->ratio, $i-1);
        }
        return array(
            array(
                // 'type' => 'Tile',
                'width' => $this->tilewidth,
                'height' => $this->tileheight,
                'scaleFactors' => $scalefactors
            )
        );
    }
}

class IIIF_Exception extends Exception {
    
    public function __construct($message = "Bad Request", $code = 400)
    {
        parent::__construct($message, $code);
    }
    
    public function sendError()
    {
        http_response_code($this->getCode());
        header('Content-Type: text/plain');
        echo $this->getMessage();
        exit;
    }
}

if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {

        if ($code !== NULL) {

            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;

        } else {

            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);

        }

        return $code;

    }
}

    
