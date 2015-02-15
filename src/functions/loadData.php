<?php

// Handle human-readable data (flat-file database)
use Symfony\Component\Yaml\Yaml;

// Simple global dependency container
use \attitude\Elements\DependencyContainer;

/**
 * Low-level data loader
 *
 * 1. Parse YAML data
 * 2. Unify using JSON parser
 * 3. Translate
 *
 */
function loadData($path, $language = null, $mergeDataAttribute = true)
{
    if (is_string($path) && !realpath($path)) {
        $path = glob($path);
    }

    if (is_array($path)) {
        foreach ($path as &$subpath) {
            $subpath = loadData($subpath);
        }

        return $path;
    }

    if (!realpath($path)) {
        throw new Exception('Loading of data failed: file `'.$path.'` does not exist.');
    }

    $data = json_decode(json_encode(YAML::parse(file_get_contents($path))));

    if (!is_object($data)) {
        throw new Exception('Loading of data from file `'.$path.'` failed: no data.');
    }

    $collection = $language !== null ? translateData($data, $language) : $data;

    $result = new StdClass;

    // Merge <object>.data attribute with <object>
    if ($mergeDataAttribute && isset($collection->data) && is_string($collection->data) && strpos($collection->data, 'data:/') === 0) {
        try {
            if (strstr($collection->data, '*')) {
                $files = glob(APPROOT.'/'.str_replace('data:/', 'data/', $collection->data));
                $result = loadData($files, $language);
            } else {
                $result = loadData(APPROOT.'/'.str_replace('data:/', 'data/', $collection->data), $language);
            }
        } catch (Exception $e) {
            // Fail
        }

        unset($collection->data);
    }

    // Copy rest of data
    foreach (array_keys((array) $collection) as $key) {
        $result->{$key} = $collection->{$key};
    }

    return deepLoadData($result, $language);
}

function loadResource($path, $language)
{
    if (!$realpath = realPath($path.'/resource.yaml')) {
        throw new Exception("Resource `{$path}` does not exist.");
    }

    $resource = loadData($realpath, $language, false);

    if (!isset($resource->type)) {
        throw new Exception("Resource `{$path}` MUST define `type` attribute.");
    }

    if (isset($resource->data)) {
        if (!is_object($resource->data)) {
            throw new Exception('If defined, `data` of resource `{$path}` must be an object.');
        }

        $data = $resource->data;
    } else {
        $data = new StdClass;
    }

    if (isset($resource->collection)) {
        if (!is_object($resource->collection)) {
            throw new Exception('If defined, `collection` of resource `{$path}` must be an object.');
        }

        $collection = $resource->collection;
    } else {
        $collection = new StdClass;
    }

    $collection->slug = $data->slug = basename($path);
    $collection->type = $data->type = $resource->type;

    if (!isset($data->title) && !isset($collection->title)) {
        $collection->title = $data->title = $data->slug;
    } elseif (!isset($data->title)) {
        $data->title = $collection->title;
    } elseif (!isset($collection->title)) {
        $collection->title = $data->title;
    }

    if (!isset($data->navigationTitle) && !isset($collection->navigationTitle)) {
        $collection->navigationTitle = $data->navigationTitle = $data->title;
    } elseif (!isset($data->navigationTitle)) {
        $data->navigationTitle = $collection->navigationTitle;
    } elseif (!isset($collection->navigationTitle)) {
        $collection->navigationTitle = $data->navigationTitle;
    }

    if (!isset($data->description) && !isset($collection->description)) {
        $collection->description = $data->description = $data->title;
    } elseif (!isset($data->description)) {
        $data->description = $collection->description;
    } elseif (!isset($collection->description)) {
        $collection->description = $data->description;
    }

    if (!isset($data->href) && !isset($collection->href)) {
        $collection->href = $data->href = fullURL(url(str_replace(APPROOT.'/collections', '', $path)));
    } elseif (!isset($data->href)) {
        $data->href = fullURL(url($path));
    } elseif (!isset($collection->href)) {
        $collection->href = fullURL(url($path));
    }

    $resource->data = $data;
    $resource->collection = $collection;

    return $resource;
}


function deepLoadData($data, $language)
{
    if (is_string($data) && strpos($data, 'data:/') === 0) {
        return loadData(APPROOT.'/'.str_replace('data:/', 'data/', $data), $language);
    } elseif (is_object($data)) {
        $result = new StdClass;

        foreach (array_keys((array) $data) as $key) {
            if (strstr($key, '()')) {
                $f = str_replace('()', '', $key);

                if (is_callable($f)) {
                    $query = $f($data->{$key});

                    if (is_object($query)) {
                        foreach (array_keys((array) $query) as $queryKey) {
                            $result->{$queryKey} = $query->{$queryKey};
                        }
                    } else {
                        return $query;
                    }
                }
            }

            $result->{$key} = deepLoadData($data->{$key}, $language);
        }

        return $result;
    } elseif (is_array($data)) {
        foreach ($data as $key => $v) {
            $data[$key] = deepLoadData($v, $language);
        }

        return $data;
    }

    return $data;
}

function getNavigation($path, $language)
{
    global $request;

    $links = [];

    // Load collections info to use as navigation
    foreach (glob($path.'/*', GLOB_ONLYDIR) as $link) {
        try {
            $linkData = loadData($link.'/resource.yaml', $language, false);

            if (!isset($linkData->type)) {
                throw new Exception('Navigation item for `'.basename($path).'` has no `type` attribute set.');
            }

            if (!isset($linkData->collection)) {
                throw new Exception('Navigation item for `'.basename($path).'` has no `collection` attribute set.');
            }

            $linkData->collection->type = $linkData->type;
            $linkData = $linkData->collection;

            $linkData->priority = isset($linkData->priority) ? $linkData->priority : 0;
            $linkData->href = fullURL(url(str_replace($path, '/'.basename($path), $link)));

            if (isset($linkData->navigationTitle)) {
                $linkData->text = $linkData->navigationTitle;
            } elseif (isset($linkData->title)) {
                $linkData->text = $linkData->title;
            }

            if ($request->path === parse_url($linkData->href, PHP_URL_PATH)) {
                $linkData->current = true;
            }

            if ($linkData->type === DependencyContainer::get('data:siteType', 'index')) {
                $linkData->home = true;
            }

            $links[] = $linkData;
        } catch (Exception $e) {
            // Silently fail
            trigger_error('Exception: '.$e->getMessage());
        }
    }

    // Order navigation links by priority
    usort($links, function($a, $b) {
        $ap = isset($a->priority) ? $a->priority : 0;
        $bp = isset($b->priority) ? $b->priority : 0;

        return ($ap > $bp) ? -1 : 1;
    });

    return $links;
}

function getChildren($path, $language)
{
    $links = [];

    // Look for resource children
    foreach(glob(APPROOT.'/collections'.$path.'/*', GLOB_ONLYDIR) as $childCollectionPath) {
        try {
            $child = loadData($childCollectionPath.'/collection.yaml', $language);
            $child->href = fullURL(url($path.'/'.basename($childCollectionPath)));

            $links[] = $child;
        } catch (Exception $e) {
            // Silently fail
            trigger_error('Exception: '.$e->getMessage());
        }
    }

    usort($links, function($a, $b) {
        $ap = isset($a->language) && isset($a->language->priority) ? $a->language->priority : 0;
        $bp = isset($b->language) && isset($b->language->priority) ? $b->language->priority : 0;

        return ($ap > $bp) ? -1 : 1;
    });

    return $links;
}
