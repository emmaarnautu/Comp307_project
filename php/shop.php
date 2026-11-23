<?php
/**To call: use POST with functionName = ... and parameters needed:
 *  BUY:
 *      Input parameters: "user_id", "item_id"
 *      Returns:
 *          $response =
 *              'status' => 0|1 (failed|succeeded)
 *              'message' => "Item bought!" | "Error: Decoration already bought!","Error: Tree not in final stage!", "Error: Not enough coins!"
 *  TOGGLE:
 *       Input parameters: "user_id", "item_id"
 *       Returns:
 *           $response =
 *               'status' => 0|1, failed (item not owned) or succeeded
 *
 *  SHOW_COINS:
 *       Input parameters: "user_id"
 *       Returns:
 *           $response =
 *               'coins' => number of coins user has
 *
 *   SHOW_STREAK:
 *        Input parameters: "user_id"
 *        Returns:
 *            $response =
 *                'streak' => streak number
 *                'consumed' => 0|1 was a watering can consumed to save streak?
 *
 *  SHOW_SHOP:
 *       Input parameters: "user_id"
 *       Returns:
 *           $response =
 *               'elements' =>
 *                     'item_id' =>
 *                     'item_name' =>
 *                     'is_bought' => 0|1
 *                     'is_active' => 0|1
 */

//initialise db
require_once "db.php";

//method menu
$method = '0';
if (isset($_POST['functionName'])) {
    $method = strtoupper(trim($_POST['functionName']));
}
switch ($method) {
    case "BUY" :
        buy();
    case "TOGGLE" :
        toggle();
    case "SHOW_COINS" :
        show_coins();
    case "SHOW_STREAK" :
        show_coins();
    case "SHOW_SHOP" :
        show_shop();
    default :
        echo "Error: No such method found! shop.php";
        exit;
}

function buy() :void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $itemID = $_POST["item_id"] ?? "";

    //check if user already has decoration
    $query = $pdo->prepare("SELECT purchase_id FROM purchases WHERE user_id = :id AND item_id = :item_id");
    $query->execute([
        'id' => $userID,
        'item_id' => $itemID]);
    $isFound = $query->fetch();
    if($isFound) {
        $response = [
            'status' => 0,
            'message' => "Error: Decoration already bought!"
        ];
        echo json_encode($response);
        exit;
    }
    //check if tree is final stage
    $query = $pdo->prepare("SELECT growth_stage FROM trees WHERE user_id = :id");
    $query->execute([
        'id' => $userID]);
    $tree_growth = $query->fetchColumn();
    if((int)$tree_growth !== 7) {
        $response = [
            'status' => 0,
            'message' => "Error: Tree not in final stage!"
        ];
        echo json_encode($response);
        exit;
    }

    //check if user has enough coins
    $userID = $_POST["user_id"] ?? "";
    $query = $pdo->prepare("SELECT coins FROM users WHERE user_id = :user_id");
    $query->execute([
        'user_id' => $userID
    ]);
    $coins = $query->fetchColumn();
    $query = $pdo->prepare("SELECT price FROM shop_items WHERE item_id = :item_id");
    $query->execute([
        'item_id' => $itemID
    ]);
    $price = $query->fetchColumn();

    if((int)$coins < (int)$price) {
        $response = [
            'status' => 0,
            'message' => "Error: Not enough coins!"
        ];
        echo json_encode($response);
        exit;
    }

    //make new purchase and decoration entries
    $query = $pdo->prepare("INSERT INTO purchases (user_id, item_id) VALUES (:user_id, :item_id)");
    $query->execute([
        'user_id' => $userID,
        'item_id' => $itemID]);

    $purchaseID = $pdo->lastInsertId();

    $query = $pdo->prepare("INSERT INTO tree_decorations (user_id, purchase_id, is_active) VALUES (:user_id, :purchase_id, 0)");
    $query->execute([
        'user_id' => $userID,
        'purchase_id' => $purchaseID]);

    $newCoins = (int)$coins - (int)$price;

    //calculate and update coins
    $query = $pdo->prepare("
    UPDATE users 
        SET coins = :newCoins 
        WHERE user_id = :user_id 
        ");

    $query->execute([
        'newCoins' => $newCoins,
        'user_id' => $userID]);

    $response = [
        'status' => 1
    ];
    echo json_encode($response);
    exit;
}

function toggle() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $itemID = $_POST["item_id"] ?? "";

    //check if user already has decoration
    $query = $pdo->prepare("SELECT purchase_id FROM purchases WHERE user_id = :id AND item_id = :item_id");
    $query->execute([
        'id' => $userID,
        'item_id' => $itemID]);
    $purchaseID = $query->fetch(PDO::FETCH_ASSOC);
    if(!$purchaseID) {
        $response = [
            'status' => 0
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("
    UPDATE tree_decorations 
        SET is_active = 1 - is_active 
        WHERE purchase_id = :purchase_id 
          AND user_id = :user_id
    ");

    $query->execute([
        ':purchase_id' => $purchaseID['purchase_id'],
        ':user_id'       => $userID
    ]);
    $response = [
        'status' => 1
    ];
    echo json_encode($response);
    exit;
}

function show_coins() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $query = $pdo->prepare("SELECT coins FROM users WHERE user_id = :user_id");
    $query->execute([
        'user_id' => $userID
    ]);

    $coins = $query->fetch();
    if (!$coins) {
        echo json_encode([
            'error' => 'User not found'
        ]);
        exit;
    }

    $response = [
        'coins' => (int)$coins['coins']
    ];
    echo json_encode($response);
    exit;
}
function show_streak() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $query = $pdo->prepare("SELECT current_streak, last_streak_date FROM users WHERE user_id = :user_id");
    $query->execute([
        'user_id' => $userID
    ]);

    $streakInfo = $query->fetch();
    if (!$streakInfo) {
        echo json_encode([
            'error' => 'User not found'
        ]);
        exit;
    }

    //check if last_streak_day is:
    //  1.today/yesterday (last streak day is today/yesterday)
    //  2.broken -> if so, check if watering can available and use!
    $today = (new DateTime())->format('Y-m-d');
    $yesterday = (new DateTime())->modify('-1 day')->format('Y-m-d');
    if($streakInfo['last_streak_date'] != $today || $streakInfo['last_streak_date'] != $yesterday) {
        //check if user has available watering can
        $query = $pdo->prepare("SELECT purchase_id FROM purchases WHERE user_id = :user_id AND item_id = 4");
        $query->execute([
            'user_id' => $userID
        ]);
        $wateringCan = $query->fetchColumn();
        if($wateringCan) {
            $query = $pdo->prepare("DELETE FROM purchases WHERE purchase_id = :purchase_id");
            $query->execute([
                'purchase_id' => $wateringCan
            ]);
            $query = $pdo->prepare("
            UPDATE users 
            SET last_streak_date = :yesterday
            WHERE user_id = :user_id");
            $query->execute([
                'user_id' => $userID,
                'yesterday' => $yesterday
            ]);
            $response = [
                'streak' => $streakInfo['current_streak'],
                'consumed' => 1
            ];
            echo json_encode($response);
            exit;
        }
        else {
            $response = [
                'streak' => 0,
                'consumed' => 0
            ];
            echo json_encode($response);
            exit;
        }
    }

    $response = [
        'streak' => $streakInfo['current_streak'],
        'consumed' => 0
    ];
    echo json_encode($response);
    exit;
}



function show_shop() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    //determine how many items in shop
    $query = $pdo->prepare("
    SELECT item_id, item_name
    FROM shop_items
    ORDER BY item_id
");

    $query->execute();

    $items = $query->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $itemID = $item['item_id'];
        //return item name
        $query = $pdo->prepare("SELECT item_name FROM shop_items WHERE item_id = :item_id");
        $query->execute([
            'item_id' => $itemID
        ]);
        $item_name = $query->fetch();

        //check if user bought one already
        $query = $pdo->prepare("SELECT purchase_id FROM purchases WHERE user_id = :id AND item_id = :item_id");
        $query->execute([
            'id' => $userID,
            'item_id' => $itemID]);
        $purchaseID = $query->fetch();
        if(!$purchaseID) {
            $is_bought = false;
            $is_active = false;
        }
        else{
            $is_bought = true;
            //check if active
            $query = $pdo->prepare("SELECT is_active FROM tree_decorations WHERE user_id = :id AND purchase_id = :purchase_id");
            $query->execute([
                'id' => $userID,
                'purchase_id' => $purchaseID['purchase_id']]);
            $is_active = $query->fetchColumn();
        }

        $elements[] = [
            'item_id' => $itemID,
            'item_name' => $item_name['item_name'],
            'is_bought' => $is_bought,
            'is_active' => $is_active
        ];
    }
    $response = [
            'elements' => $elements
    ];
    echo json_encode($response);
    exit;
}
?>