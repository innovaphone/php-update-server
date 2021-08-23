<?php

require_once('classes/updateserverV2.class.php');

$url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
$isHTTPS = false;
$isHTTPSport = null;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $url = str_replace("http://", "https://", $url);
    $isHTTPS = true;
    $isHTTPSport = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 443;
}

// get config
$baseurl = dirname($url);
$pi = new UpdateServerV2($baseurl);

function notify($msg, $die = false) {
    global $pi;
    print "# $msg\r\n";
    if (isset($pi))
        $pi->logDeviceState("msg", $msg, "msgs", "");
    if ($die) {
        $pi->cleanupDeviceStates();
        exit;
    }
    return($msg);
}

function doQueries($pi, $classes, $envs) {
    if (($queries = $pi->doQueries($classes, $envs)) !== null) {
        foreach ($queries as $id => $q) {
            print "# query '$id'\r\n";
            print "$q\r\n";
        }
        return true;
    } else {
        print "# no queries defined for any of callers classes: " . implode("+", $classes) . "\r\n";
        return false;
    }
}

/*
 * DEBUG
 */

function cb_ob($buffer, $phase) {
    $i = -1;
    if (isset($_REQUEST['sn'])) {
        chdir(dirname($_SERVER['SCRIPT_FILENAME']));
        $fn = "debug-{$_REQUEST['sn']}-certs.txt";
        $obfd = "\r\n";
        $obfd .= "========\r\n";
        $obfd .= "--" . implode(", ", $_REQUEST) . "\r\n";
        $obfd .= "--\r\n";
        $obfd .= $buffer;
        $obfd .= "-- " . time() . "\r\n";
        $i = file_put_contents($fn, $obfd, FILE_APPEND);
    }
    $cwd = getcwd();
    return "\r\n# DEBUG $fn -- $i -- $cwd\r\n" . $buffer;
}

function dbgout($msg, $content) {
    $dbg = str_replace("\r\n", "\n", $content);
    print "# $msg >\r\n";
    foreach (explode("\n", $dbg) as $dbgline) {
        print "# $dbgline\r\n";
    }
    print "# > $msg \r\n\r\n";
}

$debugscript = false;
if (isset($_REQUEST['sn'])) {
    $debugscript = LessSimpleXMLElement::getAttributeFromXML($pi->xmluserconfig, 'debugscript', "false") == "true";
    if ($debugscript) {
        ob_start("cb_ob");
    }
}
/*
 * END DEBUG
 */

if (isset($_REQUEST['show']))
    $_GET['mode'] = 'show'; // upward compat
if (isset($_GET['mode'])) {
    switch ($_GET['mode']) {
        case 'show' :
        case 'status';
        case 'ui' :
            die("for user interface functions, call admin/admin.php instead");
            break;
        case 'backup' :
            if (!isset($_REQUEST['hwid']))
                UpdateServerV2::bailout("need 'hwid' arg for mode=backup, use ?hwid=#h");
            if ($stamp = $pi->saveBackup($_REQUEST['hwid'])) {
                $pi->logDeviceState("backup", $stamp, 'device');
            }
            break;
        case 'query' :
            if (!isset($_REQUEST['sn']))
                UpdateServerV2::bailout("need 'sn' arg for mode=query, use ?sn=#m");
            if (!isset($_REQUEST['id']))
                UpdateServerV2::bailout("need 'id' arg for mode=query, go fix update.php");
            // get raw put data 
            if (($stream = fopen('php://input', "r")) !== FALSE) {
                $content = (stream_get_contents($stream));
            }
            $pi->saveQuery($_REQUEST['sn'], $_REQUEST['id'], $content);
            // DEBUG
            dbgout("query {$_REQUEST['id']}", $content);
            // DEBUG END
            break;
        case 'vardump' :
            if (!isset($_REQUEST['sn']))
                UpdateServerV2::bailout("need 'sn' arg for mode=vardump, use ?sn=#m");
            if (!isset($_REQUEST['var']))
                UpdateServerV2::bailout("need 'var' arg for mode=vardump, use ?var=varname");
            if (!isset($_REQUEST['file']))
                UpdateServerV2::bailout("need 'file' arg for mode=vardump, use ?file=filename");
            if (!UpdateServerV2::isNameInSubfolder($_REQUEST['file']))
                UpdateServerV2::bailout("?file: filename must not point to external directory");
            if (strtolower(pathinfo($_REQUEST['file'], PATHINFO_EXTENSION) == "php"))
                UpdateServerV2::bailout("bad file name");
            if (($stream = fopen('php://input', "r")) !== FALSE) {
                $content = (stream_get_contents($stream));
            } else
                UpdateServerV2::bailout("cannot read PUT data for mode=vardump");
            // DEBUG
            dbgout("vardump (before proc) {$_REQUEST['var']}", $content);
            // DEBUG END
            if (($value = $pi->getVarDump($_REQUEST['var'], $content)) === false)
                UpdateServerV2::bailout("failed to extract '{$_REQUEST['var']}' from PUT data for mode=vardump");
            if (isset($_REQUEST['proc']) && method_exists($pi, $_REQUEST['proc'])) {
                $member = $_REQUEST['proc'];
                $value = UpdateServerV2::$member($value);
            } else {
                $member = "none";
            }
            print "# processed (with '$member') length: " . strlen($value) . "\r\n";
            file_put_contents($_REQUEST['file'], $value);
            if (isset($_REQUEST['postproc']) && method_exists($pi, $_REQUEST['postproc'])) {
                $postproc = $_REQUEST['postproc'];
                if (($msg = $pi->$postproc($value, $_REQUEST['file'])) === null) {
                    print "# sucessfully post-processed (with '$postproc')\r\n";
                } else {
                    print "# failed to  post-process (with '$postproc'): $msg\r\n";
                }
            } else {
                $postproc = "none";
            }
            $pi->logDeviceState("var-" . UpdateServerV2::str2dnsname($_REQUEST['var']), time(), "device");
            notify("uploaded {$_REQUEST['file']} with proc=$member/postproc=$postproc $msg");
            break;
        case 'upload' :
            if (!isset($_REQUEST['sn']))
                UpdateServerV2::bailout("need 'sn' arg for mode=upload, use ?sn=#m");
            if (!isset($_REQUEST['file']))
                UpdateServerV2::bailout("need 'file' arg for mode=upload, use ?file=filename");
            if (!UpdateServerV2::isNameInSubfolder($_REQUEST['file']))
                UpdateServerV2::bailout("?file: filename must not point to external directory");
            if (empty($_FILES)) {
                die("No File uploaded - use back button to continue");
            }

            if (strtolower(pathinfo($_REQUEST['file'], PATHINFO_EXTENSION) == "php"))
                UpdateServerV2::bailout("bad file name");
            foreach ($_FILES as $id => $f) {
                if (strtolower($id) == strtolower("cert-{$_REQUEST['sn']}")) {
                    if ($f['error'] == 0) {
                        if (!move_uploaded_file($f['tmp_name'], $_REQUEST['file'])) {
                            UpdateServerV2::bailout("could not upload '{$f['name']}'");
                        }
                        if (isset($_REQUEST['proceed'])) {
                            header("Location: {$_REQUEST['proceed']}", true, 303);
                            exit;
                        }
                        UpdateServerV2::bailout("file upload - ?proceed= missing");
                    } else {
                        UpdateServerV2::bailout("upload error {$f['error']} - use back button to continue");
                    }
                }
            }
            UpdateServerV2::bailout("No File uploaded (2) - use back button to continue");
            break;

        default :
            UpdateServerV2::bailout("unknown cmd={$_GET['mode']}");
    }
    $pi->cleanupDeviceStates();
    exit;
}

notify("");

function rewriteUrl($usehttps = false, $usehttpsport = 443) {
    global $pi;
    global $missingArgs;
    global $url;
    global $phase;
    global $polling;
    global $nextphase;
    global $isHTTPS;
    $newurl = $url;

    print "\r\n";
    // rewrite poll url with state query args
    if (($newargs = $pi->getChangedStateArgs()) !== false || count($missingArgs) || $usehttps) {

        if (isset($newargs['polling']) && $polling != $newargs['polling']) {
            // polling has changed
            if ($newargs['polling'] == 0) {
                // turn it off
                notify("turn off fast polling");
                print "mod cmd UP1 provision 0\r\n";
                print "config add UP1 /poll " . $pi->getInterval() . "\r\n";
            } else {
                // turn it on
                notify("turn on fast polling ({$newargs['polling']})");
                print "mod cmd UP1 provision " . $pi->getPolling() . "\r\n";
                echo "config add UP1 /poll 1\r\n";
            }
        }

        // do not replicate update clients std query args
        {
            $args = array();
            // set new args
            if (is_array($newargs))
                foreach ($newargs as $key => $val) {
                    $args[$key] = "$val";
                }
            // add missing args
            if (is_array($missingArgs))
                foreach ($missingArgs as $key => $val) {
                    if (!isset($args[$key]))
                        $args[$key] = "$val";
                }
            // inherit old args (if not set new)
            foreach ($_REQUEST as $key => $val) {
                if (!isset($args[$key]))
                    $args[$key] = "$val";
            }
            // map meta arguments (such as e.g. "#m")
            $finalargs = array();
            foreach ($args as $key => &$val) {
                if (($newval = $pi->mapStdArg($key, $val)) !== null) {
                    $finalargs[] = "$key=$newval";
                }
            }
        }

        $newurl .= "?" . implode("&", $finalargs);
        // https?
        $httpsport = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "httpsport", "");
        if ($usehttps && (!$isHTTPS || $usehttpsport != $httpsport)) {
            notify("using HTTP and 'forcehttps' is set or wrong port ($usehttpsport) -> need to switch to HTTPS on port $httpsport");
            if (preg_match('@https?(://[^:/]+)(:[\d]+)?(.*)@i', $newurl, $matches)) {
                $newurl = "https" . $matches[1] . ($httpsport == "" ? "" : ":$httpsport") . $matches[3];
            } else {
                
            }
            if (($httpsurlmod = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "httpsurlmod", null)) != "") {
                // urlmod is something like a sed substitute expression, eg. s/mtls// without the s (that is, /mtls//)
                $umparts = explode($umsep = $httpsurlmod[0], $httpsurlmod);
                $pattern = "$umsep{$umparts[1]}$umsep{$umparts[3]}";
                $replace = $umparts[2];
                $newurl = preg_replace($pattern, $replace, $newurl);
                // notify("umparts: pattern: $pattern, replace: $replace, newurl: " . $newurl);
            }
        }

        print "\r\n";
        /*
          if ($newargs !== false && count($newargs))
          notify("changed state query args: " . implode(", ", array_keys($newargs)));
         */
        if (is_array($missingArgs) && count($missingArgs))
            notify("missing query args: " . implode(", ", array_keys($missingArgs)));

        echo "config add UP1 /url " . rawurlencode($newurl) . "\r\n";
        echo "config add UP1 /no-dhcp\r\n";
        echo "config write\r\n";
        echo "config activate\r\n";
        $pi->logDeviceState("nextphase", $nextphase);
        $pi->logDeviceState("phase", $phase);
        return true;
    } else {
        return false;
    }
}

// determine type
$requiredArgs = array(
    "type",
    "sn",
    "hwid",
    "ip"
);

// see if we miss required args, no use in continueing without this information
$missingArgs = array();
foreach ($requiredArgs as $key) {
    if (!isset($_REQUEST[$key])) {
        // $pi->setStateArg($key, "");
        $missingArgs[$key] = null;
    }
}
if (count($missingArgs) > 0) {
    if (!isset($_REQUEST['phase']))
        $pi->setStateArg("phase", $pi->firstPhase());
    if (!isset($_REQUEST['polling']))
        $pi->setStateArg("polling", $pi->getPolling());
    rewriteUrl();
    exit;
}

if (isset($_REQUEST['type'])) {
    $type = $_REQUEST['type'];
    // determine classes
    $classes = $pi->getClasses($type);
    $pi->logDeviceState("type", $type, "device");
    $pi->logDeviceState("classes", implode(", ", $classes), "device");
} else {
    $type = "generic";
    $classes = array();
}

// determine IP address
$pi->logDeviceState("ip", UpdateServerV2::getRemoteIp($rp, $via), "device");
$pi->logDeviceState("rp", $rp ? "true" : "false", "device");
if ($rp) {
    $pi->logDeviceState("proxy", $via, "device");
}

// determine phases
if (!isset($_REQUEST['phase']) || empty($_REQUEST['phase'])) {
// use first phase
    $phase = $pi->firstPhase();
} else {
    $phase = $_REQUEST['phase'];
    if (!$pi->checkPhase($phase))
        notify("invalid phase: '{$_REQUEST['phase']}'", true);
}
$nextphase = $pi->nextPhase($phase);
$pi->logDeviceState("phase", $phase, "device");

// determine polling 
$polling = isset($_REQUEST['polling']) ? $_REQUEST['polling'] : null;

// determine environment
if (!isset($_REQUEST['env'])) {
    $rawenvs = array($pi->firstEnvironment());
} else {
    $rawenvs = explode(",", $_REQUEST['env']);
}
// compute implications
$envs = array();
foreach ($rawenvs as $env) {
    if (!$pi->checkEnvironment($env))
        notify("invalid environment: '$env' ({$_REQUEST['env']})", true);
    $envs += $pi->expandEnvironment($env);
}
$pi->logDeviceState("environments", implode(", ", $envs), "device");


notify("phase: $phase, nextphase: $nextphase, environment: " . implode('+', $envs) . ", type: $type, classes: " . implode('+', $classes) . "");
if ($pi->doSkipNext()) {
    print "# need up-to-date query state, skipping update script, doing queries only\r\n";
    doQueries($pi, $classes, $envs);
    // make sure we are in fast polling mode
    $pi->setStateArg("polling", $pi->getPolling());
    $pi->cleanupDeviceStates();
    exit;
}

// determine valid files
print "\r\n";
print "# existing files (from " . $pi->scriptdir . "):\r\n";
$pfiles = $pi->getPossibleScripts($newestfiletime, $newestfile, isset($_REQUEST['sn']) ? $_REQUEST['sn'] : null, $classes, $phase, $envs);
$files = array();
$now = time();
foreach ($pfiles as $fn => $mtime) {
    if (is_numeric($mtime)) {
        print "# > $fn: ";
        print UpdateServerV2::_since($mtime);
        $files[] = $fn;
        print "\r\n";
    }
}
print "\r\n";
print "# possible further files (from " . $pi->scriptdir . "):\r\n";
foreach ($pfiles as $fn => $mtime) {
    if (!is_numeric($mtime)) {
        print "# $fn   ";
        // print $mtime;
        print "\r\n";
    }
}
print "\r\n";
$age = $now - $newestfiletime;
$grace = ($pi->getGrace());


// shall we do backups?
// must do this before times and check cmd
if (($scfg = $pi->doBackups()) !== null) {
    if (LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "forcehttps", "false") == "true" && !$isHTTPS) {
        notify("backups enabled but forcehttps is true and not using HTTPS");
    } else {
        print "\r\n";
        print "$scfg\r\n";
    }
} else {
    print "\r\n";
    notify("Backups not enabled in config");
}


// see if we want to defer script execution cause someone is working on it
if ($newestfiletime) {
    print "\r\n# newest script ($newestfile) {$age}s old\r\n";
    if ($age < $grace) {
        notify("files being updated ($newestfile {$age}s old, waiting for at least {$grace}s to expire)", true);
    }
}

// do we need to do certificate renewal?
print "\r\n";
$certcmds = $pi->doCertificates($type, $certmsgs, $certificateIsOK);
if (count($certmsgs))
    $pi->logDeviceState("certstat", $certmsgs[array_pop(array_keys($certmsgs))], "device");


// shall we gather more device data?
doQueries($pi, $classes, $envs);

// see if we have restricted times
$times = $pi->getTimes();
if ($times != null) {
    print "\r\n# Restricted times\r\n$times\r\n";
}

print "\r\n";
$reset = false;
// see if we need to update boot code or firmware
if (isset($pi->xmlconfig->master) && isset($pi->xmlconfig->master['info']) && $pi->xmlconfig->master['info'] != "") {

    // query pbx fw info
    if ($pi->cacheIsCurrent()) {
        print "# firmware build info cache is current\r\n";
        $info = $pi->getCachedPbxInfo();
    } else {
        $info = new SimpleXMLElement("<result><error>cache not current</error></result>");
    }
    if (!isset($info->prot)) {
        print "# failed to use cached data ({$info->error}), retrieving info online\r\n";
        $info = $pi->getOnlinePbxInfo();
        if (!isset($info->prot)) {
            print("# cannot get PBX info: {$info->error}, reading cache\r\n");
            $info = $pi->getCachedPbxInfo();
            if (!isset($info->prot)) {
                UpdateServerV2::bailout("cannot get cached PBX info: {$info->error}");
            }
        }
    } {
        $nofirms = $noboots = array();
        if (isset($pi->xmlconfig->nobootdev->model)) {
            foreach ($pi->xmlconfig->nobootdev->model as $dev) {
                $noboots[] = strtolower((string) $dev);
            }
        }
        // print "# noboot: " . implode(", ", $noboots) . "\r\n";
        if (isset($pi->xmlconfig->nofirmdev->model)) {
            foreach ($pi->xmlconfig->nofirmdev->model as $dev) {
                $nofirms[] = strtolower((string) $dev);
            }
        }
        print "# nofirm: " . implode(", ", $nofirms) . " ype=$type\r\n";

        if (!in_array(strtolower($type), $nofirms)) {
            if (!isset($_REQUEST['PROT']) || ($_REQUEST['PROT'] != (string) $info->prot)) {
                notify("current firmware (" . (isset($_REQUEST['PROT']) ? $_REQUEST['PROT'] : "unknown") . ") does not match required firmware ({$info->prot})");
                print "mod cmd UP0 prot " . $pi->getFWStorage((string) $info->prot, $type, "prot") . " ser {$info->prot}\r\n";
                $pi->logDeviceState("requested", $info->prot, "firmware");
                $pi->logDeviceState("requested-at", time(), "firmware");
                $reset = true;
            }
        } else {
            print "# no firmware for device of type '$type'\r\n";
        }
        if (!in_array(strtolower($type), $noboots)) {
            if (!isset($_REQUEST['BOOT']) || ($_REQUEST['BOOT'] != (string) $info->boot)) {
                notify("current boot code (" . (isset($_REQUEST['BOOT']) ? $_REQUEST['BOOT'] : "unknown") . ") does not match required boot code ({$info->boot})");
                print "mod cmd UP0 boot " . $pi->getFWStorage((string) $info->boot, $type, "boot") . " ser {$info->boot}\r\n";
                $pi->logDeviceState("requested", $info->boot . "bootcode");
                $pi->logDeviceState("requested-at", time(), "bootcode");
                $reset = true;
            }
        } else {
            print "# no bootcode for device of type '$type'\r\n";
        }
    }
}
if (isset($_REQUEST['PROT'])) {
    $pi->logDeviceState("build", $_REQUEST['PROT'], "firmware");
}
if (isset($_REQUEST['BOOT'])) {
    $pi->logDeviceState("build", $_REQUEST['BOOT'], "bootcode");
}
if (isset($_REQUEST['hwid'])) {
    $pi->logDeviceState("hwid", $_REQUEST['hwid'], "device");
}
if (isset($_REQUEST['ip'])) {
    $pi->logDeviceState("realip", $_REQUEST['ip'], "device");
}

if ($reset) {
    print "# make sure remaining update scripts are executed with up-to-date firmware and update URL\r\n";
    print "iresetn\r\n";
}

// shall we deploy certificates?
foreach ($certmsgs as $msg) {
    notify("certificates: $msg ");
}
if ($certcmds !== null) {
    foreach ($certcmds as $cmt => $c) {
        notify("certificates: $cmt");
        print "$c\r\n";
    }
    $pi->setStateArg("polling", $pi->getPolling());
    if (!rewriteUrl()) {
        echo "config write\r\n";
        echo "config activate\r\n";
    }
    print "iresetn\r\n";
    notify("certificate cmds need to process - no further cmds", true);
    exit;
}


/*
 * this is where we actually deliver update scripts
 */
print "# no certificate cmds required - processing normal updates\r\n";

// do we need to enforce HTTPS?
if (LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "forcehttps", "false") == "true") {
    $needhttpsport = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "httpsport", 443);
    if (!$isHTTPS || $needhttpsport != $isHTTPSport) {
        $pi->setStateArg("polling", $pi->getPolling());
        rewriteUrl(true, $isHTTPSport);
        notify("forcehttps - currently " . ($isHTTPS ? "" : "NOT ") . "using HTTPS on port $isHTTPSport, new polling: " . $pi->getPolling(), true);
        // this notify never comes back
    }
    // CERT fiddling
    $debugcerts = LessSimpleXMLElement::getAttributeFromXML($pi->xmluserconfig, 'debugcerts', "false") == "true";
    if ($debugcerts) {
        $fd = fopen("Request-Certs.txt", "a");
        foreach (
        array(
            "HTTPS", "SSL_CLIENT_S_DN", "SSL_CLIENT_S_DN_CN", "SSL_CLIENT_I_DN", "SSL_CLIENT_VERIFY", "SSL_SERVER_S_DN"
        )
        as $var) {
            fwrite($fd, "# $var: '" . (empty($_SERVER[$var]) ? "<not-set>" : $_SERVER[$var]) . "'\n");
        }
        fwrite($fd, "-----\n");
        fclose($fd);
    }

    if (LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->times, "forcetrust", "false") == "true") {
        // see if we are using ligHTTPd and do some glitches

        if (!isset($pi->xmlconfig->customcerts['CAname'])) {
            UpdateServerV2::bailout("certificates: customcerts/@CAname must be defined for certificate trust enforcement");
        }
        $_sep = isset($pi->xmlconfig->customcerts['CAnamesep']) ? (string) $pi->xmlconfig->customcerts['CAnamesep'] : ",";
        $goodCA = explode($_sep, (string) $pi->xmlconfig->customcerts['CAname']);

        if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'lighttpd/') !== false) {
            // this is ligHTTPd
            $isligHTTPd = true;
            $_SERVER['SSL_CLIENT_S_DN'] = "/C={$_SERVER['SSL_CLIENT_S_DN_C']}/O={$_SERVER['SSL_CLIENT_S_DN_O']}/CN={$_SERVER['SSL_CLIENT_S_DN_CN']}";
            $_SERVER['SSL_CLIENT_VERIFY'] = 'SUCCESS'; // make sure this is asserted in lighttpd.conf (using ssl.verifyclient.enforce);
            $_SERVER['SSL_CLIENT_I_DN'] = $goodCA[count($goodCA) - 1];
        } else {
            $isligHTTPd = false;
        }

        if (!isset($_SERVER['SSL_CLIENT_S_DN_CN'])) {
            notify("cannot verify CN with HTTPS: SSL_CLIENT_S_DN_CN not present - fix web server configuration or use MTLS-enabled port!", true);
        } else {
            $shownCN = $_SERVER['SSL_CLIENT_S_DN_CN'];
            $requiredCN = $_REQUEST['hwid'];
            $requiredAlternativeCN = str_replace("-", "", $_REQUEST['sn']);
            $wildcardCN = LessSimpleXMLElement::getAttributeFromXML($pi->xmlconfig->customcerts, "CAwildcard", null);
            // some early certificates where broken, fix here:
            $brokenCN = array(
                "IP222s" => "IP222",
                "IP232s" => "IP232",
                "IP240a" => "IP240",
                "IP200a" => "IP200",
            );
            $cnparts = explode("-", $shownCN);

            foreach ($brokenCN as $broken => $fix) {
                if ($cnparts[0] == $broken) {
                    $cnparts[0] = $fix;
                    $shownCN = implode("-", $cnparts);
                    break;
                }
            }
            if ($shownCN != $requiredCN && $shownCN != $requiredAlternativeCN &&
                    (($wildcardCN === null) || ($wildcardCN != $shownCN))) {
                notify("Device claims to be '$requiredCN / $requiredAlternativeCN' but identifies as '$shownCN' by TLS", true);
            }
            if (empty($_SERVER['SSL_CLIENT_VERIFY']) || $_SERVER['SSL_CLIENT_VERIFY'] != 'SUCCESS') {
                notify("TLS certificate ($shownCN) not trusted -- " . ( isset($_SERVER['SSL_CLIENT_VERIFY']) ? $_SERVER['SSL_CLIENT_VERIFY'] : "<undefined>"), true);
            }
            if (empty($_SERVER['SSL_CLIENT_I_DN']) || !in_array($_SERVER['SSL_CLIENT_I_DN'], $goodCA)) {
                notify(
                        "TLS certificate issuer ({$_SERVER['SSL_CLIENT_I_DN']}) not trusted for TLS certificate ($shownCN) -- " .
                        ( isset($_SERVER['SSL_CLIENT_VERIFY']) ?
                                $_SERVER['SSL_CLIENT_VERIFY'] :
                                "<undefined>"), true);
            }
            notify("good certificate(CN='$shownCN'" . ($isligHTTPd ? "" : ", ISSUER='{$_SERVER['SSL_CLIENT_I_DN']}'") . ")");
        }
    }
}

print "\r\n";
// see if we need to warp over to next phase

if ($nextphase !== null) {
    $pi->setStateArg("phase", $nextphase);
    $pi->setStateArg("polling", $pi->getPolling());
} else {
    $pi->setStateArg("polling", 0);
}

// guard against unchanged config files (only for the final phase, all others are repeated upon request)
$deliverSnippets = true;
$docheck = false;
if (($nextphase === null) && ($sig = $pi->getFilesSignature($files)) !== null) {
    $docheck = true;
    if (isset($_REQUEST['CHECK']) && $_REQUEST['CHECK'] == $sig) {
        $deliverSnippets = false;
    }
}

if ($deliverSnippets) {
    // need to deliver snippets
    foreach ($files as $file) {
        notify("    $file");
        print "# { \r\n# begin script '$file' \r\n";
        $script = @file_get_contents("./$file");
        if ($script === false)
            notify("cannot read script '$file' in " . getcwd() . "");
        else {
            print "$script\r\n";
            $pi->logDeviceState("delivered", time(), "config", array("filename", "$file"));
            $pi->logDeviceState("version", filemtime($file), "config", array("filename", "$file"));
        }
        print "# end script '$file'\r\n# }\r\n";
    }
    print "\r\n# end of all scripts \r\n";

    if (!rewriteUrl()) {
        print "config write\r\n";
        print "config activate\r\n";
    }
    if ($docheck) {
        // we do this just to set the CHECK var, we do not send snippets on our own if the CHECK serial ist good already (see a few lines below)
        print "# set CHECK var\r\n";
        print "mod cmd UP1 check ser $sig\r\n";
    }
    print "# trigger reset if required\r\n";
    print "iresetn\r\n";
} else {
    print "# not delivering any snippets - CHECK serial $sig is good\r\n";
    rewriteUrl();
}

// better no extra newlines at end of script
// print "\r\n";

$pi->cleanupDeviceStates();
