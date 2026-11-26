<?php

/**To call: use POST with functionName = (SHOW_HABITS,HABIT_DONE,HABIT_ADDED) and parameters needed:
 * SHOW_GOAL :
 *      Input parameters: "functionName", "user_id",
 *           $response =
 *               'error'|'goal' => error message or goal value
 *  STORE_GOAL :
 *       Input parameters: "functionName", "user_id", "new_goal"
 *            $response =
 *                'error' | 'status' = 1 => error message or nothing if succeeds
 *
 * HABIT_DONE: marks a habit as done
 *      Input parameters: "functionName", "user_id", "habit_id"
 *      Returns:
 *          $response =
 *              'need_to_update' => 0|1, change in coins, tree growth, streak, etc...
 *              'habit_count' => habits done today by user (useful debugging)
 *
 *  HABIT_ADDED:
 *       Input parameters: "functionName", "user_id", "habit_days", "habit_name", "habit_category"
 *          $response =
 *              'success' => 0|1
 *              'error'|'habit_id => "..."
 *
 * HABIT_COUNT:
 *      Input parameters: "functionName", "user_id", "date"
 *          $response =
 *              'count' =>
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
require_once __DIR__ . "/session.php";

if (isset($_POST['ticket'])) {
    $ticketUser = check_ticket($_POST['ticket']);
    if (!$ticketUser) {
        echo json_encode(['error' => "Invalid ticket"]);
        exit;
    }
    $_POST['user_id'] = $ticketUser;
}

//switch case functions: habit-done(habitID, userID). habit-added(habit_name, habit_days, user_ID, habit_category). show-habits(category, userID).
$method = strtoupper(trim($_POST['functionName'] ?? ''));

switch ($method) {
    case "SHOW_HABITS" :
        show_habits();
        break;
    case "SHOW_GOAL" :
        show_goal();
        break;
    case "STORE_GOAL" :
        store_goal();
        break;
    case "HABIT_DONE" :
        habit_done();
        break;
    case "HABIT_ADDED" :
        habit_added();
        break;
    case "HABIT_COUNT":
        habit_count();
        break;
    default :
        $response = [
            'error' => "Invalid request",
        ];
        echo json_encode($response);
        exit;
}

function show_goal() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";

    if($userID == "") {
        $response = [
            'error' => "Invalid user_id!",
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("SELECT daily_goal FROM users WHERE user_id = :user_id");
    $query->execute([
        'user_id' => $userID
    ]);

    $goal = $query->fetch();
    if (!$goal) {
        echo json_encode([
            'error' => 'User not found'
        ]);
        exit;
    }

    $response = [
        'goal' => (int)$goal['daily_goal']
    ];
    echo json_encode($response);
    exit;
}

function store_goal() : void {
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $newGoal = $_POST["new_goal"] ?? "";

    if($userID == "" || $newGoal == "") {
        $response = [
            'error' => "Invalid user_id or goal value!",
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("
                UPDATE users 
                SET daily_goal = :goal 
                WHERE user_id = :id
            ");

    $query->execute([
        'goal' => $newGoal,
        'id' => $userID,
    ]);
    $response = [
        'status' => 1
    ];
    echo json_encode($response);
    exit;
}


function habit_count()
{
    //count habits done today, check if goal reached
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $date = $_POST["date"] ?? "";

    if($userID == "" || $date == "") {
        $response = [
            'error' => "Invalid user_id or category!"
        ];
        echo json_encode($response);
        exit;
    }

    $query = $pdo->prepare("
            SELECT COUNT(*) 
                FROM habits 
            WHERE user_id = :id 
                AND is_active = 1 
                AND last_completion_date = :today
    ");

    $query->execute([
        'today' => $date,
        'id'    => $userID
    ]);

    $habitCount = (int)$query->fetchColumn();

    $response = [
        'count' => $habitCount
    ];
    echo json_encode($response);
    exit;

}

function show_habits() : void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $categoryShown = $_POST["habit_category"] ?? "";
    if($userID == "" || $categoryShown == "") {
        $response = [
            'error' => "Invalid user_id or category!"
        ];
        echo json_encode($response);
        exit;
    }

    $curDayOfWeek = date('w'); //stores current day of the week: sunday = 0, monday = 1...

    //send query: find all habits in category belonging to user that are active
    if ($categoryShown === '' || $categoryShown === 'All') {
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id AND is_active = 1");
        $query->execute(['id' => $userID]);
    } else {
        $query = $pdo->prepare("SELECT * FROM habits WHERE user_id = :id AND category = :category AND is_active = 1");
        $query->execute(['id' => $userID, 'category' => $categoryShown]);
    }

    $habits = $query->fetchAll(PDO::FETCH_ASSOC);
    $today = (new DateTime('today'))->format('Y-m-d');
    $formattedHabits = [];

    foreach ($habits as $habit) {
        //day here is 0100000 for a habit occurring only on Mondays
        $days = $habit['days'];

        if (strlen($days) !== 7) {
            continue;
        }

        if ($days[$curDayOfWeek] !== '1') {
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

    if($userID == "" || $habitID == "") {
        $response = [
            'error' => "Invalid user_id or habit_id!"
        ];
        echo json_encode($response);
        exit;
    }

    $needsUpdate = 0;

    //obtain info for sql
    //: habit_id, user_id, habit_name, category, is_active, last_completion_date, created_at
    $query = $pdo->prepare("SELECT * FROM habits WHERE habit_id = :id");
    $query->execute(['id' => $habitID]);
    $habit = $query->fetch();

    //check if done already
    $today = (new DateTime('today'))->format('Y-m-d');
    if ($habit['last_completion_date'] === $today) { //done today already, ignore, returns false if $habit[..] is null
        echo json_encode([
            'need_to_update' => 0
        ]);
        exit;
    }

    //set date completed = 'today'
    $query = $pdo->prepare("
    UPDATE habits
        SET last_completion_date = :today
        WHERE habit_id = :id
        AND user_id = :user_id
    ");

    $query->execute([
        'today' => $today,
        'id' => $habitID,
        'user_id' => $userID
    ]);

    //find user data : daily_goal, coins, current_streak, last_streak_date
    $query = $pdo->prepare("SELECT daily_goal, coins, current_streak, last_streak_date FROM users WHERE user_id = :id");
    $query->execute(['id' => $userID]);
    $userData = $query->fetch();

    //count habits done today, check if goal reached
    $query = $pdo->prepare("
        SELECT COUNT(*) 
            FROM habits 
        WHERE user_id = :id 
            AND is_active = 1 
            AND last_completion_date = :today
");

    $query->execute([
        'today' => $today,
        'id'    => $userID
    ]);

    $habitCount = (int)$query->fetchColumn();

    if ($habitCount >= (int)$userData['daily_goal'] && $userData['last_streak_date'] !== $today) { //goal reached: add/reset streak, coin, tree + tree stage check + TODO: daily logs??
        //daily goal reached: needs update
        $needsUpdate = 1;
        //check streak is continuous
        $yesterday = (new DateTime('yesterday'))->format('Y-m-d');
        if ($userData['last_streak_date'] === $yesterday) {
            $newStreak = (int)$userData['current_streak'] + 1;
            //calculate coins. 1.2 multiplier per week of streak
            $weeksCompleted = intdiv($newStreak, 7);
            $coinsEarned = 5 + ($weeksCompleted * 1.2);
            $newCoins = $userData['coins'] + (int)$coinsEarned;

            $query = $pdo->prepare("
                UPDATE users 
                SET current_streak = :streak, 
                    coins = :coins, 
                    last_streak_date = :today 
                WHERE user_id = :id
            ");

            $query->execute([
                'streak' => $newStreak,
                'coins' => $newCoins,
                'today' => $today,
                'id' => $userID, //
            ]);
        } else { //break streak and add 5 coins.
            $newCoins = $userData['coins'] + 5;
            $query = $pdo->prepare("
                UPDATE users 
                SET current_streak = 1, 
                    last_streak_date = :today,
                    coins = :coins
                WHERE user_id = :id
            ");
            $query->execute([
                'today' => $today,
                'id' => $userID,
                'coins' => $newCoins]);

        }

        //tree
        //stores points needed to "grow" from stage. ex: [1,4,9,16,23,30]=>(1,3,5,7,7,7 )means 1 point needed to grow from stage 1->2, 3+1 points needed for 2->3...
        $stage_to_points = [1, 4, 9, 16, 23, 30];

        $query = $pdo->prepare("SELECT growth_points, growth_stage, last_growth_date FROM trees WHERE user_id = :id");
        $query->execute(['id' => $userID]);
        $treeData = $query->fetch(PDO::FETCH_ASSOC);

        $currentStage = (int)$treeData['growth_stage'];
        $nextPoints = (int)$treeData['growth_points'] + 1;
        $pointsNeeded = $stage_to_points[$currentStage - 1] ?? null; //if reached max -> nothing

        if ($pointsNeeded !== null && $nextPoints >= $pointsNeeded) {
            $query = $pdo->prepare("
                UPDATE trees 
                SET growth_points = :nextPoints, 
                    growth_stage = :nextStage, 
                    last_growth_date = :today 
                WHERE user_id = :id
            ");
            $query->execute([
                'nextPoints' => $nextPoints,
                'nextStage' => ((int)$currentStage+1),
                'today' => $today,
                'id' => $userID,
            ]);
        } else {
            $query = $pdo->prepare("
                UPDATE trees 
                SET growth_points = :nextPoints
                WHERE user_id = :id
            ");
            $query->execute(['id' => $userID, 'nextPoints' => $nextPoints]);
        }
    }

    $response = [
        'need_to_update' => $needsUpdate,
        'habit_count' => $habitCount
    ];
    echo json_encode($response);
    exit;
}

function habit_added(): void
{
    global $pdo;
    $userID = $_POST["user_id"] ?? "";
    $habitDays = $_POST["habit_days"] ?? "";
    $habitName = trim($_POST["habit_name"] ?? "");
    $habitCategory = $_POST["habit_category"] ?? "";

    $today = date('Y-m-d');

    if (
        $userID === ""
        || $habitName === ""
        || $habitCategory === ""
        || $habitDays === ""
    ) {

        echo json_encode([
            'status' => 0,
            'error' => 'Missing required parameters'
        ]);
        exit;
    }

    if (is_array($habitDays)) {
        $habitDays = json_encode($habitDays);
    }

    try {

        $query = $pdo->prepare("
            INSERT INTO habits (
                user_id,
                habit_name,
                category,
                is_active,
                last_completion_date,
                days,
                created_at
            )
            VALUES (
                :id,
                :habit_name,
                :category,
                1,
                NULL,
                :days,
                :today
            )
        ");

        $query->execute([
            'id' => $userID,
            'habit_name' => $habitName,
            'category' => $habitCategory,
            'days' => $habitDays,
            'today' => $today
        ]);

        echo json_encode([
            'status' => 1,
            'habit_id' => $pdo->lastInsertId()
        ]);

        exit;

    } catch (Throwable $e) {
        echo json_encode([
            'status' => 0,
            'error' => 'Could not add habit'
        ]);
        exit;
    }
}
?>