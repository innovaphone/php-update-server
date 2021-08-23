<?php
/*
 * the one and only thing you have to update in this file is the URL of your new update server in constant newServer below.
 * Set it to the URL of your new update server, do NOT add any query arguments
 */
const newServer = 'https://update.yourcompany.com:444/update/update.php';
/*
 * do NOT change anything below this line
 */

if (!isset($_SERVER['QUERY_STRING'])) {
    // oops, 
    die('# $_SERVER["QUERY_STRING"] not available - cannot redirect');
}

$url = newServer . "?{$_SERVER['QUERY_STRING']}";
print "# warping to '$url'\r\n";
header("Location: $url");