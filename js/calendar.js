/* Paths to relevant PHP files */
const API = {
    calendar: "php/calendar.php",
    habits: "php/habits.php",
    weather: "php/weather.php"
};

function getTicket() { return localStorage.getItem("ticket"); }

async function post(url, data) {
    const response = await fetch(url, {
        method: "POST",
        body: new URLSearchParams(data)
    });
    return response.json();    
}

/* Mood faces */
const MOODS = [
    { value: 1, label: "Awful", img: "assets/moods/Awful.svg"},
    { value: 2, label: "Sad",   img: "assets/moods/Sad.svg"},
    { value: 3, label: "Okay",  img: "assets/moods/Ok.svg" },
    { value: 4, label: "Good",  img: "assets/moods/Good.svg"},
    { value: 5, label: "Great", img: "assets/moods/Great.svg"}
];

/* Weather Icons */
const WEATHER = [
    {value: 1, label: "Clear", img: "assets/weathers/Clear.png"},
    {value: 2, label: "Drizzle", img: "assets/weathers/Drizzle.png"},
    {value: 3, label: "Fog", img: "assets/weathers/Fog.png"},
    {value: 4, label: "FreezingDrizzle", img: "assets/weathers/FreezinDrizzle.png"},
    {value: 5, label: "FreezingRain", img: "assets/weathers/FreezingRain.png"},
    {value: 6, label: "MainlyClear", img: "assets/weathers/MainlyClear.png"},
    {value: 7, label: "Overcast", img: "assets/weathers/Overcast.png"},
    {value: 8, label: "PartlyCloudy", img: "assets/weathers/PartlyCloudy.png"},
    {value: 9, label: "Rain", img: "assets/weathers/Rain.png"},
    {value: 10, label: "RainShowers", img: "assets/weathers/RainShowers.png"},
    {value: 11, label: "Snow", img: "assets/weathers/Snow.png"},
    {value: 12, label: "SnowShowers", img: "assets/weathers/SnowShowers.png"},
    {value: 13, label: "Thunderstorm", img: "assets/weathers/Thunderstorm.png"},
];

/* Return mood face */
function makeFace(mood, size) {
    return '<img src="' + mood.img + '" alt="' + mood.label + '" width="' + size + '" height="' + size + '">';
}

/* Return weather icon */
function weatherImg(description){
    // Remove Whitespace
    const name = description.replace(/\s/g, "");
    const found = WEATHER.find(function (w) {
        return w.label === name;
    });
    return found ? found.img : null;
}



/* Helper Functions */

// Convert date "YYYY-MM-DD"
function dateKey(date) {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return date.getFullYear() + "-" + month + "-" + day;
}

// Boolean
function isToday(date) {
    return dateKey(date) === dateKey(new Date());
}

// Format side panel date
function ordinal(n) {
    if (n % 100 >= 11 && n % 100 <= 13) {
        return n + "th";
    }
    const suffixes = { 1: "st", 2: "nd", 3: "rd" };
    return n + (suffixes[n % 10] || "th");
}



/* Database */

// Mood getter and setter
let moods = {};

async function loadMoods() {
    try {
        const data = await post(API.calendar,
            { functionName: "SHOW_LOGS", ticket: getTicket() });
            (data.entries || []).forEach(function (entry) {
                if (entry.mood != null) {
                    moods[entry.date] = Number(entry.mood);
                }
            });

    }
    catch (e) {
        console.error("Could not load moods", e);
    }

    window.dispatchEvent(new Event("moodchange"));
    showMood();
}

async function saveMood(value) {
    try {
        const data = await post(API.calendar, 
            { functionName: "STORE_LOG", ticket: getTicket(), mood: value });

            if (data.error) {
                console.error("Could not save mood", data.error);
                return;
        }
        moods[dateKey(new Date())] = value;
        windows.dispatchEvent(new Event("moodchange"));
        showMood();
    }
    catch (e) {
        console.error("Could not save mood", e);
    }
}

// Weather getter
const weatherMemory = {};
async function getWeather(key) {
    if (weatherMemory[key]) {
        return weatherMemory[key];
    }
    try {
        const data = await post(API.weather, 
            { action: "get_weather", ticket: getTicket(), date: key });
            if (data.success) {
                weatherMemory[key] = data.weather;
                return data.weather;
            }
    }
    catch (e) {
        console.error("Could not load weather", e);
    }
    return null;
}

// Habits getter
async function getCompleted(key) {
    try{
        const data = await post(API.habits, 
            {functionName: "HABIT_COUNT", ticket: getTicket(), date: key });
            if (data.count !== undefined) {
                return data.count;
            }    
    }
    catch (e) {
        console.error("Could not load completed tasks", e)
    }
    return null;
}



/* Side Panel */
let selectedDate = new Date();
const moodPicker = document.getElementById("mood-picker");

// Mood
function showMood() {
    const mood = moods[dateKey(selectedDate)];
    document.getElementById("cal-mood").textContent =
        mood ? mood + "/5" : "--";
    document.getElementById("cal-mood-face").innerHTML =
        mood ? makeFace(MOODS[mood - 1], 50) : "";
    
    const editable = isToday(selectedDate);

    moodPicker.querySelectorAll("button").forEach(
        function (btn) {
            const chosen = Number(btn.dataset.value) === mood;
            btn.classList.toggle("selected", chosen);
            btn.setAttribute("aria-checked", chosen);
            btn.disabled = !editable;
        }
    );
}

async function showDetails(key) {
    const results = await Promise.all([getCompleted(key), getWeather(key)]);
    if (key !== dateKey(selectedDate)) {
        return;
    }

    const completed = results[0];
    document.getElementById("cal-tasks").textContent =
        completed === null ? "--" : completed 
            + (completed === 1 ? "tasks" : " tasks");
    
        const weather = results[1];
        const icon = document.getElementById("cal-weather-icon");
        const img = weather ? weatherImg(weather.weather) : null;
        document.getElementById("cal-temp").textContent =
            weather ? Math.round(weather.temperature) + "°C" : "--";
        icon.style.display = img ? "" : "none";
        if (img) {
            icon.src = img;
            icon.alt = weather.weather;
        }
}

// Select day and display
function showDay() {
    document.getElementById("cal-month").textContent =
        selectedDate.toLocaleDateString("en-US", { month: "long" });
    document.getElementById("cal-day").textContent = 
        ordinal(selectedDate.getDate());

    showMood();

    document.getElementById("cal-tasks").textContent = "--";
    document.getElementById("cal-temp").textContent = "--";
    document.getElementById("cal-weather-icon").style.display = "none";
    showDetails(dateKey(selectedDate));
}



/* 5 Moods panel */

MOODS.forEach(function (mood) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mood-btn";
    btn.dataset.value = mood.value;
    btn.setAttribute("role", "radio");
    btn.setAttribute("aria-label", mood.label);
    btn.innerHTML = makeFace(mood, 40);

    btn.addEventListener("click", function () {
        saveMood(mood.value);
    });

    moodPicker.appendChild(btn);
});

/* Talking to the React calendar */
window.blossom = {
    getMoods: function () { return moods; },
    dateKey: dateKey,
    moodImg: function (value) { return MOODS[value - 1].img; }
};

window.addEventListener("datechange", function (e) {
    selectedDate = e.detail;
    showDay();
});

showDay();
loadMoods();