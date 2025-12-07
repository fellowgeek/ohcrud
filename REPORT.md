# ohCRUD Audit Report

This report contains the findings of a security and code audit of the ohCRUD project. The audit focused on identifying security vulnerabilities, bugs, and other issues in the codebase.

## Summary of Findings

The audit revealed several vulnerabilities, including critical and high-severity issues. The most significant vulnerabilities are:

*   **Critical:** SQL Injection in the admin controller.
*   **Critical:** Stored Cross-Site Scripting (XSS) in the pages controller.
*   **Critical:** Local File Inclusion (LFI) in the CMS controller.
*   **High:** Path Traversal / File Existence Information Disclosure in the CMS and pages controllers.
*   **Medium:** Potential for arbitrary class instantiation in the CMS controller.

The following sections provide details on each finding.

---

## Vulnerability Details

### 1. Critical: SQL Injection in `cAdmin.php`

**Location:** `public/app/controllers/cAdmin.php`, `getTableData` method.

**Description:**
The `getTableData` method in the `cAdmin` controller is vulnerable to SQL injection. The `ORDER BY` and `ORDER` parameters from the request are directly concatenated into the SQL query without any sanitization.

```php
// public/app/controllers/cAdmin.php:164
$order = $request->payload->ORDER ??  'DESC';
$orderBy = $request->payload->ORDER_BY ?? $this->getPrimaryKeyColumn($table);

// ...

// public/app/controllers/cAdmin.php:175
$SQL = "SELECT * FROM " . $table . "\n";
if ($orderBy != false) {
    $SQL .= "ORDER BY " . $orderBy . " " . $order . "\n";
}
```

An authenticated admin user can exploit this vulnerability to execute arbitrary SQL queries, potentially leading to data exfiltration, modification, or deletion.

**Recommendation:**
The `ORDER BY` and `ORDER` parameters should be validated against a whitelist of allowed column names and sort directions.

### 2. Critical: Stored Cross-Site Scripting (XSS) in `cPages.php`

**Location:** `public/app/controllers/cPages.php`, `save` method.

**Description:**
The `save` method in the `cPages` controller is vulnerable to stored XSS. While the `TITLE` of a page is sanitized using `HTMLPurifier`, the main content of the page, the `TEXT` field, is not sanitized before being saved to the database.

```php
// public/app/controllers/cPages.php:49
$request->payload->TITLE = $purifier->purify($request->payload->TITLE);

// ...

// public/app/controllers/cPages.php:82
$this->create('Pages', [
    'URL' => $request->payload->URL,
    'TITLE' => $request->payload->TITLE,
    'TEXT' => $request->payload->TEXT, // Unsanitized input
    'THEME' => $request->payload->THEME,
    'LAYOUT' => $request->payload->LAYOUT,
    'STATUS' => $this::ACTIVE
    ]
);
```

An authenticated admin user can inject malicious JavaScript code into a page's content. This code will be executed in the browser of any user who views the page.

**Recommendation:**
The `TEXT` field should also be sanitized using `HTMLPurifier` before being stored in the database.

### 3. Critical: Local File Inclusion (LFI) in `cCMS.php`

**Location:** `public/app/controllers/cCMS.php`, `getContentFromFile` method.

**Description:**
The `getContentFromFile` method is vulnerable to Local File Inclusion. The `$path` variable, which is derived from the URL, is not properly sanitized before being used in an `include` statement.

```php
// public/app/controllers/cCMS.php:336
private function getContentFromFile($path, $is404 = false) {
    // ...
    ob_start();
    include(__SELF__ . 'app/views/cms/' . trim(($is404 ? '404' : $path), '/') . '.phtml');
    $content->text = ob_get_clean();
    // ...
}
```

An attacker can use path traversal sequences (`../`) to include and execute arbitrary `.phtml` files from the server's filesystem. This could lead to remote code execution.

**Recommendation:**
The `$path` variable should be strictly validated to ensure it only contains alphanumeric characters, and does not contain any path traversal sequences. It should also be checked to ensure it resolves to a path within the intended directory.

### 4. High: Path Traversal / File Existence Information Disclosure

**Location:**
*   `public/app/controllers/cPages.php`, `save` and `restoreDeletePage` methods.
*   `public/app/controllers/cCMS.php`, `getContent` method.

**Description:**
These methods use `file_exists` with a path constructed from user-provided input (`URL` or `path`). The input is not sufficiently sanitized, allowing an attacker to check for the existence of arbitrary files on the server.

```php
// public/app/controllers/cPages.php:36
if (file_exists(__SELF__ . 'app/views/cms/' . trim($request->payload->URL ?? '', '/') . '.phtml') == true)
    $this->error('You can\'t edit a hard coded page.');
```

This can be used to gather information about the server's file system, which can aid in further attacks.

**Recommendation:**
Sanitize the input used to construct the file path to prevent path traversal. Ensure that the resolved path is within the expected directory.

### 5. Medium: Arbitrary Class Instantiation in `cCMS.php`

**Location:** `public/app/controllers/cCMS.php`, `getComponent` method.

**Description:**
The `getComponent` method dynamically instantiates classes based on a component string provided in the page content.

```php
// public/app/controllers/cCMS.php:433
$componentClass = '\\app\\components\\' . str_replace('/', '\\', $componentClassFile);
// ...
if (file_exists(__SELF__ . 'app/components/' . $componentClassFile . '.php') == true && class_exists($componentClass) == true) {
    $component = new $componentClass($this->request, $this->path);
    //...
}
```

While the class must exist within the `app/components` directory, an attacker who can write page content (via the XSS vulnerability) could potentially instantiate classes that are not intended to be used as components, leading to unexpected behavior or other vulnerabilities.

**Recommendation:**
Implement a whitelist of allowed component classes to prevent arbitrary class instantiation.