var email = "";
var message = document.getElementById("message");
var register = document.getElementById("registerForm");
var verify = document.getElementById("verifyForm");

register.addEventListener("submit", function(event) {
    event.preventDefault();
    var firstName = document.getElementById("firstName").value;
    var lastName = document.getElementById("lastName").value;
    var username = document.getElementById("username").value;
    var password = document.getElementById("password").value;
    email = document.getElementById("email").value;

    var req = new XMLHttpRequest();
    req.open("POST", "php/register.php", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    req.onload = function() {
        try {
            var data = JSON.parse(req.responseText);
            message.textContent = data.message;
            if (data.success) {
                register.hidden = true;
                verify.hidden = false;
            }
        } catch (error) {
            message.textContent = "Something went wrong";
        }
    };
    req.onerror = function() {
        message.textContent = "Could not connect to server";
    };
    req.send(
        "action=register" + "&first_name=" + encodeURIComponent(firstName) + "&last_name=" + encodeURIComponent(lastName) +
        "&username=" + encodeURIComponent(username) + "&email=" + encodeURIComponent(email) + "&password=" + encodeURIComponent(password)
    );
});

verify.addEventListener("submit", function(event) {
    event.preventDefault();
    var code = document.getElementById("code").value;
    var req = new XMLHttpRequest();
    req.open("POST", "php/register.php", true);
    req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    req.onload = function() {
        try {
            var data = JSON.parse(req.responseText);
            message.textContent = data.message;
            if (data.success) {
                window.location.href = "login.html";
            }
        } catch (error) {
            message.textContent = "Something went wrong";
        }
    };
    req.onerror = function() {
        message.textContent = "Could not connect to server";
    };
    req.send("action=verify" + "&email=" + encodeURIComponent(email) + "&code=" + encodeURIComponent(code));
});
