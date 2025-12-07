# Project Overview

This project is a PHP micro-framework called ohCRUD. It's designed to be a lightweight and flexible foundation for building web applications and APIs. The framework includes a simple routing system, a database abstraction layer, a content management system (CMS), and user authentication features.

## Key Technologies

*   **Backend:** PHP
*   **Dependency Management:** Composer
*   **Database:** Supports MySQL and SQLite
*   **Frontend:** The framework is flexible, but the default theme uses a mix of plain HTML, CSS, and JavaScript. It can be easily integrated with modern frontend frameworks.

## Security

The ohCRUD framework has undergone a security audit. Several critical and high-severity vulnerabilities have been addressed, including protections against SQL Injection, Cross-Site Scripting (XSS), Local File Inclusion (LFI), and Path Traversal. These changes make the framework significantly more secure out of the box.

## Architecture

The framework follows a simple MVC-like architecture:

*   **Models:** Located in `public/app/models`, these classes are responsible for interacting with the database.
*   **Views:** View templates are organized into several directories:
    *   `public/app/views/cms/`: Contains templates for public-facing hard-coded pages (e.g., `login.phtml`, `404.phtml`).
    *   `public/app/views/admin/`: Contains templates for the internal admin panel (e.g., `tables.phtml`, `edit.phtml`). This separation prevents direct public access to admin views.
    *   `public/themes/`: Contains the themes that control the overall look and feel of the site.
*   **Controllers:** Located in `public/app/controllers`, these classes handle incoming requests, interact with models, and render views.

The core of the framework resides in the `public/ohcrud` directory, which contains the following key files:

*   `Core.php`: The base class for all framework components, providing common functionalities like logging, caching, and session management.
*   `DB.php`: A database abstraction layer that simplifies database operations.
*   `Router.php`: Handles routing of incoming requests to the appropriate controllers.
*   `Users.php`: Manages user authentication and permissions.

## Documentation

Detailed documentation for the framework is located in the `docs/` directory. The main documentation file is `docs/DOCUMENTATION.md`. The documentation for the core framework objects has been organized into separate files for clarity:
*   `docs/Core.md`
*   `docs/DB.md`
*   `docs/Router.md`
*   `docs/Users.md`

Additionally, you can find guides on specific topics:
*   `docs/Themes.md`: Explains how the theming system works.
*   `docs/Admin.md`: Provides an overview of the admin panel's features and capabilities.


## Building and Running

### Prerequisites

*   PHP >= 8.0
*   Composer
*   A web server (Apache or Nginx)
*   PDO PHP Extension
*   Mbstring PHP Extension
*   PHP Curl Extension
*   PHP SQLITE 3 (if using SQLite)

### Installation

1.  Clone the repository:
    ```bash
    git clone https://github.com/fellowgeek/ohCRUD.git
    ```
2.  Install dependencies using Composer:
    ```bash
    composer install
    ```
3.  Create a `settings.php` file from the `settings.sample.php` template and configure your database and other settings.
4.  Configure your web server to point to the `public` directory and set up URL rewriting to redirect all requests to `index.php`.

### Running the Application

Once the installation is complete, you can access the application through your web server. The default credentials for the admin user are:

*   **Username:** admin
*   **Password:** admin

## Development Conventions

*   **Coding Style:** The codebase generally follows the PSR-4 autoloading standard.
*   **Routing:** Routes are defined in the `public/routes.php` file.
*   **CMS:** The built-in CMS allows for content to be created and edited from files or the database. The admin panel provides a rich interface for managing content, users, database tables, and more.
*   **Components:** Reusable UI components can be created in the `public/app/components` directory.
*   **Theming:** The look and feel of the application can be customized by creating themes in the `public/themes` directory. See `docs/Themes.md` for more details.