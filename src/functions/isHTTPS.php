<?php

/**
 *
 * Checks whether request is HTTPS
 *
 * @see: http://stackoverflow.com/questions/1175096/how-to-find-out-if-you-are-using-https-without-serverhttps
 *
 */
function isHTTPS(){
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
}
