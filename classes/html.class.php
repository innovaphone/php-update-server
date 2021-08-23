<?php

class html {
    // various helper functions to help create pages that look innovaphone-ish
    static $encoding = "ISO-8859-1";
    
    // fully html quote incl. " and '
    static function hq($v) { 
        /*if (is_array($v) || is_object($v)) {  // slows down code...
            $i = $i;
        }*/
        return htmlentities($v, ENT_QUOTES, self::$encoding); 
    }

    // return the full URL of the calling script (or $myname if given) (not the calling file!)
    static function myFullURL($myname = null) {
	$https = (isset($_SERVER['HTTPS']) &&
	         ($_SERVER['HTTPS'] !== false) && 
	         ($_SERVER['HTTPS'] != "off"));
	$port = (($_SERVER['SERVER_PORT'] == 80) ||
	    ($_SERVER['SERVER_PORT'] == "")) ? "" : ":{$_SERVER['SERVER_PORT']}";
	if ($myname === null) $myname = basename($_SERVER['PHP_SELF']);
	$dir = dirname($_SERVER['PHP_SELF']);
	$path = (($dir == ".") ||
	    ($dir == "/") ||
	    ($dir == "\\") ||
	    ($dir == "") ||
	    ($dir == "./") ||
	    ($dir == ".\\")) ? $myname : ($dir . "/" . $myname);
	return ($https ? "https" : "http") . "://" . $_SERVER['SERVER_NAME'] . $port . "/" . $path;
    }
    
    private static function structuredAttributes() {
        return  $sattrs = array(
                "style" => array(": ", "; "),
                );
    }
    
    // this will merge into $values (if not yet set)
    public static function mergeAttributes(array &$attrs, array $merges) {
        $attrs += $merges;
        foreach ($merges as $name => $value) {
            if ($value === (array)$value) {
                $attrs[$name] += $value;
            }
        }
    }
    
    // render object attribs
    static function formatAttributes($attributes) {
        // special care is taken for structured attribute values (such as style="color: red; font: arial")
        $r = "";
	foreach ($attributes as $a => $v) {
	    $r .= $a;
            if($v === null) {   // do nothing for attributes like readonly
            }
            else if($v !== (array)$v) {        // no array
                $r .= "='".self::hq($v)."'";
            }
            else if(count($v)) {    // structured array like 'style'
                $r .= "='";
                $sep = array(": ", "; ");
                $sattrs = self::structuredAttributes();
                // if value is an array, it should be one of the structured attributes
                if ($sattrs[$a] === (array)$sattrs[$a]) $sep = $sattrs[$a];
                foreach ($v as $name => $value) $r .= $name . $sep[0] . $value . $sep[1];
                $r .= "'";
            }
	    $r .= " ";
	}
	return $r;
    }

    // print header meta data
    static function printHead($title, $links, $metas) {
        foreach ($links as $link) {
	        echo '<link ';
	        foreach ($link as $a => $v) {
		        echo "$a='$v' ";
	        }
	        echo '/>';
	    }
	    foreach ($metas as $m => $v) {
            if($m == "content-type") {
                echo "<meta charset='".html::$encoding."'/>";
            }
            else {
                echo "<meta http-equiv='$m' content='$v'/>";
            }
	    }
	    print "<title>" . $title . "</title>";
    }

    // debug print_r
    static function debug($objs) {
        if (is_array($objs)) {
            print "<hr/>";
            foreach ($objs as $title => $obj) {
                print "$title "; 
                var_dump($obj);
            }
            print "<hr/>";
        } else {
            return self::debug(array("unnamed" => $objs));
        }
    }
}

// simple class to create an html page
abstract class HTMLDoc {
    // usually not modified
    // if need be, override constructor and modify vars
    var $xmlns = 'http://www.w3.org/1999/xhtml';
    var $styletype = 'text/css';
    var $googleAnalytics = false;
    var $links = array();
    var $ielinks = array(); // links loaded only fpr IE
    var $metas = array();
    var $baseURI = "/";     // base path links are relative to
    var $scriptfiles = array();
    var $scriptcodes = array();
    var $bodyScriptFiles = array();
    // HTML attributes used on the whole page
    var $attributes = array();
    
    public function __construct() {
        // evaluate base for implicit links
        if (isset($_SERVER['REQUEST_URI'])) {
            $this->baseURI = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $parts = explode("/", $this->baseURI);
            if(count($parts) > 0) {
                unset($parts[count($parts) - 1]);
            }
            $this->baseURI = implode("/", $parts);
            // hack: care for some sepcial cases
            $dir = explode('/', $this->baseURI);
            switch (strtolower($dir[count($dir) - 1])) {
                case 'classes' :
                case 'css' :
                case 'core' :
                    $this->baseURI = dirname($this->baseURI);
            }
        }
    }
    
    public function makeRelativeLink($link) {
        if ($link[0] == '/') $link = substr($link, 1);
        $ret = $this->baseURI . '/' . $link;
        return $ret;
    }

    // usually not overridden
    public function generateHTML() {    // USE DOCTYPE that IE looks good...
        //return '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
        return '<!DOCTYPE html>
                <html xmlns="' . $this->xmlns . '">' . $this->html() . '</html>';
    }

    // usually not overridden
    public function html() {
        return '<head>' . $this->head() . '</head><body ' . html::formatAttributes($this->attributes) . '>'. $this->bodyScripts(). $this->body() . '</body>';
    }

    // usually not overridden
    public function head() {
        $ret = '<title>' . $this->title . '</title>' . $this->links() . $this->metas() . $this->scripts() . '<style type="' . $this->styletype . '">' . $this->style() . '</style>';
        if($this->googleAnalytics === true) {
            $ret .= "<script type=\"text/javascript\"> 
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', 'UA-1067265-7']);
            _gaq.push(['_trackPageview']);
            (function() {
                var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
             })();</script>";
        }
        return $ret;
    }

    // std function to create java script code blocks from file or directly
    public function scripts() {
        $ret = '';
        foreach ($this->scriptfiles as $scriptfile) {
            if (strpos($scriptfile, "calendarDateInput.js") !== false) {
                $ret .= '<script src="' . $this->makeRelativeLink($scriptfile) . '" type="text/javascript">
                    /***********************************************
                    * Dynamic Countdown script- © Dynamic Drive (http://www.dynamicdrive.com)
                    * This notice MUST stay intact for legal use
                    * Visit http://www.dynamicdrive.com/ for this script and 100s more.
                    ***********************************************/
                    </script>';
            } else {
                $scriptUrl = $scriptfile;
                if(strpos($scriptfile, "http") !== 0) {
                    $scriptUrl = $this->makeRelativeLink($scriptfile);
                }
                $ret .= '<script src="' . $scriptUrl . '" type="text/javascript"></script>';
            }
        }
        foreach ($this->scriptcodes as $scriptcode) {
            $ret .= '<script type="text/javascript">' . $scriptcode . '</script>';
        }
        return $ret;
    }

    public function bodyScripts() {
        $ret = '';
        foreach ($this->bodyScriptFiles as $scriptfile) {
            $ret .= '<script src="' . $scriptfile . '" type="text/javascript"></script>';
        }
        return $ret;
    }

    // std function to create <link> tags.  usually not overridden
    public function links() {
	$ret = '';
        foreach ($this->links as $link) {
            $ret .= '<link ';
            foreach ($link as $a => $v) {
                switch (strtolower($a)) {
                    case 'href' :
                    case 'src' :
                        $v = $this->makeRelativeLink($v);
                }
                $ret .= "$a='$v' ";
            }
            $ret .= '/>';
        }
        if (count($this->ielinks) > 0) {
            $ret .= "<!--[if IE]>";
            foreach ($this->ielinks as $link) {
                $ret .= '<link ';
                foreach ($link as $a => $v) {
                    switch (strtolower($a)) {
                        case 'href' :
                        case 'src' :
                            $v = $this->makeRelativeLink($v);
                    }
                    $ret .= "$a='$v' ";
                }
                $ret .= '/>';
            }
            $ret .= "<![endif]-->";
        }
	return $ret;
    }

    // std function to create <meta> tags.  usually not overridden
    public function metas() {
	    $ret = "";
	    foreach ($this->metas as $m => $v) {
	        if($m == "content-type") {
                $ret .= "<meta charset='".html::$encoding."'/>";
            }
            else {
                $ret .= "<meta http-equiv='$m' content='$v'/>";
            }
	    }
        return $ret;
    }

    // these function make up the beef, so they shall be overridden 
    abstract public function style();
    abstract public function body();
}

// html email body class (give a simple standard email body look and feel)
abstract class HTMLEmail extends HTMLDoc {
    var $title = "";
    
    public function style() {
	return '
	body { font-family: Arial; font-size: 12; }
	h1 { font-family: Arial; font-size: 22; font-variant: small-caps; font-weight: normal;}
	h2 { font-family: Arial; font-size: 18; font-weight: normal;}
	a { color: gray; text-decoration: none;  }
	td { text-indent: 0; }
	td.heading {  font-family: Arial; font-size: normal; font-weight: bold;  }
	td.text {  font-family: Arial; font-size:    normal; color: blue; font-weight: normal;  text-indent: 0; }
	td.data  {  font-family: Arial; font-size:    xx-small; font-weight: lighter; }
	td.debug {  font-family: Arial; font-size:    xx-small; font-weight: lighter; }
	table { margin-left: .5cm; margin-bottom: 2cm; }
	th { text-align: right; font-size: xx-small; }
	a.navigate { text-decoration: underline; color: blue; }
	.innologo { color: #008080; font-family:Tahoma; font-size: small; font-weight:bold;}
	.innoslogan { color: #808080; font-family:Arial; }
	.innofineprint { font-family:Arial; font-size: smaller; }
	.alert { color: red; font-weight: bold; font-style: italic; }
	';
    }

    final public function legalFooter($email = "info", $extension = "0") {
	return "<br>
	    <hr>
	    <span class='innologo'>innovaphone AG</span>
        <p class='innoslogan'>communicate. connect. collaborate.</p>
	    <p>
	    Umberto-Nobile-Straße 15 | 71063 Sindelfingen | Germany<br>
            Fon: + 49 (7031) 73009 $extension | Fax: + 49 (7031) 73009 99<br>
            E-Mail: $email@innovaphone.com | www.innovaphone.com<br>
	    <hr>
	    <p class='innofineprint'>
	    [Sitz der Gesellschaft: Sindelfingen | HBR Nr. 5196 Amtsgericht Böblingen | Vorsitzender des Aufsichtsrates: Gebhard Michel | Vorstand: Dagmar Geer (Vorsitzende), Carsten Bode, Guntram Diehl, Gerd Hornig]
	    <p/>
	    ";
    }

    // must return the subject
    abstract public function subject() ;

    // must return addresses
    // as array(
    //     [from] => array( address => name ),
    //     [to] => array( array(address => name), array(address => name), ... ),
    //     [cc] => array( array(address => name), array(address => name), ... ),
    //     [bcc] => array( array(address => name), array(address => name), ... ),
    //     [replyto] => array( array(address => name), array(address => name), ... )
    // e.g.
    // public function addresses() {
    //     return array( "from" => array("me@my.tld" => "me"),
    //                   "to" => array(array("you@your.tld" => "you"), 
    //                                 array("him@his.tld" => "he")));
    // }
    abstract public function addresses() ;
}

?>