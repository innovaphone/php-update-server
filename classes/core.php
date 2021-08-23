<?php

	# some basic functions for the download server

    function CIHead($title = "") {
	if ($title <> "") $title = " - " . $title;
	echo '<html><head>';
	echo '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">';
	echo '<link rel="stylesheet" type="text/css" href="/style.css" />';
	echo "<title>innovaphone Download Site$title</title>";
	echo "</head><body>";
	echo '<table width="100%" border="0" cellspacing="0">';

	echo '<table border="1" width="100%" cellspacing="0" cellpadding="0">';
	echo '  <tr>';
	echo '    <td width="100%">';
	echo '      <table width="100%" border="0" cellspacing="0">';
	echo ' 	    <tr><td colspan="3" background="/images/gradient_down.jpg" height="11">&nbsp;</td></tr>';
	echo ' 	    <tr><td valign="bottom" width="110">&nbsp;</td><td align="center"><a href="http://www.innovaphone.com"><img src="/images/web_logo.jpg" border=0></a></td><td width="150" valign="top" align="center">&nbsp;</td></tr>';

	echo ' 	    <tr><td colspan="3" background="/images/gradient_up.jpg" height="11">&nbsp;</td></tr>';
	echo '      </table>';
	echo '    </td>';
	echo '  </tr>';
	echo '  <tr>';
	echo '    <td width="100%">';
	echo '      <table width="100%" border="0" cellspacing="7">';
	echo ' 	    <tr valign="top">';
	echo '<td width="110">';
	echo '		    <table border="0" cellspacing="0" cellpadding="0">';
	echo '		      &nbsp;';
	echo '			</table>';
	echo '	      </td>';
	echo '	      <td>';
    }

    function CIFoot() {
	echo '	      </td>';
	echo '	      <td width="115">';
	echo '	        &nbsp;';
	echo '	      </td>';
	echo '        </tr>';
	echo '	  </table>';
	echo '	</td>';
	echo '  </tr>	';
	echo '</table> ';
	echo '<p class="small" align="center">';
	echo '  <a href="http://www.innovaphone.com/index.php?id=25&L=0">Imprint</a> |';
	echo '  <a href="http://www.innovaphone.com/index.php?id=26&L=0">Terms of Use</a> |';
	echo '  <a href="http://www.innovaphone.com/index.php?id=27&L=0">Terms of Trade</a> |';
	echo '  <a href="http://www.innovaphone.com/index.php?id=28&L=0">Privacy</a><br>';
	echo '  &copy;&nbsp;1997-2007 innovaphone AG ';
	echo '</p>';
    }

    function CINavigation() {
	print '<div class="level1-item-build" align="right"> ';
	print '<a title="back to last page" href="javascript:history.back()">&larr;back</a><br>';
	if ( $_SERVER['SERVER_NAME'] != "www.innovaphone.com" ) {
	    print '<a title="go to ' . $_SERVER['SERVER_NAME'] . '"  href="/">home&rarr; </a><br>';
	}
	print '<a title="go to www.innovaphone.com "  href="http://www.innovaphone.com">innovaphone&rarr; </a> ';
	print '</div>';
    }

    # take file info (from stat()) and show its size abbreviated
    function showsize($info) {
	$size = $info[7];
	if ($size < 1000) return $size . " B";
	else if ($size < 1000000) return round($size / 1024, 1) . " kB";
	else return round($size / (1024 * 1024), 2) . " MB";
    }


    # take a file and return it in a variable (usually used with echo)
    function copyfile2html($fn) {
	$ret = "";
	if (is_file($fn) && ($fd = fopen($fn, "r"))) {
	    while (!feof($fd)) {
		$ret .= fgets($fd);
	    }
	    fclose($fd);
	    return $ret;
	}
    }

    # take a file und execute it as PHP
    # this is a multi-language replacement for copyfile2html
    function executefile2html($fn) {
	// the file is expected to contain php code that fills the variable $output
	// e.g. 
	// < ?php
	// $output = tl::tl("mystring");
	// ? >
	$output = "";
	if (is_file($fn) && ($fd = fopen($fn, "r"))) {
	    fclose($fd);
	    include $fn;
	} else {
            $output = "($fn missing)";
        }
	return " $output";
    }

    function pathsqueeze($path) {
	# squeeze out "." and ".." entries in path
	# also remove uppercases for windows
	$path = str_replace("\\", "/", $path);
	$pa = explode("/", $path);
	$reta = array();
	foreach ($pa as $e) {
	    if ($e == ".") continue;
	    else if ($e == "..") array_pop($reta);
	    else array_push($reta, $e);
	}
	return strtolower(implode("/", $reta));
    }

    function startswith($path, $start) {
	# see if $path is underneath $start
	$start = pathsqueeze($start);
	$ret = (substr($path, 0, strlen($start)) == $start);
	return $ret;
    }

    /** Validate an email address.
     *  code source: http://www.linuxjournal.com/article/9585?page=0,3
     *  @param string $email Provide email address (raw input)
     *  @return Returns true if the email address has the email address format and the domain exists.
     */
    
    function valid_email($email, $checkDNS = false)
    {
        $isValid = true;
        $atIndex = strrpos($email, "@");
        if (is_bool($atIndex) && !$atIndex)
        {
            $isValid = false;
        }
        else
        {
            $domain = substr($email, $atIndex+1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);
            if ($localLen < 1 || $localLen > 64)
            {
                // local part length exceeded
                $isValid = false;
            }
            else if ($domainLen < 1 || $domainLen > 255)
            {
                // domain part length exceeded
                $isValid = false;
            }
            else if ($local[0] == '.' || $local[$localLen-1] == '.')
            {
                // local part starts or ends with '.'
                $isValid = false;
            }
            else if (preg_match('/\\.\\./', $local))
            {
                // local part has two consecutive dots
                $isValid = false;
            }
            else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
            {
                // character not valid in domain part
                $isValid = false;
            }
            else if (preg_match('/\\.\\./', $domain))
            {
                // domain part has two consecutive dots
                $isValid = false;
            }
            else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local)))
            {
                // character not valid in local part unless 
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/',
                    str_replace("\\\\", "", $local)))
                {
                    $isValid = false;
                }
            }
            if ($isValid && $checkDNS && !(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A")))
            {
                // domain not found in DNS
                $isValid = false;
            }
        }
        return $isValid;
    }
    
?>
