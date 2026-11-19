<?php
//call on load using POST, echoes full tree's image: (base tree) + (decorations). Here assuming front end takes care of background/weather API call.

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
$query = $pdo->prepare("SELECT decoration_id FROM tree_decorations WHERE user_id = :id");
$query->execute(['id' => $userID]);
$decorationIDs = $query->fetchAll();


//returned html: 
echo '<html>';
$imageName = "treestage" . $treeGrowth . ".png";
echo '<img src="' . $imageName . '">';

foreach($decorationIDs as $decorationID) {
    //find image_path from decorationID. Assumes decoration-id = item-id.
    $query = $pdo->prepare("SELECT image_path FROM shop_items WHERE item_id = :id");
    $query->execute(['id' => $decorationID]);
    $imagePath = $query->fetch();
    echo '<img src="' . $imagePath . '">';
}

echo '</html>';
?>