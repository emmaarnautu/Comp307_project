// ---------- Mood faces ----------
// value = the mood score (1-5), mouth = the SVG path for the mouth
const MOODS = [
    { value: 1, label: "Awful", img: "assets/moods/Awful.svg"},
    { value: 2, label: "Sad",   img: "assets/moods/Sad.svg"},
    { value: 3, label: "Okay",  img: "assets/moods/Ok.svg" },
    { value: 4, label: "Good",  img: "assets/moods/Good.svg"},
    { value: 5, label: "Great", img: "assets/moods/Great.svg"}
];

// Returns the SVG markup for one face
// (you can replace this with <img> tags once you have face icons in assets/icons)
function makeFace(mood, size) {
    return '<img src="' + mood.img + '" alt="' + mood.label + '" width="' + size + '" height="' + size + '">';
}

// Save moods as { Calendar_Date : Mood_Value}
// TODO: replace with data from the backend
let moods = {};
try {
    moods = JSON.parse(localStorage.getItem("blossom-moods")) || {};
} catch (e) {
    moods = {};
}

// TODO: save data to backend
function saveMoods() {
    try {
        localStorage.setItem("blossom-moods", JSON.stringify(moods));
    } catch (e) {}
}

// Convert date "YYYY-MM-DD"
function dateKey(date) {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return date.getFullYear() + "-" + month + "-" + day;
}

// Side Panel data
let selectedDate = new Date();
const moodPicker = document.getElementById("mood-picker");

// Formatting date
function ordinal(n) {
    if (n % 100 >= 11 && n % 100 <= 13) {
        return n + "th";
    }
    const suffixes = { 1: "st", 2: "nd", 3: "rd" };
    return n + (suffixes[n % 10] || "th");
}

// Select day and display
function showDay() {
    document.getElementById("cal-month").textContent =
        selectedDate.toLocaleDateString("en-US", { month: "long" });
    document.getElementById("cal-day").textContent = ordinal(selectedDate.getDate());

    // Mood
    const mood = moods[dateKey(selectedDate)];
    document.getElementById("cal-mood").textContent = mood ? mood + "/5" : "-";
    document.getElementById("cal-mood-face").innerHTML = mood ? makeFace(MOODS[mood - 1], 44) : "";

    // Highlight the chosen button in the picker
    moodPicker.querySelectorAll("button").forEach(function (btn) {
        const chosen = Number(btn.dataset.value) === mood;
        btn.classList.toggle("selected", chosen);
        btn.setAttribute("aria-checked", chosen);
    });

    // TODO: load the real tasks completed and weather for this date
    document.getElementById("cal-tasks").textContent = "10 tasks";
    document.getElementById("cal-temp").textContent = "35°C";
}

// Builds the 5 mood buttons
MOODS.forEach(function (mood) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mood-btn";
    btn.dataset.value = mood.value;
    btn.setAttribute("role", "radio");
    btn.setAttribute("aria-label", mood.label);
    btn.innerHTML = makeFace(mood, 38);

    btn.addEventListener("click", function () {
        moods[dateKey(selectedDate)] = mood.value;
        saveMoods();
        // tell the React calendar to refresh its dots
        window.dispatchEvent(new Event("moodchange"));
        showDay();
    });

    moodPicker.appendChild(btn);
});

// ---------- Talking to the React calendar ----------
// The React part reads the saved moods from here
window.blossom = {
    getMoods: function () { return moods; },
    dateKey: dateKey
};

// The React calendar fires "datechange" when the user clicks a date
window.addEventListener("datechange", function (e) {
    selectedDate = e.detail;
    showDay();
});

showDay();