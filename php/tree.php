<?php
/**
 * Input parameters: user_id
 * Returns:
 * $response =
 *      'treepath' => image path for tree from asset
 *      'decorations' => array containing all image paths for decorations
 */

//initialise db
require_once "db.php";
global $pdo;

$userID = $_POST["user_id"] ?? "";

if($userID == "") {
    $response = [
        'error' => "Invalid user ID",
    ];
    echo json_encode($response);
    exit;
}

$query = $pdo->prepare("SELECT growth_stage FROM trees WHERE user_id = :id");
$query->execute(['id' => $userID]);
$treeGrowth = $query->fetchColumn();

//if user doesn't have a tree, initialise tree
if(!$treeGrowth) {
    $query = $pdo->prepare("INSERT INTO trees (user_id, growth_points, growth_stage, last_growth_date) VALUES (:user_id,0,1,:today)");
    $query->execute([
        'user_id' => $userID,
        'today' => date("Y-m-d")
    ]);
    $treeGrowth = 1;
}

//send query find all active decorations_ids
$query = $pdo->prepare(
    "SELECT si.image_path
FROM tree_decorations td
JOIN purchases p ON td.purchase_id = p.purchase_id
JOIN shop_items si ON p.item_id = si.item_id
WHERE td.user_id = :id
  AND td.is_active = 1
  AND si.item_type = 'Decoration'"
);

$query->execute(['id' => $userID]);
$decorationArray = $query->fetchAll(PDO::FETCH_COLUMN);


//return:
$imageName = "/home/cs307-user/app/assets/treestage" . $treeGrowth . ".png";

$response = [
    'treepath' => $imageName,
    'decorations' => $decorationArray
];
echo json_encode($response);
exit;

?>