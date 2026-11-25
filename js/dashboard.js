var ticket = localStorage.getItem("ticket");
var userId = null;

var SESSION_URL = "php/session.php";
var TREE_URL = "php/tree.php";
var SHOP_URL = "php/shop.php";
var WEATHER_URL = "php/weather.php";

function redirectToLogin() {
    localStorage.removeItem("ticket");
    localStorage.removeItem("username");
    window.location.href = "login.html";
}

function request(url, params, callback) {
    var req = new XMLHttpRequest();
    req.open("POST", url, true);
    req.setRequestHeader(
        "Content-Type",
        "application/x-www-form-urlencoded"
    );

    req.onload = function() {
        if (req.status === 401) {
            redirectToLogin();
            return;
        }

        if (req.status === 200) {
            var data = JSON.parse(req.responseText);
            if (data.error || data.success === false) {
                console.log(data.message || data.error);
                return;
            }
            callback(data);
        } else {
            console.log("Request failed: " + req.status);
        }
    };

    req.onerror = function() {
        console.log("Connection failed");
    };

    req.send(params + "&ticket=" + encodeURIComponent(ticket));
}

function checkSession() {
    if (!ticket) {
        redirectToLogin();
        return;
    }

    request(SESSION_URL, "action=check", function(data) {
        if (data.valid) {
            userId = data.user_id;
            displayProfile();
            refreshDashboard();
        } else {
            redirectToLogin();
        }
    });
}

function relativePath(path) {
    if (!path) {
        return "";
    }
    return path.replace("/home/cs307-user/app/", "");
}

function displayProfile() {
    var username = localStorage.getItem("username");
    if (username) {
        document.getElementById("nickname").textContent = "@" + username;
    } else {
        document.getElementById("nickname").textContent = "@User";
    }
}

function loadTree() {
    request(TREE_URL, "user_id=" + userId, function(data) {
        var tree = document.getElementById("tree");
        tree.src = relativePath(data.treepath);
        var treeWrap = document.getElementsByClassName("tree-layers")[0];
        var decorations = treeWrap.getElementsByClassName("decoration");
        while (decorations.length > 0) {
            decorations[0].remove();
        }
        for (var i = 0; i < data.decorations.length; i++) {
            var image = document.createElement("img");
            image.src = relativePath(data.decorations[i]);
            image.className = "decoration";
            treeWrap.appendChild(image);
        }
    });
}

function loadCoins() {
    var params = "functionName=SHOW_COINS&user_id=" + userId;
    request(SHOP_URL, params, function(data) {
        document.getElementById("coins").textContent = data.coins;
    });
}

function loadStreak(callback) {
    var params = "functionName=SHOW_STREAK&user_id=" + userId;
    request(SHOP_URL, params, function(data) {
        document.getElementById("streak").textContent = data.streak;
        if (Number(data.consumed) === 1) {
            alert("A watering can saved your streak!");
        }
        callback();
    });
}

function loadWeather() {
    var params = "action=get_weather";

    request(WEATHER_URL, params, function(data) {
        if (data.success) {
            displayWeather(data.weather);
        }
    });
}


function getSavedWeather() {
    request(WEATHER_URL, "action=get_weather", function(data) {
        displayWeather(data.weather);
    });
}

function displayWeather(weather) {
    document.getElementById("weather-temp").textContent = Math.round(weather.temperature) + "°C";
    var icon = document.getElementById("weather-icon");
    icon.src = "assets/weathers/" + weather.weather + ".png";
    icon.alt = weather.weather;
    icon.title = weather.weather;
}

function loadShop() {
    request(SHOP_URL, "functionName=SHOW_SHOP", function(data) {
        var shop = document.getElementById("shop");
        shop.innerHTML = "";
        for (var i = 0; i < data.elements.length; i++) {
            createShopItem(data.elements[i]);
        }
    });
}

function createShopItem(item) {
    var shop = document.getElementById("shop");
    var card = document.createElement("button");
    card.className = "shop-item";
    card.type = "button";
    var image = document.createElement("img");
    image.src = relativePath(item.image_path).replace("assets/", "assets/icons/").replace(".png", "-icon.png");

    image.className = "item-image";
    image.alt = item.item_name;
    card.appendChild(image);
    var bought = Number(item.is_bought) === 1;
    var active = Number(item.is_active) === 1;
    if (!bought) {
        var price = document.createElement("div");
        price.className = "price";
        var coin = document.createElement("img");
        coin.src = "assets/icons/coinBlured.png";
        coin.className = "price-coin";
        coin.alt = "Coins";
        var amount = document.createElement("span");
        amount.className = "price-amount";
        amount.textContent = item.price;
        price.appendChild(coin);
        price.appendChild(amount);
        card.appendChild(price);
    }
    if (active) {
        card.classList.add("equipped");
    }
    card.onclick = function() {
        if (!bought) {
            buyItem(item.item_id);
        } else if (Number(item.item_id) !== 4) {
            toggleItem(item.item_id);
        }
    };
    if (bought && Number(item.item_id) === 4) {
        card.disabled = true;
        card.classList.add("equipped");
    }
    shop.appendChild(card);
}

function buyItem(itemId) {
    var params = "functionName=BUY" +
        "&item_id=" + encodeURIComponent(itemId);

    request(SHOP_URL, params, function(data) {
        if (Number(data.status) === 1) {
            loadCoins();
            loadShop();
            loadTree();
        } else {
            alert(data.message || "Purchase failed");
        }
    });
}

function toggleItem(itemId) {
    var params = "functionName=TOGGLE" +
        "&item_id=" + encodeURIComponent(itemId);

    request(SHOP_URL, params, function(data) {
        if (Number(data.status) === 1) {
            loadTree();
            loadShop();
        } else {
            alert(data.message || "Could not toggle item");
        }
    });
}

function setupNavigation() {
    var buttons = document.getElementsByClassName("nav-btn");
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].onclick = function() {
            var pages = document.getElementsByClassName("page");
            for (var j = 0; j < pages.length; j++) {
                pages[j].classList.remove("active");
            }
            for (var j = 0; j < buttons.length; j++) {
                buttons[j].classList.remove("active");
            }
            this.classList.add("active");
            var pageId = "page-" + this.dataset.page;
            document.getElementById(pageId).classList.add("active");
        };
    }
}

function refreshDashboard() {
    loadWeather();
    loadStreak(function() {
        loadTree();
        loadCoins();
        loadShop();
    });
}

window.onload = function() {
    setupNavigation();
    checkSession();
};
