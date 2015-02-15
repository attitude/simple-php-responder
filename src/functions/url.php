<?php

/**
 * Unify any relative and return full url
 *
 */
function url($s)
{
    global $cfg;
    return $cfg->baseURI.'/'.trim($s, '/');
}
