# OhCRUD! 

OhCRUD is a PHP micro framework that helps you quickly write simple yet powerful applications and create APIs. At its core, it provides the basic Create Read Update Delete methods to interact with MySQL or SQLITE.

<blockquote>
Ninety percent of everything is crud.

Theodore Sturgeon
</blockquote>


OhCRUD uses composer and fully supports PSR-4 autoloading so you can reference your classes using the name spaces, You can easily define API or HTML endpoints and map incomming requests to your classes to handle, or you can use a catch all __OHCRUD_DEFAULT_PATH_HANDLER__ to catch all the other requests.

Framework comes with a secure users and permissions handling, you can define per method permissions for all your API endpoints.

OhCRUD use Monolog liberary to handle logs, all PDO exceptions are automatically logged into the designated log file in a well formated way.

While I belive any programming language that comes with fulctions like money_format, or ucwords and so on... right out of the box is CRUD, my goal is to make things less shitty with my framework, but as a rule still contains ninety percent crud!

