PHP Responder
=============

Build simple websites using PHP.

**(Little brother of [Publisher](https://github.com/attitude/publisher))**

## A/ Setup

In terminal, change working directory of your choice:

```sh
$ cd /Users/you/Sites/local/site/folder
```

Clone this repository into working directory with the default app:

```sh
$ git clone --recursive git@github.com:attitude/simple-php-responder.git .
```

Install dependencies:

```sh
$ composer install
Loading composer repositories with package information
Installing dependencies (including require-dev) from lock file
- Installing …
```

## B/ Install

Open the index.php in browser, default `.htaccess` will be created for you:

```apacheconf
<IfModule mod_rewrite.c>

RewriteEngine On
Options +FollowSymlinks

RewriteBase /

RewriteCond %{REQUEST_URI}::$1 ^(.*?/)(.*)::\2$
RewriteRule ^(.*)$ - [E=BASE:%1]

# Change any `/default/` according to your app name

RewriteCond %{REQUEST_URI} !/apps/default/public/
RewriteRule ^(.*)$ %{ENV:BASE}apps/default/public/$1 [L,QSA]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ %{ENV:BASE}apps/default/public/index.php [L,QSA]

</IfModule>
```

It will run the default app. To run your own app, either edit the default app,
or replace every occurence of `/default/` with `/your-app-name/`.

More considerations about `public` subdirectory usage:

- [Set RewriteBase to the current folder path dynamically](http://stackoverflow.com/questions/21062290/set-rewritebase-to-the-current-folder-path-dynamically#answer-21063276)
- [Using mod_rewrite in .htaccess files without knowing the RewriteBase](http://www.zeilenwechsel.de/it/articles/8/Using-mod_rewrite-in-.htaccess-files-without-knowing-the-RewriteBase.html)

## C/ URL Structure

URIs, the path part of URL, act as landing pages to your users. Think of any URI as of collection of same data. Such URIs:

1. **MUST** carry data of one object/list of objects (list of phones)
1. **SHOULD** include some description of self (about phones collection)
1. **MAY** describe context (parent collections, site, languages...)

To represent full website visually, all 3 pieces are usually required. All can be loaded in one request or lazily later (asynchronously).

### &lt;APP&gt;/collections/

There's `resource.yaml` in every directory representing the URI path of static pages visible to visitor.

Content of `resource.yaml` MUST include `collection` and `type` attributes, optionally the `data` attribute. In addition to that, the `resource.yaml` in language root must include `language` attribute.

```
+[en]               # ~/en (English site index)
  +[about-us]       # ~/en/about-us
  +[products]       # ~/en/products
    +[phones]       # ~/en/products/phone
    +[notebooks]    # ~/en/products/notebooks
  + [contact]       # ~/en/contact
+[sk]               # ~/sk (Slovak site index)
  +[o-nas]          # ~/sk/o-nas
  + [produkty]      # ~/sk/produkty
     + [telefony]   # ~/sk/produkty/telefony
      + [notebooky] # ~/sk/produkty/notebooky
  + [kontakt]       # ~/sk/kontakt
```

##### Why two (three) attributes in `resource.yaml` file?

- **type** – defines the type of collection/object
- **collection** – attribute holds basic infromation, let's call them metadata
- *data* – attribute holds the bare data directly associated with the collection, sometimes can be empty

### &lt;APP&gt;/data/

Data files can be stored as needed, no structure is forced upon you. These data can be referrenced and can be combined in *resource.yaml* files.

Example for `/en/products/phones/google-nexus-7/resource.yaml`:

```yaml
type: product
data:
    specs: data:/phones/google/nexus/7/specs.yaml
    features: data:/copytexts/google-nexus-7.yaml
    storeItem: data:/store/sku-0192831123.yaml
    manufacturer: data:/manufacturers/google.yaml
collection:
    en_EN: Google Nexus 7 Phone
    sk_SK: Telefón Google Nexus 7
```

You don't have use **data:/** at all, and it's possible to mix them with any YAML data. **data:/** are useful for reuse of same data.

> It's easier to add new or edit translation of a product in one data refferenced location, than on separate language files.

By giving `data:/some/path/*` form of [glob()](http://php.net/manual/en/function.glob.php), it's possible to list all objects withing data directory or matching other valid glob rule. Handy for listing array of items.

## D/ Response structure

Response data is always an object with these attributes:

- *data* – requested collection item data, e.g. for `/products/phones`,
  `data` would hold information about phones collection
- **collections** – collections object which the resource belongs to
- **languages** – list of available languages
- **language** – curent response language
- *navigation* – collections children, the navigation

Important: `collections`, `languages` and `language` attribute are always present and non-empty.

> **Note:** Site information will be present under `collections.index` provided you set type in language root `resource.yaml` collection as `type: index`.

### Breakdown of paths and attributes:

For various paths, the response would look as follows:

- `GET /`:

    ```yaml
        data: {…}        # Site index: news, services, featured… (optional)
        collections:
            index: {…}   # Site collection
        breadcrumbs: […] # Current page breadcrumbs
        navigation:
            index: […]   # Site collection children
        languages: […]   # List of available languages
        language: {…}    # Current language
    ```
- `GET /products`:

    ```yaml
        data: {…}        # Products index: latest, featured… (optional)
        collections:
            index: {…}
            section: {…} # Products collection
        breadcrumbs: […]
        navigation:
            index: […]
            section: […] # Products collection children
        languages: […]
        language: {…}
    ```
- `GET /products/phones`:
    
   ```yaml
        data: {…}                # Products index: latest, featured… (optional)
        collections:
            index: {…}
            section: {…}
            productCategory: {…} # Phones collection
        breadcrumbs: […]
        navigation:
            index: […]
            section: […]
            productCategory: […] # Phones collection children
        languages: […]
        language: {…}
    ```

- `GET /products/phones/google-nexus-7`:

    ```yaml
        data: {…}                # Goole Nexus index: features, specs… (optional)
        collections:
            index: {…}
            section: {…}
            productCategory: {…}
            product: {…}         # Current phone collection
        breadcrumbs: […]
        navigation:
            index: […]
            section: […]
            productCategory: […]
            product: […]         # Current phone collection children
        languages: […]
        language: {…}
    ```
- `GET /products/phones/google-nexus-7/late-2014`:

    ```yaml
        data: {…}                 # Goole Nexus Late 2014 features, specs… (optional)
        collections:
            index: {…}
            section: {…}
            productCategory: {…}
            product: {…}
            productVariation: {…} # Phone variation collection
        breadcrumbs: […]
        navigation:
            index: […]
            section: […]
            productCategory: […] # Phone variation collection children
            product: […]
        languages: […]
        language: {…}
    ```

This way the data is kept flat. Which simplifies the reuse of templates even for complicated site structures.

---

A work in progress/experiment state.

[@martin_adamko](https://twitter.com/martin_adamko)
