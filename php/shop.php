<?php
/**To call: use POST with functionName = ... and parameters needed:
 *  BUY:
 *      Input parameters: "user_id", "item_id"
 *      Returns:
 *          $response =
 *              'status' => 0|1, failed or succeeded
 *
 *  TOGGLE:
 *       Input parameters: "user_id", "item_id"
 *       Returns:
 *           $response =
 *               'status' => 0|1, failed or succeeded
 *
 *  SHOW_COINS:
 *       Input parameters: "user_id"
 *       Returns:
 *           $response =
 *               'coins' => number of coins user has
 *
 *  SHOW_SHOP:
 *       Input parameters: "user_id"
 *       Returns:
 *           $response =
 *               'element' =>
 *                     'item_id' =>
 *                     'item_name' =>
 *                     'is_bought' => 0|1
 *                     'is_active' => 0|1
 */

//initialise db
require_once "db.php";

//switch case functions: habit-done(habitID, userID). habit-added(habit_name, habit_days, user_ID, habit_category). show-habits(category, userID).
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
    case "SHOW_SHOP" :
        show_shop();
        break;
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
            'status' => 0
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

    $query = $pdo->prepare("INSERT INTO tree_decorations (user_id, purchase_id, is_active, position) VALUES (:user_id, :purchase_id, 0, 'top')");
    $query->execute([
        'user_id' => $userID,
        'purchase_id' => $purchaseID['purchase_id']]);

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
    $purchaseID = $query->fetch();
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
    $response = [
        'coins' => $coins['coins']
    ];
    echo json_encode($response);
    exit;
}

function show_shop() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
}

?>