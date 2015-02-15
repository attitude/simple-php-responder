<?php

function arrayHasItems($data)
{
    if (is_object($data) || is_array($data)) {
        foreach (array_keys((array) $data) as $k) {
            if (is_array($data)) {
                $v =& $data[$k];
            } elseif (is_object($data)) {
                $v =& $data->{$k};
            }

            if (is_array($v) || is_object($v)) {
                $v = arrayHasItems($v);

                if (is_array($v) && ! is_assoc_array($v)) {
                    $count = count($v);

                    if (is_array($data)) {
                        $data['__has'.ucfirst($k)] = empty($v) ? false : $count;
                    } else {
                        $data->{'__has'.ucfirst($k)} = empty($v) ? false : $count;
                    }
                }
            }
        }
    }

    return $data;
}
