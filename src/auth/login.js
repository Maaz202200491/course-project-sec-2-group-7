/*
  Requirement: Add client-side validation to the login form.

  Instructions:
  1. This file is already linked to your HTML via a <script> tag with the 'defer' attribute
     at the bottom of the <body> in login.html.

  2. In your login.html, a <div id="message-container"> has been added *after* the </fieldset>
     but *before* the </form> closing tag. This div will be used to display success or error messages.

  3. Implement the JavaScript functionality as described in the TODO comments.
*/

// --- Element Selections ---
// We can safely select elements here because 'defer' guarantees
// the HTML document is parsed before this script runs.

// Select the login form by its id "login-form".
const loginForm = document.getElementById("login-form");

// Select the email input element by its ID.
const emailInput = document.getElementById("email");

// Select the password input element by its ID.
const passwordInput = document.getElementById("password");

// Select the message container element by its ID.
const messageContainer = document.getElementById("message-container");

// --- Functions ---

/**
 * Implement the displayMessage function.
 * This function takes two arguments:
 * 1. message (string): The message to display.
 * 2. type (string): "success" or "error".
 *
 * It should:
 * 1. Set the text content of `messageContainer` to the `message`.
 * 2. Set the class name of `messageContainer` to `type`
 * (this will allow for CSS styling of 'success' and 'error' states).
 */
function displayMessage(message, type) {
  messageContainer.textContent = message;
  messageContainer.className = type;
}

/**
 * Implement the isValidEmail function.
 * This function takes one argument:
 * 1. email (string): The email string to validate.
 *
 * It should:
 * 1. Use a regular expression to check if the email format is valid.
 * 2. Return `true` if the email is valid.
 * 3. Return `false` if the email is invalid.
 */
function isValidEmail(email) {
  const emailRegex = /\S+@\S+\.\S+/;
  return emailRegex.test(email);
}

/**
 * Implement the isValidPassword function.
 * This function takes one argument:
 * 1. password (string): The password string to validate.
 *
 * It should:
 * 1. Check if the password length is 8 characters or more.
 * 2. Return `true` if the password is valid.
 * 3. Return `false` if the password is not valid.
 */
function isValidPassword(password) {
  return password.length >= 8;
}

/**
 * Implement the handleLogin function.
 * This function will be the event handler for the form's "submit" event.
 */
async function handleLogin(event) {

  // Prevent the form's default submission behavior.
  event.preventDefault();

  // Get the values from emailInput and passwordInput.
  const email = emailInput.value.trim();
  const password = passwordInput.value.trim();

  // Validate the email using isValidEmail().
  if (!isValidEmail(email)) {
    displayMessage("Invalid email format.", "error");
    return;
  }

  // Validate the password using isValidPassword().
  if (!isValidPassword(password)) {
    displayMessage("Password must be at least 8 characters.", "error");
    return;
  }

  // If both email and password are valid,
  // send the login request to the backend API.
  try {

    const response = await fetch("api/index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        email: email,
        password: password
      })
    });

    const result = await response.json();

    // Handle successful login response.
    if (result.success) {

      displayMessage("Login successful!", "success");

      // Store user information in local storage.
      localStorage.setItem("user", JSON.stringify(result.user));

      // Redirect admin users to manage users page.
      if (result.user.is_admin == 1) {
        window.location.href = "../admin/manage_users.html";
      } else {

        // Redirect normal users to the home page.
        window.location.href = "../../index.html";
      }

    } else {

      // Display error message from the server.
      displayMessage(result.message, "error");
    }

  } catch (error) {

    // Handle unexpected errors.
    displayMessage("Something went wrong. Please try again.", "error");
  }
}

/**
 * Implement the setupLoginForm function.
 * This function will be called once to set up the form.
 */
function setupLoginForm() {

  // Check if loginForm exists.
  if (loginForm) {

    // Add a submit event listener to the form.
    loginForm.addEventListener("submit", handleLogin);
  }
}

// --- Initial Page Load ---
// Call the main setup function to attach the event listener.
setupLoginForm();
