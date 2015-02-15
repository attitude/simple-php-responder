<?php

////////////////////////////////////////////////////////////////////////////////
//
// 0. SYSTEM BOOTSTRAP
//
////////////////////////////////////////////////////////////////////////////////

// Set timezone for proper date/time handling
date_default_timezone_set('Europe/Bratislava');

// By default respond in plain text
header('Content-Type: text/plain');

// Cover traces
header_remove('X-Powered-By');

// ROOT directory
define('ROOT', dirname(dirname(__FILE__)));

if (!defined('APPROOT')) {
    // Select app to run
    define('APPROOT', ROOT.'/apps/default');
}

// Load vendors (Requires Composer)
require_once ROOT.'/vendor/autoload.php';

// Handle human-readable data (flat-file database)
use Symfony\Component\Yaml\Yaml;

// Simple global dependency container
use \attitude\Elements\DependencyContainer;

// Extended/custom {Mustache} able to use translation syntax
use \attitude\Mustache\DataPreprocessor_Component;

////////////////////////////////////////////////////////////////////////////////
//
// 0.1 Load extra functions/helpers
//
require_once ROOT.'/src/functions/isHTTPS.php';
require_once ROOT.'/src/functions/url.php';
require_once ROOT.'/src/functions/fullURL.php';
require_once ROOT.'/src/functions/statusHeader.php';
require_once ROOT.'/src/functions/redirectToURL.php';
require_once ROOT.'/src/functions/translateData.php';
require_once ROOT.'/src/functions/fixMissingKeys.php';
require_once ROOT.'/src/functions/arrayHasItems.php';
require_once ROOT.'/src/functions/loadData.php';
require_once ROOT.'/src/functions/minifyHTMLResponse.php';

////////////////////////////////////////////////////////////////////////////////
//
// 0.2 Config
//
// Can be overwritten in /<your-app>/index.php which than calls this file
//
if (!isset($cfg)) {
    global $cfg;

    $cfg = new StdClass;

    $cfg->environment = 'dev';
    $cfg->https       = false;
    $cfg->baseURI     = '';
}

////////////////////////////////////////////////////////////////////////////////
//
// 0.3 CHECKS
//
// Is HTTPS
if ($cfg->https && !isHTTPS()) {
    header('HTTP/1.1 301 Moved permanently');
    header('Location: '.fullURL());

    exit;
}

////////////////////////////////////////////////////////////////////////////////
//
// 1. PARSE REQUEST
//
////////////////////////////////////////////////////////////////////////////////

global $request;

// Parse URI without the base URI
$request = (object) parse_url(str_replace($cfg->baseURI, '', $_SERVER['REQUEST_URI']));

// Create breadcrumbs
$breadcrumbs = explode('/', trim($request->path, '/'));

// Remember extracted language
$languageCode = $breadcrumbs[0];
$languagePath = APPROOT.'/collections/'.$languageCode;

// Remove last part (handled by resource)
array_pop($breadcrumbs);

////////////////////////////////////////////////////////////////////////////////
//
// 2. PREPARE RESPONSE
//
////////////////////////////////////////////////////////////////////////////////

// Set default output headers (overwritten later)
header('Content-type: text/plain; charset=utf-8');

// Set response data
$data = new StdClass;

// Set full response schema ////////////////////////////////////////////////////

// Resource
$data->data        = null;

// Breadcrumbs
$data->breadcrumbs = [];

// Collection (immediate parent)
$data->collections = new StdClass;

// Children (navigation)
$data->navigation  = new StdClass;

// Current language
$data->language    = null;

// Languages
$data->languages   = [];

////////////////////////////////////////////////////////////////////////////////
//
// 2.1 Load Site & Languages
//
////////////////////////////////////////////////////////////////////////////////

// Identify language attributes for data translations
DependencyContainer::set('global::languageRegex', '/^(?:[a-z]{2}|[a-z]{2}_[A-Z]{2})$/');

if (!$languageVersions = glob(APPROOT.'/collections/*', GLOB_ONLYDIR)) {
    die('There must be at least one language directory.');
}

foreach ($languageVersions as $versionPath) {
    if (!preg_match(DependencyContainer::get('global::languageRegex'), basename($versionPath), $m)) {
        die('Language directory `'.basename($versionPath).'` does not match regex: '.DependencyContainer::get('global::languageRegex'));
    }

    $version = loadResource($versionPath, $languageCode);

    if (!isset($version->language)) {
        die('`resource.yaml` of '.basename($versionPath).' must define `language` attribute');
    }

    if (basename($versionPath) !== $version->language->code) {
        header('Content-type: text/plain; charset=utf-8');
        die('Language code does not match collection endpoint. Please edit data.');
    }

    // Language is not published yet. Skip:
    if (isset($version->language->published) && !$version->language->published) {
        continue;
    }

    $version->language->href = fullURL(url('/'.$version->language->code));
    $data->languages[] = $version->language;

    // Current site version match:
    if ($version->language->code === $languageCode) {
        $language            = $version->language;
        $data->language      = $version->language;
        $data->data          = $version->data;
        $data->collections->{$version->type} = $version->collection;
        // $data->collections->{$version->type} = fullURL(url('/'));

        // Add navigation
        $data->navigation->{$version->type} = [];

        $data->breadcrumbs[] = (object) [
            'title'           => $version->collection->title,
            'navigationTitle' => $version->collection->navigationTitle,
            'text'            => $version->collection->navigationTitle,
            'href'            => $language->href,
            'level'           => 1,
            'home'            => true
        ];

        $data->navigation->{$version->type} = getNavigation($languagePath, $language);
    }
}

// Order languages by priority
usort($data->languages, function($a, $b) {
    $ap = isset($a->priority) ? $a->priority : 0;
    $bp = isset($b->priority) ? $b->priority : 0;

    return ($ap > $bp) ? -1 : 1;
});

// Redirect to first language if nothing matches
if ($data->language === null) {
    $url = $data->languages[0]->href;
    redirectToURL($url, sprintf('Language attribute in URL is missing. Continue to <a href="%s">default language</a>.', $url));
}

// If no language is set... Exception
if (!isset($data->languages[0])) {
    statusHeader(500);
    exit('No language defined');
}

////////////////////////////////////////////////////////////////////////////////
//
// 3. CURRENT REQUEST
//
////////////////////////////////////////////////////////////////////////////////
//
// 3.1 Current Resource
//
// Unify path
$request->path = trim($request->path, '/');

if ($request->path[0]!=='/') {
    $request->path = '/'.$request->path;
}

if (fullURL(url($request->path)) !== $language->href) {
    $pathToData = APPROOT.'/collections'.$request->path;

    // Lookup resource data
    try {
        $resource = loadResource($pathToData, $language);
        $data->data = $resource->data;
        $data->collections->{$resource->type} = $resource->collection;

        $data->breadcrumbs[] = (object) [
            'title'           => $resource->collection->title,
            'navigationTitle' => $resource->collection->navigationTitle,
            'text'            => $resource->collection->navigationTitle,
            'href'            => $resource->collection->href,
            'level'           => count($breadcrumbs) + 1,
            'current'         => true
        ];

        $data->navigation->{$resource->type} = getNavigation($pathToData, $language);
    } catch (Exception $e) {
        header('HTTP/1.1 404 Not found');
        echo 'Not found';

        exit;
    }
}

////////////////////////////////////////////////////////////////////////////////
//
// 3.2 Look for parent collections
//
//
while (count($breadcrumbs) > 1) {
    $path = APPROOT.'/collections/'.implode('/', $breadcrumbs);

    try {
        $parent = loadResource($path, $language);
        $data->collections->{$parent->type} = $parent->collection;

        $data->breadcrumbs[] = (object) [
            'title'           => $parent->collection->title,
            'navigationTitle' => $parent->collection->navigationTitle,
            'text'            => $parent->collection->navigationTitle,
            'href'            => $parent->collection->href,
            'level'           => count($breadcrumbs)
        ];

        $data->navigation->{$parent->type} = getNavigation($path, $language);
    } catch (Exception $e) {
        // Silently fail
        trigger_error('Exception: '.$e->getMessage());
    }

    array_pop($breadcrumbs);
}

// Order navigation links by priority
usort($data->breadcrumbs, function($a, $b) {
    $ap = isset($a->level) ? $a->level : 0;
    $bp = isset($b->level) ? $b->level : 0;

    return ($ap < $bp) ? -1 : 1;
});

// Hook/filter to add any other data to response
if (function_exists('filterMachineResponse')) {
    filterMachineResponse($data);
}

////////////////////////////////////////////////////////////////////////////////
//
// 4. RESPONSE
//
////////////////////////////////////////////////////////////////////////////////
//
// 4.1 MACHINE-MACHINE RESPONSES
//
// 4.1.1 JSON response first
//
if (!isset($_SERVER['HTTP_ACCEPT'])) {
    statusHeader(406);
    exit('`Accept` header is required. Allowed accept headers: application/json, text/html, application/yaml');
}

// Overriden in URL?
$forceJSON     = isset($_GET['format']) && strstr($_GET['format'], 'json') ? true : false;
$forceJSONUTF8 = isset($_GET['format']) && strstr($_GET['format'], 'json-utf8') || strstr($_SERVER['HTTP_ACCEPT'], 'charset=utf-8') ? true : false;

if (strstr($_SERVER['HTTP_ACCEPT'], 'application/json') || $forceJSON) {
    $data = arrayHasItems(fixMissingKeys($data));
    header('Content-type: application/json');
    echo json_encode($data, ($forceJSON ? JSON_PRETTY_PRINT : 0) | ($forceJSONUTF8 ? JSON_UNESCAPED_UNICODE : 0));

    exit;
}

////////////////////////////////////////////////////////////////////////////////
//
// 4.1.2 YAML
//
// Overriden in URL?
$forceYAML     = isset($_GET['format']) && strstr($_GET['format'], 'yaml') ? true : false;

if (strstr($_SERVER['HTTP_ACCEPT'], 'application/yaml') || $forceYAML) {
    header('Content-type: application/yaml');
    echo Yaml::dump(json_decode(json_encode($data), true), 4, 4, true);

    exit;
}

if (!strstr($_SERVER['HTTP_ACCEPT'], 'text/html')) {
    statusHeader(406);
    exit('Wrong `Accept` header. Allowed accept headers: application/json, text/html, application/yaml');
}

////////////////////////////////////////////////////////////////////////////////
//
// 4.2 HUMAN RESPONCE
//
// 4.2.1 HTML Response
//
// 4.2.1.0 Bootstrap
//
header('Content-type: text/html; charset=utf-8');

require_once APPROOT.'/config/constants.php';
require_once APPROOT.'/config/htmlEngine.php';

// Hook/filter to add any other data to response
if (function_exists('filterHumanResponse')) {
    filterHumanResponse($data);
}


////////////////////////////////////////////////////////////////////////////////
//
// 4.2.1.1 Start HTML response
//
// Template engine
//
try {
    $html = DataPreprocessor_Component::instance();
} catch (HTTPException $e) {
    $e->header();
    echo $e->getMessage();

    exit;
}

$template = null;

if (isset($data->collection->template)) {
    $template = $data->collection->template;
}

if (isset($data->data->template)) {
    $template = $data->data->template;
}

// Get HTML response
$response = $html->render(json_decode(json_encode($data), true), str_replace('-', '_', $language->locale), $template);

////////////////////////////////////////////////////////////////////////////////
//
// Assets concatenation
//

require_once APPROOT.'/config/concatenation.php';

// Walk HTML, find assets and concatenate
$response = $concatenator->defaultConcatenateAssets($response);

// Maybe minify HTML reponse
$response = isset($_GET['clean-html-response']) && $_GET['clean-html-response'] === 'false' ? $response : minifyHTMLResponse($response);

// Set lenght value
header('Content-Length: '.strlen($response));

// Finally respond:
echo $response;
