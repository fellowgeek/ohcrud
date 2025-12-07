# Admin Panel

ohCRUD includes a powerful, built-in admin panel for managing your application's data, users, and content. To access the admin panel, you must be logged in as a user with sufficient permissions (by default, permission level `1`).

## Accessing Admin Features

Admin features are typically accessed by adding `?action=<feature>` to a URL. For example, to edit the content of the home page, you would go to `/?action=edit`.

The main admin sections are:
- **Content Editor** (`?action=edit`)
- **Database Tables** (`?action=tables`)
- **File Manager** (`?action=files`)
- **Log Viewer** (`?action=logs`)

## Content Editor

The content editor provides a user-friendly interface for editing page content.

### Features
- **Markdown Editor**: Uses SimpleMDE for a rich markdown editing experience with a toolbar and preview mode.
- **File & Image Uploads**: Directly upload images and files from the editor. They will be stored in `public/global/files` and the corresponding markdown will be inserted into the editor.
- **Theme & Layout**: Change the theme and layout for the page you are editing.
- **Save, Cancel, Delete/Restore**: Full control over the page's lifecycle.

## Database Table Manager

The table manager provides a generic UI for viewing and manipulating data in any table in your database.

### Features
- **CRUD Operations**: Create, Read, Update, and Delete rows in any table.
- **Data Grid**: Displays table data in a paginated grid.
- **Customizable View**: Show or hide columns to customize the view for each table.
- **Specialized User Management**: When viewing the `Users` table, a specialized interface is provided for managing users, including:
    - Creating and updating users.
    - Changing passwords.
    - Viewing and refreshing API tokens and TOTP secrets.
- **Page Management**: The `Pages` table links to the content editor for each page.

## File Manager

The file manager provides a view of all uploaded files.

### Features
- **Card and Table Views**: View files as cards with image previews or in a table.
- **Easy Linking**: Copy the markdown for an image or a link to a file with a single click.
- **File Upload**: Upload new files directly.

## Log Viewer

The log viewer allows you to inspect application logs directly from the admin panel.

### Features
- **Log File Selection**: View a list of all available log files.
- **Paginated View**: Log entries are paginated for easy navigation.
- **Context Viewer**: View the full context of a log entry in a popup.
- **Clear Logs**: Clear the contents of a log file.

## Admin APIs

The admin panel is powered by a set of APIs handled by the `cAdmin` controller. These APIs perform the backend operations for all the features listed above. All admin API endpoints require the user to be authenticated and have a permission level of `1` or lower.

### Main Endpoints
- `/admin/getTableList/`: Get a list of database tables.
- `/admin/getTableData/`: Get data for a specific table.
- `/admin/createTableRow/`: Create a new row in a table.
- `/admin/updateTableRow/`: Update a row.
- `/admin/deleteTableRow/`: Delete a row.
- `/admin/createUserRow/`: Create a user.
- `/admin/updateUserRow/`: Update a user.
- `/admin/getUserSecrets/`: Get user's API token or TOTP secret.
- `/admin/refreshUserSecrets/`: Refresh a user's secrets.
- `/admin/getLogData/`: Get log file content.
- `/admin/clearLog/`: Clear a log file.
