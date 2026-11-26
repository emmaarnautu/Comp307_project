// JS for Change Password page: Shows the username, checks the login, and changes the password

// Store file paths
const SESSION_URL = "php/session.php";
const ACCOUNT_URL = "php/register.php"; // The script that has the change_password action

// Clears the saved login and goes back to the login page
function goToLogin() {
    localStorage.removeItem("ticket");
    localStorage.removeItem("username");
    window.location.href = "login.html";
}

// Shows username in the header
function displayProfile() {
    const username = localStorage.getItem("username");
    document.getElementById("nickname").textContent = "@" + (username || "User");
}

// Makes sure the user is logged in, otherwise sends them to the login page
async function checkSession() {

    // Get ticket from local storage
    const ticket = localStorage.getItem("ticket");

    // If there was no ticket, user was never logged in, so send them to login page
    if (!ticket) {
        goToLogin();
        return;
    }

    // Create form with action (check) and ticket
    const data = new FormData();
    data.append("action", "check");
    data.append("ticket", ticket);

    try {

        // Send request to session.php
        const response = await fetch(SESSION_URL, {
            method: "POST",
            body: data
        });

        // Result of the request
        const result = await response.json();

        // Only leave the page if the server says the ticket is bad
        // A connection problem shouldn't log anyone out
        if (response.status === 401 || result.valid === false) {
            goToLogin();
        }

        // If something goes wrong (for example, no connection), write a note to the Console
    } catch (error) {
        console.log("Could not check the session: " + error.message);
    }
}

// Shows a message under the fields, in red (error) or green (success)
function showMessage(text, isSuccess) {
    const message = document.getElementById("message");
    message.textContent = text;
    message.className = "form-message " + (isSuccess ? "success" : "error");
}

// Checks the form, then asks the server to change the password
async function changePassword(event) {

    // Stop the browser from reloading the page when the form is submitted
    event.preventDefault();

    // Get what the user typed
    const currentPassword = document.getElementById("current-password").value;
    const newPassword = document.getElementById("new-password").value;
    const confirmPassword = document.getElementById("confirm-password").value;
    const button = document.getElementById("save");

    // Checks done in the browser first (the server checks them again)
    if (currentPassword === "" || newPassword === "" || confirmPassword === "") {
        showMessage("Please fill in every field.", false);
        return;
    }

    if (newPassword !== confirmPassword) {
        showMessage("The new passwords don't match.", false);
        return;
    }

    // Get ticket from local storage
    const ticket = localStorage.getItem("ticket");

    if (!ticket) {
        goToLogin();
        return;
    }

    // Turn off the button so it can't be clicked twice, and clear old messages
    button.disabled = true;
    showMessage("", false);

    // Create form with action (change_password), both passwords and ticket
    const data = new FormData();
    data.append("action", "change_password");
    data.append("current_password", currentPassword);
    data.append("new_password", newPassword);
    data.append("ticket", ticket);

    try {

        // Send request to register.php
        const response = await fetch(ACCOUNT_URL, {
            method: "POST",
            body: data
        });

        // Result of the request
        const result = await response.json();

        // 401 means the login ticket is no longer valid
        if (response.status === 401) {
            goToLogin();
            return;
        }

        if (result.success) {

            // The server deletes every ticket when the password changes,
            // so the user has to log in again
            document.getElementById("password-form").reset();
            showMessage((result.message || "Password changed.") + " Taking you to the login page...", true);
            setTimeout(goToLogin, 2000);
            return;
        }

        // Show the server's message (for example "Incorrect current password")
        showMessage(result.message || "Something went wrong. Please try again.", false);

        // If something goes wrong (for example, no connection), tell the user
    } catch (error) {
        console.log("Could not change the password: " + error.message);
        showMessage("Could not reach the server. Please try again.", false);
    }

    // Only reached when the change failed, so let the user try again
    button.disabled = false;
}

// Fill in the username
displayProfile();

// Check if logged in
checkSession().catch(function(error) {
    console.log("Session check failed: " + error.message);
});

// Run changePassword when the form is submitted
document.getElementById("password-form").onsubmit = changePassword;
