<?php
//To call: use POST with functionName = (SHOW_HABITS,HABIT_DONE,HABIT_ADDED) and parameters needed:
//HABIT_DONE: "functionName", "user_id", "habit_id",
//HABIT_ADDED: "functionName", "user_id", "habit_days", "habit_name", "habit_category"
//SHOW_HABITS: "functionName", "user_id", "habit_category"

//initialise db
require_once "db.php";

//switch case functions: habit-done(habitID, userID). habit-added(habit_name, habit_days, user_ID, habit_category). show-habits(category, userID).
$method = '0';
if (isset($_POST['functionName'])) {
    $method = strtoupper(trim($_POST['functionName']));
}
switch($method) {
    case "SHOW_HABITS" :
        show_habits();
        break;
    case "HABIT_DONE" :
        habit_done();
        break;
    case "HABIT_ADDED" :
        habit_added();
        break;
    default :
        echo "Error: No such method found! habits.php";
}


function show_habits(): void {
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $categoryShown = $_POST["habit_category"] ?? "";
    $curDayOfWeek = date('w'); //stores current day of the week: sunday = 0, monday = 1...
    
    //send query: find all habits in category belonging to user
    //: habit_id, user_id, habit_name, category, is_active, last_completion_date, created_at
    if($categoryShown == "" || "All"){
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id AND days[$curDayOfWeek] == 1");
        $query->execute(['id' => $userID]);
    }
    else {
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id AND category = :category AND days[$curDayOfWeek] == 1");
        $query->execute(['id' => $userID, 'category' => $categoryShown]);
    }
    $habits = $query->fetchAll();
    
    //returned html:
    echo '<ul class="checklist">';

    foreach($habits as $habit) {
        echo '<li>';
            $today = (new DateTime('today'))->format('Y-m-d');
            if($habit[5]!== $today){ //not yet done
                echo '<label><input type="checkbox" value="' . $habit[0] . '">' . $habit[2] . '</label>';
            }  
            else { //done => checked
                echo '<label><input type="checkbox" value="' . $habit[0] . '" checked >' . $habit[2] . '</label>';
            }  
        echo '</li>';    
    }
    echo  '</ul>';




}

function habit_done() : void {
    global $pdo;
    //get habit, check if done today (if so: break), else check if daily_goal met -> add streak + add coins + grow tree (new stage reached?)
    //get habit

    //get POST vars
    $userID = $_POST["user_id"] ?? "";
    $habitID = $_POST["habit_id"] ?? "";

    //obtain info for sql
    //: habit_id, user_id, habit_name, category, is_active, last_completion_date, created_at
    $query = $pdo->prepare("SELECT * FROM habits WHERE habit_id = :id");
    $query->execute(['id' => $habitID]);
    $habit = $query->fetchAll();

    //check if done already
    $today = (new DateTime('today'))->format('Y-m-d');
    if($habit[5] === $today) { //done today already, ignore
        return;
    }

    //set date completed = 'today'
    $query = $pdo->prepare("UPDATE habits SET last_completion_date = $today WHERE habit_id = :id");
    $query->execute(['id' => $habitID]);

    //find user data : daily_goal, coins, current_streak, last_streak_day
    $query = $pdo->prepare("SELECT daily_goal, coins, current_streak, last_streak_day FROM users WHERE user_id = :id");
    $query->execute(['id' => $userID]);
    $userData = $query->fetch();
    
    //count habits done today, check if goal reached
    $query = $pdo->prepare("SELECT SUM(CASE WHEN last_completion_date === $today THEN 1 ELSE 0 END) FROM habits");
    $query->execute();
    $habitCount = $query->fetch();

    if($habitCount == $userData[0]) { //goal reached: add/reset streak, coin, tree + tree stage check + TODO: daily logs??
        //check streak is continuous
        $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
        if($userData[3] === $yesterday) { //good, streak is continuous
            $query = $pdo->prepare("UPDATE users SET 
            current_streak = $userData[2] + 1, 
            coins = $userData[1] + 5 * intdiv($userData[2], 7)*1.2,
            last_streak_day = $today
            WHERE habit_id = :id");
            $query->execute(['id' => $habitID]);
        }
        else { //streak broken
            $query = $pdo->prepare("UPDATE users SET 
            current_streak = 1, 
            last_streak_day = $today
             WHERE user_id = :id");
            $query->execute(['id' => $userID]);
        }

        //tree
        //stores points needed to "grow" from stage. ex: [1,4,9,16,23,30]=>(1,3,5,7,7,7 )means 1 point needed to grow from stage 1->2, 3+1 points needed for 2->3...
        $stage_to_points = [
            [1,4,9,16,23,30],
        ];

        //send querys: tree_result: 0-growth_points, 1-growth_stage, 2-last_growth. habit_result: 
        $query = $pdo->prepare("SELECT growth_points, growth_stage, last_growth_date FROM trees WHERE user_id = :id");
        $query->execute(['id' => $userID]);
        $treeData = $query->fetch();

        //check if needed to update ex: cur = 2, points = 8 means +1 would grow
        if($treeData[0] + 1 >= $stage_to_points[$treeData[1]-1]){ //check if tree changes stage
            $query = $pdo->prepare("UPDATE trees SET 
            growth_points = $treeData[0] + 1, 
            growth_stage = $treeData[1] + 1,
            last_growth_day = $today
             WHERE user_id = :id");
            $query->execute(['id' => $userID]);
        }
        else {
            $query = $pdo->prepare("UPDATE trees SET 
            growth_points = $treeData[0] + 1
            WHERE user_id = :id");
            $query->execute(['id' => $userID]);
        }
        }
}

function habit_added() : void {
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $habit_days = $_POST["habit_days"] ?? "";
    $habit_name = $_POST["habit_name"] ?? "";
    $habit_category = $_POST["habit_category"] ?? "";
    $today = (new DateTime('today'))->format('Y-m-d');

    //insert habit in db, habit_id auto increments, [...,user_id,habit_name,category,is_active,last_completion_date,days,created_at]
    $query = $pdo->prepare(
        "INSERT INTO habits (user_id, habit_name, category, is_active, last_completion_date, days, created_at)
        VALUES (:id, :habit_name, :category, 1, NULL, :days, $today);"
    );
    $query->execute([
        'id' => $userID,
        'habit_name'=> $habit_name,
        'category' => $habit_category,
        'days' => $habit_days
        ]);
}
?>