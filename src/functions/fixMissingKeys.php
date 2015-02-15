<?php

function fixMissingKeys($data)
{
    if (is_array($data)) {
        foreach ($data as &$values) {
            $values = $this->fixMissingKeys($values);

            if (is_array($values) && !$this->is_assoc_array($values)) {
                $empty_keys = array();
                foreach ($values as &$v) {
                    if (is_array($v) && $this->is_assoc_array($v)) {
                        foreach ($v as $empty_key => $empty_value) {
                            $empty_keys[$empty_key] = (is_array($empty_value)) ? array() : null;
                        }
                    }
                }

                foreach ($values as &$v) {
                    foreach ($empty_keys as $empty_key => $empty_value) {
                        if (!isset($v[$empty_key])) {
                            $v[$empty_key] = $empty_value;
                        }
                    }
                }
            }
        }
    }

    return $data;
}
