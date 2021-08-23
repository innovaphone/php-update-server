<?php

/*
 * protected admin user interface
 */
require_once('../classes/updateserverV2.class.php');

// get config
$url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
$isHTTPS = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $url = str_replace("http://", "https://", $url);
    $isHTTPS = true;
}

// revert back to root directory
chdir("..");
$baseurl = dirname(dirname($url));
$pi = new UpdateServerV2($baseurl);

/*
 * UI pages
 */
require_once 'classes/innoinputpage.class.php';

class doLogin extends InputPageAction {

    function __construct($name, $value, $prompt = null) {
        parent::__construct($name, $value, $prompt);
        $this->anyway = true;
    }

    function action(InputPage &$page, $phase) {

        if (($page->goodpw == "" && $page->gooduser == "") || ($page->user->value == $page->gooduser && $page->pw->value == $page->goodpw)) {
            $page->pw->msg = "";
            UpdateV2Page::$isLoggedin = true;
            $page->login->hidden = true;
            $page->user->hidden = true;
            $page->pw->hidden = true;
        } else {
            // $pw->msg .= " (gooduser=$gooduser, goodpw=$goodpw, user={$user->value}, pw={$pw->value}";  
            UpdateV2Page::$isLoggedin = false;
            $page->logout->hidden = true;
        }
        $_SESSION['logged-on'] = UpdateV2Page::$isLoggedin;
	// print "<pre> doLogin session: "; var_dump($_SESSION); print "</pre>";
	// print "<pre> doLogin sessionId: "; var_dump(session_id()); print "</pre>";
        $class = get_class($page);
        $newpage = new $class($page->pi, $page->site);
        $newpage->render();
    }

}

class doLogout extends InputPageAction {

    function __construct($name, $value, $prompt = null) {
        parent::__construct($name, $value, $prompt);
        $this->anyway = true;
    }

    function action(InputPage &$page, $phase) {
        $class = get_class($page);
        $_SESSION['logged-on'] = false;
        UpdateV2Page::$isLoggedin = false;
        $page->logout->hidden = true;
        $newpage = new $class($page->pi, $page->site);
        $newpage->render();
    }

}

class doSubmit extends InputPageAction {

    function action(InputPage &$page, $phase) {
        $page->reload($this->name, "show");
    }

}

class UpdateV2Page extends innoInputPage {

    public function head() {
        return parent::head() . '<base href="../">';
    }

    function renderSuffix() {
        return "";
    }

    public function renderEpilog() {
        return "";
    }

    static $isLoggedin = false;

    /**
     *
     * @var UpdateServerV2 
     */
    var $pi = null;
    var $gooduser = null;
    var $goodpw = null;

    /**
     *
     * @var InputPageStringField
     */
    var $user = null;

    /**
     *
     * @var InputPagePasswordField 
     */
    var $pw = null;

    /**
     *
     * @var doLogin 
     */
    var $login = null;

    /**
     *
     * @var doLogout 
     */
    var $logout = null;

    function __construct(UpdateServerV2 $pi, $site, $title = null) {
        parent::__construct($site, $title);
        self::$method = "post";
        InputPage::$method = "post";
        $this->logoImg = "/web/innovaphone_logo_claim_fisch.png";

        $this->scriptfiles = array();
        $this->baseURI = "."; // set using "<base>" statement in header
        $this->links = array(array("rel" => "stylesheet", "type" => "text/css", "href" => "../web/style.css"));


        session_start();
	// print "<pre>";
	// var_dump($_COOKIE);
	// var_dump($_SESSION);
	// print "</pre>";
        UpdateV2Page::$isLoggedin = isset($_SESSION['logged-on']) ? $_SESSION['logged-on'] : false;

        $this->pi = $pi;
        $this->gooduser = $gooduser = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->master, "user", null);
        $this->goodpw = $goodpw = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->master, "password", null);
        if (!($goodpw == "" && $gooduser == "")) {
            // maintain a session
            // force https
            if (!(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')) {
                $url = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}?{$_SERVER["QUERY_STRING"]}";
                $this->warp($url);
                exit;
            }
        }

        // create fields
        $this->addField($this->user = new InputPageStringField("user", "User: ", "", null, "admin user name"));
        $this->addField($this->pw = new InputPagePasswordField("pw", "Password: ", "", null));
        $this->addField($this->login = new doLogin("dologin", "Login"));
        $this->addField($this->logout = new doLogout("dologout", "Logout"));
        $this->user->updateFromForm();
        $this->pw->updateFromForm();

        if (!self::$isLoggedin) {
            $this->logout->hidden = true;
            $this->pw->msg = "Wrong User/Password";
        } else {
            $this->user->hidden = true;
            $this->pw->hidden = true;
            $this->login->hidden = true;
        }
        $this->addField(new InputPageHorizontalRule());
    }

}

class DownloadPage extends UpdateV2Page {

    function __construct(UpdateServerV2 $pi) {
        parent::__construct($pi, "", "");
    }

    public function download($fn) {
        if (self::$isLoggedin) {
            // download file
            $bs = basename($fn);
            $ubs = urlencode($bs);
            header("Content-Disposition: attachment; filename=$ubs");
            header("Expires: -1");
            header("Cache-Control: Public");
            header("Content-Type: application/octet-stream");
            header("Content-Length: " . filesize($fn));
            readfile($fn);
            exit;
        } else
            UpdateServerV2::bailout("not logged in");
    }

}

class SelectPage extends UpdateV2Page {

    function __construct(UpdateServerV2 $pi, $site, $title = null) {
        parent::__construct($pi, $site, $title);
        if (self::$isLoggedin) {
            $this->addField(new InputPageText(null, "
                <ul>
                    <li>
                        <a href='admin/admin.php?mode=status'>->Status Page</a>
                    <li>
                        <a href='admin/admin.php?mode=info'>->Info Page</a>
                    <li>
                        <a href='admin/admin.php?mode=show'>->Configuration Page</a>
                </ul>"));
        }
    }

}

class PlainTextPage extends UpdateV2Page {

    var $text = null;

    function __construct(UpdateServerV2 $pi, $site, $title) {
        parent::__construct($pi, $site, $title);

        if (self::$isLoggedin) {
            $this->addField(new InputPageHiddenIdField("mode", "status"));
            $this->textfield = $this->addField(new InputPageText("text", ""));
        }
    }

}

class StatusPage extends PlainTextPage {

    function __construct(UpdateServerV2 $pi, $site, $title) {
        parent::__construct($pi, $site, $title);
        if (self::$isLoggedin) {
            $this->textfield->value = $pi->status();
        }
    }

}

class ShowPage extends PlainTextPage {

    function __construct(UpdateServerV2 $pi, $site, $title) {
        parent::__construct($pi, $site, $title);
        if (self::$isLoggedin) {
            $this->textfield->value = $pi->show();
        }
    }

}

class InfoPage extends UpdateV2Page {

    /**
     *
     * @var InputPageDropdownField 
     */
    var $env_field;

    function __construct(UpdateServerV2 $pi, $site, $title) {
        parent::__construct($pi, $site, $title);

        if (self::$isLoggedin) {
            //  mode arg
            $this->addField(new InputPageHiddenIdField("mode", "info"));
            // selectable environment
            $envs = array();
            foreach ($pi->xmlconfig->environments->environment as $b) {
                $id = (string) $b['id'];
                $envs[$id] = $id;
            }
            asort($envs);
            $this->env_field = $this->addField(new InputPageDropdownField("env", "Environment", $envs));
            $this->env_field->updateFromForm();
            $env = "";
            foreach ($this->env_field->value as $id => $selected) {
                if ($selected)
                    $env = $id;
            }

            // device name for mobile deployment
            $this->devname_field = $fld = $this->addField(new InputPageStringField("devname", "Device Name", "", null, "enter desired device name"));
            $fld->updateFromForm();
            $fld->attributes["size"] = 50;
            $fld->attributes["maxlength"] = 32;
            $fld->updateFromForm();
            $devname = $fld->value;
            $udevname = urlencode($devname);
            $hdevname = htmlspecialchars($devname);

            $this->compute_field = $fld = $this->addField(new doSubmit("submit", "Update Links"));
            if (!empty($this->devname_field->value)) {
                $this->addField(new InputPageHorizontalRule);

                // update URL
                $scheme = "http";
                $url = "://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
                $isHTTPS = false;
                if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                    $scheme = "https";
                    $isHTTPS = true;
                }
                $this->addField(new InputPageText(null, "You can use the following line as value for the <i>Command File URL</i> field in <i>Services/Update</i>: "));
                $httpport = (($isHTTPS ? "443" : "80") == $_SERVER['SERVER_PORT']) ? "" : "{$_SERVER['SERVER_PORT']}";
                $url = ($isHTTPS ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
                $url = str_ireplace("/admin/admin.php", "", $url);
                $uenv = urlencode($env);
                $this->url_field = $fld = $this->addField(new InputPageText("url", "<pre>" . htmlspecialchars("$url/update.php?env=$uenv") . "</pre>"));
                $this->addField(new InputPageText(null, "You should also use <pre>1</pre> for the <i>Interval [min]</i> field."));
                $this->addField(new InputPageHorizontalRule);

                /*
                 * mypbx mobile setup commands
                 */
                $dscheme = "com.innovaphone.commands";
                // config+add+UP1+%2Furl+http%3A%2F%2Fupdatev2.innovaphone.com%2Fmtls%2Fupdatev2%2Fupdate.php+%2Fpoll+1%0D%0Aconfig+add+CMD0+%2Fname+Nico%2520Mobile%0D%0Aconfig+write%0D%0Aconfig+activate
                $cmd = "config add UP1 /url $url/update.php?env=$uenv /poll 1\r\n" .
                        "config add CMD0 /name $udevname\r\n" .
                        "config write\r\n" .
                        "config activate\r\n";

                $this->addField(new InputPageText(null, "Here is the link you can send to a <i>myPBX for iOS</i> user to set up his device properly:"));
                $this->urlwi_field = $fld = $this->addField(new InputPageText(null, "<div><a href='$dscheme:$cmd'>Please click here to setup your myPBX for iOS as <i>$hdevname</i></a>.</div>"));
                // $this->addField(new InputPageHorizontalRule());

                $this->addField(new InputPageText(null, "Here is the link you can send to a <i>myPBX for Android</i> user to set up his device properly:"));
                $this->urlwa_field = $fld = $this->addField(new InputPageText(null, "<div><a href='https://$dscheme/$cmd'>Please click here to setup your myPBX for Android as <i>$hdevname</i></a>.</div>"));

                /*
                 * generic
                 */
                $this->addField(new InputPageHorizontalRule());
                $this->addField(new InputPageText(null, "The following command script snippet can be used to set up a software phone (or any other device) properly. Save it to a file and upload it with <i>Maintenance/Upload/Config</i>"));
                $this->url3a_field = $fld = $this->addField(new InputPageText(null, "<pre>$cmd</pre>"));
            }
        }
    }

    function show(UpdateServerV2 $pi) {
        $page = new InfoPage($pi, "UpdateServerV2", "Device Initialization");
        $page->updateFields();
        /*
          if ($page->page() == "")
          $page = new InfoPage($pi, "InfoPage", $page->page->value);
          else
          print "show page " . $page->page();
         */
        $page->work();
    }

}

if (isset($_REQUEST['show']))
    $_REQUEST['mode'] = 'show'; // upward compat
if (isset($_REQUEST['mode'])) {
    switch ($_REQUEST['mode']) {
        case 'show' :
            $page = new ShowPage($pi, "UpdateServerV2", "Configuration");
            $page->work();
            break;
        case 'status';
            $page = new StatusPage($pi, "UpdateServerV2", "Status");
            $page->work();
            break;
        case 'info' :
            $page = new InfoPage($pi, "UpdateServerV2", "Info");
            $page->work();
            break;

        case 'ui' :
            if (!isset($_REQUEST['cmd']))
                UpdateServerV2::bailout("need 'cmd' arg for mode=ui");
            switch ($_REQUEST['cmd']) {
                case 'delstatus' :
                    if (!isset($_REQUEST['sn']))
                        UpdateServerV2::bailout("need 'sn' arg for mode=ui and cmd=delstatus");
                    print json_encode($pi->delStatus($_REQUEST['sn']));
                    break;
                case 'delfile' :
                    if (!isset($_REQUEST['sn']))
                        UpdateServerV2::bailout("need 'sn' arg for mode=ui and cmd=delfile");
                    if (!isset($_REQUEST['file']))
                        UpdateServerV2::bailout("need 'file' arg for mode=ui and cmd=delfile");
                    print json_encode($pi->delFile($_REQUEST['sn'], $_REQUEST['file']));
                    break;
                case 'touchstatus' :
                    if (!isset($_REQUEST['sn']))
                        UpdateServerV2::bailout("need 'sn' arg for mode=ui and cmd=touchstatus");
                    print json_encode($pi->touchStatus($_REQUEST['sn']));
                    break;
                case 'updatestatus' :
                    if (!isset($_REQUEST['sn']))
                        UpdateServerV2::bailout("need 'sn' arg for mode=ui and cmd=updatestatus");
                    print json_encode($pi->showDevice($_REQUEST['sn']));
                    break;
                case 'deliverfile' :
                    if (!isset($_REQUEST['fn']))
                        UpdateServerV2::bailout("need 'fn' arg for mode=ui and cmd=deliverfile");
                    $fn = $_REQUEST['fn'];
                    if (strpos($fn, "..") !== false)
                        UpdateServerV2::bailout("must not have relative parth in 'fn' arg for mode=ui and cmd=deliverfile");
                    if (!is_readable($fn))
                        UpdateServerV2::bailout("nope for 'fn' arg for mode=ui and cmd=deliverfile");
                    $page = new DownloadPage($pi);
                    $page->download($fn);
                    break;
                default:
                    UpdateServerV2::bailout("unknown cmd={$_REQUEST['cmd']} for cmd=ui");
            }
            exit; // no cleanupDeviceStates

            break;
        default :
            die("unknown mode={$_REQUEST['mode']}");
    }
} else {
    $page = new SelectPage($pi, "UpdateServerV2", "");
    $page->work();
}
