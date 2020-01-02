<?php
/**
 * Usefull functions mostly for backward compatibility
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to http://doc.prestashop.com/display/PS15/Overriding+default+behaviors
 * #Overridingdefaultbehaviors-Overridingamodule%27sbehavior for more information.
 *
 * @author    Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license   commercial license see license.txt
 * @category  Prestashop
 * @category  Module
 */

class Samdha_Commons_Tools
{
    public $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
    * Register the current module to hooks
    *
    * @param array $hooks array of hooks names
    * @return boolean
    **/
    public function registerHooks(array $hooks)
    {
        $result = true;
        foreach ($hooks as $hook) {
            $result &= $this->module->registerHook($hook);
        }
        return $result;
    }

    /* curl management */
    public function canUseCurl()
    {
        return (
            function_exists('curl_init')
            && function_exists('curl_setopt')
            && function_exists('curl_exec')
            && function_exists('curl_close')
        );
    }

    public function canAccessInternet()
    {
        return (ini_get('allow_url_fopen') || $this->canUseCurl());
    }

    /**
     * Copy a remote file
     * @param $url string file to copy
     * @param $file string file to create
     * @return boolean
     */
    public function copyUrl($url, $file)
    {
        if ($this->canUseCurl()) {
            $ch = curl_init();
            if ($ch) {
                $fp = fopen($file, 'w');
                if ($fp) {
                    if (!curl_setopt($ch, CURLOPT_URL, $url)) {
                        fclose($fp); // to match fopen()
                        trigger_error(curl_error($ch));
                    }
                    if (!curl_setopt($ch, CURLOPT_FILE, $fp)) {
                        fclose($fp); // to match fopen()
                        trigger_error(curl_error($ch));
                    }
                    if (!curl_setopt($ch, CURLOPT_HEADER, 0)) {
                        fclose($fp); // to match fopen()
                        trigger_error(curl_error($ch));
                    }
                    if (!curl_setopt($ch, CURLOPT_TIMEOUT, 600)) {
                        fclose($fp); // to match fopen()
                        trigger_error(curl_error($ch));
                    }
                    curl_setopt(
                        $ch,
                        CURLOPT_USERAGENT,
                        'Module '.$this->module->name.' v'.$this->module->version.' for Prestashop v'._PS_VERSION_
                    );
                    if (!$this->curlExecFollow($ch)) {
                        fclose($fp); // to match fopen()
                        trigger_error(curl_error($ch));
                    }

                    curl_close($ch);
                    fclose($fp);
                } else {
                    throw new Exception('FAIL: fopen()');
                }
            } else {
                throw new Exception('FAIL: curl_init()');
            }
        } elseif (!copy($url, $file)) {
            // Compatibility 1.4
            throw new Exception('FAIL: copy()');
        }
        return true;
    }

    /**
     * Bypass safe_mode restriction for curl
     * http://www.php.net/manual/en/function.curl-setopt.php#102121
     */
    public function curlExecFollow(/*resource*/ $ch, /*int*/ $max_redirect = null)
    {
        $mr = $max_redirect === null ? 5 : (int) $max_redirect;
        if (ini_get('open_basedir') == '' && in_array(ini_get('safe_mode'), array('Off', 'off', '0'))) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            if ($mr > 0) {
                $new_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                $rch = curl_init();

                $curlopt = array(
                    CURLOPT_TIMEOUT => 600,
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true,
                    CURLOPT_FORBID_REUSE => false,
                    CURLOPT_USERAGENT =>
                        'Module '.$this->module->name.' v'.$this->module->version.' for Prestashop v'._PS_VERSION_
                );
                curl_setopt_array($rch, $curlopt);
                do {
                    curl_setopt($rch, CURLOPT_URL, $new_url);
                    $header = curl_exec($rch);
                    if (curl_errno($rch)) {
                        $code = 0;
                    } else {
                        $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                        if ($code == 301 || $code == 302) {
                            preg_match('/Location:(.*?)\n/', $header, $matches);
                            $new_url = trim(array_pop($matches));
                        } else {
                            $code = 0;
                        }
                    }
                } while ($code && --$mr);
                curl_close($rch);
                if (!$mr) {
                    if ($max_redirect === null) {
                        trigger_error(
                            'Too many redirects. When following redirects, libcurl hit the maximum amount.',
                            E_USER_WARNING
                        );
                    } else {
                        $max_redirect = 0;
                    }
                    return false;
                }
                curl_setopt($ch, CURLOPT_URL, $new_url);
            }
        }
        return curl_exec($ch);
    }

    public function extractZip($file, $directory = _PS_MODULE_DIR_)
    {
        if (method_exists('Tools', 'ZipExtract')) {
            if (!Tools::ZipExtract($file, $directory)) {
                throw new Exception(Tools::displayError('error while extracting module (file may be corrupted).'));
            }
        } elseif (class_exists('ZipArchive', false)) {
            $zip = new ZipArchive();
            $zipped = ($zip->open($file) === true)
                && $zip->extractTo($directory)
                && $zip->close();

            if (!$zipped) {
                $error = error_get_last();
                throw new Exception(
                    Tools::displayError('error while extracting module (file may be corrupted)')
                    .($error?' '.$error['message']:'')
                );
            }
        } else {
            throw new Exception(
                Tools::displayError('zip is not installed on your server. Ask your host for further information.')
            );
        }
        return true;
    }

    /**
     * get current http host
     * for Prestashop < 1.3
     *
     * @param boolean $http @see Tools::getHttpHost
     * @param boolean $entities @see Tools::getHttpHost
     * @return string
     **/
    public function getHttpHost($http = false, $entities = false)
    {
        if (method_exists('Tools', 'getHttpHost')) {
            $host = Tools::getHttpHost($http, $entities);
        } else {
            $host = (isset($_SERVER['HTTP_X_FORWARDED_HOST'])?$_SERVER['HTTP_X_FORWARDED_HOST']:$_SERVER['HTTP_HOST']);
            if ($entities) {
                $host = htmlspecialchars($host, ENT_COMPAT, 'UTF-8');
            }
            if ($http) {
                $host = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').$host;
            }
        }
        return $host;
    }

    /**
     * Performs a jsonRCP request and gets the results as an array
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function jsonRPCCall($method, $params)
    {
        // prepares the request
        $rand_id = 1 + rand();
        $request = $this->jsonEncode(array(
            'method' => $method,
            'params' => $params,
            'id' => $rand_id
        ));

        // performs the HTTP POST
        if ($this->canUseCurl()) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->module->rpc_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 'content-type: text/plain;');
            curl_setopt($ch, CURLOPT_TRANSFERTEXT, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_PROXY, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // some servers don't accept my certificat

            // decode result
            $response = curl_exec($ch);
            curl_close($ch);

            $response = $this->jsonDecode($response);
        } else {
            $opts = array (
                'http' => array (
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/json',
                    'content' => $request
                )
            );
            $context  = stream_context_create($opts);
            if ($fp = fopen($this->module->rpc_url, 'r', false, $context)) {
                $response = '';
                while ($row = fgets($fp)) {
                    $response .= trim($row)."\n";
                }
                $response = $this->jsonDecode($response);
            } else {
                throw new Exception('Unable to connect to '.$this->module->rpc_url);
            }
        }

        // final checks and return
        // check
        if (!$response) {
            throw new Exception('Unable to connect to '.$this->module->rpc_url);
        } elseif ($response->id != $rand_id) {
            throw new Exception('Incorrect response id (request id: '.$rand_id.', response id: '.$response->id.')');
        } elseif (!is_null($response->error)) {
            if (is_string($response->error)) {
                throw new Exception('Request error: '.$response->error);
            } else {
                throw new Exception('Request error: '.$response->error->string);
            }
        }

        return $response->result;
    }

    /**
     * Check if the current page use SSL connection on not
     *
     * compatibility Prestashop 1.3
     * @return bool uses SSL
     */
    public static function usingSecureMode()
    {
        if (method_exists('Tools', 'usingSecureMode')) {
            return Tools::usingSecureMode();
        }

        if (isset($_SERVER['HTTPS'])) {
            return in_array(Tools::strtolower($_SERVER['HTTPS']), array(1, 'on'));
        }
        // $_SERVER['SSL'] exists only in some specific configuration
        if (isset($_SERVER['SSL'])) {
            return in_array(Tools::strtolower($_SERVER['SSL']), array(1, 'on'));
        }
        // $_SERVER['REDIRECT_HTTPS'] exists only in some specific configuration
        if (isset($_SERVER['REDIRECT_HTTPS'])) {
            return in_array(Tools::strtolower($_SERVER['REDIRECT_HTTPS']), array(1, 'on'));
        }
        if (isset($_SERVER['HTTP_SSL'])) {
            return in_array(Tools::strtolower($_SERVER['HTTP_SSL']), array(1, 'on'));
        }

        return false;
    }


    /**
     * jsonDecode convert json string to php array / object
     * compatibility Prestashop 1.3
     *
     * @param string $json
     * @param boolean $assoc if true, convert to associativ array
     * @return array
     */
    public function jsonDecode($json, $assoc = false)
    {
        if (method_exists('Tools', 'jsonDecode') && (!$assoc || version_compare(_PS_VERSION_, '1.4.2.4', '>='))) {
            return Tools::jsonDecode($json, $assoc);
        } elseif (function_exists('json_decode')) {
            $func = 'json_decode'; // validator
            return $func($json, $assoc); /* compatibility Prestashop 1.3 */
        } else {
            // compatibility < PHP 5.2
            if (!class_exists('Services_JSON')) {
                include_once(_PS_MODULE_DIR_.$this->module->name.'/libs/json.php');
            }
            $pear_json = new Services_JSON(($assoc) ? SERVICES_JSON_LOOSE_TYPE : 0);
            return $pear_json->decode($json);
        }
    }

    /**
     * Convert an array to json string
     * compatibility Prestashop 1.3
     *
     * @param array $data
     * @return string json
     */
    public function jsonEncode($data)
    {
        if (method_exists('Tools', 'jsonEncode')) {
            return Tools::jsonEncode($data);
        } elseif (function_exists('json_encode')) {
            $func = 'json_encode'; // validator
            return $func($data); /* compatibility Prestashop 1.3 */
        } else {
            // compatibility < PHP 5.2
            if (!class_exists('Services_JSON')) {
                include_once(_PS_MODULE_DIR_.$this->module->name.'/libs/json.php');
            }
            $pear_json = new Services_JSON();
            return $pear_json->encode($data);
        }
    }

    /**
    * compatibility Prestashop 1.3
    **/
    public function fileGetContents($url, $use_include_path = false, $stream_context = null, $curl_timeout = 5)
    {
        if (method_exists('Tools', 'file_get_contents')
            && (version_compare(_PS_VERSION_, '1.5.4.0', '>=') || preg_match('/^https?:\/\//', $url))
        ) {
            return Tools::file_get_contents($url, $use_include_path, $stream_context, $curl_timeout);
        } else {
            // from Tools::file_get_contents
            if ($stream_context == null && preg_match('/^https?:\/\//', $url)) {
                $stream_context = stream_context_create(array('http' => array('timeout' => $curl_timeout)));
            }
            if (in_array(ini_get('allow_url_fopen'), array('On', 'on', '1')) || !preg_match('/^https?:\/\//', $url)) {
                $func = 'file_get_contents'; // validator
                return $func($url, $use_include_path, $stream_context); /* compatibility Prestashop 1.3 */
            } elseif ($this->canUseCurl()) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($curl, CURLOPT_TIMEOUT, $curl_timeout);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
                if ($stream_context != null) {
                    $opts = stream_context_get_options($stream_context);
                    if (isset($opts['http']['method']) && Tools::strtolower($opts['http']['method']) == 'post') {
                        curl_setopt($curl, CURLOPT_POST, true);
                        if (isset($opts['http']['content'])) {
                            parse_str($opts['http']['content'], $datas);
                            curl_setopt($curl, CURLOPT_POSTFIELDS, $datas);
                        }
                    }
                }
                $content = curl_exec($curl);
                curl_close($curl);
                return $content;
            } else {
                return false;
            }
        }
    }

    /**
    * compatibility Prestashop 1.1
    **/
    public function stripSlashes($string)
    {
        if (method_exists('Tools', 'stripslashes')) {
            return Tools::stripslashes($string);
        }

        if (!defined('_PS_MAGIC_QUOTES_GPC_')) {
            define('_PS_MAGIC_QUOTES_GPC_', get_magic_quotes_gpc());
        }

        if (_PS_MAGIC_QUOTES_GPC_) {
            $func = 'stripslashes'; // validator
            $string = $func($string); /* compatibility Prestashop 1.1 */
        }
        return $string;
    }

    public function stripSlashesArray($value)
    {
        $value = is_array($value) ?array_map(array($this, 'stripSlashesArray'), $value):$this->stripSlashes($value);
        return $value;
    }

    /**
     * execute sql file
     * @return boolean
     **/
    public function executeSQLFile($file)
    {
        $path = _PS_MODULE_DIR_.$this->module->name.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR; // @since 1.3.3.0
        if (!file_exists($path.$file)) {
            $path = _PS_MODULE_DIR_.$this->module->name.DIRECTORY_SEPARATOR;
        }
        if (!file_exists($path.$file)) {
            throw new Exception('File not found : '.$file);
        }
        if ($sql = $this->fileGetContents($path.$file)) {
            $db = Db::getInstance();

            // get mysql version
            $mysql_version = $db->getValue('SELECT VERSION() as mysql_version');
            $mysql_version = preg_replace('/[^0-9\.]*/', '', $mysql_version);
            if (version_compare($mysql_version, '5.5.3', '>=')) {
                $default_charset = 'utf8mb4';
                $default_collation = 'utf8mb4_general_ci';
            } else {
                $default_charset = 'utf8';
                $default_collation = 'utf8_general_ci';
            }

            $sql= str_replace(
                array('PREFIX_', '_CHARSET_', '_COLLATION_'),
                array(_DB_PREFIX_, $default_charset, $default_collation),
                $sql
            );

            $sql = preg_split("/;\s*[\r\n]+/", $sql);
            foreach ($sql as $query) {
                $query = trim($query);
                if ($query) {
                    if (!$db->Execute($query)) {
                        throw new Exception($db->getMsgError().' '.$query);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Update mails subjects translations files
     *
     * $translations = array(
     *     'fr' => array(
     *         'Your cart' => 'Votre panier',
     *         'Your order => 'Votre commande'
     *     ),
     *     'es' => array(
     *         'Your cart' => 'Su carrito',
     *         'Your order => 'Su pedido'
     *     )...
     * );
     * @param array $translations subjects
     */
    public function setEmailSubjectTranslation($translations)
    {
        $langmail = '_LANGMAIL'; // for validator
        global $$langmail;
        if (isset($$langmail)) {
            $backup_langmail = $$langmail;
        } else {
            $backup_langmail = array();
        }

        foreach ($translations as $iso_lang => $subjects) {
            $file = _PS_MAIL_DIR_.$iso_lang.'/lang.php';
            if (file_exists($file)) {
                include($file);
                $$langmail = array_merge($$langmail, $subjects);
                $this->writeSubjectTranslationFile($$langmail, $file);
            }
            $file = _PS_THEME_DIR_.'mails/'.$iso_lang.'/lang.php';
            if (file_exists($file)) {
                include($file);
                $$langmail = array_merge($$langmail, $subjects);
                $this->writeSubjectTranslationFile($$langmail, $file);
            }
        }
        $$langmail = $backup_langmail;
        return true;
    }

    protected function writeSubjectTranslationFile($sub, $path)
    {
        if (file_exists($path) && ($fd = fopen($path, 'w'))) {
            $tab = 'LANGMAIL';
            fwrite($fd, "<?php\n\nglobal \$_".$tab.";\n\$_".$tab." = array();\n");

            foreach ($sub as $key => $value) {
                $value = $this->stripSlashes($value);
                fwrite($fd, '$_'.$tab.'[\''.pSQL($key).'\'] = \''.pSQL($value).'\';'."\n");
            }

            fwrite($fd, "\n?>");
            fclose($fd);
        }
    }

    /**
     * addJS load a javascript file in the header
     * Idem than Tools::addJS with Prestashop < 1.4 compatibility
     *
     * @param mixed $js_uri
     * @return void/string for order.php and Prestashop < 1.4
     */
    public function addJS($js_uri)
    {
        $result = '';
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            $context = Context::getContext();
            $context->controller->addJS($js_uri);
        } elseif (method_exists('Tools', 'addJS')) {
            // prestashop 1.4
            Tools::addJS($js_uri);
        } else {
            global $js_files; // compatibility prestashop 1.3

            if (!isset($js_files)) {
                $js_files = array();
            }
            // avoid useless operation...
            if (!in_array($js_uri, $js_files)) {
                // detect mass add
                if (!is_array($js_uri) && !in_array($js_uri, $js_files)) {
                    $js_uri = array($js_uri);
                } else {
                    foreach ($js_uri as $key => $js) {
                        if (in_array($js, $js_files)) {
                            unset($js_uri[$key]);
                        }
                    }
                }

                //overriding of modules js files
                foreach ($js_uri as $key => &$file) {
                    if (!preg_match('/^http(s?):\/\//i', $file)) {
                        $different = 0;
                        $override_path = str_replace(
                            __PS_BASE_URI__.'modules/',
                            _PS_ROOT_DIR_.'/themes/'._THEME_NAME_.'/js/modules/',
                            $file,
                            $different
                        );
                        if ($different && file_exists($override_path)) {
                            $file = str_replace(
                                __PS_BASE_URI__.'modules/',
                                __PS_BASE_URI__.'themes/'._THEME_NAME_.'/js/modules/',
                                $file,
                                $different
                            );
                        } else {
                            // remove PS_BASE_URI on _PS_ROOT_DIR_ for the following
                            $url_data = parse_url($file);
                            $file_uri = _PS_ROOT_DIR_.$this->strReplaceOnce(
                                __PS_BASE_URI__,
                                DIRECTORY_SEPARATOR,
                                $url_data['path']
                            );
                            // check if js files exists
                            if (!file_exists($file_uri)) {
                                unset($js_uri[$key]);
                            }
                        }
                    }
                }

                $fileindex = basename($_SERVER['PHP_SELF']);
                if ($fileindex == 'order.php') {
                    foreach ($js_uri as $key => &$file) {
                        $result .= '<script type="text/javascript" src="'.$file.'"></script>';
                    }
                } else {
                    // adding file to the big array...
                    $js_files = array_merge($js_files, $js_uri);
                }
            }
        }
        return $result;
    }

    /**
     * addCSS allows you to add stylesheet at any time.
     * Idem than Tools::addCSS with Prestashop < 1.4 compatibility
     *
     * @param mixed $css_uri
     * @param string $css_media_type
     * @return void
     */
    public function addCSS($css_uri, $css_media_type = 'all')
    {
        $result = '';
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            $context = Context::getContext();
            $context->controller->addCSS($css_uri, $css_media_type);
        } elseif (method_exists('Tools', 'addCSS')) {
            // prestashop 1.4
            Tools::addCSS($css_uri, $css_media_type = 'all');
        } else {
            // prestashop 1.3
            if (is_array($css_uri)) {
                foreach ($css_uri as $file => $media_type) {
                    $this->addCSS($file, $media_type);
                }
            } else {
                //overriding of modules css files
                $different = 0;
                $override_path = str_replace(
                    __PS_BASE_URI__.'modules/',
                    _PS_ROOT_DIR_.'/themes/'._THEME_NAME_.'/css/modules/',
                    $css_uri,
                    $different
                );
                if ($different && file_exists($override_path)) {
                    $css_uri = str_replace(
                        __PS_BASE_URI__.'modules/',
                        __PS_BASE_URI__.'themes/'._THEME_NAME_.'/css/modules/',
                        $css_uri,
                        $different
                    );
                } else {
                    // remove PS_BASE_URI on _PS_ROOT_DIR_ for the following
                    $url_data = parse_url($css_uri);
                    $file_uri = _PS_ROOT_DIR_.$this->strReplaceOnce(
                        __PS_BASE_URI__,
                        DIRECTORY_SEPARATOR,
                        $url_data['path']
                    );
                    // check if css files exists
                    if (!file_exists($file_uri)) {
                        return;
                    }
                }

                $fileindex = basename($_SERVER['PHP_SELF']);
                if ($fileindex == 'order.php') {
                    $result .= '<link href="'.$css_uri.'" rel="stylesheet"
                        type="text/css" media="'.$css_media_type.'" />';
                } else {
                    // detect mass add
                    $css_uri = array($css_uri => $css_media_type);

                    global $css_files; // compatibility prestashop 1.3

                    // adding file to the big array...
                    if (is_array($css_files)) {
                        $css_files = array_merge($css_files, $css_uri);
                    } else {
                        $css_files = $css_uri;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Idem than Tools::str_replace_once
     * With Prestashop < 1.4 compatibility
     *
     * @param  [type] $needle   [description]
     * @param  [type] $replace  [description]
     * @param  [type] $haystack [description]
     * @return [type]           [description]
     */
    public function strReplaceOnce($needle, $replace, $haystack)
    {
        if (method_exists('Tools', 'str_replace_once')) {
            return Tools::str_replace_once($needle, $replace, $haystack);
        } else {
            $pos = strpos($haystack, $needle);
            if ($pos === false) {
                return $haystack;
            }
            return substr_replace($haystack, $replace, $pos, Tools::strlen($needle));
        }
    }

    /**
     * Smarty unescape modifier plugin
     *
     * Type:     modifier<br>
     * Name:     unescape<br>
     * Purpose:  unescape html entities
     *
     * @author Rodney Rehm
     * @param array $params parameters
     * @return string with compiled code
     */
    public function smartyModifiercompilerUnescape($string, $esc_type = 'html', $char_set = 'UTF-8')
    {
        switch (trim($esc_type, '"\'')) {
            case 'entity':
            case 'htmlall':
                if (function_exists('mb_convert_encoding')) {
                    return mb_convert_encoding($string, $char_set, 'HTML-ENTITIES');
                }

                return html_entity_decode($string, ENT_NOQUOTES, $char_set);
            case 'html':
                return htmlspecialchars_decode($string, ENT_QUOTES);
            case 'url':
                return rawurldecode($string);
            default:
                return $string;
        }
    }

    /**
    * compatibility Prestashop 1.4
    **/
    public function copy($source, $destination, $stream_context = null)
    {
        if (method_exists('Tools', 'copy')) {
            return Tools::copy($source, $destination, $stream_context);
        } elseif (is_null($stream_context) && !preg_match('/^https?:\/\//', $source)) {
            return @copy($source, $destination);
        }
        return @file_put_contents($destination, $this->fileGetContents($source, false, $stream_context));
    }
}
