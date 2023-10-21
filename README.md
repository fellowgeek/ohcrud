![](https://erfan.me/assets/images/OHCRUD_LOGO_LARGE.png)

# Introduction

OhCrud is a PHP micro framework that helps you quickly write simple yet powerful applications and create APIs. At its core, it provides the basic *C*reate *R*ead *U*pdate *D*elete methods to interact with MySQL or SQLITE.

> Ninety percent of everything is crud.
>
> <footer>Theodore Sturgeon</footer>

OhCrud uses composer and fully supports PSR-4 autoloading so you can reference your classes using the name spaces, You can easily define API or HTML endpoints and map incomming requests to your classes to handle, or you can use a catch all *__OHCRUD_DEFAULT_PATH_HANDLER__* to catch all the other requests.

Framework comes with a secure users and permissions handling, you can define per method permissions for all your API endpoints.

OhCrud uses [Monolog](https://github.com/Seldaek/monolog) liberary to handle logs, all PDO exceptions are automatically logged into the designated log file in a well formated way.

While I belive any programming language that comes with fulctions like _money_format_, or _ucwords_ and so on... right out of the box is CRUD, my goal is to make things less shitty with my framework, but as a rule still contains ninety percent crud!

# Getting Started

The OhCrud framework has a few system requirements and you can run it on a Raspberry Pi 1 with SQLITE database, you will need to make sure your server meets the following requirements:

*   PHP >= 5.6.0 (PHP 7 Recommended)
*   PDO PHP Extension
*   Mbstring PHP Extension
*   PHP Curl Extension
*   PHP SQLITE 3 (if using SQLITE for your database)

## Installation

OhCrud utilizes [Composer](https://getcomposer.org/) to manage its dependencies. So, before using OhCrud, make sure you have Composer installed on your machine.

To install OhCrud, first create a directory for your project in your webserver's root directory:

<pre>mkdir /var/www/OhCrud
</pre>

Then run the following commands:

<pre>git clone https://github.com/fellowgeek/OhCrud.git /var/www/OhCrud
rm -rf /var/www/OhCrud/.git
composer update
composer dump-autoload
</pre>

After all the files are copied in place you need to configure your webserver to redirect all the traffic from the missing paths to go to *index.php*, you can achieve this for Apache and Nginx using the configuration below:

### Apache configuration

Make sure apache has the *mod_rewrite* enabled, there is a *.htaccess* file included in OhCrud package that should take care of everything for you but if you need to make one make sure it looks like this:

<pre>
&lt;IfModule mod_rewrite.c&gt;
	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule ^.+$ index.php [QSA,L]

	RewriteRule ^/?logs/app\.log$ - [F,L]
	RewriteRule ^/?assets/db/data\.db$ - [F,L]
&lt;/IfModule&gt;
</pre>

Make sure your Apache virtual host is configured with the AllowOverride option so that the .htaccess rewrite rules can be used:

<pre>AllowOverride All
</pre>

### Nginx configuration

This is an example Nginx virtual host configuration for the domain example.com. It listens for inbound HTTP connections on port 80.

You should update the *server_name*, *error_log*, *access_log*, and *root* directives with your own values. The root directive is the path to your application’s root directory.

<pre>server {
    listen 80;
    index index.php index.html index.htm;
    error_log /path/to/example.error.log;
    access_log /path/to/example.access.log;
    root /path/to/ohcrud;
    server_name example.com;

    location / {
	try_files $uri $uri/ @rewrite;
    }

    location @rewrite {
        rewrite ^.+$ /index.php;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.0-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /(logs|assets/db) {
	deny all;
    }
}
</pre>

## Configuration

Use the table below to define the following settings constants in the *<span class="monospace">settings.php</span>* file that should be placed at the application's root directory. A *<span class="monospace">settings.sample.php</span>* file is provided as a starting point.

| Setting | Type | Description |
| --- | --- | --- |
| __SELF__ | String | Same as *<span class="monospace">$_SERVER['DOCUMENT_ROOT']</span>*, contains the path to application's root folder. |
| __SITE__ | String | Enter yout applications domain here i.e. *example.com* |
| __OHCRUD_DEBUG_MODE__ | Boolean | If this is enabled, every OhCrud response will contain a *<span class="monospace">runtime</span>* and *<span class="monospace">SQL</span>* property if applicaple. This hsould be set to *<span class="monospace">false</span>* for production. |
| __OHCRUD_DEBUG_EXPANDED_LEVEL__ | Integer | This sets the initally expanded levels in the output of the *<span class="monospace">debug()</span>* method. |
| __OHCRUD_LOG_FILE__ | String | Path to the defualt log file, initally set to *<span class="monospace">/logs/app.log</span>* |
| __OHCRUD_SECRET__ | String | Secret string used to generate passwords, you must change this to a random string for each project. |
| __OHCRUD_SESSION_LIFETIME__ | Integer | Application session cookie lifetime in seconds, defualt 3600 seconds. |
| __OHCRUD_DB_STAMP__ | Boolean | This will determione if OhCrud should add CDATE (create date), MDATE (midified date), CUSER (created by user id), MUSER (modified by user id) columns to every table. |
| __OHCRUD_DB_CONFIG__ | Serialize Array | Database settings for SQLITE or MYSQL should be entered here in the form of a serialized array. *If you are using a SQLITE database, it is strongly recommended that you move the databse file outside of your web application directory.* |
| __OHCRUD_ENDPOINTS__ | Serialize Array | API or HTML endpoints should be entered here in the form of a serialized array. You can map any arbitary URL path to a OhCrud or OhCrud compatible class to handle. |
| __OHCRUD_DEFAULT_PATH_HANDLER__ | String | Use this setting to map any URL path that is not handled by the *<span class="monospace">__OHCRUD_DEFAULT_PATH_HANDLER__</span>*. This needs to be a OhCrud or OhCrud compatible classs. This is usefull if you have a content managment class to handle all the web pages. |

## Directory Structure

OhCrud utilizes [Composer](https://getcomposer.org/) to manage its dependencies, all classes should be [PSR-4](http://www.php-fig.org/psr/psr-4/) compliant to work with autoloading.

Below you can see a breakdown of the OhCrud folders in alphabetical order.

| Directory | Description |
| --- | --- |
| /app/ | The app directory, as you might expect, contains the core code of your application. We'll explore this directory in more detail below; however, almost all of the classes in your application will be in this directory. |
| /app/controllers/ | By default, this directory is namespaced under *\app\controllers* and is autoloaded by Composer using the PSR-4 autoloading standard. Ideally your controller classes should exists under this directory. |
| /app/models/ | By default, this directory is namespaced under *\app\models* and is autoloaded by Composer using the PSR-4 autoloading standard. Ideally your model classes should exists under this directory. |
| /app/views/ | This directory is for your application views. |
| /assets/css/ | CSS assets are located here, by default OhCrud comes with boostrap framework. |
| /assets/db/ | We used this directory to include a default SQLITE databse file. |
| /assets/fonts/ | Font assets are located here. |
| /assets/images/ | Image assets including the awesome OhCrud logo are located here. |
| /assets/js/ | Javascript assets are located here, by default OhCrud includes a copy of jQuery. |
| /logs/ | This is the defualt applicstion logs directory. |
| /OhCrud/ | This directory contains the OhCrud framework which consists of *Core.php*, *DB.php*, *Router.php*, and *Users.php*. We will discuss these files in detail in [The Basics](#section3) section of this documentaion. |
| /vendor/ | The vendor directory contains your Composer dependencies. |
| /composer.json | This file describes the dependencies of your project and may contain other metadata as well. You may add other classes you wish to include in autoloading under psr-4 key. |
| /index.php | OhCrud index file, all web, API, and console requests will hit this file first. |
| /settings.php | OhCrud settings are defined here. You should use the *settings.sample.php* to generate this. |

## The Basics

All OhCrud classes inherit from the Core class. Ideally all of your models and controllers should do the same. If a class needs databse functions should inherit from the DB class, which itself inherits from Core.

API and WEB endpoints are handled by the Router class, the Router checks if user has the correct permissions to access the end point and Routes the request to the correct class to handle.

OhCrud uses the Users class to authenticate users and check permissions, when *__OHCRUD_DEBUG_MODE__* set to *TRUE* if the Users table does not exists, OhCrud will auto create the table and insert a default admin user into it.

### OhCrud default username and password

<pre>Username : admin
Password : admin
</pre>

## Core Object

Every class in OhCrud inherits from the Core object, This class is responsible for setting the output type and http headers and performing tasks like logging, errors handling, remote requests, and debug.

### Properties

| Property | Type | Description |
| --- | --- | --- |
| data | Array | This property holds results of a remote request or database query or any data. |
| errors | Array | If any errors occur it will be included in this element. |
| success | Boolean | This is set to *TRUE* if operation was successfull or *FALSE* otherwise. |
| outputType | String / NULL | Output can be set to *'HTML'*, *'JSON'* or *NULL*. |
| outputHeaders | Array | You can set HTML headers here. |
| outputHeadersSent | Boolean | This is set to *TRUE* if HTML headers already sent or *FALSE* otherwise. |
| outputStatusCode | Integer | This is set automatically if operation is successfull or if any errors happen. You can set your own HTTP status code here as well. |

### Methods

All the methods in Core can be used in a chain, see the example below:

```php
$this->log('info', 'This is a test log.')->debug($_SERVER)->request('https://www.reddit.com/r/cats/.json')->debug();
```

| Method | Description | Return Value |
| --- | --- | --- |
| setOutputType($outputType) | Output can be set to *'HTML'*, *'JSON'* or *NULL*. | OhCrud Object |
| setOutputHeaders($outputHeaders = array()) | You can set HTML headers here. | OhCrud Object |
| setOutputStatusCode($outputStatusCode) | This is set automatically if operation is successfull or if any errors happen. You can set your own HTTP status code here as well. | OhCrud Object |
| setSession($key, $value) | This method sets a PHP session value and unlocks session data. | OhCrud Object |
| unsetSession($key) | This method unsets a PHP session value and unlocks session data. | OhCrud Object |
| output() | This method causes the class to produce the output based on the *outputType* | OhCrud Object |
| log($level = 'debug', $message, array $context = array()) | This method is a wrapper for [Monolog](https://seldaek.github.io/monolog/) and will produce an entry into the defualt log file. | OhCrud Object |
| error($message, $outputStatusCode = 500) | This method will throw an error and logs the error into the defualt log file. Calling this method also cause *success* to be *FALSE*. | OhCrud Object |
| request($url, $method = 'GET', $data = '', array $headers = array()) | This is a wrapper method for php-curl, this allows you to call remote APIs and receive the response in OhCrud friendly way. | OhCrud Object |
| debug($expression = null) | If called without any parameters will output *$this* class. If an experssion is passed, this method will output that expression instead. Here an example of the *debug()* method: |  OhCrud Object |

![](https://erfan.me/assets/images/debug001.png)

## Database Object

DB object is in charge of all the CRUD (*C*reate *R*ead *U*pdate *D*elete) work. This class inherits from Core, and comes with all the properties and methods that Core has.

### Properties

Same as Core object

### Methods

Same as Core object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| run($sql, $bind=array()) | This method will execute any SQL query agaist the database and will return the resulting dataset or a simple *TRUE* or *FALSE* if dataset is not present. While it is optional, you should use the *$bind* array to bind your data to your SQL placeholders. see example below: | OhCrud Object |

```php
$this->run(
	'SELECT * FROM Users Where ID=:ID',
	[
		':ID' => 1
	]
);
```

| Method | Description | Return Value |
| --- | --- | --- |
| create($table, $data=array()) | Use this method to insert a new record into the database, while it is optional, you should use the *$bind* array to bind your data to your SQL placeholders. see example below: | OhCrud Object |

```php
$this->create(
	'Users',
	[
		'USERNAME' => 'admin',
		'PASSWORD' => password_hash(
			'admin', PASSWORD_BCRYPT, [
				'salt' => __OHCRUD_SECRET__,
				'cost' => 10
				]
			),
		'FIRSTNAME' => 'admin',
		'LASTNAME' => 'admin',
		'GROUP' => 1,
		'PERMISSIONS' => 1,
		'STATUS' => 1
	]
);
```

| Method | Description | Return Value |
| --- | --- | --- |
| read($table, $where="", $bind=array(), $fields="*") | Use this method to read records from database, while it is optional, you should use the *$bind* array to bind your data to your SQL placeholders. see example below: | OhCrud Object |

```php
$this->read(
	'Users',
	'ID=:ID',
	[
		':ID' => 1,
	],
	'ID, USERNAME, FIRSTNAME, LASTNAME'
);
```

| Method | Description | Return Value |
| --- | --- | --- |
| update($table, $data, $where, $bind=array()) | Use this method to update records in database, while it is optional, you should use the *$bind* array to bind your data to your SQL placeholders. see example below: | OhCrud Object |

```php
$this->update(
	'Users',
	[
		'FIRSTNAME' => 'JOE',
		'LASTNAME' => 'COOl'
	],
	'ID=:ID',
	[
		':ID' => 1,
	]
);
```

| Method | Description | Return Value |
| --- | --- | --- |
| delete($table, $where, $bind=array()) | Use this method to delete records from database, while it is optional, you should use the *$bind* array to bind your data to your SQL placeholders. see example below: | OhCrud Object |

```php
$this->delete(
	"Users",
	"ID=:ID",
	[
		':ID' => 1,
	]
);
```

| Method | Description | Return Value |
| --- | --- | --- |
| first() | This method returns the first element of the *data* property. This method will terminate a method chain. See example below: | Object (This method will return the fisrt element of the *data* property. |

```php
$this->run('SELECT * FROM Users')->first();
```

## Router Object

Router object is your interface to the outside world, it will take a *path* which can be a *URI* or a *console argument* and will call upon the appropriate class and method to handle the request.

To accomplish this task Router will look at the *__OHCRUD_ENDPOINTS__* and will call the class and method based on the defined name space, if the path was not defined in *__OHCRUD_ENDPOINTS__* then Router will look at *__OHCRUD_DEFAULT_PATH_HANDLER__* and will route the request to the *defaultPathHandler* method of the class defined here.

If Router fails to find a path after trying the steps above, it will return a 404 error.

Becasue router is matching paths to namespaces, it is very fast and you can change your endpoints at any time or redirect them to new namespaces to handle, this will make rolling out new versions of APIs super easy.

### Permissions

Before calling objects and methods Router checks the target class for the public *$permissions* array property.

```php
namespace app\models;

class Example extends \OhCrud\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'exampleMethodA' => 2,
        'exampleMethodB' => 7
    ];
/*
the rest of the code for the Example class goes here...
*/
}
```

If a user is present in the *$_SESSION*, Router will check that users *PERMISSIONS* value against the number assigned to the target method in *$permissions* array. if user's *PERMISSIONS* value *smaller than or equal to* the methods value it will grant access otherwise will return a 403 error.

the *'object'* key in the *$permissions* array controls the global access to the object.

To grant full access to a method or object without requiring user to login use *__OHCRUD_PERMISSION_ALL__* constant.

When accessing a protected method, if user is not logged in, OhCrud will ask for the login credentians via basic authentication.

## Users Object

Users object is the OhCrud's way of handling users and permissions, if *__OHCRUD_DEBUG_MODE__* is set to *TRUE*, OhCrud will create a Users table and will insert a defualt admin user into it as the starting point.

### OhCrud default username and password

<pre>Username : admin
Password : admin
</pre>

### Properties

Same as DB object

### Methods

Same as DB object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| login($username, $password) | This method will attempt to authenticate the user. If authentication is successfull results are stored in *$_SESSION['User']*. | Boolean (TRUE on success, FALSE on failure) |
| logout() | This will log out the user and remove the *$_SESSION['User']* value. | TRUE |

## Errors & Logging

OhCrud objects inherit from Core object, which means they all have a *error($message, $outputStatusCode = 500)* and *log($level = 'debug', $message, array $context = array())* method. Use this method to throw errors and set the HTTP status code.

Database object (DB) will call the *error* method when a PDO exception occurs.

OhCrud uses [Monolog](https://github.com/Seldaek/monolog) liberary to handle logs, the *log* method in Core is a wrapper for Monolog. Use this method to record PSR-3 compliant log messages. The error method will also record a log.

In OhCrud logs are fomatted like the examples below (output is trimmed to fit in this documentation page) :

<pre>[2017-07-25 15:01:16] OHCRUD.WARNING:
Login attempt was not successful
{"ID":"1","USERNAME":"admin","FIRSTNAME":"admin","LASTNAME":"admin","GROUP":"1",...
----------------------------------------
[2017-07-25 15:21:33] OHCRUD.ERROR:
SQLSTATE[HY000]: General error: 1 no such table: SomeTable
[{"file":"/var/www/ohcrud/ohcrud/DB.php","line":74,"function":"error","class":"O...
----------------------------------------
[2017-07-25 15:37:09] OHCRUD.ERROR:
I'm sorry Dave, I'm afraid I can't do that.
[{"file":"/var/www/ohcrud/ohcrud/Router.php","line":141,"function":"error","clas...
----------------------------------------
</pre>

## Command Line Interface

You can call OhCrud paths and endpoints from your server's command line. When calling an endpoint from command line OhCrud will bypass the permissions array completely. If the otput type is not defined, OhCrud will print a debug output in the console. See example output below:

<pre>php path_to/index.php "/endpont/example/"
</pre>

![](https://erfan.me/assets/images/debug002.png)


[![forthebadge](http://forthebadge.com/images/badges/powered-by-electricity.svg)](http://forthebadge.com)
