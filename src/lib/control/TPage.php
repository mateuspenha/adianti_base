<?php
namespace Adianti\Base\Lib\Control;

use Adianti\Base\Lib\Core\AdiantiCoreTranslator;
use Adianti\Base\Lib\Widget\Base\TElement;
use Adianti\Base\Lib\Widget\Base\TScript;
use Dvi\Adianti\Route;
use Exception;

/**
 * Page Controller Pattern: used as container for all elements inside a page and also as a page controller
 *
 * @version    5.0
 * @package    control
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    http://www.adianti.com.br/framework-license
 */
class TPage extends TElement
{
    private $body;
    private $constructed;
    private static $loadedjs;
    private static $loadedcss;
    private static $registeredcss;
    
    /**
     * Class Constructor
     */
    public function __construct()
    {
        parent::__construct('div');
        $this->constructed = true;
    }
    
    /**
     * Interprets an action based at the URL parameters
     */
    public function run()
    {
        if ($_GET) {
            $class  = isset($_GET['class'])  ? Route::getPath($_GET['class'])  : null;
            $method = isset($_GET['method']) ? $_GET['method'] : null;

            if ($class) {
                $object = $class == get_class($this) ? $this : new $class;
                if (is_callable(array($object, $method))) {
                    call_user_func(array($object, $method), $_REQUEST);
                }
            } elseif (function_exists($method)) {
                call_user_func($method, $_REQUEST);
            }
        }
    }
    
    /**
     * Include a specific JavaScript function to this page
     * @param $js JavaScript location
     */
    public static function include_js($js)
    {
        self::$loadedjs[$js] = true;
    }
    
    /**
     * Include a specific Cascading Stylesheet to this page
     * @param $css  Cascading Stylesheet
     */
    public static function include_css($css)
    {
        self::$loadedcss[$css] = true;
    }
    
    /**
     * Register a specific Cascading Stylesheet to this page
     * @param $cssname  Cascading Stylesheet Name
     * @param $csscode  Cascading Stylesheet Code
     */
    public static function register_css($cssname, $csscode)
    {
        self::$registeredcss[$cssname] = $csscode;
    }
    
    /**
     * Open a File Dialog
     * @param $file File Name
     */
    public static function openFile($file)
    {
        TScript::create("__adianti_download_file('{$file}')");
    }
    
    /**
     * Return the loaded Cascade Stylesheet files
     * @ignore-autocomplete on
     */
    public static function getLoadedCSS()
    {
        $css = self::$loadedcss;
        $csc = self::$registeredcss;
        $css_text = '';
        
        if ($css) {
            foreach ($css as $cssfile => $bool) {
                $css_text .= "    <link rel='stylesheet' type='text/css' media='screen' href='$cssfile'/>\n";
            }
        }
        
        if ($csc) {
            $css_text .= "    <style type='text/css' media='screen'>\n";
            foreach ($csc as $cssname => $csscode) {
                $css_text .= $csscode;
            }
            $css_text .= "    </style>\n";
        }
        
        return $css_text;
    }
    
    /**
     * Return the loaded JavaScript files
     * @ignore-autocomplete on
     */
    public static function getLoadedJS()
    {
        $js = self::$loadedjs;
        $js_text = '';
        if ($js) {
            foreach ($js as $jsfile => $bool) {
                $js_text .= "    <script language='JavaScript' src='$jsfile'></script>\n";
                ;
            }
        }
        return $js_text;
    }
    
    /**
     * Discover if the browser is mobile device
     */
    public static function isMobile()
    {
        $isMobile = false;
        
        if (PHP_SAPI == 'cli') {
            return false;
        }
        
        if (isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])) {
            $isMobile = true;
        }
        
        $mobiBrowsers = array('android',   'audiovox', 'blackberry', 'epoc',
                              'ericsson', ' iemobile', 'ipaq',       'iphone', 'ipad',
                              'ipod',      'j2me',     'midp',       'mmp',
                              'mobile',    'motorola', 'nitro',      'nokia',
                              'opera mini','palm',     'palmsource', 'panasonic',
                              'phone',     'pocketpc', 'samsung',    'sanyo',
                              'series60',  'sharp',    'siemens',    'smartphone',
                              'sony',      'symbian',  'toshiba',    'treo',
                              'up.browser','up.link',  'wap',        'wap',
                              'windows ce','htc');
                              
        foreach ($mobiBrowsers as $mb) {
            if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), $mb) !== false) {
                $isMobile = true;
            }
        }
        
        return $isMobile;
    }
    
    /**
     * Intercepts whenever someones assign a new property's value
     * @param $name     Property Name
     * @param $value    Property Value
     */
    public function __set($name, $value)
    {
        parent::__set($name, $value);
        $this->$name = $value;
    }
    
    /**
     * Decide wich action to take and show the page
     */
    public function show()
    {
        // just execute run() from toplevel TPage's, not nested ones
        if (!$this->getIsWrapped()) {
            $this->run();
        }
        parent::show();
        
        if (!$this->constructed) {
            throw new Exception(AdiantiCoreTranslator::translate('You must call ^1 constructor', __CLASS__));
        }
    }
}
