<?php

function is_assoc_array(array $array, $speedy=true)
{
    if ($speedy) {
        return ($array !== array_values($array));
    }

    // More memory efficient
    return $array = array_keys($array); return ($array !== array_keys($array));
}
