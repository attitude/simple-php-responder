<?php

/**
 * Translate data recursivelly
 *
 */
function translateData($data, $language)
{
    if (is_array($data)) {
        foreach ($data as &$v)  {
            $v = translateData($v, $language);
        }
    } elseif (is_object($data)) {
        $keys = array_keys((array) $data);

        $locale = translateDataLocale($language);

        // Translations object:
        if (in_array($locale, $keys)) {
            return translateData($data->{$locale}, $language);
        }

        foreach ($keys as $key) {
            $data->{$key} = translateData($data->{$key}, $language);
        }
    }

    return $data;
}

function translateDataLocale($language)
{
    if (is_string($language)) {
        if (strlen($language) === 2) {
            return $language.'_'.strtoupper($language);
        } else {
            return str_replace('-', '_', $language);
        }
    } elseif (is_array($language) || is_object($language)) {
        $laguage = (object) $language;

        if (isset($language->locale)) {
            return str_replace('-', '_', $language->locale);
        }
    }

    throw Exception('Unexpected: Language format.');
}
