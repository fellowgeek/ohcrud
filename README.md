# ohCRUD

![ohCRUD Logo](docs/logo.png)

> "Ninety percent of everything is crud."
>
> <footer>Theodore Sturgeon</footer>

**ohCRUD** is a lightweight, open-source PHP micro-framework designed for developers who need a simple yet powerful toolkit to build web applications and APIs quickly. It's built to be fast, flexible, and easy to learn, allowing you to focus on creating your application without being bogged down by complex configurations.

## Features

- **Simple Routing:** A straightforward routing system that maps URLs to controllers.
- **Database Abstraction:** A simple database layer for performing CRUD operations.
- **Built-in CMS:** A basic Content Management System for managing pages and content.
- **User Authentication:** A simple user management system with permission levels.
- **Theming Engine:** Easily customize the look and feel of your application.
- **Composer Ready:** Utilizes Composer for dependency management and autoloading.
- **CLI Support:** Interact with your application through the command line.

## Requirements

- PHP >= 8.0
- Composer
- PDO PHP Extension
- Mbstring PHP Extension
- PHP Curl Extension
- PHP SQLITE 3 (if using SQLite)

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/fellowgeek/ohcrud.git
    cd ohcrud
    ```

2.  **Install dependencies:**
    ```bash
    composer install
    ```

3.  **Configure your application:**
    -   Copy `public/settings.sample.php` to `public/settings.php`.
    -   Update `public/settings.php` with your database credentials and other settings.

4.  **Set up your web server:**
    -   Point your web server's document root to the `public` directory.
    -   Ensure URL rewriting is enabled to direct all requests to `index.php`.

## Quick Start

After installation, you can access the built-in CMS and admin panel.

-   **Admin Login:** `/login`
-   **Default Username:** `admin`
-   **Default Password:** `admin`

## Documentation

For detailed information on configuration, API, CMS, theming, and more, please refer to our full [documentation](docs/DOCUMENTATION.md).

## Contributing

Contributions are welcome! Please feel free to submit a pull request or open an issue.

## License

ohCRUD is open-source software licensed under the [MIT license](LICENSE).