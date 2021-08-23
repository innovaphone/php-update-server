<?php

/*
 * a class that implements a single input page that maintains its state within URL query args
 * (so that you can create a meaningful link to it)
 *
 */

require_once(__DIR__."/inputpage.class.php");
require_once(__DIR__."/translator.class.php");
tl::register("innoInputPage", dirname(__FILE__) . "\\lang");

class innoMenuItem {
    const classMainMenuItem = "main-menu";
    const classTabMenuItem = "tab";
    const classLeftMenuItem = "left-menu";
    var $class;
    var $title;
    var $name;
    var $href;
    var $disabled;
    
    public function innoMenuItem($class, $name, $href, $active, $title, $disabled) {
        $this->href = $href;
        $this->name = $name;
        $this->title = $title;
        $this->disabled = $disabled;
        if($disabled) $this->class = $class."-disabled";
        elseif($active) $this->class = $class."-active";
        else $this->class = $class;
    }
}

class innoInputPage extends InputPage {
    // an innovaphone'ish input page
    
    var $title = null;
    var $site = null;
    var $loginArea = "";
    var $searchArea = "";
    var $bannerArea = "";
    var $infoArea = "";
    var $mainMenuItems = array();
    var $tabMenuItems = array();
    var $leftMenuItems = array();
    var $logoImg = "/images/innovaphone_logo_claim_fisch.png";
    var $printAdditionalLinks = true;
    
    /**
     *
     * @var boolean If set, the tab menu are will be rendered before the logo area
     */
    var $swapTabMenuAndLogoArea = false;
    
    /**
     * The name of a "special" section, which will be rendered next to the logo div.
     * All fields in this section will be rendered there, but not in the standard
     * renderFields() method.
     * You may have to edit your own CSS file for the id #search-area, if you want to
     * position the fields e.g. at the left.
     */
    const searchAreaSection = "_search_area_section";
    
    /**
     * constructor
     * @param string $site user visible name for the whole site (used in title)
     * @param type $title  user visisible title for this page (used together with $site as title)
     */
    public function __construct($site, $title = null) {
        parent::__construct();
        $this->metas[] = array("content-type" => "text/html; charset=ISO-8859-1");
        $this->links[] = array("rel" => "stylesheet", "type" => "text/css", "href" => "/style_inno.css");
        $this->links[] = array("rel" => "stylesheet", "type" => "text/css", "href" => "/style.css");
        $this->ielinks[] = array("rel" => "stylesheet", "type" => "text/css", "href" => "/iestyle.css");
        $this->setFieldSectionAttributes("inline", 
                new _InputPageFieldSectionAttributes(
                    array(
                        "align" => "center",
                        // "width" => "100%"
                        )));
        $this->setFieldSectionAttributes("bottom", 
                new _InputPageFieldSectionAttributes(
                    array(
                        "align" => "center",
                        ),
                    999,
                    1));
        $this->setFieldSectionAttributes("top", 
                new _InputPageFieldSectionAttributes(
                    array(
                        "align" => "center",
                        ),
                    999
                    ));
        $this->setFieldSectionAttributes(self::searchAreaSection, 
                new _InputPageFieldSectionAttributes(
                    array(
                        "align" => "left",
                        ),
                        1,
                        4,
                        false,  // no fieldset
                        "",
                        false,
                        false,
                        false   // do not render in renderFields
                    ));
        $this->site = $site;
        $this->title = ($title === null) ? "$site" : "$site - $title";
        $this->scriptcodes[] = '
                
                function getLanguage() {
                //
                // determine the language the user probably is using...
                //
                // language modifyers (as in "de-at") are ignored.
                //
                var l, i;
                if (navigator.userLanguage != null) l = navigator.userLanguage;
                else if (navigator.language != null) l = navigator.language;
                else if (navigator.systemLanguage != null) l = navigator.systemLanguage;
                else l = "unknown (defaulted)"
                
                i = l.indexOf("-", 2);
                if (i >= 0) l = l.substring(0,i);
                
                // alert("effective language is " + l)
                return l
                }
                
                ';
    }
    
    public function addMainMenuItem($name, $href, $active = false, $title = "", $disabled = false) {
        $this->mainMenuItems[] = new innoMenuItem(innoMenuItem::classMainMenuItem, $name, $href, $active, $title, $disabled);
    }
    
    public function addTabMenuItem($name, $href, $active = false, $title = "", $disabled = false) {
        $this->tabMenuItems[] = new innoMenuItem(innoMenuItem::classTabMenuItem, $name, $href, $active, $title, $disabled);
    }
    
    public function addLeftMenuItem($name, $href, $active = false, $title = "", $disabled = false) {
        $this->leftMenuItems[] = new innoMenuItem(innoMenuItem::classLeftMenuItem, $name, $href, $active, $title, $disabled);
    }
    
    public function renderMenuItems($items, $td = false) {
        $ret = "";
        foreach($items as $item) {
            if($td) $ret .= "<td>";
            $ret .= '    <div class="'.$item->class.'" title="'.$item->title.'">';
            if($item->disabled) {
                $ret .= '    '.$item->name;
            }
            else {
                $ret .= '    <a href="'.$item->href.'" title="'.$item->title.'" class="'.$item->class.'">'.$item->name.'</a>';
            }
            $ret .= '    </div>';
            if($td) $ret .= "</td>";
        }
        return $ret;
    }
    
    public function renderProlog() {
        $ret = "";
        $ret .= '<div id="main">';
        $ret .= $this->renderMenuItems($this->mainMenuItems);
        $ret .= '</div>';
        $ret .= '<div id="login">';
        $ret .= '    '.$this->loginArea;
        $ret .= '</div>';
        $id = "logo-area";
        if($this->swapTabMenuAndLogoArea) {
            $id = "logo-area-swapped";
        }
        $ret .= "<div id=\"$id\">";
        $ret .= '    <div id="logo">';
        $ret .= '        <img src="'.$this->makeRelativeLink($this->logoImg).'"/>';
        $ret .= '    </div>';
        $ret .= '    <div id="search-area">';
        $searchAreaFlds = array();
        foreach($this->fields as $fld) {
            if($fld->style == self::searchAreaSection) {
                $searchAreaFlds[] = $fld;
            }
        }
        if(count($searchAreaFlds) > 0) {
            $ret .= $this->renderFieldRows($searchAreaFlds);
        }
        else {
            $ret .= '        '.$this->searchArea;
        }
        $ret .= '    </div>';
        $ret .= '</div>';
        $id = "tab-menu";
        if($this->swapTabMenuAndLogoArea) {
            $id = "tab-menu-swapped";
        }
        $ret .= "<div id=\"$id\">";
        $ret .= '    <table class="tab">';
        $ret .= '        <tr class="tab">';
        $ret .= '            <td><div class="tab-left">&nbsp;</div></td>';
        $ret .= $this->renderMenuItems($this->tabMenuItems, true);
        $ret .= '            <td width="100%">&nbsp;</td>';
        $ret .= '            <td><div class="tab tab-info">'.$this->infoArea.'</div></td>';
        $ret .= '        </tr>';
        $ret .= '    </table>';
        $ret .= '</div>';
        
        if($this->bannerArea != "") {
            $ret .= '<div id="banner">'.$this->bannerArea.'</div>';
        }
        
        $contentClass = "content-without-left-menu";
        if(count($this->leftMenuItems) > 0) {
            $ret .= '<div id="left-menu">';
            $ret .= $this->renderMenuItems($this->leftMenuItems);   
            $ret .= '</div>';
            $contentClass = "content-with-left-menu";
        }
        $ret .= '  <div id="'.$contentClass.'">';
        $ret .= '    <table border="0">';
        $ret .= ' 	     <tr valign="top">';
        $ret .= '	         <td>';
        return $ret;
    }
    
    public function getStdLinks() {
                $links = array();
        switch(tl::currentLanguage()) { 
            case "de": 
                $links["contact"] = "http://www.innovaphone.com/de/kontakt.html";
                $links["imprint"] = "http://www.innovaphone.com/de/impressum.html";
                $links["use"] = "http://www.innovaphone.com/de/nutzungsbedingungen.html";
                $links["trade"] = "http://www.innovaphone.com/de/agbs.html";
                $links["privacy"] = "http://www.innovaphone.com/de/datenschutz.html";
                break;
            case "it": 
                $links["contact"] = "http://www.innovaphone.com/it/contatti.html";
                $links["imprint"] = "http://www.innovaphone.com/it/imprint.html";
                $links["use"] = "http://www.innovaphone.com/it/condizioni-di-Utilizzo.html";
                $links["trade"] = "http://www.innovaphone.com/it/termini-e-condizioni.html";
                $links["privacy"] = "http://www.innovaphone.com/it/privacy-policy.html";
                break;
            case "nl": 
                $links["contact"] = "http://www.innovaphone.com/nl/contact.html";
                $links["imprint"] = "http://www.innovaphone.com/nl/colofon.html";
                $links["use"] = "http://www.innovaphone.com/nl/gebruiksvoorwaarden.html";
                $links["trade"] = "http://www.innovaphone.com/nl/algemene-handelsvoorwaarden.html";
                $links["privacy"] = "http://www.innovaphone.com/nl/privacy.html";
                break;
            case "fr": 
                $links["contact"] = "http://www.innovaphone.com/fr/contact.html";
                $links["imprint"] = "http://www.innovaphone.com/fr/mentions-legales.html";
                $links["use"] = "http://www.innovaphone.com/fr/conditions-d-utilisation.html";
                $links["trade"] = "http://www.innovaphone.com/fr/cgv.html";
                $links["privacy"] = "http://www.innovaphone.com/fr/protection-des-donn%C3%A9es.html";
                break;
            case "es": 
                $links["contact"] = "http://www.innovaphone.com/es/contacto.html";
                $links["imprint"] = "http://www.innovaphone.com/es/impressum.html";
                $links["use"] = "http://www.innovaphone.com/es/politica-uso.html";
                $links["trade"] = "http://www.innovaphone.com/es/condiciones-generales-venta.html";
                $links["privacy"] = "http://www.innovaphone.com/es/proteccion-datos.html";
                break;
			default:
                $links["contact"] = "http://www.innovaphone.com/en/contact.html";
                $links["imprint"] = "http://www.innovaphone.com/en/imprint.html";
                $links["use"] = "http://www.innovaphone.com/en/policy.html";
                $links["trade"] = "http://www.innovaphone.com/en/terms-of-trade.html";
                $links["privacy"] = "http://www.innovaphone.com/en/privacy.html";
                break;
        }
        return $links;
    }
    
    public function renderEpilog() {
		// translate to Typo3 language ID
        $links = $this->getStdLinks();

        $ret = "";
        $ret .= '	         </td>';
        $ret .= '        </tr>';
        $ret .= '    </table>';
        $ret .= '</div>';
        $ret .= '<div id="footer">';
		$ret .= '  <p align="left">';
        $ret .= '    <a href="'.$links["contact"].'" target="_blank">' . tl::tl("Contact") . '</a> |';
        $ret .= '    <a href="'.$links["imprint"].'"  target="_blank">' . tl::tl("Imprint") . '</a> |';
        $ret .= '    <a href="'.$links["use"].'"  target="_blank">' . tl::tl("Terms Of Use") . '</a> |';
        $ret .= '    <a href="'.$links["trade"].'"  target="_blank">' . tl::tl("Terms Of Trade") . '</a> |';
        $ret .= '    <a href="'.$links["privacy"].'"  target="_blank">' . tl::tl("Privacy") . '</a> |';
        $ret .= '    Copyright &copy;&nbsp;1997 - '.date('Y').' innovaphone AG';
		$ret .= '  </p>';
	    $ret .= '</div>';
        return $ret;
    }
    
    public function renderPrefix() {
		return '';
    }
    
    public function renderSuffix() {
        $ret = "";
		if($this->printAdditionalLinks) {
			$ret .= '   <tr>';
			$ret .= '     <td>';
			$ret .= '       <div class="innosuffix" border=1 align="right"> ';
			$ret .= '         <a class="novisit" title="' . tl::tl("Back to last page") . '" href="javascript:history.back()">&larr;' . tl::tl("Back") . '</a><br>';
			if ( $_SERVER['SERVER_NAME'] != "www.innovaphone.com" ) {
				$ret .= '     <a class="novisit" title="' . tl::tl("Go to") . ' ' . $_SERVER['SERVER_NAME'] . '"  href="/">' . tl::tl("Home") . '&rarr; </a><br>';
			}
			$ret .= '         <a class="novisit" title="' . tl::tl("Go to www.innovaphone.com") . ' "  href="http://www.innovaphone.com">innovaphone&rarr; </a> ';
			$ret .= '       </div>';
			$ret .= '	  </td>';
			$ret .= '    </tr>';
		}
		return $ret;
    }
    
    // normally not used. prints out PHP code to access page fields.
    public function printPHP($name = "parent", $thisname = "this", $varprefix = "f_") {
        $decl = "";
        $code = "";
        foreach ($this->fields as $field) {
            if ((!$field->formdata) || (($val = $field->dumptext()) === false)) continue;
            $decl .=  "var \${$varprefix}{$field->name};	// " . get_class($field) . "\n";
            $code .= "\$tmp = \${$name}->getField(\"{$field->name}\"); \${$thisname}->{$varprefix}{$field->name} = ";
            if ($field->value == $field->dumptext()) $code .= "\$tmp->value; ";
            else $code .= "\$tmp->dumptext(); ";
            $code .= " /* $val */\n";
        }
        print "<pre>";
        print "\n// page shadow values {\n$decl// }\n";
        print "\n// code that retrieves page values {\n$code// }\n";
        print "</pre>";
    }
}

// some inno special fields
class InputPageLanguageSelectorDropdownField extends InputPageDropdownField {
    // give user option to select one of the available languages
    public function __construct($name, $prompt = null, $withclasses = false) {
        if ($prompt == null) $prompt = tl::tl("Select Language");
        $choices = array();
        foreach (tl::languages($withclasses) as $shorthand => $path) {
            $choices[$shorthand] = tl::iso639($shorthand);
        }
        parent::__construct($name, $prompt, $choices);
        $this->default = array(tl::currentLanguage() => true);
        $this->hint = tl::tl("You can switch to any translation from the list of available languages.");
    }
    
    public function valid() {
        $keys = array_keys($this->value);
        // tl::change($keys[0]);
        return true;
    } 
    
    static function getLanguage($fn) {
        // assume there is a field called "$fn" and get it from request data
        $tmp = new InputPageLanguageSelectorDropdownField($fn);
        $tmp->default = null;
        $tmp->getFieldValueFromForm();
        if (count($tmp->value) > 0) {
            $keys = array_keys($tmp->value);
            return $keys[0];
        }
        return null;
    }
    
    static function getDefaultedLanguage($def, $arg = "lang", $cook = "language", $scope = ".innovaphone.com") {
        // switches to proper language either from form query arg or from coockie or to default and stores result in cookie
        // $arg is the name of the language field (e.g. "lang")
        // $cook is the name of the language cookie (e.g. "language")
        // $def is the default language if nothing is set
        
        $cookie = new innoCookie($cook, innoCookie::NEVER, $scope);
        
        // read current selected language from request
        $forml = InputPageLanguageSelectorDropdownField::getLanguage($arg);
        $cookiel = $cookie->get(); 
        
        // if not present and cookie found, switch to cookie language
        $usel = $forml;
        if (($forml == "") && ($cookiel !== false)) {
            $usel = $cookiel;
        }
        if ($usel == "") $usel = $def;
        if (($usel != "") && ($usel != $cookiel)) $cookie->set($usel);
        
        return $usel;
    }
}

class InputPageLanguageReloadDropdownField extends InputPageLanguageSelectorDropdownField {
    public function linkedToPage() {
        $this->attributes += array("onchange" => "document.getElementsByName('{$this->rootPage->name}')[0].submit()");
    }
}

class InputPageSerialField extends InputPageStringField {
    
    // currently either a MAC address or an IPEI
    var $isMAC = false;
    var $isIPEI = false;
    /**
     * if true, then we allow any serial 
     * @var type 
     */
    var $allowAny = false;
    const macPattern = "^ *([0-9A-Fa-f]{2})[-. ]?([0-9A-Fa-f]{2})[-. ]?([0-9A-Fa-f]{2})[-. ]?([0-9A-Fa-f]{2})[-. ]?([0-9A-Fa-f]{2})[-. ]?([0-9A-Fa-f]{2}) *$";
    const ipeiPattern = "^ *(([0-9]{5}) *([0-9]{7})) *([0-9*]?) *$";
    
    public function __construct($name, $prompt = "", $default = "", InputPageAction $button = null) {
        parent::__construct($name, $prompt, $default, $button);
        $this->setHint(tl::tl("A serial number looks like 00-90-33-01-0A-FF or 0007703915528."));
        $this->default = "00-90-33-";
        $this->placeHolder = tl::tl("e.g.")." 00-90-33-01-0A-FF";
        $this->pattern = "(".self::macPattern.")|(".self::ipeiPattern.")";
    }
    
    public function valid() {
        $this->isMAC = false;
        $this->isIPEI = false;
        
        if (!parent::valid()) return false;
        
        $this->value = strtoupper($this->value);
        // MAC  e.g. 00-90-33-01-02-03
        if (preg_match('%'.self::macPattern.'%', $this->value, $match) >= 1) {
            $vendor = $match[1] . $match[2] . $match[3];
            switch ($vendor) {
                case "009033" :
                case "809033" :
                case "00013E" :
                    $mac = $match[1] . "-" . $match[2] . "-" . $match[3] . "-" . $match[4] . "-" . $match[5] . "-" . $match[6];
                    if ($mac != $match[0]) { $this->value = "$mac"; $this->msg = tl::tl("MAC address normalized"); return false; }
                    $this->isMAC = true;
                    return true;
                
                default :
                    $this->msg = tl::tl("This is not an innovaphone mac address.");
                    // but it might be an IPEI in fact
                    break;
            }
        }
        // IPEI e.g. 000770466191
        if (preg_match('%'.self::ipeiPattern.'%', $this->value, $match) >= 1) {
            $emc = $match[2];	// Equipment Manufacturer Code
            $psn = $match[3];	// Portable Serial Number
            $check = $match[4];	// check digit
            $eipei = $match[1]; // IPEI as entered
            $cipei = $match[0]; // IPEI w/ check digit as entered (complete entry)
            $ipei = $emc . $psn . $check;
            // canonical IPEI
            if ($emc != "00077" && $emc != "05003") { $this->msg = tl::tl("This doesn't seem to be an innovaphone DECT device."); return false; }
            // if ($check == "") { $this->msg = tl::tl("Please provide the check digit too (this is the 13th digit found on the label under the battery)"); return false; }
            $digit =   (1*substr($ipei,0,1)+
                    2*substr($ipei,1,1)+
                    3*substr($ipei,2,1)+
                    4*substr($ipei,3,1)+
                    5*substr($ipei,4,1)+
                    6*substr($ipei,5,1)+
                    7*substr($ipei,6,1)+
                    8*substr($ipei,7,1)+
                    9*substr($ipei,8,1)+
                    10*substr($ipei,9,1)+
                    11*substr($ipei,10,1)+
                    12*substr($ipei,11,1)) % 11;
            if ($digit == 10) $digit = "*";
            $match[] = $digit;
            if ($check == "") { $this->value = $ipei . $digit; $this->msg = tl::tl("Missing IPEI check digit added."); return false; }
            if ($digit != $check) { $this->msg = tl::tl("wrong IPEI check digit"); return false; }
            if ($ipei != $cipei) { $this->value = $ipei; $this->msg = tl::tl("IPEI converted to canonical format (no white space etc)."); return false; }
            
            $this->isIPEI = true;
            return true;
        }
        if ($this->msg == "" && !$this->allowAny) $this->msg = tl::tl("Please enter a serial number here."); 
        return $this->allowAny;
    }
}


/**
 * a field that obeys PBX name rules
 * This allows more or less everything, but no @
 */
class InputPagePBXNameField extends InputPageStringField {
    
    const pattern = '/^[[:print:]]+$/';
    
    public function __construct($name, $prompt = "", $default = "", \InputPageAction $button = null, $placeholder = "") {
        parent::__construct($name, $prompt, $default, $button, $placeholder);
        $this->setHint("PBX names can contain any printing character except '@'");
    }

    public function valid() {
        if (parent::valid()) {
            if (!preg_match(self::pattern, $this->value) ||
                    strstr($this->value, "@") !== false) {
                $this->msg = tl::tl("PBX names can contain any printing character except '@'");
                return false;
            }
            return true;
        }
        return false;
    }

}

/**
 * a field that obeys PBX key ID field 
 * this is used in fields which we want to be "safe" and simple, eg. filter names, group names, pbx-name
 * - no white spaces
 * - no special characters, except letters, digits, ., -, _
 * may be overly restrictive, but then again....
 */
class InputPagePBXIDField extends InputPageStringField {
    
    const pattern = '/^[-[:alnum:]_.]+$/';
    
    public function __construct($name, $prompt = "", $default = "", \InputPageAction $button = null, $placeholder = "") {
        parent::__construct($name, $prompt, $default, $button, $placeholder);
        $this->setHint("PBX identifiers can consist of letters, digits, hyphen (-), underscore (_) and dot (.)");
    }

    public function valid() {
        if (parent::valid()) {
            if (!preg_match(self::pattern, $this->value)) {
                $this->msg = tl::tl("PBX identifiers must not contain any characters except letters, digits, hyphen (-), underscore (_) and dot (.)");
                return false;
            }
            return true;
        }
        return false;
    }

}

/**
 * a class that enforces a domain name 
 */
class InputPageDomainField extends InputPageStringField {
    const pattern = '#^(([a-z0-9][-a-z0-9]*?[a-z0-9])\.)+[a-z]{2,6}$#';
    
    public function __construct($name, $prompt = "", $default = "", \InputPageAction $button = null, $placeholder = "") {
        parent::__construct($name, $prompt, $default, $button, $placeholder);
        $this->setHint("Domain names can consist of lower case lettters, digits, dashes (-) and dots(.)");
    }

    public function valid() {
        if (parent::valid()) {
            if (!preg_match(self::pattern, $this->value)) {
                $this->msg = tl::tl("Domain names can consist of lower case lettters, digits, dashes (-) and dots(.) only");
                return false;
            }
            if (substr($this->value, 0, 4) == "www.") {
                $this->msg = tl::tl("The 'www.' prefix has been stripped from the domain name");
                $this->value = substr($this->value, 4);
                return false;
            }
            return true;
        }
        return false;
    }
}


// somewhat generic cookie handling

class innoCookie {
    var $scope;		// domain name or path, sets visibility
    var $name;		// php visible name
    var $fullname;	// name with prefix etc. (as seen by browser)
    var $value;		// cookie value, may be an array
    var $isarray;
    var $isbool;
    var $httponly;	
    const NEVER = -1;
    const prefix = "_inno_";
    
    // $scope has to be set to "" for local debugging!
    public function __construct($name, $expire = self::NEVER, $scope = ".innovaphone.com", $httponly = true) {
        $host = (isset($_SERVER['HTTP_HOST']) ? parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) : ""); 
        if ($scope == ".innovaphone.com" && ($host == "localhost" || $host == "127.0.0.1")) $scope = "";
        
        $this->scope = $scope;		// a domain name or host name such as .innovphone.com or www.innovaphone.com 
        $this->path = "/";
        if ($expire == self::NEVER) $expire = 60 * 60 * 24 * 365 * 1; // 1 years
        $this->expire = $expire; 	// 0 => expires at end of session, positive => expires in time() + seconds (use innoCookie::$NEVER for eternal cookies), -1 expires virtually never
        if ($expire != 0) $this->expire += time();
        $this->name = $name;
        $this->fullname = self::prefix . ":" . $name;
        $this->httponly = $httponly;
        $this->isbool = false;
        $this->value = false;
        $this->get();
    }
    
    // get value, returns false if not set
    // note: you cannot set/get boolean values for cookies
    public function get() {
        $this->value = false;
        if (isset($_COOKIE[$this->fullname])) $this->value = $_COOKIE[$this->fullname];
        $this->isbool = is_bool($this->value);
        $this->isarray = is_array($this->value);
        return $this->value;
    }
    
    public function set($newvalue, $expire = null) {
        // first remove old values
        if ($expire === null) {
            $expire = $this->expire;
            $this->clear();
        }
        if (is_array($newvalue)) {
            $this->isarray = true;
            foreach ($newvalue as $key => $value) {
                if (($value === true) || ($value === false)) $this->isbool = true;
                setcookie($this->fullname . '[' . $key . ']', $value, $expire, $this->path, $this->scope, false, $this->httponly);
            }
        } else {
            setcookie($this->fullname, $newvalue, $expire, $this->path, $this->scope, false, $this->httponly);
        }
        $_COOKIE[$this->fullname] = $this->value = $newvalue;
        $this->get();
    }
    
    public function clear() {
        if ($this->isarray) {
            foreach ($this->value as $key => &$value) $value = false;
        } else {
            $this->value = false;
        }
        $this->set($this->value, 1000);
    }
    
    
}

?>