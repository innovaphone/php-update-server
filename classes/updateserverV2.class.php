<?php

class LessSimpleXMLElement extends SimpleXMLElement {

    /**
     * get an attribute from xml, cares against "not set" issues and returns a default if so, converts from utf8
     * @param SimpleXMLElement $xml
     * @param string $name attribute's name
     * @param string $def default value
     * @return string
     */
    static function getAttributeFromXML($xml, $name, $def = null) {
        $attrs = $xml->attributes();
        return (isset($attrs->$name)) ? utf8_decode((string) $attrs->$name) : $def;
    }

    /**
     * insert a complete child tag ($new) in to an existing ($root) XML object
     * @param SimpleXMLElement $root
     * @param SimpleXMLElement $new
     */
    static function addChildFromXml($root, $new) {
        $node = $root->addChild($new->getName(), (string) $new);
        foreach ($new->attributes() as $attr => $value) {
            $node->addAttribute($attr, $value);
        }
        foreach ($new->children() as $ch) {
            LessSimpleXMLElement::addChildFromXml($node, $ch);
        }
        return $node;
    }

    /**
     * merge SimpleXMLElement element into this element (not generic, very special, only merges first level)
     * @param SimpleXMLElement $add
     * @param string[] $unique array of tags which allow no duplicate subtags
     */
    function mergeXML(SimpleXMLElement $add, $unique = array()) {
// merge in first level objects
        foreach ($add as $a => $b) {
            if (in_array($a, $unique) && isset($this->$a)) {
                // if it is a tag with uniq sub-tags, forget existing ones before merging in
                foreach ($b as $x => $y) {
                    if (isset($this->$a->$x)) {
                        unset($this->$a->$x);
                    }
                    $this->addChildFromXml($this->$a, $y);
                }
            } else {
                $this->addChildFromXml($this, $b);
            }
        }
// merge in attributes
        foreach ($add->attributes() as $name => $value) {
            $this[(string) $name] = (string) $value;
        }
    }

}

class UpdateServerV2 {

    static $dead = false;

    /**
     * fatal error, give message and die
     * @param string $msg
     */
    static function bailout($msg) {
        print "# $msg - exit\r\n";
        if (self::$dieOnBailout)
            die();
        self::$dead = true;
    }

    /**
     * class internal
     * @param type $error
     * @param type $label
     * @param type $prot
     * @param type $boot
     * @param type $hw
     * @return \SimpleXMLElement
     */
    private function makeReturn($error, $label = null, $prot = null, $boot = null, $hw = null) {
        $r = new SimpleXMLElement('<result/>');
        if ($label !== false && $label !== null) {
            $r->label = $label;
        }
        if ($prot !== false && $prot !== null) {
            $r->prot = $prot;
        }
        if ($boot !== false && $boot !== null) {
            $r->boot = $boot;
        }
        if ($hw !== false && $hw !== null) {
            $r->hw = $hw;
        }
        if ($error !== null) {
            $r->error = $error;
        }
        return $r;
    }

    /**
     * class internal
     * @param string $infoUrl 
     * @param bool $save
     * @return SimpleXMLElement
     */
    private function getPbxInfo($infoUrl, $save = null) {
        $xml = @file_get_contents($infoUrl, false);
        if ($xml === false)
            return $this->makeReturn("cannot access " . $infoUrl);
        try {
            $pxml = @new SimpleXMLElement($xml);
        } catch (Exception $e) {
            return $this->makeReturn("result from $infoUrl not parsable");
        }
        if (!isset($pxml->sys))
            return $this->makeReturn("no <sys> tag in PBX info");
        if (!isset($pxml->sys['version']))
            return $this->makeReturn("no version attribute in <sys> tag in PBX info");
        $version = $pxml->sys['version'];
        if (($prot = preg_match('/(?<label>.*)\[(?<prot>[^]]*)\].*\[(?<boot>[^]]*)\].*\[(?<hw>[^]]*)\].*/', $version, $m)) != 1)
            return $this->makeReturn("cannot parse box info '$version'");
        $m['prot'] = str_replace('.', '', $m['prot']);
        if ($save !== null)
            $this->cachePbxInfo($pxml);
        return $this->makeReturn(null, $m['label'], $m['prot'], $m['boot'], $m['hw']);
    }

    var $cachefile = "cache/master-info.xml";
    var $cacheexpire = 3600;  // one hour

    function getOnlinePbxInfo() {
        $r = ($this->getPbxInfo((string) $this->xmlconfig->master['info'], $this->cachefile));
        $r->time = date('Y-m-d H:i:s');
        return $r;
    }

    function getCachedPbxInfo() {
        $r = ($this->getPbxInfo($this->cachefile));
        if (!isset($r->error)) {
            $r->time = date('Y-m-d H:i:s', filemtime($this->cachefile));
        } else {
            $r->time = 0;
        }
        return $r;
    }

    function cachePbxInfo(SimpleXMLElement $r) {
        $cachedir = dirname($this->cachefile);
        if (!is_dir($cachedir))
            @mkdir($cachedir, 0777, true);
        if (@file_put_contents($this->cachefile, $r->asXML()) === false)
            self::bailout("cannot write to cache file $this->cachefile");
    }

    function cacheIsCurrent($delta = null) {
        if ($delta === null)
            $delta = $this->cacheexpire;
        return file_exists($this->cachefile) && ((time() - filemtime($this->cachefile)) < $delta);
    }

    function getGrace() {
        if (isset($this->xmlconfig->times['grace']))
            return (int) (string) $this->xmlconfig->times['grace'];
        else
            return 900;
    }

    function getInterval() {
        if (isset($this->xmlconfig->times['interval']))
            return (int) (string) $this->xmlconfig->times['interval'];
        else
            return 15;
    }

// read config file
    const defaultConfigFile = './config.xml';
    const userConfigFile = './user-config.xml';
    const dvlUserConfigFile = './user-config-dvl.xml';

    var $xmlconfig = null;
    var $xmluserconfig = null;
    var $baseurl;

    private function mergeConfigs($lcfg = null, $rcfg = null, $level = 0) {
        $levelstring = substr("...........", 0, $level);
        if ($lcfg === null || $rcfg === null) {
            return;
        }
        // merge in body
        if ($rcfg->count() == 0 && $lcfg->count() == 0) {
            $lcfg[0] = (string) $rcfg;
        }
        // merge in attributes
        foreach ($rcfg->attributes() as $var => $value) {
            if (isset($lcfg[$var])) {
                $act = "overwriting(old value={$lcfg[$var]})";
            } else {
                $act = "adding";
            }
            $lcfg[$var] = $value;
        }
        /* merge in subtags
         * modes: 
         *  a) if lcfg has no such tag, add it
         *  b) if lcfg has such a tag and it has an id attribute, overwrite the one with the same id, otherwise add it
         *  c) if lcfg has a single subtag of this type, overwrite it
         *  d) if lfcg has multiple subtags of this type, add it
         */
        foreach ($rcfg as $tag => $body) {
            $left = null;
            if (!isset($lcfg->$tag)) { // a)
                $act = "inserting";
                $left = $lcfg->addChild($tag);
            } else {
                $ltag = $lcfg->$tag;  // this peeks at index 0 implicitly
                if (isset($ltag['id'])) { // b
                    if (!isset($body['id']))
                        self::bailout("must have 'id' attribute in '$tag' tag in user-config.xml");
                    foreach ($lcfg->$tag as $lrun) {
                        if ((string) $lrun['id'] == (string) $body['id']) {
                            $left = $lrun;
                            $act = "merge into id='{$body['id']}'";
                            break;
                        }
                    }
                    if ($left === null) {
                        $act = "adding";
                        $left = $lcfg->addChild($tag);
                    }
                } else {
                    if (count($lcfg->$tag) > 1 || count($rcfg->$tag) > 1) { // d
                        $left = $lcfg->addChild($tag);
                        $act = "adding (left has multiple '$tag')";
                    } else { // c
                        $act = "merging (left has no or 1 '$tag')";
                        $left = $lcfg->$tag;
                    }
                }
            }
            // print "$levelstring$act tag '$tag'\r\n<br>";
            $this->mergeConfigs($left, $body, $level + 1);
        }
        if (!$level) {  // end of last recursion. see if we need to sort
            // sort me if necessary
            foreach ($lcfg as $level1) {
                $tosort = array();
                foreach ($level1 as $tagname => $tagbody) {
                    if (isset($tagbody['seq'])) {
                        $tosort[$tagname] = 1;
                    }
                }
                if (count($tosort)) {
                    foreach (array_keys($tosort) as $subtag) {
                        $mem = array();
                        foreach ($level1->$subtag as $key => $body) {
                            $mem[(int) $body['seq']] = clone $body;
                        }
                        unset($level1->$subtag);
                        ksort($mem);  // sort by 'seq' (in key)
                        $i = 0;
                        foreach ($mem as $remembered) {
                            LessSimpleXMLElement::addChildFromXml($level1, $remembered);
                        }
                    }
                }
            }
        }
    }

    private function readConfig() {
        try {
            $this->xmlconfig = @new SimpleXMLElement(file_get_contents(self::defaultConfigFile));
        } catch (Exception $e) {
            self::bailout("cannot parse " . self::defaultConfigFile . ": " . $e->getMessage());
            return;
        }
        $userconfig = null;
        foreach (
        array(
            self::dvlUserConfigFile,
            self::userConfigFile,
        ) as $ucf) {
            if (is_file($ucf)) {
                try {
                    $this->xmluserconfig = @new SimpleXMLElement(file_get_contents($ucf));
                } catch (Exception $e) {
                    self::bailout("cannot parse " . $ucf . ": " . $e->getMessage());
                    return;
                }
                break;
            }
        }

        $debugmerge = LessSimpleXMLElement::getAttributeFromXML($this->xmluserconfig, 'debugmerge', "false") == "true";
        if ($debugmerge)
            file_put_contents("before.xml", $this->xmlconfig->asXML());
        $this->mergeConfigs($this->xmlconfig, $this->xmluserconfig);
        if ($debugmerge)
            file_put_contents("after.xml", $this->xmlconfig->asXML());

        $ids = array();
        if (isset($this->xmlconfig->phases->phase)) {
            foreach ($this->xmlconfig->phases->phase as $key => $value) {
                if ($value['id'] == "all")
                    self::bailout("config: phase name '{$value['id']}' not allowed");
                if (strpos($value['id'], "-") !== false)
                    self::bailout("config: phase named '{$value['id']}': no dash allowed in name");
                if (isset($ids[(string) $value['id']]))
                    self::bailout("config: phase named '{$value['id']}': duplicate definition");
                $ids[(string) $value['id']] = 1;
            }
        }
        $ids = array();
        if (isset($this->xmlconfig->environments->environment)) {
            foreach ($this->xmlconfig->environments->environment as $key => $value) {
                if ($value['id'] == "all")
                    self::bailout("config: environment name '{$value['id']}' not allowed");
                if (strpos($value['id'], "-") !== false)
                    self::bailout("config: environment named '{$value['id']}': no dash allowed in name");
                if (isset($ids[(string) $value['id']]))
                    self::bailout("config: environment named '{$value['id']}': duplicate definition");
                $ids[(string) $value['id']] = 1;
            }
        }
        $ids = array();
        if (isset($this->xmlconfig->classes->class)) {
            foreach ($this->xmlconfig->classes->class as $key => $value) {
                if ($value['id'] == "all")
                    self::bailout("config: class name '{$value['id']}' not allowed");
                if (strpos($value['id'], "-") !== false)
                    self::bailout("config: class named '{$value['id']}': no dash allowed in name");
                if (isset($ids[(string) $value['id']]))
                    self::bailout("config: class named '{$value['id']}': duplicate definition");
                $ids[(string) $value['id']] = 1;
            }
        }
        if (!isset($this->xmlconfig->master['info']))
            self::bailout("config: master tag or info attribute missing");
        if (!isset($this->xmlconfig->fwstorage['url']))
            self::bailout("config: fwstorage tag or url attribute missing");
        $fwsurl = (string) $this->xmlconfig->fwstorage['url'];
        $fwsurl = $this->makeFullUrl($fwsurl);
        $this->xmlconfig->fwstorage['url'] = $fwsurl;
        if (count($this->xmlconfig->phases->phase) <= 0)
            self::bailout("config: need at least one phase");
        if (count($this->xmlconfig->environments->environment) <= 0)
            self::bailout("config: need at least one environment");
        if (isset($this->xmlconfig->master['expire']))
            $this->cacheexpire = (int) (string) $this->xmlconfig->master['expire'];
        if (isset($this->xmlconfig->master['cache']))
            $this->cachefile = (string) $this->xmlconfig->master['cache'];
        if (isset($this->xmlconfig->environments->environment))
            foreach ($this->xmlconfig->environments->environment as $env) {
                foreach ($env->implies as $imp) {
                    $imp = trim($imp);
                    if (!$this->checkEnvironment($imp))
                        self::bailout("implication '$imp' in '{$env['id']}' must be a defined environment");
                }
            }
        $this->statedir = null;
        if (isset($this->xmlconfig->status) && isset($this->xmlconfig->status['dir'])) {
            $this->statedir = (string) $this->xmlconfig->status['dir'];
            $this->stateexpire = isset($this->xmlconfig->status['expire']) ? (int) $this->xmlconfig->status['expire'] : 0;
            $this->statemissing = isset($this->xmlconfig->status['missing']) ? (int) $this->xmlconfig->status['missing'] : 0;
        }
        $this->certdir = null;
        if (isset($this->xmlconfig->customcerts) && isset($this->xmlconfig->customcerts['dir']) && $this->xmlconfig->customcerts['dir'] != "") {
            $this->certdir = (string) $this->xmlconfig->customcerts['dir'];
            $this->certRootBaseFn = $this->certdir . "/CA";
            if (isset($_REQUEST['sn'])) {
                $this->certbasefn = $this->certdir . "/" . ($sn = $_REQUEST['sn']);
                $this->certRequestKey = $this->certbasefn . "-request.der.key";
                $this->certRequestPemFn = $this->certbasefn . "-request.p10.pem";
                $this->certRequestDerFn = $this->certbasefn . "-request.p10.der";
                $this->certResponseErrorFn = $this->certbasefn . "-requesterror";
                $this->certResponseFn = $this->certbasefn . "-signedrequest.cer";
            } else {
                $this->certbasefn = null;
            }
        } else {
            unset($this->xmlconfig->customcerts);
        }
        // kill some empty vars
        if (isset($this->xmlconfig->times['allow']) && $this->xmlconfig->times['allow'] == "")
            unset($this->xmlconfig->times['allow']);
        if (isset($this->xmlconfig->times['initial']) && $this->xmlconfig->times['initial'] == "")
            unset($this->xmlconfig->times['initial']);
        if (isset($this->xmlconfig->times['polling']) && $this->xmlconfig->times['polling'] == "")
            unset($this->xmlconfig->times['polling']);
        if (isset($this->xmlconfig->times['interval']) && $this->xmlconfig->times['interval'] == "")
            unset($this->xmlconfig->times['interval']);
        if (isset($this->xmlconfig->times['httpsport']) && $this->xmlconfig->times['httpsport'] == "")
            unset($this->xmlconfig->times['httpsport']);

        // create required directories
        $dirs2create = array(
            dirname($this->cachefile) => 0700,
            "fw" => 0755,
        );
        if (isset($this->xmlconfig->fwstorage) && isset($this->xmlconfig->fwstorage['url'])) {
            $dirs2create[(string) $this->xmlconfig->fwstorage['url']] = 0700;
        }

        if (isset($this->xmlconfig->backup) && isset($this->xmlconfig->backup['dir'])) {
            $dirs2create[(string) $this->xmlconfig->backup['dir']] = 0700;
        }
        if (isset($this->xmlconfig->status) && isset($this->xmlconfig->status['dir'])) {
            $dirs2create[(string) $this->xmlconfig->status['dir']] = 0700;
        }
        if (isset($this->xmlconfig->customcerts) && isset($this->xmlconfig->customcerts['dir'])) {
            $dirs2create[(string) $this->xmlconfig->customcerts['dir']] = 0700;
        }
        if (isset($this->xmlconfig->times) && isset($this->xmlconfig->times['dir'])) {
            $dirs2create[(string) $this->xmlconfig->times['dir']] = 0700;
        } else {
            $this->xmlconfig->times['dir'] = 'scripts';
        }
        $this->scriptdir = (string) $this->xmlconfig->times['dir'];
        $dirs2create["web"] = 0755;
        $dirs2create["classes"] = 0700;
        $dirs2create["admin"] = 0700;
        $files2check = array();
        $files2check["config.xml"] = 0700;
        $files2check["user-config.xml"] = 0700;

        foreach ($dirs2create as $dir => $mode) {
            if (!empty($dir) && !self::isUrl($dir)) {
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
                chmod($dir, $mode);
            }
        }
        foreach ($files2check as $file => $mode) {
            if (!empty($file) && !self::isUrl($file) && is_file($file)) {
                chmod($file, $mode);
            }
        }
    }

    public function getPossibleScripts(&$newestfiletime, &$newestfile, $sn, $classes, $phase, $envs) {
        $allphases = $allclasses = $allenvironments = array();
        $this->logInit($sn, false);
        $files = array();

        $allclasses = $classes;
        if (count($this->xmlconfig->classes->class) > 1)
            array_unshift($allclasses, "all");

        $allphases = array($phase);
        if (count($this->xmlconfig->phases->phase) > 1)
            array_unshift($allphases, "all");

        $allenvironments = $envs;
        if ($this->devicescript != null)
            array_unshift($allenvironments, $this->devicescript);
        if (count($this->xmlconfig->environments->environment) > 1)
            array_unshift($allenvironments, "all");

        $now = time();
        $newestfiletime = 0;
        $newestfile = "unknown";
        foreach ($allphases as $p) {
            $p = strtolower($p);
            foreach ($allclasses as $c) {
                $c = strtolower($c);
                foreach ($allenvironments as $e) {
                    $e = strtolower($e);
                    $f = $this->scriptdir . "/$p-$c-$e.txt";
                    if (is_readable($f)) {
                        $thistime = filemtime($f);
                        $files[$f] = $thistime;
                        if ($thistime > $newestfiletime) {
                            $newestfiletime = $thistime;
                            $newestfile = $f;
                        }
                    } else {
                        $files[$f] = "does not exist";
                    }
                }
            }
        }
        return $files;
    }

    private static function isUrl($url) {
        return strpos($url, "://");
    }

    // query args carrying state
    private $stateargs = array(
        "polling" => null,
        "phase" => null,
            /*
              "type" => null,
              "sn" => null,
              "hwid" => null,
              "ip" => null,
             */
    );
    private $statechanged = false;

    /**
     * change a state arg
     * @param string $key
     * @param string $value
     * @return boolean true if value changed
     */
    public function setStateArg($key, $value) {
        if (!key_exists($key, $this->stateargs)) {
            $this->stateargs[$key] = null;
        }
        if ($this->stateargs[$key] != $value) {
            $this->stateargs[$key] = $value;
            $this->statechanged = true;
            return true;
        }
        return false;
    }

    /**
     * return new state args if they changed, otherwise return false
     * @return boolean | sring[]
     */
    public function getChangedStateArgs() {
        if ($this->statechanged)
            return $this->stateargs;
        return false;
    }

    private static $dieOnBailout = true;

    public function __construct($baseurl, $dieOnBailout = true) {
        self::$dieOnBailout = $dieOnBailout;
        $this->baseurl = $baseurl;
        $this->readConfig();
        foreach ($this->stateargs as $key => $value) {
            $this->stateargs[$key] = (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
        }
    }

    public function firstPhase() {
        return (string) $this->xmlconfig->phases->phase[0]['id'];
    }

    public function firstEnvironment() {
        return (string) $this->xmlconfig->environments->environment[0]['id'];
    }

    public function nextPhase($phase) {
        $found = false;
        foreach ($this->xmlconfig->phases->phase as $sp) {
            if ($sp['id'] == $phase) {
                $found = true;
            } else if ($found)
                return (string) $sp['id'];
        }
        return null;
    }

    public function checkPhase($phase) {
        $found = false;
        foreach ($this->xmlconfig->phases->phase as $sp) {
            if ($sp['id'] == $phase) {
                return true;
            }
        }
        return false;
    }

    public function checkEnvironment($env, $returnobj = false) {
        $found = false;
        foreach ($this->xmlconfig->environments->environment as $sp) {
            if ($sp['id'] == $env) {
                return $returnobj ? $sp : true;
            }
        }
        return false;
    }

    public function expandEnvironment($env) {
        $implied = array($env);
        $envxml = $this->checkEnvironment($env, true);
        foreach ($envxml->implies as $imp)
            $implied[] = trim($imp);
        return $implied;
    }

    public function getClasses($type) {
        $classes = array();
        $thisclass = null;
        $type = strtolower($type);
        foreach ($this->xmlconfig->classes->class as $i => $sp) {
            $thisclass = (string) $sp['id'];
            foreach ($sp->model as $m) {
                if (strtolower((string) $m) == $type)
                    $classes[$thisclass] = 1;
            }
        }
        // add device type as implicit class
        $classes[$type] = 1;
        return array_keys($classes);
    }

    public function getFWStorage($build, $model, $filetype) {
        $spec = $this->xmlconfig->fwstorage['url'];
        $spec = str_replace('{build}', $build, $spec);
        $spec = str_replace('{model}', $model, $spec);
        $spec = str_replace('{filetype}', $filetype, $spec);

        return $spec;
    }

    public function mapStdArg($key, $value) {
        foreach ($this->xmlconfig->stdargs->stdarg as $sp) {
            if ($sp['key'] == $key) {
                if (isset($sp['value']))
                    return (string) $sp['value'];
                else
                    return null;
            }
        }
        return rawurlencode($value);
    }

    public function getTimes() {
        if (!isset($this->xmlconfig->times) ||
                (!isset($this->xmlconfig->times['allow']) && !isset($this->xmlconfig->times['initial'])))
            return null;
        $times = "mod cmd UP1 times ";
        if (isset($this->xmlconfig->times['allow']))
            $times .= " /allow {$this->xmlconfig->times['allow']}";
        if (isset($this->xmlconfig->times['initial']))
            $times .= " /initial {$this->xmlconfig->times['initial']}";
        return $times;
    }

    public function getFilesSignature($files) {
        if (!isset($this->xmlconfig->times['check']) ||
                (string) $this->xmlconfig->times['check'] != "true")
            return null;
        $fs = "";
        // add all file contents, removing white space
        foreach ($files as $f) {
            if (($handle = @fopen($f, "r")) === false)
                continue;
            while (!feof($handle)) {
                $line = trim(fgets($handle));
                if ($line == "")
                    continue;
                $fs .= $line;
            }
            fclose($handle);
        }
        return hash("md5", $fs);
    }

    public function getPolling() {
        if (isset($this->xmlconfig->times['polling'])) {
            return (string) $this->xmlconfig->times['polling'];
        } else {
            return 0;
        }
    }

    /**
     * this function is used (like in $cafunc = "requestCertificate_" . $this->xmlconfig->customcerts['CAtype'];)
     * @return array
     */
    private function requestCertificate_manual() {
        return array("nop" => "# nop");
    }

    private static function CertificateCompareEx(SimpleXMLElement $a, SimpleXMLElement $b) {
        if ((string) $a['issuer_cn'] == (string) $b['subject_cn'])
            return 1;
        if ((string) $b['issuer_cn'] == (string) $a['subject_cn'])
            return -1;
        return strcmp($a['subject_cn'], $b['subject_cn']);
    }

    private static function CertificateCompare(SimpleXMLElement $a, SimpleXMLElement $b) {
        $r = self::CertificateCompareEx($a, $b);
        // print "returns $r\r\n";
        return $r;
    }

    /**
     * returns the device certificate list sorted so that the issuer comes first
     * @return \SimpleXMLElement
     */
    private function getCertificates() {
        if (!isset($this->statexml->queries->certificates->info->servercert) ||
                !isset($this->statexml->queries->certificates->info->servercert->certificate)) {
            return new SimpleXMLElement('<certificate subject_cn=""-- no CN --" issuer_cn=""-- no CA --" />');
        }
        $cas = array();
        foreach ($this->statexml->queries->certificates->info->servercert->certificate as $c) {
            $cas[] = $c;
        }
        usort($cas, "self::CertificateCompare");
        return ($cas);
    }

    public static function str2dnsname($str) {
        if ($str === null)
            return null;
        return strtolower(preg_replace('/[^-a-z0-9]+/i', '-', $str));
    }

    /**
     * 
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @param boolean $undef out-par set to false if $search wa present in $subject 
     * @return string
     */
    private static function myReplace($search, $replace, $subject, &$undef) {
        $r = str_replace($search, $replace, $subject);
        if ($replace === null && $r != $subject)
            $undef = false;
        return $r;
    }

    private function replaceMagics($src) {
        /*
          {realip} - the devices ETH0 IP address
          {ip} - the devices IP address as seen by the update server (may differ if device is behinfd NAT=
          {proxy} - the IP of the reverse proxy the device comes through (may be empty if no proxy)
          {sn} - serial number like "0090333000af"
          {hwid} - hardward id like "IP232-30-00-af"
          {name} - name (as taken from General/Admin/Device Name), all non-DNS-name characters replaced by '-'
          {rdns} - dns name as retrieved by a reverse DNS lookup for the {realip}
         */
        $matched = true;
        $src = self::myReplace('{realip}', LessSimpleXMLElement::getAttributeFromXML($this->statexml->device, 'realip'), $src, $matched);
        $src = self::myReplace('{ip}', LessSimpleXMLElement::getAttributeFromXML($this->statexml->device, 'ip'), $src, $matched);
        $src = self::myReplace('{proxy}', (string) $this->statexml->device['rp'] == 'true' ?
                        LessSimpleXMLElement::getAttributeFromXML($this->statexml->device, 'proxy') : (string) $this->statexml->device['proxy'], $src, $matched);
        $src = self::myReplace('{sn}', str_replace('-', '', LessSimpleXMLElement::getAttributeFromXML($this->statexml->device, 'sn')), $src, $matched);
        $src = self::myReplace('{hwid}', LessSimpleXMLElement::getAttributeFromXML($this->statexml->device, 'hwid'), $src, $matched);
        $name = LessSimpleXMLElement::getAttributeFromXML(
                        $this->statexml->queries->admin->info, 'name');
        if ($name == "")
            $name = null;
        $src = self::myReplace('{name}', self::str2dnsname($name), $src, $matched);
        if (($dns = gethostbyaddr($this->statexml->device['realip'])) !== false) {
            $src = self::myReplace('{rdns}', $dns, $src, $matched);
        } else {
            $src = self::myReplace('{rdns}', null, $src, $matched);
        }

        if (!$matched)
            return "";  // return empty string if a used replacement was undefined
        return $src;
    }

    public static function var2bin($var) {
        $bytes = "";
        while (strlen($var) > 0) {
            sscanf($var[0] . $var[1], "%x", $x);
            $bytes .= chr($x);
            $var = substr($var, 2);
        }
        return $bytes;
    }

    public static function bin2var($bytes) {
        $out = "";
        for ($i = 0; $i < strlen($bytes); $i++) {
            $byte = ord($bytes[$i]);
            $out .= sprintf("%02x", $byte);
        }
        return $out;
    }

    static function der2pem($der) {
        $base = base64_encode($der);
        $out = "-----BEGIN CERTIFICATE REQUEST-----\r\n";
        $out .= chunk_split($base, 64);
        $out .= "-----END CERTIFICATE REQUEST-----\r\n";
        return $out;
    }

    public function createPemFromDer($der, $derfile) {
        if (@file_put_contents($this->certRequestPemFn, self::der2pem(file_get_contents($this->certRequestDerFn))) === false)
            return "@file_put_contents failure";
        return null;
    }

    public static function isNameInSubfolder($name) {
        return !preg_match('@(/\.\.$)|(^\.\.$)|(^\.\./)|(/\.\./)@', $name);
    }

    var $certdir = null;
    var $scriptdir = null;
    var $certbasefn = null;
    var $certRootBaseFn = null;
    var $certRequestDerFn = null;
    var $certResponseErrorFn = null;
    var $certRequestPemFn = null;
    var $certRequestKey = null;
    var $certResponseFn = null;

    private function dumpVar2File($var, $file, $proc = null, $postproc = null) {
        if ($proc !== null)
            $proc = "&proc=$proc";
        if ($postproc !== null)
            $postproc = "&postproc=$postproc";
        return "mod cmd UP0 scfg " .
                $this->makeFullUrl("update.php") .
                "?mode=vardump&sn=#m&var=$var&file=" .
                rawurlencode($file) .
                "$proc$postproc ser nop /always " .
                rawurlencode("vars read $var");
    }

    // get raw binary certificate either from DER file (just gets file contents) or PEM file
    public function getCertFromFile($file) {
        if (($response = file_get_contents($file)) === false) {
            self::bailout("cannot read $file");
        }
        if (stripos($response, "-----BEGIN CERTIFICATE-----") !== false) {
            $copyout = false;
            $lines = preg_split("/(\r\n|\n|\r)/", $response);
            $response = null;
            foreach ($lines as $l) {
                if (stripos($l, "-----BEGIN CERTIFICATE-----") !== false) {
                    $copyout = true;
                } else if (stripos($l, "-----END CERTIFICATE-----") !== false) {
                    break;
                } else if ($copyout) {
                    $response .= "$l";
                }
            }
            // convert to DER
            $response = base64_decode($response);
        }
        return $response;
    }

    private function cleanupCertificateStatus(&$msgs, &$cmds) {
        $hascmd = false;
        if (isset($this->certRequestDerFn) && is_file($this->certRequestDerFn)) {
            $msgs[] = "removing CSR/DER {$this->certRequestDerFn}";
            @unlink($this->certRequestDerFn);
        }
        if (isset($this->certRequestPemFn) && is_file($this->certRequestPemFn)) {
            $msgs[] = "removing CSR/PEM {$this->certRequestPemFn}";
            @unlink($this->certRequestPemFn);
        }
        if (isset($this->certResponseFn) && is_file($this->certResponseFn)) {
            $msgs[] = "removing signed CSR {$this->certResponseFn}";
            @unlink($this->certResponseFn);
        }
        if (isset($this->certRequestKey) && is_file($this->certRequestKey)) {
            $msgs[] = "removing CSR Key {$this->certRequestKey}";
            @unlink($this->certRequestKey);
        }
        if (isset($this->statexml->queries->certificates->info->requests->request)) {
            $cmds["removing CSR from device"] = "vars del X509/REQUEST ";
            $cmds["removing CSR key from device"] = "vars del X509/REQUESTKEY ";
            $hascmd = true;
        }
        $this->logDeviceState("requestDownloaded", "false", "device");
        return $hascmd;
    }

    public function doCertificates($type, &$msgs, &$certificateIsOK) {
        $certificateIsOK = false;
        $cmds = array();
        $msgs = array();
        if (!isset($this->xmlconfig->customcerts) ||
                !isset($this->statexml)) {
            $msgs[] = "either certificate handling or state tracking not enabled - not doing any certificate checking";
            $certificateIsOK = true;
            if ($this->cleanupCertificateStatus($msgs, $cmds))
                return $cmds;
            return null;
        }

        if (!isset($this->statexml->queries) ||
                !isset($this->statexml->queries->certificates)) {
            $msgs[] = "we dont know anything about the current certificate state - not doing any certificate checking";
            return $cmds;
        }

        // see if something went wrong with certificate upload before
        $errortagname = "request-error";
        if (isset($this->statexml->queries->certificates->info->$errortagname)) {
            $cmds['certificate processing STOPPED due to previous upload error, clean X509/REQUESTERROR to continue'] = "ser";
            return ($cmds);
        }

        if (!isset($this->xmlconfig->customcerts['CAname'])) {
            self::bailout("certificates: must set customcerts/@CAname to enable certificates");
        }

        $cafunc = "requestCertificate_" . $this->xmlconfig->customcerts['CAtype'];
        if (!method_exists($this, $cafunc)) {
            self::bailout("certificates: no support for certificates of type '{$this->xmlconfig->customcerts['CAtype']}'");
        }

        // see if a request is underway
        if (isset($this->statexml->queries->certificates->info->busy)) {
            $msgs[] = "wait for signing request to be generated (ongoing)";
            return $cmds;
        }

        // see if there is a good certificate already
        if (!isset($this->xmlconfig->customcerts['CAname'])) {
            self::bailout("certificates: customcerts/@CAname must be defined for certificate deployment");
        }
        $CAsep = isset($this->xmlconfig->customcerts['CAnamesep']) ? (string) $this->xmlconfig->customcerts['CAnamesep'] : ",";
        $casepexploded = array();
        foreach (explode($CAsep, (string) $this->xmlconfig->customcerts['CAname']) as $_k) {
            $casepexploded[] = trim($_k);
        }
        $dcsc = $this->getCertificates();
        $neednewcert = false;
        // issuer constraints
        foreach ($dcsc as $c) {
            if (!in_array($c['issuer_cn'], $casepexploded)) {
                $msgs[] = "CN {$c['subject_cn']}: invalid issuer: {$c['issuer_cn']}: not in {$this->xmlconfig->customcerts['CAname']} ($CAsep)";
                $neednewcert = true;
            }
        }

        // time constraints
        $utc = new DateTimeZone("UTC");
        $now = new DateTime("now", $utc);
        if (isset($this->xmlconfig->customcerts['CAname'])) {
            $renewtime = clone $now;
            $renewtime->add(new DateInterval("P{$this->xmlconfig->customcerts['renew']}D"));
        } else {
            $renewtime = $now;
        }
        foreach ($dcsc as $c) {
            if ($now < new DateTime((string) $c['not_before'], $utc)) {
                // in this case, just ignore the certificate (thus wait for it to become valid)
                $msgs[] = "{$c['subject_cn']}: not yet valid";
                return null;
            }
            if ($now > new DateTime((string) $c['not_after'], $utc)) {
                $msgs[] = "{$c['subject_cn']}: expired";
                $neednewcert = true;
            }
            if ($renewtime > new DateTime((string) $c['not_after'], $utc)) {
                $msgs[] = "{$c['subject_cn']}: valid but must be renewed";
                $certificateIsOK = true;
                $neednewcert = true;
            }
        }

        if ($neednewcert) {

            // get request error if any
            $cmds["download request error from device (if any)"] = $this->dumpVar2File('X509/REQUESTERROR', $this->certResponseErrorFn);

            if (isset($this->statexml->queries->certificates->info->requests->request)) {
                // we need a certificate and there is a CSR present on the device, we just assume that it matches!
                // did we already download it?
                if ($this->certbasefn == null) {
                    self::bailout("certificates: no certbasefn: probably no &sn query present in update URL");
                }
                if (!is_readable($this->certRequestDerFn)) {
                    // get it from device
                    $cmds["need to download CSR from device"] = $this->dumpVar2File('X509/REQUEST', $this->certRequestDerFn, "var2bin", "createPemFromDer");
                    return $cmds;
                }
                if (!is_readable($this->certRequestDerFn)) {
                    $msgs[] = "waiting for request (DER format)";
                    return $cmds;
                }
                if (!is_readable($this->certRequestPemFn)) {
                    // we have the DER, create the PEM
                    file_put_contents($this->certRequestPemFn, self::der2pem(file_get_contents($this->certRequestDerFn)));
                }

                if (is_readable($this->certResponseFn)) {
                    $msgs[] = "there is an uploaded signed CSR ({$this->certResponseFn})";
                    // well done, upload to device
                    /* in vars, we have 
                     * X509/REQUESTRESPONSE/00000 the devices certificate
                     * X509/REQUESTRESPONSE/0000x intermediate certificates
                     * X509/REQUESTRESPONSE/0000n CA certificate
                     */
                    $certindex = 0;
                    $cmds['upload new signed certificate'] = "vars create X509/REQUESTRESPONSE/" . sprintf("%05d", $certindex++) . " pb " . self::bin2var($this->getCertFromFile($this->certResponseFn));
                    if (!isset($this->xmlconfig->customcerts['CAkeys']))
                        $cafiles = array();
                    else
                        $cafiles = glob("{$this->xmlconfig->customcerts['dir']}/{$this->xmlconfig->customcerts['CAkeys']}");
                    foreach ($cafiles as $cakey) {
                        $cmds["upload CA certificate #$certindex ($cakey)"] = "vars create X509/REQUESTRESPONSE/" . sprintf("%05d", $certindex++) . " pb " . self::bin2var($this->getCertFromFile($cakey));
                    }
                    $this->setSkipNext();
                    return $cmds;
                }
                $msgs[] = "signing request must be processed by CA (ongoing)";
                $forcetrust = LessSimpleXMLElement::getAttributeFromXML($this->xmlconfig->times, "forcetrust", "false") == "true";
                return $forcetrust ? $cmds : null;
            }

            // create signing request
            // TODO: guess arguments, issue cmd to create sr
            // mod cmd X509 servercert-create /cmd servercert-create /redirect /target device /type Signing+request 
            //   /key 2048-bit 
            //   /signature SHA256 
            //   /dn-cn Common+Name 
            //   /dn-ou Organizational+Unit 
            //   /dn-o Organization 
            //   /dn-l Locality 
            //   /dn-st State+or+Province 
            //   /dn-c Country 
            //   /san-dns DNS+Name+1 
            //   /san-dns DNS+Name+2 
            //   /san-dns DNS+Name+3 
            //   /san-ip IP+Address+1 
            //   /san-ip IP+Address+2 

            if (!isset($this->statexml->queries)) {
                $msgs[] = "need to create certificate signing request but state queries not (yet) available";
                return null;
            }

            $csropts = array(
                // defaults
                'key' => 'key',
                'signature' => 'signature',
                'dn-cn' => 'dn-cn',
                'dn-ou' => 'dn-ou',
                'dn-o' => 'dn-o',
                'dn-l' => 'dn-l',
                'dn-st' => 'dn-st',
                'dn-c' => 'dn-c',
                'san-dns-1' => 'san-dns',
                'san-dns-2' => 'san-dns',
                'san-dns-3' => 'san-dns',
                'san-ip-1' => 'san-ip',
                'san-ip-2' => 'san-ip',
            );

            switch (strtolower($type)) {
                // weird bugfix, different space escaping in URL
                case "mypbxi" :
                case "mypbxa" :
                    $csr = "mod cmd X509 servercert-create /cmd servercert-create /redirect /target device /type Signing+request ";
                    break;
                default :
                    $csr = "mod cmd X509 servercert-create /cmd servercert-create /redirect /target device /type Signing%20request ";
                    break;
            }
            foreach ($csropts as $key => $opt) {
                $csr .= " /$opt " .
                        rawurlencode(
                                $this->replaceMagics(
                                        LessSimpleXMLElement::getAttributeFromXML(
                                                $this->xmlconfig->customcerts, "CSR$key", "")
                                )
                );
            }
            $cmds["wrong certificate - need to create CSR"] = "$csr";
            $this->cleanupCertificateStatus($msgs, $cmds);
            $this->setSkipNext();
        } else {
            $msgs[] = "OK";
            // remove anything we might have for this device regarding certificates
            $certificateIsOK = true;
            if ($this->cleanupCertificateStatus($msgs, $cmds))
                return $cmds;
            return null;
        }

        // return $this->$cafunc($cafunc);
        return $cmds;
    }

    private function makeFullUrl($url) {
        if (!self::isUrl($url)) {
            if ($url[0] != '/')
                $url = "/$url";
            $url = "{$this->baseurl}$url";
        }
        return $url;
    }

    public function doQueries(array $classes, array $environments) {
        $queries = array();
        if (isset($this->xmlconfig->queries) && isset($this->xmlconfig->queries->query)) {
            $url = $this->makeFullUrl((string) $this->xmlconfig->queries['scfg']);
            $url = "mod cmd UP0 scfg " . $url;
            foreach ($this->xmlconfig->queries->query as $query) {
                if (!isset($query->applies))
                    continue;
                if (!isset($query->cmd))
                    self::bailout("queries: no <cmd> defined for <query id='" . (string) $query['id'] . ">");
                foreach ($query->applies as $a) {
                    if (
                            (trim((string) $a) == "*") ||
                            in_array(
                                    trim((string) $a), (isset($a['env']) ? $environments : $classes)
                            )
                    ) {
                        $queries[(string) $query['id']] = "$url&id=" . rawurlencode((string) $query['id']) . " ser nop /always " . rawurlencode((string) trim($query->cmd));
                    }
                }
            }
        }
        if (count($queries)) {
            return $queries;
        }
        return null;
    }

    public function doBackups() {
        if (!(isset($this->xmlconfig->backup) && isset($this->xmlconfig->backup['dir']) &&
                isset($this->xmlconfig->backup['nbackups']) && isset($this->xmlconfig->backup['scfg'])))
            return null;
        $scfg = $this->makeFullUrl((string) $this->xmlconfig->backup['scfg']);
        $scfg = "mod cmd UP0 scfg $scfg";
        return $scfg;
    }

    public function saveBackup($hwid) {
        if (!isset($this->xmlconfig->backup) || !isset($this->xmlconfig->backup['dir']))
            self::bailout("backup: no backup tag or no 'dir'  attribute in config");
        $dir = (string) $this->xmlconfig->backup['dir'];
        if (!isset($this->xmlconfig->backup['nbackups']))
            self::bailout("backup: no 'nbackups' attribute in backup tag in config");
        $nbackups = (int) (string) $this->xmlconfig->backup['nbackups'];

        $loc = "$dir/$hwid";
        $files = glob("$loc/$hwid.*.txt");
        $newestindex = 0;
        // determine next index to use
        if (count($files) == 0) {
            print("# no saved files for $hwid ($loc/$hwid.*.txt)\r\n");
            $lastsavedcontent = "";
            $nfiles = 0;
            $newest = null;
        } else {
            // sort by ctime
            $rfiles = array();
            foreach ($files as $fn) {
                $rfiles[filectime($fn)] = $fn;
            }
            ksort($rfiles, SORT_NUMERIC);
            $files = array();
            foreach ($rfiles as $fn) {
                $files[] = $fn;
            }
            $newest = $files[($nfiles = count($files)) - 1];
            $newestindex = preg_replace('/.*\.(\d+)\.txt/i', '${1}', $newest);
            $newestindex++;
            // get latest backup
            if (($lastsavedcontent = @file_get_contents($newest)) === false) {
                self::bailout("backup: cannot read file '$newest'");
            }
        }
        // make sure directory exists
        $newfn = "$loc/$hwid.$newestindex.txt";
        @mkdir(dirname($newfn), 0777, true);
        // get backup content
        if (($stream = fopen('php://input', "r")) !== FALSE) {
            if (($newcontent = @stream_get_contents($stream)) === false)
                self::bailout("backup: cannot read backup stream");
            // remove irrelevant lines (which always change and would force a storage)
            // split into array
            $tmp = preg_split("/(\r\n|\n|\r)/", $newcontent);
            // remove call list entries
            $tmp = preg_replace('/^mod cmd FLASHDIR0 add-item .*\)\(type=.*\(info=.*$/', '', $tmp);
            // remove update vars
            $tmp = preg_replace('/^vars create UPDATE\/.*$/', '', $tmp);
            // ignore UP1 line
            $tmp = preg_replace('/^config change UP1 \/.*$/', '', $tmp);
            // convert back to file
            $tmp2 = array();
            foreach ($tmp as $line)
                if ($line != "")
                    $tmp2[] = $line;
            $newcontent = implode("\r\n", $tmp2);
            if (@file_put_contents($newfn, $newcontent) === false)
                self::bailout("backup: cannot save backup to file '$newfn'");
        }

        // see if there is something new in this backup
        if ($lastsavedcontent == $newcontent) {
            print "# no change (newest $newest) " . filemtime($newest) . "\r\n";
            @unlink($newfn);
            return $newest === null ? 0 : filemtime($newest);
        } else {
            print "# saving $newfn\r\n";
        }
        // remove extra old backups
        while ($nfiles-- >= $nbackups) {
            $todel = array_shift($files);
            print "# removing $todel\r\n";
            @unlink($todel);
        }
        return time();
    }

    public function getVarDump($varname, $value) {
        // read the output of a vars read
        $vars = explode("\n", str_replace(array("\r\n", "\n\r", "\r"), "\n", $value));
        foreach ($vars as $var) {
            $tokens = explode(" ", $var);
            if (count($tokens) < 3)
                continue;
            if ($tokens[0] == $varname) {
                return $tokens[2];
            }
        }
        return false;
    }

    public function saveQuery($sn, $id, $xmlcontent) {
        if (!$this->logInit($sn))
            return false;
        $wr = $this->statexml;

        try {
            $newstate = new SimpleXMLElement("<$id>$xmlcontent</$id>");
        } catch (Exception $e) {
            // probably invalid xml content
            self::bailout("invalid xml: " . $e->getMessage() . ": " . $xmlcontent);
        }

        if (isset($wr->queries))
            $wr = $wr->queries;
        else
            $wr = $wr->addChild('queries');
        if (isset($wr->$id))
            unset($wr->$id);
        $wr = LessSimpleXMLElement::addChildFromXml($wr, $newstate);

        $wr['seen'] = time();
        $this->flushDeviceStatus();
        print "# queries $id for $sn saved\r\n";
    }

    private function _showSPAN($name, $type, $content) {
        $c = "<span class='upd-$type upd-$type-$name'>\r\n";
        $c .= "  $content ";
        $c .= "</span> <!-- $type-$name -->\r\n";
        return $c;
    }

    private function _showAttribs(SimpleXMLElement $e, $name) {
        $c = "";
        if (empty($e))
            $c .= $this->_showSPAN("", "undefined", "- undefined -");
        else
            foreach ($e->attributes() as $key => $value) {
                $c .= $this->_showSPAN($key, "attrib", htmlspecialchars($key) . $this->_showSPAN($key, "value", htmlspecialchars($value)));
            }
        return $this->_showSPAN($name, "attribs", $c);
    }

    private function _showSimpleElement($name, $elem = null, $inner = "") {
        if ($elem === null)
            $elem = $this->xmlconfig->$name;
        return $this->_showSPAN($name, "tag", $name . $this->_showAttribs($elem, $name) . $inner);
    }

    public function showPhases() {
        $c = "";
        foreach ($this->xmlconfig->phases->phase as $key => $value) {
            $c .= $this->_showSimpleElement($key, $value);
        }
        return $this->_showSPAN("phases", "tag", $c);
    }

    public function showClasses() {
        $c = "";
        foreach ($this->xmlconfig->classes->class as $key => $value) {
            $cc = "";
            $models = array();
            foreach ($value->model as $model) $models[] = strtolower ($model);
            $list = implode(", ", $models);
            $c .= $this->_showSimpleElement($key, $value, $this->_showSPAN("model", "subtag", $list));
        }
        return $this->_showSPAN("classes", "tag", $c);
    }

    public function showEnvironments() {
        $c = "";
        foreach ($this->xmlconfig->environments->environment as $key => $value) {
            $cc = "";
            foreach ($value->implies as $ikey => $ivalue) {
                $cc .= $this->_showSPAN("implies", "value", $ivalue);
            }
            $c .= $this->_showSimpleElement($key, $value, $cc);
        }
        return $this->_showSPAN("environments", "tag", $c);
    }

    public function showScripts() {
        $c = "";
        foreach (glob($this->scriptdir . "/*-*-*.txt") as $f) {
            $c .= $this->_showSPAN($f, "script", $f . $this->_showSPAN("content", "content", "<pre>" . htmlspecialchars(file_get_contents($f), ENT_IGNORE) . "</pre>"));
        }
        return $this->_showSPAN("scripts", "script", $c);
    }

    public function showCache() {
        $cache = "";
        foreach ($this->getCachedPbxInfo() as $key => $value) {
            $cache .= $this->_showSPAN($key, "attrib", htmlspecialchars($key, ENT_IGNORE) . $this->_showSPAN($key, "value", htmlspecialchars($value, ENT_IGNORE)));
        }
        return $this->_showSPAN("cache", "tag", $cache);
    }

    public function show() {
        return $this->_showSPAN("config", "tag", $this->_showSimpleElement("master")
                        . $this->showCache()
                        . $this->_showSimpleElement("fwstorage")
                        . $this->_showSimpleElement("backup")
                        . $this->_showSimpleElement("times")
                        . $this->_showSimpleElement("status")
                        . $this->_showSimpleElement("customcerts")
                        . $this->showPhases()
                        . $this->showEnvironments()
                        . $this->showClasses()
                        . $this->showScripts());
    }

    static function getRemoteIp(&$rp, &$via) {
        $rp = false;
        $via = null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        // fix strange lighttpd ipv4 address
        $dumbIpPrefix = '::ffff:';
        if (strpos($ip, $dumbIpPrefix) === 0) {
            $ip = substr($ip, strlen($dumbIpPrefix));
        }
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ips = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            if (count($ips)) {
                $via = $ip;  // save remote proxy address as $via
                $ip = $ips[0];
                $rp = true;
            }
        }
        return $ip;
    }

    public static function _since($t) {
        $d = time() - $t;
        if ($t == 0) {
            return "&infin;";
        } else if ($d < 120) {
            $return = "{$d} sec";
        } else if ($d < (120 * 60)) {
            $p = round($d / 60, 1);
            $return = "{$p} min";
        } else if ($d < (48 * 60 * 60)) {
            $p = round($d / (60 * 60), 1);
            $return = "{$p} hour";
        } else if ($d < (90 * 60 * 60 * 24)) {
            $p = round($d / (60 * 60 * 24), 1);
            $return = "{$p} day";
        } else {
            $p = round($d / (60 * 60 * 24 * 7), 1);
            $return = "{$p} week";
        }
        if ((float) $return != 1)
            $return .= "s";
        return $return;
    }

    /**
     * show status files stored for a device 
     * @param SimpleXMLElement $d device XML status
     * @return string
     */
    public function showCSRFiles(SimpleXMLElement $d) {
        if (!isset($d->device['sn']))
            return "";
        $sn = (string) $d->device['sn'];
        $csr = false;
        $nocsr = false;
        $glob = glob("*/$sn*");
        $thtml = $html = "";
        // var_dump($glob);
        foreach ($glob as $fn) {
            $bs = basename($fn);
            $shown = true;
            $href = "admin/admin.php?mode=ui&cmd=deliverfile&fn=" . urldecode($fn);
            switch ($bs) {
                /*
                  case "$sn.xml" :
                  $thtml .= "<a href='$href' title='Download Device Status in XML Format'>Status</a><small>(XML)</small> ";
                  break;
                 */
                case "$sn-request.p10.der" :
                    $thtml .= "<a href='$href' title='Download Certificate Signing Request in DER Format'>CSR</a><small>(DER)</small>";
                    $csr = true;
                    break;
                case "$sn-request.p10.pem" :
                    $thtml .= "<a href='$href' title='Download Certificate Signing Request in PEM Format'>CSR</a><small>(PEM)</small>";
                    $csr = true;
                    break;
                case "$sn-signedrequest.cer" :
                    $thtml .= "<a href='$href' title='Download Signed Certificate'>Certificate</a><img src='web/cer.png'>";
                    $nocsr = true;
                    break;
                default :
                    $shown = false;
                // $html .= "sn=$sn bs=$bs fn=$fn ";
            }
            if ($shown) {
                $thtml .= "<a "
                        . "title='Delete $bs' "
                        . "onClick='javascript:deleteFile(\"$sn\", \"device-$sn\", \"$fn\"); return false;'>"
                        . "<img src='web/delete_file.gif'>"
                        . "</a> ";
            }
        }
        if ($csr) {
            if ($nocsr) {
                $thtml .= " (signed CSR waiting for device upload)";
            } else {
                $thtml .= " (CSR waiting for signature)";
            }
        }
        if ($csr && !$nocsr) {
            // print "<pre>"; debug_print_backtrace(); print "</pre>";

            $thtml .= ""
                    . "<p>Upload signed certificate (PEM or DER format) "
                    . "<input name='cert-$sn' type='file' accept='.pem,.der,.cer' title='Select signed certificate file for $sn here'> " .
                    "<button "
                    . "type='submit' "
                    . "title='upload selected signed certificate file' "
                    . "formaction='update.php?mode=upload&sn=$sn&proceed=admin/admin.php?mode=status&file=" .
                    rawurlencode("{$this->xmlconfig->customcerts['dir']}/$sn-signedrequest.cer") .
                    "' "
                    . "formmethod='post' "
                    . "formenctype='multipart/form-data'"
                    . ">"
                    . "Upload"
                    . "</button>";
        }
        if ($thtml != "") {
            $html = "<div><hr>$thtml</div>";
        }
        return $html;
    }

    public function showInfos(SimpleXMLElement $d) {
        if (isset($d->queries)) {
            $html = "";
            foreach ($this->xmlconfig->queries->query as $query) {
                if (empty($query['title']) || count($query->show) == 0)
                    continue;
                $qid = (string) $query['id'];
                if (!isset($d->queries->$qid))
                    continue;
                $q = $d->queries->$qid;
                $seen = $q['seen'];
                $html .= "<thead><tr><th>{$query['title']} <small>(" . self::_since($seen) . ")</small></th>";
                foreach ($query->show as $col) {
                    $html .= "<th>{$col['title']}</th>";
                }
                $html .= "</tr></thead>";

                $dom = new DOMDocument();
                $dom->loadXML($d->asXML());
                $domxpath = new DOMXPath($dom);
                $domxpath->registerNamespace("php", "http://php.net/xpath");
                $domxpath->registerPhpFunctions();

                foreach ($d->queries as $q) {
                    $html .= "<tr><td/>";
                    foreach ($query->show as $col) {
                        $vals = array();
                        $xpath = @$domxpath->evaluate((string) $col);
                        if ($xpath === false) {
                            $vals[] = "invalid XPath expression: $col";
                        } else {
                            if (!is_object($xpath)) {
                                $vals[] = (string) $xpath;
                            } else {
                                foreach ($xpath as $key => $value) {
                                    $vals[] = $value->value;
                                }
                            }
                        }
                        $html .= "<td>" . implode(", ", $vals) . "</td>";
                    }
                    $html .= "</tr>";
                }
            }
            return $html;
        }
        return "<tr><td>no queries</td></tr>";
    }

    public function _showScripts(SimpleXMLElement $d, &$scriptsid = null) {
        $html = "";
        $html .= "<div>" .
                (isset($d->device['phase']) ? "<span class='prompt'>phase: </span><span title='current phase'>{$d->device['phase']}</span> " : "") .
                (isset($d->device['classes']) ? "<span class='prompt'>class: </span>{$d->device['classes']}</span> " : "") .
                (isset($d->device['environments']) ? "<span class='prompt'>environment: </span><span title='device environments'>{$d->device['environments']}</span>" : "") .
                "</div>";
        if (!count($d->config))
            return "";
        $hwid = (string) $d->device['hwid'];
        $scriptsid = "scripts-$hwid";
        $html .= "<a "
                . "title='Show/hide Delivered Snippets' "
                . "onClick='javascript:toggleVisibility(\"$scriptsid\"); return false;'>"
                . "<img src='web/hide.jpg'>"
                . "</a> ";
        $html .= "<tr>"
                . "<th>Name</th>"
                . "<th>Delivered</th>"
                . "<th>Version</th>"
                . "</tr>";

        foreach ($d->config as $c) {
            $fn = (string) $c['filename'];
            $href = "admin/admin.php?mode=ui&cmd=deliverfile&fn=" . urlencode($fn);
            $html .= "<tr>"
                    . "<td><a href='$href'>$fn</a></td>"
                    . "<td>" . self::_since((int) $c['delivered']) . "</td>"
                    . "<td>" . self::_since((int) $c['version']) . '</td>'
                    . '</tr>';
        }
        return "<table id='$scriptsid' class='scriptslist'>$html</table>";
    }

    private function _initJavascript() {

        print "<script src=\"{$this->baseurl}/web/scripts.js\"></script>\r\n";
        if (isset($this->xmlconfig->status) && isset($this->xmlconfig->status['refresh']) && (int) $this->xmlconfig->status['refresh'] > 0) {
            print "<script>\r\ninitDeviceStateTimer({$this->xmlconfig->status['refresh']});\r\n</script>";
        }
    }

    private function _showUIHandles(SimpleXMLElement $d, &$devid, &$msgsid) {
        $devid = $msgsid = "nope";
        $now = time();
        if (isset($d->device) && isset($d->device['sn'])) {
            $devid = "device-{$d->device['sn']}";
            $msgsid = "msgs-{$d->device['sn']}";
        }
        $elements = array();
        $elements[] = "<a "
                . "title='Delete Status permanently' "
                . "onClick='javascript:deleteDeviceStatus(\"{$d->device['sn']}\", \"$devid\"); return false;'>"
                . "<img src='web/trash.gif'>"
                . "</a>";
        if ($this->statemissing && (
                (int) $d['seen'] < ($now - $this->statemissing)
                )) {
            $elements[] = "<a "
                    . "title='Update Timestamp on Status' "
                    . "onClick='javascript:touchDeviceStatus(\"{$d->device['sn']}\", \"$devid\"); return false;'>"
                    . "<img src='web/touch.gif'>"
                    . "</a>";
        }
        if (count($d->msgs)) {
            $elements[] = "<a "
                    . "title='Show/Hide Log Messages' "
                    . "onClick='javascript:toggleVisibility(\"$msgsid\"); return false;'>"
                    . "<img src='web/hide.jpg'>"
                    . "</a>";
        }
        $elements[] = "<a "
                . "title='Update Device Status' "
                . "onClick='javascript:updateDeviceStatus(\"{$d->device['sn']}\", \"$devid\"); return false;'>"
                . "<img src='web/update.png'>"
                . "</a>";
        if (count($elements))
            return implode(" ", $elements) . "<br>";
        else
            return "";
    }

    private function _showMsgs($d, $id) {
        $html = "<span class='msgs'>";
        $html .= "<ul id='$id'>";
        $first = true;
        foreach ($d->msgs as $m) {
            if ($first)
                $html .= "<li class='ruler'>" . self::_since($m['time']) . "<hr/>";
            else
            if ((string) $m['msg'] == "")
                $html .= "<li class='ruler'>" . self::_since($m['time']) . "<hr/>";
            else
                $html .= "<li>{$m['msg']}";
            $first = false;
        }
        return $html . "</ul></span>";
    }

    private function _showBackups($d = null, &$backupsid = null) {
        if (!isset($this->xmlconfig->backup) || empty($this->xmlconfig->backup['dir']))
            return "";
        if ($d === null)
            return "<th>Last Backup</th>";
        if (!isset($d->device) || empty($d->device['hwid']))
            return "<td/>";
        $html = "<td>";
        if (isset($d->device['backup'])) {
            $dir = (string) $this->xmlconfig->backup['dir'];
            $hwid = $d->device['hwid'];
            $loc = "$dir/$hwid";
            $files = glob("$loc/$hwid.*.txt");
            $fs = array();
            foreach ($files as $f) {
                // $fs[filemtime($f)+1] = "<li><a href='$f' title='$f'>AAA" . self::_since(filemtime($f)) . "</a></li>";
                // admin/admin.php?mode=ui&cmd=deliverfile&fn=certs/00-90-33-08-00-6e-request.p10.pem
                $href = "admin/admin.php?mode=ui&cmd=deliverfile&fn=" . urlencode($f);
                $fs[filemtime($f)] = "<li><a href='$href' title='$f'>" . self::_since(filemtime($f)) . "</a></li>";
            }
            krsort($fs, SORT_NUMERIC);
            if (count($fs)) {
                $backupsid = "backup-$hwid";
                $html .= "<a "
                        . "title='Show/hide Backup Files' "
                        . "onClick='javascript:toggleVisibility(\"$backupsid\"); return false;'>"
                        . "<img src='web/hide.jpg'>"
                        . "</a> ";
                $html .= self::_since((int) $d->device['backup']);
                $html .= "<ul class='backuplist' id='$backupsid'>" . implode("", $fs) . "</ul>";
            }
        }
        return $html . "</td>";
    }

    public function showDevice($sn) {
        return $this->_showDevice("{$this->statedir}/$sn.xml");
    }

    private function _countShowQueries() {
        $count = 0;
        if (isset($this->xmlconfig->queries) && isset($this->xmlconfig->queries->query)) {
            foreach ($this->xmlconfig->queries->query as $q) {
                foreach ($q->show as $s) {
                    if (trim((string) $s) != "none")
                        $count++;
                }
            }
        }
        return $count;
    }

    public function _showDevice($f = null) {
        $now = time();
        $withQueries = $this->_countShowQueries() > 0;
        $withCertificates = isset($this->xmlconfig->customcerts);
        if ($f == null) {
            return "<tr>"
                    . "<th>Device</th>"
                    . "<th>Last Seen</th>"
                    . $this->_showBackups()
                    . "<th>Firmware<br>"
                    . "Bootcode</th>"
                    . ($withCertificates ? "<th>Certificates</th>" : "")
                    . "<th>Scripts</th>"
                    . ($withQueries ? "<th>Info</th>" : "")
                    . "</tr>";
        }
        try {
            $d = @new SimpleXMLElement($f, 0, true);
        } catch (Exception $e) {
            $d = new SimpleXMLElement('<state/>');
            $d->device['sn'] = "$f -- cannot read state";
            $d['seen'] = time();
        }
        // missing device?
        if ($this->statemissing && (
                (int) $d['seen'] < ($now - $this->statemissing)
                )) {
            $xclass = " class='missing' ";
        } else {
            $xclass = "";
        }

        $realip = (string) $d->device['realip'];
        $ip = (string) $d->device['ip'];
        if ($realip != $ip || (string) $d->device['rp'] == 'true') {
            $via = "(via ";
            if (!empty($d->device['proxy'])) {
                $via .= "{$d->device['proxy']} and ";
            }
            $via .= "{$ip})";
        } else {
            $via = "";
        }

        $backups = $this->_showBackups($d, $backupsid);
        $handles = $this->_showUIHandles($d, $id, $msgsid);
        $scripts = $this->_showScripts($d, $scriptsid);
        $errortagname = "request-error";

        // if (isset($d->queries->certificates->info->$errortagname)) print(htmlspecialchars ($d->queries->certificates->info->$errortagname->asXML()));
        $scheme = LessSimpleXMLElement::getAttributeFromXML($d->device->status, "usehttpsdevlinks", "true") ? "https" : "http";
        $target = $d->device['sn'];
        return "<tr$xclass id='$id'  data-sn='{$d->device['sn']}' data-msgsid='$msgsid' data-backupsid='$backupsid' data-scriptsid='$scriptsid'>"
                . "<td>$handles"
                . (string) $d->device['sn'] . "<br>"
                . "<a href='$scheme://$realip' title='Open Device Web GUI' target='$target'>$realip</a>" . "$via<br>"
                . (string) $d->device['type'] . "<br>"
                . (isset($d->queries->admin->info) ? (string) $d->queries->admin->info['name'] : "")
                . $this->_showMsgs($d, $msgsid) . "</td>"
                . "<td>" . self::_since((int) $d['seen']) . "</td>"
                . $backups
                . "<td>" . (string) $d->firmware['build'] . "<br>"
                . (string) $d->bootcode['build'] . "</td>"
                . ($withCertificates ? (
                "<td>" .
                LessSimpleXMLElement::getAttributeFromXML($d->device, "certstat", "unknown") .
                ( isset($d->queries->certificates->info->$errortagname) ? "<br><div style='color: red'>Certificate upload error - certificate handling stopped: <ul><li>remove CSR on device<li>issue 'vars create X509/REQUESTERROR' on device</ul>and try again</div>" : ""
                ) .
                $this->showCSRFiles($d) .
                "</td>") :
                ""
                )
                . "<td>$scripts</td>"
                . ($withQueries ? ("<td><table>" . $this->showInfos($d) . "</table></td>") : "")
                . "</tr>";
    }

    public function _showDevices() {
        $ndevices = 0;
        $html = '<table><thead>' . $this->_showDevice() . "</thead>";
        $html .= "<tbody id='devicestable'>";
        foreach (glob("{$this->statedir}/*.xml") as $f) {
            $html .= $this->_showDevice($f);
            $ndevices++;
        }
        return $html . '</tbody></table>' . "<p/><hr/><div>$ndevices Devices</div>";
    }

    public function status() {
        $this->_initJavascript();
        if ($this->statedir == null)
            return '';
        return $this->_showSPAN("status", "tag", $this->_showDevices());
    }

    var $statedir = null;
    var $stateexpire = 0;
    var $statemissing = 0;
    var $statexml = null;
    var $statefn = null;
    var $devicescript = null;

    private function logInit($sn, $create = true) {
        if (empty($sn)) {
            print "# no SN -- cannot log: " . implode(" ", $_REQUEST) . "\r\n";
            return false;
        }
        $this->devicescript = str_replace("-", "_", strtolower($sn));
        if ($this->statedir == null) {
            // print "# no statedir -- cannot log\r\n";
            return false;
        }
        if ($this->statexml === null) {
            $this->statefn = "{$this->statedir}/$sn.xml";
            if (is_readable($this->statefn)) {
                try {
                    $this->statexml = new SimpleXMLElement($this->statefn, 0, true);
                } catch (Exception $e) {
                    $this->statexml = new SimpleXMLElement("<state><device/></state>");
                    $this->statexml['error'] = $e->getMessage() . "(" . file_get_contents($this->statefn) . ")";
                }
            } elseif ($create) {
                $this->statexml = new SimpleXMLElement("<state><device/></state>");
            } else
                return false;
            $this->statexml->device['sn'] = $sn;
        }
        $this->statexml['seen'] = time();
        return true;
    }

    public function delStatus($sn) {
        if (!$this->logInit($sn, false))
            return false;
        @unlink($this->statefn);
        return true;
    }

    public function touchStatus($sn) {
        if (!$this->logInit($sn, false))
            return false;
        $this->statexml['seen'] = time();
        return true;
    }

    public function delFile($sn, $fn) {
        if (!UpdateServerV2::isNameInSubfolder($fn))
            return false;
        return @unlink($fn) !== false;
    }

    public function logDeviceState($attribute, $value, $subtag = null, $id = null) {
        if (!isset($_REQUEST['sn']) || !$this->logInit($_REQUEST['sn']))
            return;
        $wr = $this->statexml;
        if ($subtag !== null) {
            if ($id === null) {
                $wr = $wr->$subtag;
            } elseif ($id === "") {
                $wr = $wr->addChild("$subtag");
                $wr['time'] = time();
            } else {
                $found = null;
                $idattr = $id[0];
                $idvalue = $id[1];
                foreach ($wr->$subtag as $st) {
                    if (isset($st[$idattr]) && $st[$idattr] == $idvalue) {
                        $found = $st;
                        break;
                    }
                }
                if ($found === null) {
                    $wr = $wr->addChild($subtag);
                    $wr[$idattr] = $idvalue;
                } else {
                    $wr = $found;
                }
            }
        }
        if ($value !== null) {
            $wr[$attribute] = (string) $value;
        } else {
            unset($wr[$attribute]);
        }
    }

    private function flushDeviceStatus() {
        // flush out state
        if ($this->statexml !== null) {
            $this->statexml->asXML($this->statefn);
        }
    }

    private function fixMsgs() {
        if ($this->statexml !== null && $this->statexml->msgs !== null) {
            $veryoldtime = LessSimpleXMLElement::getAttributeFromXML($this->xmlconfig->status, "logkeep", 90 * 60);
            $veryold = time() - ($veryoldtime);
            $i = 0;
            $lastruler = null;
            $lastold = null;
            foreach ($this->statexml->msgs as $m) {
                if ($m['msg'] == "") {
                    $lastruler = $i;
                } elseif ($m['time'] < $veryold) {
                    $lastold = $i;
                }
                $i++;
            }
            if ($lastold === null)
                $lastold = 0;
            if ($lastruler === null)
                $lastruler = 0;
            for ($j = 0; $j <= $i; $j++) {
                if ($j <= $lastold && $j < $lastruler) {
                    unset($this->statexml->msgs[$j]);
                } else
                    break;
            }
        }
    }

    public function cleanupDeviceStates() {
        $this->fixMsgs();
        // flush
        $this->flushDeviceStatus();
        // purge old files
        if ($this->stateexpire == 0)
            return;
        $now = time();
        foreach (glob("{$this->statedir}/*.xml") as $f) {
            if (($now - @filemtime($f)) > $this->stateexpire)
                @unlink($f);
        }
    }

    /**
     * skip next invocation of update script, just do the queries
     * this is needed if we change the state of a device and need to check for success
     * the change effect will not be shown in the next query (as the change is executed only after)
     * so we need to wait one more cycle
     */
    public function setSkipNext() {
        $this->logDeviceState("skipnext", time());
        $this->logDeviceState("msg", "skipnext set", "msgs", "");
    }

    public function doSkipNext() {
        $ret = isset($this->statexml) && isset($this->statexml['skipnext']);
        if ($ret)
            $this->logDeviceState("msg", "skipnext: " . self::_since($this->statexml['skipnext']), "msgs", "");
        unset($this->statexml['skipnext']);
        return $ret;
    }

}

class WSTEPClient extends SoapClient {

    function __construct() {
        $usedoptions = array(// forced options
            'login' => "login",
            'password' => "password",
            'location' => "http://8.8.8.8/PBX0/user.soap",
        );
        parent::__construct("wstep-wsdl/wcf.wsdl", $usedoptions);
        var_dump($this->__getFunctions());
    }

}
