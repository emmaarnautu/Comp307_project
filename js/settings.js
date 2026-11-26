// JS for Settings page: Shows the username, checks the login, and handles logging out

// Store file paths
const SESSION_URL = "php/session.php";
const LOGIN_URL = "php/login.php";

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

    // If there's an error, write a note to console
    } catch (error) {
        console.log("Could not check the session: " + error.message);
    }
}

// Tell the server to delete the ticket, then go to the login page
async function logout() {

    // Find logout button and turn it off
    const button = document.getElementById("logout");
    button.disabled = true;

    // Get the ticket from local storage
    const ticket = localStorage.getItem("ticket");

    // Only run if there's a ticket
    if (ticket) {

        // Create form with action (logout) and ticket
        const data = new FormData();
        data.append("action", "logout");
        data.append("ticket", ticket);

        try {

            // Send request to login.php
            await fetch(LOGIN_URL, {
                method: "POST",
                body: data
            });

        // If there's an error, write a note to console
        } catch (error) {
            console.log("Could not reach the server: " + error.message);
        }
    }

    // Log out on this device whether the server answered or not
    goToLogin();
}

// Fill in the username
displayProfile();

// Check if logged in
checkSession();

// Make Log Out button run logout when clicked
document.getElementById("logout").onclick = logout;
