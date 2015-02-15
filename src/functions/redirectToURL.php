<?php

/**
 * Redirects to URL
 *
 */
function redirectToURL($URL, $content = '')
{
    statusHeader(301);
    header('Location: '.$URL);

    echo $content;

    exit;
}
