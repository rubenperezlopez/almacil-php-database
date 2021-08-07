![GitHub last commit](https://img.shields.io/github/last-commit/rubenperezlopez/almacil-php-database)
![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/rubenperezlopez/almacil-php-database?label=last%20version)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/almacil/php-database)
![GitHub](https://img.shields.io/github/license/rubenperezlopez/almacil-php-database)
# 🗃 Almacil PHP Database

This is a simple **flat file** NoSQL like database implemented in PHP for small projects without any third-party dependencies that store data in plain JSON files.

<br>

*Do you want to contribute?*<br>
[![Donate 1€](https://img.shields.io/badge/Buy%20me%20a%20coffee-1%E2%82%AC-brightgreen?logo=buymeacoffee&logoColor=white&labelColor=grey&style=for-the-badge
)](https://www.paypal.com/paypalme/rubenperezlopez/1?target=_blank)

## Features
- Lightweight and Secure
- Easy to get started
- No external dependencies
- CRUD (create, read, update, delete) operations
- Supports multiple databases and tables/collections

## Installation
Installation is possible using Composer
```bash
composer requiere almacil/php-database
```
## Usage
### Summary
```php
// Create instance
$db = new \Almacil\Database($database);

// Basic
$db->find($collection, /* function to find */);
$db->insert($collection, $item);
$db->update($collection, /* function to find */, $update);
$db->remove($collection, /* function to find */, $permanent);

// More
$db->count($collection, /* function to find */);
$db->findOne($collection, /* function to find */);
$db->upsert($collection, /* function to find */, $update);
$db->drop($collection);
$db->newid();
```
### Create instance
Create an instance of \Almacil\Database. Optionally, we can maintain the order of the databases and collections with a hierarchy using slash. We can also decide that the directory corresponds to a database and create another instance for another database in another directory.
```php
// Require composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Directory containing the json files of databases
$database = __DIR__ . '/data';

// Create de instance
$db = new \Almacil\Database($database);

// "database/collection" or "collection" or "collection/subcollection"
$collection = 'mycollection/mysubcollection';
```

### Find
```php
// ... after insert

$find = new stdClass();
$find->_id = $newItem->_id;

$items = $db->update($collection, function($item) use ($find) {
    return $find->_id === $item->_id;
});
```
### Insert
```php
// ... after create instance $db
$item = new stdClass();
$item->name = 'Rubén';
$newItem = $db->insert($collection, $item);
```
### Update
```php
// ... after insert

$find = new stdClass();
$find->_id = $newItem->_id;

$update = new stdClass();
$update->name = 'Rubén Pérez';

$numberItemsUpdated = $db->update($collection, function($item) use ($find) {
    return $find->_id === $item->_id;
}, $update);
```
### Remove
When we delete an element, we don't really delete it but we set the *_removed_at* field with the value of microtime(). If we want to delete the element permanently we will send true in the third argument.
```php
// ... after insert

$find = new stdClass();
$find->_id = $newItem->_id;

$permanent = true;

$numberItemsRemoved = $db->remove($collection, function($item) use ($find) {
    return $find->_id === $item->_id;
}, $permanent);
```

<br>

*Do you want to contribute?*<br>
[![Donate 1€](https://img.shields.io/badge/Buy%20me%20a%20coffee-1%E2%82%AC-brightgreen?logo=buymeacoffee&logoColor=white&labelColor=grey&style=for-the-badge
)](https://www.paypal.com/paypalme/rubenperezlopez/1?target=_blank)

---

Made with ❤️ by developer for developers
