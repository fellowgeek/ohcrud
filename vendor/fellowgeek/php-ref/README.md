## This is a fork of digitalnature/php-ref, modified to fit the OhCrud framework better.

REF, or `r()` is a nicer alternative to PHP's [`print_r`](http://php.net/manual/en/function.print-r.php) / [`var_dump`](http://php.net/manual/en/function.var-dump.php) functions.

## [DEMO](http://dev.digitalnature.eu/php-ref/) ##

## Requirements ##

- (server) PHP 5.3+ (5.4+  displays additional info)
- (client) Any browser, except IE 8 and lower of course

## Installation using Composer

Add REF to your `composer.json`:

```js
{
    "require": {
        "fellowgeek/php-ref": "dev-master"
    }
}
```

Now tell composer to download the bundle by running:

``` bash
$ php composer.phar update digitalnature/php-ref
```

Composer will install the bundle to the directory `vendor/fellowgeek`.

## Usage ##

Basic example:

    // include the class (not needed if project runs with Composer because it's auto-loaded)
    require '/full/path/to/ref.php';

    // display info about defined classes
    r(get_declared_classes());

    // display info about global variables
    r($GLOBALS);

To print in text mode you can use the `rt()` function instead:

    rt($var);

To terminate the script after the info is dumped, prepend the bitwise NOT operator:

    ~r($var);   // html
    ~rt($var);  // text

Prepending the error control operator (@) will return the information:

    $output = @r($var);   // html
    $output = @rt($var);  // text

Keyboard shortcuts (javascript must be enabled):

- `X` - collapses / expands all levels

To modify the global configuration call `ref::config()`:

    // example: initially expand first 3 levels
    ref::config('expLvl', 3);

Currently available options and their default values:

| Option                    | Default             | Description
|:------------------------- |:------------------- |:-----------------------------------------------
| `'expLvl'`                | `1`                 | Initially expanded levels (for HTML mode only). A negative value will expand all levels
| `'maxDepth'`              | `6`                 | Maximum depth (`0` to disable); note that disabling it or setting a high value can produce a 100+ MB page when input involves large data
| `'showIteratorContents'`  | `FALSE`             | Display iterator data (keys and values)
| `'showResourceInfo'`      | `TRUE`              | Display additional information about resources
| `'showMethods'`           | `TRUE`              | Display methods and parameter information on objects
| `'showPrivateMembers'`    | `FALSE`             | Include private properties and methods
| `'showStringMatches'`     | `TRUE`              | Perform and display string matches for dates, files, json strings, serialized data, regex patterns etc. (SLOW)
| `'formatters'`            | `array()`           | Custom/external formatters (as associative array: format => className)
| `'shortcutFunc'`          | `array('r', 'rt')`  | Shortcut functions used to detect the input expression. If they are namespaced, the namespace must be present as well (methods are not  supported)
| `'stylePath'`             | `'{:dir}/ref.css'`  | Local path to a custom stylesheet (HTML only); `FALSE` means that no CSS is included.
| `'scriptPath'`            | `'{:dir}/ref.js'`   | Local path to a custom javascript (HTML only); `FALSE` means no javascript (tooltips / toggle / kbd shortcuts require JS)
| `'showUrls'`              | `TRUE`              | Gets information about URLs. Setting to false can improve performance (requires showStringMatches to be TRUE)

## License

http://opensource.org/licenses/mit-license.html
