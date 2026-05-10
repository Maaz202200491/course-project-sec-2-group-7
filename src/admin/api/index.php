<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 *
 * Database Table (ground truth: see schema.sql):
 * Table: users
 * Columns:
 *   - id         (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - name       (VARCHAR(100), NOT NULL)
 *   - email      (VARCHAR(100), NOT NULL, UNIQUE)
 *   - password   (VARCHAR(255), NOT NULL) - bcrypt hash
 *   - is_admin   (TINYINT(1), NOT NULL, DEFAULT 0)
 *   - created_at (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
 *
 * HTTP Methods Supported:
 *   - GET    : Retrieve all users (with optional search/sort query params)
 *   - GET    : Retrieve a single user by id (?id=1)
 *   - POST   : Create a new user
 *   - POST   : Change a user's password (?action=change_password)
 *   - PUT    : Update an existing user's name, email, or is_admin
 *   - DELETE : Delete a user by id (?id=1)
 *
 * Response Format: JSON
 * All responses have the shape:
 *   { "success": true,  "data": ... }
 *   { "success": false, "message": "..." }
 */


// Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow specific HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow specific headers: Content-Type, Authorization.

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type, Authorization');


// Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;
}


// Include the database connection file.
// Assume a function getDBConnection() is available.

require_once '../../common/db.php';


// Get the PDO database connection by calling getDBConnection().

$db = getDBConnection();


// Read the HTTP request method from $_SERVER['REQUEST_METHOD'].

$method = $_SERVER['REQUEST_METHOD'];


// Read the raw request body for POST and PUT requests.

$raw = file_get_contents('php://input');

$data = json_decode($raw, true);


// Read query string parameters.

$id = $_GET['id'] ?? null;

$action = $_GET['action'] ?? null;

$search = $_GET['search'] ?? null;

$sort = $_GET['sort'] ?? null;

$order = $_GET['order'] ?? 'asc';


/**
 * Function: Get all users, or search/filter users.
 * Method: GET (no ?id parameter)
 *
 * Supported query parameters:
 *   - search (string) : filters rows where name LIKE or email LIKE the term
 *   - sort   (string) : column to sort by; allowed values: name, email, is_admin
 *   - order  (string) : sort direction; allowed values: asc, desc (default: asc)
 *
 * Notes:
 *   - Never return the password column in the response.
 *   - Validate the 'sort' value against the whitelist (name, email, is_admin)
 *     to prevent SQL injection before interpolating it into the ORDER BY clause.
 *   - Validate the 'order' value; only accept 'asc' or 'desc'.
 */
function getUsers($db) {
    // Build a SELECT query for id, name, email, is_admin, created_at.
    // Do NOT select the password column.
    $sql = "SELECT id, name, email, is_admin, created_at FROM users";

    $params = [];

    // If the search query parameter is present, append a WHERE clause.
    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    // Validate the sort value against allowed fields.
    $allowedSort = ['name', 'email', 'is_admin'];

    if (!empty($_GET['sort']) && in_array($_GET['sort'], $allowedSort)) {
        $order = strtolower($_GET['order'] ?? 'asc');
        $order = $order === 'desc' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY " . $_GET['sort'] . " " . $order;
    }

    // Prepare the statement.
    $stmt = $db->prepare($sql);

    // Execute the statement.
    $stmt->execute($params);

    // Fetch all rows as an associative array.
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the users.
    sendResponse($users, 200);
}


/**
 * Function: Get a single user by primary key.
 * Method: GET with ?id=<int>
 *
 * Query parameters:
 *   - id (int, required) : the user's primary key in the users table
 */

function getUserById($db, $id) {
    // Prepare SELECT query.
    // Do NOT select the password column.
    $sql = "SELECT id, name, email, is_admin, created_at
            FROM users
            WHERE id = :id";

    // Prepare the statement.
    $stmt = $db->prepare($sql);

    // Bind id and execute.
    $stmt->execute([
        ':id' => $id
    ]);

    // Fetch one row.
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no row is found, return 404.
    if (!$user) {
        sendResponse("User not found", 404);
    }

    // If found, return the user.
    sendResponse($user, 200);
}


/**
 * Function: Create a new user.
 * Method: POST (no ?action parameter)
 *
 * Expected JSON body:
 *   - name     (string, required)
 *   - email    (string, required) - must be a valid email address and unique
 *   - password (string, required) - plaintext; will be hashed before storage
 *   - is_admin (int, optional)    - 0 (student) or 1 (admin); defaults to 0
 */
function createUser($db, $data) {

    // TODO: Check that name, email, and password are all present and non-empty.
    //       If any are missing, call sendResponse() with HTTP 400.
    if (
        empty($data['name']) ||
        empty($data['email']) ||
        empty($data['password'])
    ) {

        sendResponse("All fields are required", 400);
    }

    // TODO: Trim whitespace from name, email, and password.
    //       Validate email format with filter_var(FILTER_VALIDATE_EMAIL).
    //       If invalid, call sendResponse() with HTTP 400.
    $name = sanitizeInput($data['name']);

    $email = trim($data['email']);

    $password = trim($data['password']);

    if (!validateEmail($email)) {

        sendResponse("Invalid email format", 400);
    }

    // TODO: Validate that password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($password) < 8) {

        sendResponse("Password must be at least 8 characters", 400);
    }

    // TODO: Check whether the email already exists in the users table.
    //       If it does, call sendResponse() with an appropriate message and HTTP 409.
    $checkSql = "SELECT id FROM users WHERE email = :email";

    $checkStmt = $db->prepare($checkSql);

    $checkStmt->execute([
        ':email' => $email
    ]);

    if ($checkStmt->fetch()) {

        sendResponse("Email already exists", 409);
    }

    // TODO: Hash the password using password_hash($password, PASSWORD_DEFAULT).
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // TODO: Read is_admin from $data; default to 0 if not provided.
    //       Accept only the values 0 or 1.
    $isAdmin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;

    // TODO: Prepare and execute an INSERT INTO users (name, email, password, is_admin)
    //       VALUES (:name, :email, :password, :is_admin).
    $sql = "INSERT INTO users (name, email, password, is_admin)
            VALUES (:name, :email, :password, :is_admin)";

    $stmt = $db->prepare($sql);

    $success = $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':is_admin' => $isAdmin
    ]);

    // TODO: If the insert succeeds, call sendResponse() with the new user's id and HTTP 201.
    //       If it fails, call sendResponse() with HTTP 500.
    if ($success) {

        sendResponse([
            'id' => $db->lastInsertId()
        ], 201);

    } else {

        sendResponse("Failed to create user", 500);
    }
}


/**
 * Function: Delete a user by primary key.
 * Method: DELETE
 *
 * Query parameter:
 *   - id (int, required) : primary key of the user to delete
 */

function deleteUser($db, $id) {

    // TODO: Check that $id is present and non-zero.
    //       If not, call sendResponse() with HTTP 400.
    if (empty($id)) {

        sendResponse("User id is required", 400);
    }

    // TODO: Check that a user with this id exists.
    //       If not, call sendResponse() with HTTP 404.
    $checkSql = "SELECT id FROM users WHERE id = :id";

    $checkStmt = $db->prepare($checkSql);

    $checkStmt->execute([
        ':id' => $id
    ]);

    if (!$checkStmt->fetch()) {

        sendResponse("User not found", 404);
    }

    // TODO: Prepare and execute: DELETE FROM users WHERE id = :id
    $sql = "DELETE FROM users WHERE id = :id";

    $stmt = $db->prepare($sql);

    $success = $stmt->execute([
        ':id' => $id
    ]);

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If the query fails, call sendResponse() with HTTP 500.
    if ($success) {

        sendResponse("User deleted successfully", 200);

    } else {

        sendResponse("Failed to delete user", 500);
    }
}


/**
 * Function: Change a user's password.
 * Method: POST with ?action=change_password
 *
 * Expected JSON body:
 *   - id               (int, required)    : primary key of the user whose password is changing
 *   - current_password (string, required) : must match the stored bcrypt hash
 *   - new_password     (string, required) : plaintext; will be hashed before storage
 */
function changePassword($db, $data) {

    // TODO: Check that id, current_password, and new_password are all present.
    //       If any are missing, call sendResponse() with HTTP 400.
    if (
        empty($data['id']) ||
        empty($data['current_password']) ||
        empty($data['new_password'])
    ) {

        sendResponse("All password fields are required", 400);
    }

    // TODO: Validate that new_password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($data['new_password']) < 8) {

        sendResponse("Password must be at least 8 characters", 400);
    }

    // TODO: SELECT password FROM users WHERE id = :id
    //       to retrieve the current hash.
    $sql = "SELECT password FROM users WHERE id = :id";

    $stmt = $db->prepare($sql);

    $stmt->execute([
        ':id' => $data['id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: If no user is found, call sendResponse() with HTTP 404.
    if (!$user) {

        sendResponse("User not found", 404);
    }

    // TODO: Call password_verify($current_password, $hash).
    //       If verification fails, call sendResponse() with HTTP 401.
    if (!password_verify($data['current_password'], $user['password'])) {

        sendResponse("Current password is incorrect", 401);
    }

    // TODO: Hash the new password.
    $hashedPassword = password_hash(
        $data['new_password'],
        PASSWORD_DEFAULT
    );

    // TODO: Prepare and execute UPDATE query.
    $updateSql = "UPDATE users
                  SET password = :password
                  WHERE id = :id";

    $updateStmt = $db->prepare($updateSql);

    $success = $updateStmt->execute([
        ':password' => $hashedPassword,
        ':id' => $data['id']
    ]);

    // TODO: If successful, return HTTP 200.
    //       If the query fails, return HTTP 500.
    if ($success) {

        sendResponse("Password updated successfully", 200);

    } else {

        sendResponse("Failed to update password", 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        // TODO: If the 'id' query parameter is present and non-empty,
        // call getUserById($db, $id).
        if (!empty($id)) {

            getUserById($db, $id);

        } else {

            // TODO: Otherwise, call getUsers($db).
            getUsers($db);
        }

    } elseif ($method === 'POST') {

        // TODO: If the 'action' query parameter equals
        // 'change_password', call changePassword($db, $data).
        if ($action === 'change_password') {

            changePassword($db, $data);

        } else {

            // TODO: Otherwise, call createUser($db, $data).
            createUser($db, $data);
        }

    } elseif ($method === 'PUT') {

        // TODO: Call updateUser($db, $data).
        updateUser($db, $data);

    } elseif ($method === 'DELETE') {

        // TODO: Read the 'id' query parameter.
        // TODO: Call deleteUser($db, $id).
        deleteUser($db, $id);

    } else {

        // TODO: Return HTTP 405 (Method Not Allowed).
        sendResponse("Method not allowed", 405);
    }

} catch (PDOException $e) {

    // TODO: Log the error.
    error_log($e->getMessage());

    // TODO: Return generic database error.
    sendResponse("Database error", 500);

} catch (Exception $e) {

    // TODO: Return exception message.
    sendResponse($e->getMessage(), 500);
}



// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sends a JSON response and terminates execution.
 *
 * @param mixed $data       Data to include in the response.
 *                          On success, pass the payload directly.
 *                          On error, pass a string message.
 * @param int   $statusCode HTTP status code (default 200).
 */
function sendResponse($data, $statusCode = 200) {
  // TODO: Call http_response_code($statusCode).
    http_response_code($statusCode);

    // TODO: If $statusCode indicates success (< 400), echo success JSON.
    //       Otherwise echo error JSON.
    if ($statusCode < 400) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $data
        ]);
    }

    // TODO: Call exit to stop further execution.
    exit;
}


/**
 * Validates an email address.
 *
 * @param  string $email
 * @return bool   True if the email passes FILTER_VALIDATE_EMAIL, false otherwise.
 */
function validateEmail($email) {
   // TODO: return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}




/**
 * Sanitizes a string input value.
 * Use this before inserting user-supplied strings into the database.
 *
 * @param  string $data
 * @return string Trimmed, tag-stripped, and HTML-escaped string.
 */
function sanitizeInput($data) {
      // TODO: trim($data)
    $data = trim($data);

    // TODO: strip_tags(...)
    $data = strip_tags($data);

    // TODO: htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    // TODO: Return the sanitized value.
    return $data;
}

?>
