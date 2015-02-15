<?php

/**
 * Minify HTML response
 *
 * WARNING: REMOVES ALL WHITESPACE
 */
function minifyHTMLResponse($html)
{
    $lines = explode("\n", $html);
    foreach ($lines as $i => &$line) {
        if ($pos = strpos($line, '//')) {
            if ($line[$pos-1] !== ':' && $line[$pos-1] !== '"' && $line[$pos-1] !== "'") {
                $line = substr($line, 0, $pos);
            }
        }

        $line = trim($line);

        if (empty($line)) {
            unset($lines[$i]);
        }
    }

    return implode(' ', $lines);
}
