var login = document.getElementById("loginForm");
var forgot = document.getElementById("forgotForm");
var reset = document.getElementById("resetForm");
var message = document.getElementById("message");
var email = "";

login.addEventListener("submit", function(event) {
    event.preventDefault();
    var email = document.getElementById("email").value;
    var password = document.getElementById("password").value;
    var req = new XMLHttpRequest();
    req.open("POST", "php/login.php", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.onload = function() {
        try {
            var data = JSON.parse(req.responseText);
            if (data.success) {
                localStorage.setItem("ticket", data.ticket);
                localStorage.setItem("username", data.username);
                setLocation(data.ticket);
                message.textContent = "Logging in...";
            } else {
                message.textContent = data.message;
            }
        } catch (error) {
            message.textContent = "Something went wrong";
        }
    };
    req.onerror = function() {
        message.textContent = "Could not connect to server";
    };
    req.send("action=login" + "&email=" + encodeURIComponent(email) + "&password=" + encodeURIComponent(password)
    );
});

document.getElementById("forgotPassword").addEventListener("click", function() {
    login.hidden = true;
    forgot.hidden = false;
    document.getElementById("resetEmail").value = document.getElementById("email").value;
    message.textContent = "";
});

forgot.addEventListener("submit", function(event) {
    event.preventDefault();
    email = document.getElementById("resetEmail").value;
    var req = new XMLHttpRequest();
    req.open("POST", "php/register.php", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.onload = function() {
        try {
            var data = JSON.parse(req.responseText);
            message.textContent = data.message;
            if (data.success) {
                forgot.hidden = true;
                reset.hidden = false;
            }
        } catch (error) {
            message.textContent = "Something went wrong";
        }
    };
    req.onerror = function() {
        message.textContent = "Could not connect to server";
    };
    req.send("action=forgot_password" +"&email=" + encodeURIComponent(email));
});

reset.addEventListener("submit", function(event) {
    event.preventDefault();
    var code = document.getElementById("code").value;
    var newPassword = document.getElementById("newPassword").value;
    var req = new XMLHttpRequest();
    req.open("POST", "php/register.php", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.onload = function() {
        try {
            var data = JSON.parse(req.responseText);
            message.textContent = data.message;
            if (data.success) {
                reset.hidden = true;
                login.hidden = false;
                document.getElementById("email").value = email;
            }
        } catch (error) {
            message.textContent = "Something went wrong";
        }
    };
    req.onerror = function() {
        message.textContent = "Could not connect to server";
    };
    req.send(
        "action=reset_password" + "&email=" + encodeURIComponent(email) +
        "&code=" + encodeURIComponent(code) + "&new_password=" + encodeURIComponent(newPassword)
    );
});


function setLocation(ticket) {
    if (!navigator.geolocation) {
        window.location.href = "homepage.html";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            var latitude = position.coords.latitude.toFixed(2);
            var longitude = position.coords.longitude.toFixed(2);
            var req = new XMLHttpRequest();
            req.open("POST", "php/weather.php", true);
            req.setRequestHeader(
                "Content-Type",
                "application/x-www-form-urlencoded"
            );
            req.timeout = 5000;
            req.onload = function() {
                window.location.href = "homepage.html";
            };
            req.onerror = function() {
                window.location.href = "homepage.html";
            };
            req.ontimeout = function() {
                window.location.href = "homepage.html";
            };
            req.send(
                "action=set_location" + "&ticket=" + encodeURIComponent(ticket) +
                "&latitude=" + encodeURIComponent(latitude) + "&longitude=" + encodeURIComponent(longitude)
            );
        },
        function(error) {
            window.location.href = "homepage.html";
        },
        {
            enableHighAccuracy: false,
            timeout: 5000,
            maximumAge: 300000
        }
    );
}