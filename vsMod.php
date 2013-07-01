<?php
/** ************************************************************************************************
  | Software Name        : VideoSwiper plugin
  | Version              : Wordpress r13.06.20
  | Software Author      : VideoSwiper  Team
  | Website              : http://www.videoswiper.com
  | E-mail               : support@videoswiper.com
  |**************************************************************************************************
  |
  |**************************************************************************************************
  | Please upload/place the file in the root folder where your script is installed and setup your
  | account at videoswiper.com .
  |**************************************************************************************************
  | Copyright (c) 2009-2013 videoswiper.com. All rights reserved.
  |************************************************************************************************* */

/* Dynamic Config */

$apiUsers = "";

/* No Editing below this line */

define('_NL',"\n");
define('_TIME',time());
if(function_exists('date_default_timezone_set')) {
    date_default_timezone_set('America/New_York');
}
define('_MYSQL_TIME',date('Y-m-d H:i:s',_TIME));
define('VS','http://www.videoswiper.com/');
define('VSPLUS','http://www.videoswiperplus.com/api2/');

$ffmpeg = '/usr/bin/ffmpeg';
if(!is_file($ffmpeg))$ffmpeg = '/usr/local/bin/ffmpeg';

$flvtool = '/usr/bin/flvtool2';
if(!is_file($flvtool))$flvtool = '/usr/local/bin/flvtool2';

$mp4box = '/usr/bin/MP4Box';
if(!is_file($mp4box))$mp4box = '/usr/local/bin/MP4Box';

$php = '/usr/bin/php';
if(!is_file($php))$php = '/usr/local/bin/php';


class vsmod
{
    var $lastError = false;
    var $lastQuery = false;
    var $dbLink    = false;
    var $post = array();
    var $action = false;
    var $server = false;
    var $database = false;
    var $metaMap = array();

    /* Script specefic settings */

    var $cConfig = "includes/config.php";
    var $cPaths = array();
    var $cDataBase = array('host'=>'host','username'=>'username','password'=>'password','database'=>'database','prefix'=>'','extract'=>'');    
    
    var $cPrefix = '';

    var $cUserField = 'id';
    var $cUserTable = 'users';
    var $cUserConditions = "username = %s AND password = %s";

    var $cCategoryFields = 'id,name';
    var $cCategoryTable = 'categories';
    var $cCategoryConditions = "";

    var $cVideoEmbed = true;
    var $cVideoDownload = true;
    var $cVideoDownloadHD = true;
    /* video common */

    var $cImageTemp = false;
    var $cImageWidth = 160;
    var $cImageHeight = 120;

    var $cVideoField = 'id';
    var $cVideoFile = false;
    var $cVideoFilePath = false;
    var $cMp4File = false;
    var $cMp4FilePath = false;
    var $cMp4Only     = false;
    var $cVideoTable = "videos";
    var $cVideoVarMap = false; //  assc array
    var $cVideoUpdateVarMap = false;
    var $cVideoThumbs = false; // Default thumbs array

    var $cSeprator = '-';

    /* server */

    var $cServerField = 'id';
    var $cServerTable = 'servers';
    var $cServerConditions = "";
    var $cServerVarMap = false;
    var $cServerUpdateVarMap = false;

    /* constructor */

    function vsmod()
    {
        
        // get post variables
        foreach ($_POST as $var=>$val)
        {
            $this->post[$var] = stripslashes($val);
        }

        // Initialize the database

        $this->initDB();
    }

    /** some common functions */

    function path($relativePath)
    {
        if($relativePath[0] == '/') {
            return dirname(__FILE__).$relativePath;            
        }else {
            return dirname(__FILE__).'/'.$relativePath;
        }
        
        return $relativePath;
    }

    function response($payload,$success = true)
    {

        $xmlFormat = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n<rsp stat=\"%s\">\n%s\n</rsp>";

        if ($success)
        {
            $response = sprintf($xmlFormat,'ok',$payload);
        }
        else
        {
            $response  = sprintf($xmlFormat,'fail','<err msg="'.$payload.'" detail="'.$this->lastError.'" />');
        }

        header("Content-type: application/xml"); 
       echo $response;
        die();
    }

    function getField($field,$table,$conditions) {

        $result = $this->query("SELECT $field FROM {$this->cPrefix}$table WHERE $conditions");

        $rowCount = mysql_num_rows($result);

        if ($rowCount) {
            $row = mysql_fetch_array($result);
            return $row[$field];
        }

        return false;

    }

    function valReplace($input)
    {
        extract($this->post);

        if (preg_match_all('%{\$([a-zA-Z0-9_.]+)}%',$input,$omatches))
        {
            $matches = array_unique($omatches[1]);

            $replaces = array();
            foreach ($matches as $match)
            {
                $replace = '';
                if(strstr($match,'.'))
                {
                    list($r_array,$r_index) = explode('.',$match);

                    $r_array = $$r_array;
                    $replace = isset($r_array[$r_index])?$r_array[$r_index]:$replace;

                } else {
                    $replace = isset(${$match})?${$match}:$replace;
                }

                $replaces[] = $replace;
            }

            $input = str_replace(array_unique($omatches[0]),$replaces,$input);
        }

        return $input;
    }

    function safeURL($title)
    {
        $title = strtolower($title);
        $title = preg_replace('/[^'.$this->cSeprator.'a-z0-9\s]+/', '', $title);
        $title = preg_replace('/['.$this->cSeprator.'\s]+/', $this->cSeprator, $title);

        return trim($title, $this->cSeprator);
    }

    function safeEncode($string)
    {
        $string = base64_encode($string);
        return str_replace(array('/','+','='),array('_','-',''),$string);
    }

    function safeDecode($string)
    {
    $string = str_replace(array('_','-'),array('/','+'),$string);
    return base64_decode($string);
    }

    function qs($value)
    {
        if (get_magic_quotes_gpc()) {
            $value = stripslashes($value);
        }

        return "'" . mysql_real_escape_string($value) . "'";
    }

    /**
     * Execute a mysql query on established connection
     *
     * @param string $query
     * @return MySql result
     */

    function query($query)
    {
        $this->initDB();
        $this->lastQuery = $query;
        $result = mysql_query($query,$this->dbLink);

        if(!$result){
            $this->response('MySQL Error executing '.$query.' : '.mysql_error(),false);
        }

        return $result;
    }

    function varMap($table,$varMap)
    {
        $qMap = array();

        foreach ($varMap as $var=>$val)
        {
            $qName  =  preg_replace('/[^A-Za-z0-9_]+/', '', $var);
            if($var[0] == '#') {
                $qValue =  $this->qs($this->valReplace($val)); // standard define
            }
            else if($var[0] == '@') {
                $qValue =  $this->valReplace($val);
            }
            else {
                $qValue =  $this->qs($this->post[$val]);
            }
            $qMap[$qName] = $qValue;
        }

       $result = $this->query("SHOW COLUMNS FROM ".$table);

       $this->metaMap = $nTable = array();
       
       while ($row = mysql_fetch_assoc($result))
       {
           $this->metaMap[$row['Field']] = 1;
           if(isset($qMap[$row['Field']]))
           {
               $nTable['`'.$row['Field'].'`'] = $qMap[$row['Field']];
           }
        }

        return $nTable;


    }

    function queryCreate($table,$varMap)
    {

        $qMap = $this->varMap($this->cPrefix.$table,$varMap);

        $qNames  = implode(',',  array_keys($qMap));
        $qValues = implode(',',  array_values($qMap));

        $sql = "INSERT INTO {$this->cPrefix}$table ($qNames) VALUES ($qValues)";

        return $this->query($sql);

    }
    
    function queryUpdate($table,$varMap,$conditions)
    {
        $qMap = $this->varMap($this->cPrefix.$table,$varMap);

        $setArray = array();
        foreach ($qMap as $var=>$val)
        {
            $setArray[] = $var."=".$val;
        }

        $setString = implode(',',$setArray);

        $sql = "UPDATE {$this->cPrefix}$table SET $setString WHERE $conditions";

        return $this->query($sql);
    }

    function getMultiDownload($url, $filename, $size) {
    
        $threads = intval($this->post['threads']);
        $splits = range(0, $size, round($size / $threads));
        $megaconnect = curl_multi_init();
        $partnames = array();
        for ($i = 0; $i < sizeof($splits); $i++) {
            $ch[$i] = curl_init();
            curl_setopt($ch[$i], CURLOPT_URL, $url);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 0);
            curl_setopt($ch[$i], CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch[$i], CURLOPT_VERBOSE, 1);
            curl_setopt($ch[$i], CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_FRESH_CONNECT, 0);
            curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 10);
            $partnames[$i] = $filename . '_' . $i;
            $bh[$i] = fopen( $partnames[$i], 'w+');
            curl_setopt($ch[$i], CURLOPT_FILE, $bh[$i]);
            $x = ($i == 0 ? 0 : $splits[$i] + 1);
            $y = ($i == sizeof($splits) - 1 ? $size : $splits[$i + 1]);
            $range = $x . '-' . $y;
            curl_setopt($ch[$i], CURLOPT_RANGE, $range);
            curl_setopt($ch[$i], CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            curl_multi_add_handle($megaconnect, $ch[$i]);
        }
    
        $active = null;
    
        do {
            $mrc = curl_multi_exec($megaconnect, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($megaconnect) != -1) {
                do {
                    $mrc = curl_multi_exec($megaconnect, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        
        for ($i = 0; $i < sizeof($splits); $i++) {
            curl_multi_remove_handle($megaconnect, $ch[$i]);            
            curl_close($ch[$i]);
        }
        curl_multi_close($megaconnect);
        
        $final = fopen($filename, "w+");
        for ($i = 0; $i < sizeof($splits); $i++) {
            fseek($bh[$i], 0, SEEK_SET);
            while(!feof($bh[$i])) {
                $contents = fread($bh[$i], 1024*1024);
                if(!$contents) break;
                fwrite($final,$contents);
            }            
            fclose($bh[$i]);
            unlink($partnames[$i]);
        }
        fclose($final);
        
        return true;
    }


    function getRemoteFileSize($url,$resume=false) {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    
        $head = curl_exec($ch);
        curl_close($ch);

        $regex = '/Content-Length:\s([0-9].+?)\s/';
        $count = preg_match($regex, $head, $matches);
        
        if($resume) {
            if(strstr($head,'Accept-Ranges') < 0) {
                return 0;
            }
        }
    
        return isset($matches[1]) ? $matches[1] : "0";
    }

    function getRemoteFile($url,$filename = false,$params = false,$cookie = false,$precheck = false)
    {

        if (!$precheck && $filename && intval($this->post['threads']) > 1) {
    
            $size = $this->getRemoteFileSize($url,true);
    
            if ($size > 1024*500 ) { // only greater than 500 kb files 
                // check download resume supported 
                if($this->getMultiDownload($url, $filename, $size)) {
                    if(filesize($filename)>1024) {
                        return true;
                    }
                }
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($params) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, base64_decode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect: ')); // lighttpd fix
        }

        if ($cookie)
        {
            curl_setopt($ch, CURLOPT_COOKIE,base64_decode($cookie));
        }
        else {
            if(preg_match('/([a-z0-9]+)\.com/',$url,$match))
            {
                /*$name = $match[1];
                $cookieFile = $this->path($this->valReplace($this->cVideoFile));
                $cookieFile = dirname($cookieFile).'/'.$name.'.cookie';
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
                 */
            }
        }
        
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
            
        if ($filename) {

            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

            if($precheck)curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout

            $fp = fopen($filename, 'wb');
            if (!$fp)
            {
                $lastError = "Could not open $filename for writing";
                curl_close($ch);
                return false;
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            $data = true;
        }
        else
        {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            $data = curl_exec($ch);
        }

        $error = curl_error($ch);

        curl_close($ch);

        if (!empty($error) && !($precheck && strstr($error,'timed out')))
        {
            $this->lastError = $error;
            $this->response('Curl Error downloading '.$url,false);
        }
        
        

        return $data;
    }

    function resizeImage($sourceImage,$targetImage,$width,$height)
    {
        //ini_set('gd.jpeg_ignore_warning', 1); ( for black thumbnails in embedding )
        $srcImg = imagecreatefromjpeg($sourceImage);
        $tmpImg = imagecreatetruecolor($width,$height);
        list($widthOrig, $heightOrig) = getimagesize($sourceImage);

        imagecopyresampled($tmpImg,$srcImg,0,0,0,0,$width,$height,$widthOrig,$heightOrig);
        imagedestroy($srcImg); 
        
        imagejpeg($tmpImg,$targetImage,100);           
        imagedestroy($tmpImg);
    }

    /**
     * Initalize Database with new link
     *
     */

    function initDB($config = false)
    {
        if (!$this->database) {
            if (!$config) {
                $configFile = $this->path($this->cConfig);

                if (!is_file($configFile)) {
                    $this->response('Could not find database config file', false);
                }

                include $configFile;
            } else {
                extract($config);
            }

            if (isset($this->cDataBase['extract'])) {
                extract(${$this->cDataBase['extract']});
            }
            // New link due to time out issue

            $this->database['host']     = ${$this->cDataBase['host']};
            $this->database['username'] = ${$this->cDataBase['username']};
            $this->database['password'] = ${$this->cDataBase['password']};
            $this->database['database'] = ${$this->cDataBase['database']};
            @$this->database['prefix']   = ${$this->cDataBase['prefix']};

        } 
        
        $this->dbLink = mysql_connect($this->database['host'],$this->database['username'],$this->database['password'],true);
        
        if (!$this->dbLink)
        {
            $this->response('Could not connect to database : '.mysql_error(),false);
        }


        $result = mysql_select_db($this->database['database'], $this->dbLink);

        if (!$result) {
            $this->response('Cant use '.$this->database['database'].' : '.mysql_error(),false);
        }

        if(function_exists('mysql_set_charset')) mysql_set_charset("utf8",$this->dbLink);
        else  mysql_query ('SET NAMES utf8',  $this->dbLink);


        if(isset($this->cDataBase['prefix']))
        {
            $this->cPrefix = $this->database['prefix'];
        }

    }

    /**
     * Check CURL its required for download and Getting Thumbnails
     */

    function checkCurl()
    {
        if (!function_exists('curl_init'))
        {
            $this->response('CURL was not found on the server, please enable it',false);
        }
    }

    /**
     *  Checks video swiper plus connectivity
     */

    function checkAPI()
    {
        if(!$this->cVideoDownload) return;

        $scriptURL = "http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        $response = $this->getRemoteFile(VSPLUS.'sync/'.$this->safeEncode($scriptURL));

        if (!strstr($response,'stat="ok"'))
        {
            if (preg_match('%err msg="(.*?)"%',$response,$matches))
            {
                $this->lastError = $matches[1];
            }
            $this->response('Videoswiper API connectivity check Failed',false);
        }
    }

    /**
     * Check Weather required path can be written
     *
     */

    function checkPaths()
    {
        foreach ($this->cPaths as $path)
        {
            if (!is_writable($path))
            {
                $this->response($path." is not writable, Please make it writable",false);
            }
        }
    }

    /**
     * Check if Open Base Dir is  on
     *
     */

    function checkOpenBaseDir() {
        $val = ini_get('open_basedir');

        if(!empty($val)) {
            $this->response('Server has open_basedir enabled, please disable it',false);
        }
    }

    /**
     * Returns password hash based on script , override for advanced password generation
     *
     */

    function password()
    {
        return md5($this->post['password']);
    }

    /**
     * Check if valid username is using the script
     *
     */

    function checkLogin()
    {
        global $apiUsers;

        $usersAllowed = empty($apiUsers)?false:explode(',',$apiUsers);

        // is he allowed to use this ?
        if ($usersAllowed && !in_array($this->post['username'],$usersAllowed))
        {
            $this->response('EC101 : This username is not allowed to use this script.',false);
        }

        $username = $this->qs($this->post['username']);
        $password = $this->qs($this->password());

        $conditions = sprintf($this->cUserConditions,$username,$password);

        $this->userId = $this->getField($this->cUserField,$this->cUserTable,$conditions);
        $this->post['uid'] = $this->userId;

        if (!$this->userId)
        {
            $this->response("EC102 : Could not validate username and password",false);
        }

        error_reporting(E_ALL);
        ini_set('display_errors',true);
    }

    function checkGD() {
        if(!function_exists('imagecreatefromjpeg')) {
            $this->response('Server does not have GD Enabled,Please ask host to enable it.',false);
        }
    }
    /**
     * Check All Default action
     *
     */

    function checkAll()
    {
        $this->checkCurl();
        $this->checkPaths();
        
        if($this->cVideoDownload) {
            // for downloads only 
            $this->checkOpenBaseDir();
        }
        
        $this->checkAPI();
        $this->checkGD();
        

        $this->response("All checks were successfull for Wordpress r13.06.20 .");
    }

    /**
     * This is used to make user site as our gateway
     *
     */

    function forward()
    {
        $params = false;
        $cookie = false;
        
        if (isset($this->post['params']))
        {
            $params = $this->post['params'];
        }

        if (isset($this->post['cookie']))
        {
            $cookie = $this->post['cookie'];
        }

        $url = $this->post['url'];

        if(!strstr($url,'http://'))
        {
            $url = $this->safeDecode($url);
        }

        echo $this->getRemoteFile($url,false,$params,$cookie);
    }

    /**
     * Get Categories from user site
     *
     */

    function getInfo()
    {

        list($id,$name) = explode(',',$this->cCategoryFields);

        $where = (empty ($this->cCategoryConditions)?'':' WHERE '.$this->cCategoryConditions);

        $result = $this->query("SELECT ".$this->cCategoryFields." FROM ".$this->cPrefix.$this->cCategoryTable." $where ORDER BY ".$name." ASC");

        $rowCount = mysql_num_rows($result);

        if ($rowCount)
        {
            $categories = "";

            while ($row = mysql_fetch_array($result)) {
                $categories.= '<category id="'.$row[$id].'">'.$row[$name].'</category>'._NL;
            }

            // Supported types

            $actions = "";

            if ($this->cVideoEmbed) {
                $actions .= '<action>embed</action>';
            }
            if ($this->cVideoDownload) {
                $actions .= '<action>download</action>';
                if ($this->cVideoDownloadHD) {
                    $actions .= '<action>downloadhd</action>';
                }
            }
            

            $response = '<categories>'._NL.$categories.'</categories>';
            $response .= _NL.'<actions>'.$actions.'</actions>';

            $this->response($response);

        }

        $this->response('E103 : Could not find any categories.',false);

    }

    /**
    * Viedo Handling functions
    */

    function initPost()
    {

        static $time = _TIME;

        // database stuff

        $this->post['time']        = $time;
        $this->post['mysql_time']  = date('Y-m-d H:i:s',$time);
        $this->post['mysql_date']  = date('Y-m-d',$time);

        $this->post['alias']       = $this->safeURL($this->post['title']);

        $this->post['duration_ms'] = sprintf('%02d:%02d',$this->post['duration']/60,$this->post['duration']%60);
        $this->post['duration_hms'] = sprintf('%02d:%02d:%02d',$this->post['duration']/3600,$this->post['duration']/60,$this->post['duration']%60);

        $this->post['key']         = substr(md5($time.$this->post['title']),0,20);
        $this->post['skey']        = substr(md5($time.$this->post['title']),0,10);

    }

    function getThumbnails() {
        
        global $ffmpeg;

        $videoFilePath = $this->path($this->valReplace($this->cVideoFile));

            $index = 1;
            $interval = $this->post['duration']/(count($this->cVideoThumbs)+1);


            if(is_file($ffmpeg)) {
            // init ffmpeg
            } else if (extension_loaded('ffmpeg'))
            {
                // init ffmpeg
                $handle = new ffmpeg_movie($videoFilePath);
            } else {
                // init normal
            }


            foreach ($this->cVideoThumbs as $thumb) {
            extract($thumb);
            if (!isset($thumb['width']))
                $width = $this->cImageWidth;
            if (!isset($thumb['height']))
                $height = $this->cImageHeight;
            $thumbPath = $this->path($this->valReplace($path));
            $thumbtime = intval($interval * $index);

            if (is_file($ffmpeg) && is_file($videoFilePath)) {
                exec("$ffmpeg -i {$videoFilePath} -f image2  -ss $thumbtime -s {$width}x{$height} -vframes 1 -an -y $thumbPath");
            } else {

                if (!isset($firstImage))
                    $firstImage = $thumbPath;

                if (!is_file($firstImage)) {
                    if (!$this->getRemoteFile($this->post['thumbnail'], $firstImage)) {
                        $this->response('EC121 : Could not download thumbnail.', false);
                    }
                    $this->resizeImage($firstImage, $thumbPath, $width, $height);
                } else {
                    $this->resizeImage($firstImage, $thumbPath, $width, $height);
                }
            }
            $index++;
        }
        
        if(is_file($this->cImageTemp)) {
            unlink($this->cImageTemp);
        }

    }

    function getDuration() {
        global $ffmpeg;

        $videoFilePath = $this->path($this->valReplace($this->cVideoFile));

        if ($this->post['duration'] == 0) {
            if(is_file($ffmpeg)) {
                ob_start();
                passthru("$ffmpeg -i {$videoFilePath} 2>&1");
                $duration = ob_get_contents();
                ob_end_clean();


                if(preg_match('/Duration: (.*?),/', $duration, $matches, PREG_OFFSET_CAPTURE, 3)) {
                    $this->post['duration'] = $matches[1][0];
                }

            }

        }

    }



    function downloadVideo($precheck = false) {

        global $ffmpeg,$flvtool,$mp4box;

        $videoPath = $this->path($this->valReplace($this->cVideoFile));
        $this->cVideoFilePath = $videoPath;

        $scriptURL = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

        if (!isset($this->post['downloadURL'])) {

            // get download URL
            $downloadAPIURL = VSPLUS . 'get/' . $this->post['token'] . '/' . $this->safeEncode($this->post['permalink']) . '/' . $this->safeEncode($scriptURL . '|' . $this->post['username'] . '|' . $this->post['password']);
            // if ! flv file do something
            $response = $this->getRemoteFile($downloadAPIURL);


            if (preg_match('%err msg="(.*?)"%', $response, $matches)) {
                $this->lastError = $matches[1];
            }

            if (!$response || empty($response) || strstr($response, 'stat="Fail"')) {
                $this->response('EC111 : Could not get download URL using API.', false);
            }
        }


        if (isset($this->post['downloadURL']) || preg_match('%<download>(.*?)</download>%', $response, $match)) {
            if (!isset($this->post['downloadURL'])
                )$this->post['downloadURL'] = $match[1];

            set_time_limit(0); // can take long

            @unlink($videoPath);
            if (!$this->getRemoteFile($this->post['downloadURL'], $videoPath, false, false, $precheck)) {
                $this->response('EC112 : Could not download video file.', false);
            }

            // get size
            static $rsize = 0;

            if (!isset($this->post['size'])) {
                $rsize = $this->getremoteFileSize($this->post['downloadURL']);
            }

            // automatic mp4 conversion


            $fp = fopen($videoPath, "r");
            if (!$fp) {
                $this->response('EC113 : Could not open downloaded video file.', false);
            }
            $header = fread($fp, 3);
            fclose($fp);

            if ($header != 'FLV') {
                // Convert Video mp4 to flv
                //ffmpeg -i input.mov -ar 22050 -qscale .1 output.flv
                if ($this->cMp4File) {
                    $outPath = $this->cMp4FilePath = $this->path($this->valReplace($this->cMp4File));
                    copy($videoPath, $this->cMp4FilePath);                    
                    if(is_file($mp4box))
                    {
                        exec("$mp4box -inter 500 {$outPath} 2>&1",$return);
                    }
                }

                if (!$precheck) {
                    if (is_file($ffmpeg) && !$this->cMp4Only) {
                        $tempPath = "{$videoPath}.tmp.flv";
                        exec("$ffmpeg -i {$videoPath} -ar 22050 -qscale 11 {$tempPath} 2>&1", $return);
                        if (is_file($tempPath) && (filesize($tempPath) > 1024)) {
                            copy($tempPath, $videoPath);
                            $this->cVideoFilePath = $videoPath;
                            unlink($tempPath);

                            if(is_file($flvtool))
                            {
                                exec("$flvtool -U {$videoPath} 2>&1",$return);
                            }

                        } else {
                            echo implode("\n", $return);
                        }
                    }
                }
            } 
            else {
                // Insert Meta in FLV if required
                if(is_file($flvtool))
                {
                    exec("$flvtool -U {$videoPath} 2>&1",$return);
                }
                
                // if flv file and mp4 is required 
                
                if($this->cMp4File && !$precheck) {
                    
                    if (is_file($ffmpeg)) {
                        $outPath = $this->cMp4FilePath = $this->path($this->valReplace($this->cMp4File));
                        //-vcodec libx264 -vpre lossless_medium -threads 0 -r 25 -g 50 -crf 28 -me_method hex -trellis 0 -bf 8 -acodec libfaac -ar 44100 -ab 128k -f mp4
                        exec("$ffmpeg -i {$videoPath} -vcodec libx264 -vpre lossless_medium -threads 0 -r 25 -g 50 -crf 28 -me_method hex -trellis 0 -bf 8  -acodec libfaac -ar 22050 {$outPath} 2>&1", $return);

                        if (is_file($mp4box)) {
                            exec("$mp4box -inter 500 {$outPath} 2>&1", $return);
                        }
                    }
                }
                
                
            }
            $size = filesize($videoPath);

            if ($rsize > $size) {
                $size = $rsize;
            }

            $this->post['size'] = $size;
            $this->post['size_kb'] = sprintf("%.2f", $size / (1024));
            $this->post['size_KB'] = sprintf("%.2f KB", $size / (1024));
            $size /= 1024;
            $this->post['size_mb'] = sprintf("%.2f", $size / (1024));
            $this->post['size_MB'] = sprintf("%.2f MB", $size / (1024));
            
            $size = filesize($videoPath);
            
            if($size < 1024) {
              $this->response('EC115 : Download file size is 0. ['.$this->post['downloadURL'].']',false);
            }
            // get Video Duration

            $this->getDuration();

            return;
        }

        $this->response('EC114 : No download link in API response.', false);
    }

    function uploadVideo()
    {
        $s = $this->server;

        $conn = ftp_connect($s['hostname']);
        if(!$conn)
        {
            $this->response('EC151 : Could not connect to '.$s['hostname'],false);
        }

        $result = ftp_login($conn, $s['username'], $s['password']);
        if(!$result)
        {
            $this->response('EC152 : Could not login to '.$s['hostname'],false);
        }

        // creat anyway
        ftp_mkdir($conn,$s['path']);

        $localFile  = $this->path($this->valReplace($this->cVideoFile));

        $remoteFile = $s['path'].($s['path'][strlen($s['path'])-1]=='/'?'':'/').basename($localFile);

        $result = ftp_put($conn,$remoteFile,$localFile,FTP_BINARY);
        if(!$result)
        {
            $this->response('EC153 : Could not upload '.$localFile.' to '.$remoteFile.' on '.$s['hostname'],false);
        }

        if($this->cMp4File && $this->cMp4FilePath)
        {
            $localFile  = $this->path($this->valReplace($this->cMp4File));

            $remoteFile = $s['path'].($s['path'][strlen($s['path'])-1]=='/'?'':'/').basename($localFile);

            $result = ftp_put($conn,$remoteFile,$localFile,FTP_BINARY);
            if(!$result)
            {
                $this->response('EC154 : Could not upload '.$localFile.' to '.$remoteFile.' on '.$s['hostname'],false);
            }
        }

        ftp_close($conn);

        unlink($localFile);

    }
    
    function getServer()
    {
        if(!$this->cServerVarMap) return false;
        $where = (empty ($this->cServerConditions)?'':' WHERE '.$this->cServerConditions);
 
        $result = $this->query("SELECT * FROM ".$this->cPrefix.$this->cServerTable.$where);

        $rowCount = mysql_num_rows($result);

        if ($rowCount)
        {
            $row = mysql_fetch_assoc($result);

            $m = $this->cServerVarMap;
            $this->server['id'] = $row[$m['id']];
            $this->server['hostname'] = $row[$m['hostname']];
            $this->server['username'] = $row[$m['username']];
            $this->server['password'] = $row[$m['password']];
            $this->server['path']     = $row[$m['path']];
            $this->server['url']      = $row[$m['url']];

            $this->post['server_id']   = $row[$m['id']];
            $this->post['server_path'] = $row[$m['path']];
            $this->post['server_url']  = $row[$m['url']];

            return true;
        }

        return false;
    }



    /**
    * Embed
    */

    function embed()
    {
        $this->initPost();

        $this->getThumbnails();

        $result = $this->queryCreate($this->cVideoTable,$this->cVideoVarMap);
        $this->post['id'] = mysql_insert_id();
        return $result;
    }


    /**
    * Download
    */

    function download()
    {
    	 $this->initPost();

    	 // Precheck  ( video file + size + duration )
    	 $this->downloadVideo(true);

    	 $this->getThumbnails();

    	 $this->queryCreate($this->cVideoTable,$this->cVideoVarMap);
         $this->post['id'] = mysql_insert_id();
         
    	 $this->downloadVideo();

    	 $this->getThumbnails();

         if($this->getServer())
         {
             $this->uploadVideo();
             $this->queryUpdate($this->cVideoTable,$this->cServerUpdateVarMap,$this->cVideoField. ' = '.$this->post['id']);
         }

         if($this->cVideoUpdateVarMap)
         $this->queryUpdate($this->cVideoTable,$this->cVideoUpdateVarMap,$this->cVideoField. ' = '.$this->post['id']);

         if($this->cMp4Only) {
             unlink($this->cVideoFilePath);
         }
    }

    /**
     * The main executing function
     *
     */

    function dispatch()
    {

        if (is_array($_POST) && count($_POST) && ($_POST['action']!='sync') ) {
            $this->checkLogin();
            
                if (isset($this->post['secure'])) {
                $result = $this->getRemoteFile(VS . 'cart/item/' . $this->post['secure']);
                // match 
                if (preg_match('/\[\[(.*?)\]\]/', $result, $reqMatch)) {
                    $reqArray = unserialize(base64_decode($reqMatch[1]));
                    foreach ($reqArray as $var => $val) {
                        $this->post[$var] = $val;
                    }
                } else {
                    $this->response('EC117 : Invalid response from server.', false);
                }
            }            
        } 

        $action = (isset($_POST['action']))?$_POST['action']:'default';
        $this->action = $action;
    
        switch ($action) {

            case 'login':
                break;

            case 'info':
                $this->getInfo();
                break;

            case 'embed':
                $this->embed();
                break;


            case 'downloadhd':
            case 'download':
                $this->download();
                break;
            
            case 'stream':
                $this->post['url'] = base64_decode($this->post['u']);
            case 'forward':
                $this->forward();
                break;

            case 'sync':
                $this->response("");
                break;

            default:
                $this->checkAll();
                break;
        }


        $response = "[$action]";

        if(isset($this->post['size']) && $this->post['size'] > 0 ) {
            $response .= sprintf("[size:%d]",$this->post['size']);            
        } 
        
        $this->response($response);
        

    }

}
?><?php
require( './wp-load.php' );

class wordpress extends vsmod {
    
    var $cVideoDownload = false;
    
    
    // No database required 
    function initDB() {
        return true;
    }
    
    // No Path required 
    function checkPaths() {        
        
        $upload_dir = wp_upload_dir();
        
        if (!is_writable($upload_dir['basedir']))
            {
                $this->response($upload_dir['basedir']." is not writable, Please make it writable",false);
            }
        
    }
    
    // No Downloading
    function checkAPI() {         
    }
     
    function checkLogin() {
        global $apiUsers, $rpcURL;

        $usersAllowed = empty($apiUsers) ? false : explode(',', $apiUsers);

        // is he allowed to use this ?
        if ($usersAllowed && !in_array($this->post['username'], $usersAllowed)) {
            $this->response('This username is not allowed to use this script.', false);
        }

        $username = $this->post['username'];
        $password = $this->post['password'];
        
        $userdata = get_user_by('login', $username);
        if(!isset ($userdata->ID)) {
           $this->response('Invalid username supplied.', false); 
        }
        
        $result = wp_check_password($password, $userdata->user_pass, $userdata->ID);
        
        if(!$result) {
           $this->response('Invalid password supplied.', false); 
        }
        
        wp_set_current_user($userdata->ID);
        $this->post['uid'] = $userdata->ID;
        return $userdata->ID;
    }
    
     function getInfo() {
            
        $result = get_categories(array('hide_empty'=>false)); // default is post 
        if (is_array($result)) {
            
            $categories = array();
            
            foreach ($result as $category) {
                $categories[] = '<category id="' . $category->cat_ID . '">' . $category->cat_name . '</category>';
            }
            
            $categories = implode("\n", $categories);
            
            $actions = '<action>embed</action>';
            $response = '<categories>' . $categories . '</categories>';
            $response .= _NL . '<actions>' . $actions . '</actions>';

            $this->response($response);
        }
  
        $this->response('Could not get Categories,make sure categories are defined.', false);
    }

    function embed() {
        extract($this->post);
              
        // create a post ( http://codex.wordpress.org/Function_Reference/wp_insert_post )
        
        $wpPost= array( 'post_title'    => $title,
                        'post_content'  => $embed."\n".$description,
                        'post_status'   => 'publish', // draft or publish 
                        'post_author'   => $this->post['uid'],
                        'tags_input'    => $tags, 
                        'filter' => true // fake its already filtered
                        );
         
         
              
         
        $wpPostID = wp_insert_post($wpPost);
        
        if(!$wpPostID) {
            $this->response('Could not create Post.', false);
        }
        
        // insert category 
        
         wp_set_post_terms($wpPostID,array($this->post['category_id']),'category');
        
        // create a featured image 
               
        $upload_dir = wp_upload_dir();
        
        $filename = 'thumb-'.$wpPostID.'.jpg';
        if (wp_mkdir_p($upload_dir['path']))
            $filepath = $upload_dir['path'] . '/' . $filename;
        else
            $filepath = $upload_dir['basedir'] . '/' . $filename;

       
        if(!$this->getRemoteFile($thumbnail,$filepath)) {
            $this->response('Could not download thumbnail.', false);
        }
        
        $wp_filetype = wp_check_filetype($filename, null );
        
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
         
        $attach_id = wp_insert_attachment( $attachment, $filepath, $wpPostID );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
  
        set_post_thumbnail( $wpPostID, $attach_id );

        // post custom fields change as you need 
        
        add_post_meta($wpPostID,"video-code",$embed);
        add_post_meta($wpPostID,"video-thumb",$thumbnail);
        add_post_meta($wpPostID,'video-time',$duration);
        add_post_meta($wpPostID,'video-views',$views);
                
    }
}

$object = new wordpress();
$object->dispatch();
