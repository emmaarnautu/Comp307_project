<?php

/**To call: use POST with functionName = (SHOW_HABITS,HABIT_DONE,HABIT_ADDED) and parameters needed:
 * HABIT_DONE: marks a habit as done
 *      Input parameters: "functionName", "user_id", "habit_id"
 *      Returns:
 *          $response =
 *              'need_to_update' => 0|1, change in coins, tree growth, streak, etc...
 *
 *  HABIT_ADDED:
 *       Input parameters: "functionName", "user_id", "habit_days", "habit_name", "habit_category"
 *
 *  SHOW_HABITS:
 *       Input parameters: "functionName", "user_id", "habit_category"
 *       Returns:
 *           $response =
 *               'habits' =>
 *                     'habit_id' =>
 *                     'habit_name' =>
 *                     'is_done' => 0|1
 */

//initialise db
require_once "db.php";

//switch case functions: habit-done(habitID, userID). habit-added(habit_name, habit_days, user_ID, habit_category). show-habits(category, userID).
$method = '0';
if (isset($_POST['functionName'])) {
    $method = strtoupper(trim($_POST['functionName']));
}
switch ($method) {
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


function show_habits() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $categoryShown = $_POST["habit_category"] ?? "";
    $curDayOfWeek = date('w'); //stores current day of the week: sunday = 0, monday = 1...

    //send query: find all habits in category belonging to user
    // habit_id, user_id, habit_name, category, is_active, last_completion_date, created_at
    if ($categoryShown === '' || $categoryShown === 'All') {
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id");
        $query->execute(['id' => $userID]);
    } else {
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id AND category = :category");
        $query->execute(['id' => $userID, 'category' => $categoryShown]);
    }

    $habits = $query->fetchAll(PDO::FETCH_ASSOC);
    $today = (new DateTime('today'))->format('Y-m-d');
    $formattedHabits = [];

    foreach ($habits as $habit) {
        //day here is 0100000 for a habit occurring only on Mondays
        $days = is_string($habit['days']) ? json_decode($habit['days'], true) ?? $habit['days'] : $habit['days'];
        if (($days[$curDayOfWeek] ?? 0) != 1) {
            continue;
        }

        $formattedHabits[] = [
            'habit_id'   => $habit['habit_id'],
            'habit_name' => $habit['habit_name'],
            'is_done'    => ($habit['last_completion_date'] === $today) ? 1 : 0,
        ];
    }
    //return json
    $response = [
        'habits' => $formattedHabits
    ];
    echo json_encode($response);
    exit;
}

function habit_done(): void
{
    global $pdo;
    //get habit, check if done today (if so: break), else check if daily_goal met -> add streak + add coins + grow tree (new stage reached?

    //get POST vars + ensure habit belongs to user
    $userID = $_POST["user_id"] ?? "";
    $habitID = $_POST["habit_id"] ?? "";
    $needsUpdate = 0;

    //obtain info for sql
    //: habit_id, user_id, habit_name, category, is_active, last_completion_date, created_at
    $query = $pdo->prepare("SELECT * FROM habits WHERE habit_id = :id");
    $query->execute(['id' => $habitID]);
    $habit = $query->fetchAll();

    //check if done already
    $today = (new DateTime('today'))->format('Y-m-d');
    if ($habit['last_completion_date'] === $today) { //done today already, ignore
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

    if ($habitCount == $userData['daily_goal']) { //goal reached: add/reset streak, coin, tree + tree stage check + TODO: daily logs??
        //daily goal reached: needs update
        $needsUpdate = 1;
        //check streak is continuous
        $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
        if ($userData['last_streak_day'] === $yesterday) {
            $newStreak = $userData['current_streak'] + 1;
            //calculate coins. 1.2 multiplier per week of streak
            $weeksCompleted = intdiv($newStreak, 7);
            $coinsEarned = 5 + ($weeksCompleted * 1.2);
            $newCoins = $userData['coins'] + $coinsEarned;

            $query = $pdo->prepare("
                UPDATE users 
                SET current_streak = :streak, 
                    coins = :coins, 
                    last_streak_day = :today 
                WHERE user_id = :id
            ");

            $query->execute([
                'streak' => $newStreak,
                'coins' => $newCoins,
                'today' => $today,
                'id' => $userID, //
            ]);
        } else { //streak broken, example, missed yesterdays goal
            $query = $pdo->prepare("
                UPDATE users 
                SET current_streak = 1, 
                    last_streak_day = :today
                WHERE user_id = :id
            ");
            $query->execute([
                'today' => $today,
                'id' => $userID]);
        }

        //tree
        //stores points needed to "grow" from stage. ex: [1,4,9,16,23,30]=>(1,3,5,7,7,7 )means 1 point needed to grow from stage 1->2, 3+1 points needed for 2->3...
        $stage_to_points = [
            [1, 4, 9, 16, 23, 30],
        ];

        $query = $pdo->prepare("SELECT growth_points, growth_stage, last_growth_date FROM trees WHERE user_id = :id");
        $query->execute(['id' => $userID]);
        $treeData = $query->fetch(PDO::FETCH_ASSOC);

        $currentStage = (int)$treeData['growth_stage'];
        $nextPoints = (int)$treeData['growth_points'] + 1;
        $pointsNeeded = $stage_to_points[$currentStage - 1] ?? null; //if reached max -> nothing

        if ($pointsNeeded !== null && $nextPoints >= $pointsNeeded) {
            $query = $pdo->prepare("
                UPDATE trees 
                SET growth_points = growth_points + 1, 
                    growth_stage = growth_stage + 1, 
                    last_growth_date = :today 
                WHERE user_id = :id
            ");
            $query->execute([
                'today' => $today,
                'id' => $userID,
            ]);
        } else {
            $query = $pdo->prepare("
                UPDATE trees 
                SET growth_points = growth_points + 1 
                WHERE user_id = :id
            ");
            $query->execute(['id' => $userID]);
        }
    }
    $response = [
        'need_to_update' => $needsUpdate
    ];
    echo json_encode($response);
    exit;
}

function habit_added(): void
{
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
        'habit_name' => $habit_name,
        'category' => $habit_category,
        'days' => $habit_days
    ]);
    exit;
}

?>