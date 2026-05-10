/*
  Requirement: Add interactivity and data management to the Admin Portal.
*/

// --- Global Data Store ---
let users = [];

// --- Element Selections ---
// Select the user table body element.
const userTableBody = document.getElementById("user-table-body");

// Select the Add User form.
const addUserForm = document.getElementById("add-user-form");

// Select the Change Password form.
const changePasswordForm = document.getElementById("password-form");

// Select the search input field.
const searchInput = document.getElementById("search-input");

// Select all table headers.
const tableHeaders = document.querySelectorAll("#user-table thead th");

// --- Functions ---

/**
 * Implement the createUserRow function.
 */
function createUserRow(user) {

  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${user.name}</td>
    <td>${user.email}</td>
    <td>${user.is_admin == 1 ? "Yes" : "No"}</td>
    <td>
      <button class="edit-btn" data-id="${user.id}">Edit</button>
      <button class="delete-btn" data-id="${user.id}">Delete</button>
    </td>
  `;

  return tr;
}

/**
 * Implement the renderTable function.
 */
function renderTable(userArray) {

  if (!userTableBody) return;

userTableBody.innerHTML = "";

  userArray.forEach(user => {
    const row = createUserRow(user);
    userTableBody.appendChild(row);
  });
}

/**
 * Implement the handleChangePassword function.
 */
async function handleChangePassword(event) {

  event.preventDefault();

  const currentPassword =
    document.getElementById("current-password").value;

  const newPassword =
    document.getElementById("new-password").value;

  const confirmPassword =
    document.getElementById("confirm-password").value;

  if (newPassword !== confirmPassword) {
    alert("Passwords do not match.");
    return;
  }

  if (newPassword.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }
  document.getElementById("current-password").value = "";
document.getElementById("new-password").value = "";
document.getElementById("confirm-password").value = "";

  const user = typeof localStorage !== "undefined"
  ? JSON.parse(localStorage.getItem("user"))
  : { id: 1 };

  try {

    const response = await fetch("../api/index.php?action=change_password", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: user.id,
        current_password: currentPassword,
        new_password: newPassword
      })
    });

    const result = await response.json();

    if (result.success) {

      alert("Password updated successfully!");

      document.getElementById("current-password").value = "";
      document.getElementById("new-password").value = "";
      document.getElementById("confirm-password").value = "";

    } else {

      alert(result.message);
    }

  } catch (error) {

    alert("Error updating password.");
  }
}

/**
 * Implement the handleAddUser function.
 */
async function handleAddUser(event) {

  event.preventDefault();

  const name =
    document.getElementById("user-name").value.trim();

  const email =
    document.getElementById("user-email").value.trim();

  const password =
    document.getElementById("default-password").value;

  const isAdmin =
    document.getElementById("is-admin").value;

  if (!name || !email || !password) {
    alert("Please fill out all required fields.");
    return;
  }

  if (password.length < 8) {
    alert("Password must be at least 8 characters.");
    return;
  }

  try {

    const response = await fetch("../api/index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        name: name,
        email: email,
        password: password,
        is_admin: isAdmin
      })
    });

    const result = await response.json();

    if (response.status === 201) {

      await loadUsersAndInitialize();

      addUserForm.reset();

    } else {

      alert(result.message);
    }

  } catch (error) {

    alert("Error adding user.");
  }
}

/**
 * Implement the handleTableClick function.
 */
async function handleTableClick(event) {

  if (event.target.classList.contains("delete-btn")) {

    const id = event.target.dataset.id;

    try {

      const response = await fetch(`../api/index.php?id=${id}`, {
        method: "DELETE"
      });

      const result = await response.json();

      if (result.success) {

        users = users.filter(user => user.id != id);

        renderTable(users);

      } else {

        alert(result.message);
      }

    } catch (error) {

      alert("Error deleting user.");
    }
  }

  if (event.target.classList.contains("edit-btn")) {

    alert("Edit functionality can be added later.");
  }
}

/**
 * Implement the handleSearch function.
 */
function handleSearch() {

  const searchTerm = searchInput.value.toLowerCase();

  if (searchTerm === "") {
    renderTable(users);
    return;
  }

  const filteredUsers = users.filter(user =>
    user.name.toLowerCase().includes(searchTerm) ||
    user.email.toLowerCase().includes(searchTerm)
  );

  renderTable(filteredUsers);
}

/**
 * Implement the handleSort function.
 */
function handleSort(event) {

  const columnIndex = event.currentTarget.cellIndex;

  let property = "";

  if (columnIndex === 0) property = "name";
  if (columnIndex === 1) property = "email";
  if (columnIndex === 2) property = "is_admin";

  let direction = event.currentTarget.dataset.sortDir || "asc";

  direction = direction === "asc" ? "desc" : "asc";

  event.currentTarget.dataset.sortDir = direction;

  users.sort((a, b) => {

    let comparison = 0;

    if (property === "name" || property === "email") {

      comparison = a[property].localeCompare(b[property]);

    } else {

      comparison = a[property] - b[property];
    }

    return direction === "asc"
      ? comparison
      : -comparison;
  });

  renderTable(users);
}

/**
 * Implement the loadUsersAndInitialize function.
 */
async function loadUsersAndInitialize() {

  try {

    const response = await fetch("../api/index.php");

    if (!response.ok) {
      alert("Failed to load users.");
      return;
    }

    const result = await response.json();

    users = result.data;

    renderTable(users);

  } catch (error) {

    console.error(error);

    alert("Error loading users.");
  }

// Attach event listeners safely

if (changePasswordForm) {
  changePasswordForm.addEventListener(
    "submit",
    handleChangePassword
  );
}

if (addUserForm) {
  addUserForm.addEventListener(
    "submit",
    handleAddUser
  );
}

if (userTableBody) {
  userTableBody.addEventListener(
    "click",
    handleTableClick
  );
}

if (searchInput) {
  searchInput.addEventListener(
    "input",
    handleSearch
  );
}

if (tableHeaders.length > 0) {
  tableHeaders.forEach(header => {
    header.addEventListener("click", handleSort);
  });
}
}

// --- Initial Page Load ---
loadUsersAndInitialize();
