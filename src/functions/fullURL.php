<?php

/**
 * Returns full site URL
 *
 */
function fullURL($uri = '')
{
    if (empty($uri)) {
        $uri = $_SERVER['REQUEST_URI'];
    }

    return 'http'.(isHTTPS() ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$uri;
}
