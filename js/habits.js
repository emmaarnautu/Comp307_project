// JavaScript for Habits page
// Show today's habits, let the user tick them off, and add new habits

// Store file paths
const HABITS_URL = "php/habits.php";

// Pictures for the checkbox
const BOX_EMPTY = "assets/icons/square.svg";
const BOX_CHECKED = "assets/icons/check-square.svg";

// Category currently shown ("All" shows every category)
let currentCategory = "All";

// Count the loads so an old, slow answer can't replace a newer one
let loadCount = 0;

// Clear the saved login and goes back to the login page
function goToLogin() {
    localStorage.removeItem("ticket");
    localStorage.removeItem("username");
    window.location.href = "login.html";
}

// Show username in the header
function displayProfile() {
    const username = localStorage.getItem("username");
    document.getElementById("nickname").textContent = "@" + (username || "User");
}

// Show a message in the element with this id, in red (error) or green (success)
// An empty text clears the message
function setMessage(id, text, isSuccess) {
    const message = document.getElementById(id);
    message.textContent = text;

    if (text === "") {
        message.className = "form-message";
    } else {
        message.className = "form-message " + (isSuccess ? "success" : "error");
    }
}

// Send a request to habits.php and returns the answer as an object
// fields holds the extra values for this request, for example { habit_id: 5 }
// Return null if the user was sent to the login page
async function callHabits(functionName, fields) {

    // Get ticket from local storage
    const ticket = localStorage.getItem("ticket");

    // If there was no ticket, user was never logged in, so send them to login page
    if (!ticket) {
        goToLogin();
        return null;
    }

    // Create form with the function name, the ticket and the extra fields
    const data = new FormData();
    data.append("functionName", functionName);
    data.append("ticket", ticket);

    for (const name of Object.keys(fields)) {
        data.append(name, fields[name]);
    }

    // Send request to habits.php
    const response = await fetch(HABITS_URL, {
        method: "POST",
        body: data
    });

    // Result of the request
    const result = await response.json();

    // habits.php answers "Invalid ticket" when the login has expired
    if (result.error === "Invalid ticket") {
        goToLogin();
        return null;
    }

    return result;
}

// Change one habit's checkbox between empty and ticked
function showTick(button, isDone) {
    button.querySelector(".habit-box").src = isDone ? BOX_CHECKED : BOX_EMPTY;
    button.setAttribute("aria-pressed", isDone ? "true" : "false");
    button.closest(".habit-item").classList.toggle("done", isDone);
}

// Add one habit to the list using the template in the HTML
function showHabit(habit) {
    const template = document.getElementById("habit-template");
    const row = template.content.cloneNode(true);

    const item = row.querySelector(".habit-item");
    const button = row.querySelector(".habit-check");

    // Fill in this habit's details
    item.dataset.habitId = habit.habit_id;
    row.querySelector(".habit-name").textContent = habit.habit_name;
    button.setAttribute("aria-label", "Mark " + habit.habit_name + " as done");

    // Show the box ticked if the habit was already done today
    showTick(button, Number(habit.is_done) === 1);

    // Tick the habit off when the box is clicked
    button.onclick = function() {
        markDone(habit.habit_id, button);
    };

    document.getElementById("habit-list").appendChild(row);
}

// Get today's habits for the chosen category and shows them
async function loadHabits() {
    const list = document.getElementById("habit-list");
    const empty = document.getElementById("empty-message");

    // Remember which load this is
    loadCount++;
    const thisLoad = loadCount;

    try {
        const result = await callHabits("SHOW_HABITS", { habit_category: currentCategory });

        // Ignore the answer if the user was sent away or picked another category since
        if (!result || thisLoad !== loadCount) {
            return;
        }

        if (result.error) {
            console.log(result.error);
            setMessage("message", "Could not load your habits.", false);
            return;
        }

        // Replace the old list with the new one
        const habits = result.habits || [];
        list.innerHTML = "";

        for (const habit of habits) {
            showHabit(habit);
        }

        // Show a message when there is nothing for today
        empty.hidden = habits.length > 0;

        // If something goes wrong (for example, no connection), tell the user
    } catch (error) {
        console.log("Could not load habits: " + error.message);
        setMessage("message", "Could not reach the server. Please try again.", false);
    }
}

// Load the habits and make sure an unexpected error is written to the Console
function refreshHabits() {
    loadHabits().catch(function(error) {
        console.log("Loading habits failed: " + error.message);
    });
}

// Tell the server a habit was done today, then tick the box
async function markDone(habitId, button) {

    // Already done: The server has no way to undo it, so there is nothing to do
    if (button.getAttribute("aria-pressed") === "true") {
        return;
    }

    // Turn off the button while saving so it can't be clicked twice
    button.disabled = true;
    setMessage("message", "", false);

    let saved = false;

    try {
        const result = await callHabits("HABIT_DONE", { habit_id: habitId });

        if (!result) {
            return;
        }

        if (result.error) {
            console.log(result.error);
        } else {
            saved = true;
            showTick(button, true);

            // need_to_update is 1 when this habit reached the daily goal
            if (Number(result.need_to_update) === 1) {
                setMessage("message", "Daily goal reached! Check your dashboard for your coins and streak.", true);
            }
        }

        // If something goes wrong (for example, no connection), write a note to the Console
    } catch (error) {
        console.log("Could not save the habit: " + error.message);
    }

    if (!saved) {
        setMessage("message", "Could not save that. Please try again.", false);
    }

    button.disabled = false;
}

// Open the pop-up for a new habit
function openAddDialog() {

    // reset() puts back the empty name and the ticked days from the HTML
    document.getElementById("add-form").reset();
    setMessage("add-message", "", false);

    document.getElementById("add-dialog").showModal();
    document.getElementById("habit-name").focus();
}

// Check the new habit form, then asks the server to add it
async function addHabit(event) {

    // Stop the browser from reloading the page when the form is submitted
    event.preventDefault();

    // Get what the user entered
    const name = document.getElementById("habit-name").value.trim();
    const category = document.getElementById("habit-category").value;
    const saveButton = document.getElementById("save-habit");

    // Build the days as 7 characters, Sunday first, for example "0111110"
    // (this is the format habits.php expects)
    const dayList = ["0", "0", "0", "0", "0", "0", "0"];
    const boxes = document.querySelectorAll('#add-form input[name="day"]');

    for (const box of boxes) {
        dayList[Number(box.value)] = box.checked ? "1" : "0";
    }

    const days = dayList.join("");

    // Check done in the browser first
    if (name === "") {
        setMessage("add-message", "Please enter a name for the habit.", false);
        return;
    }

    if (!days.includes("1")) {
        setMessage("add-message", "Please choose at least one day.", false);
        return;
    }

    // Turn off the button while saving, and clear old messages
    saveButton.disabled = true;
    setMessage("add-message", "", false);

    try {
        const result = await callHabits("HABIT_ADDED", {
            habit_name: name,
            habit_category: category,
            habit_days: days
        });

        if (!result) {
            return;
        }

        if (Number(result.status) === 1) {

            // Close the pop-up and show the updated list
            document.getElementById("add-dialog").close();
            setMessage("message", "Habit added! It will appear on the days you picked.", true);
            refreshHabits();

        } else {
            console.log(result.error);
            setMessage("add-message", result.error || "Could not add the habit. Please try again.", false);
        }

        // If something goes wrong (for example, no connection), tell the user
    } catch (error) {
        console.log("Could not add the habit: " + error.message);
        setMessage("add-message", "Could not reach the server. Please try again.", false);
    }

    saveButton.disabled = false;
}

// Choose a category: Highlight its tab and load its habits
document.getElementById("category-tabs").onclick = function(event) {
    const tab = event.target.closest(".category-tab");

    if (!tab) {
        return;
    }

    currentCategory = tab.dataset.category;

    for (const other of document.querySelectorAll(".category-tab")) {
        other.classList.toggle("active", other === tab);
    }

    setMessage("message", "", false);
    refreshHabits();
};

// Open and close the new habit pop-up, and send the form
document.getElementById("open-add").onclick = openAddDialog;

document.getElementById("cancel-add").onclick = function() {
    document.getElementById("add-dialog").close();
};

document.getElementById("add-form").onsubmit = addHabit;

// Fill in the username
displayProfile();

// Show today's habits (this also logs the user out if the ticket is no longer valid)
refreshHabits();