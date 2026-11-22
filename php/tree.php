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

//get userID !assumes in URL "id=?"
$userID = $_POST["user_id"] ?? "";

//send query: treeGrowth: 0-growth_stage
$query = $pdo->prepare("SELECT growth_stage FROM trees WHERE user_id = :id");
$query->execute(['id' => $userID]);
$treeGrowth = $query->fetch();

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
$imageName = "/asset/" . $treeGrowth['growth_stage'] . ".png";


$response = [
    'treepath' => $imageName,
    'decorations' => $decorationArray
];
echo json_encode($response);
exit;

?>