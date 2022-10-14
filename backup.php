<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/sqlite_reserved_column_names.php";
require_once __DIR__ . "/prosonata.php";

$api = new ProSonata(PROSONATA_WORKSPACE_URL, PROSONATA_APP_ID, PROSONATA_API_KEY);

$endpoints = getEndpoints();
$filename = ARCHIVE_FOLDER . date("Y-m-d-H-i") . "-archive.sql";

// Check latest archive for endpoints that haven't been
// fully copied yet
$archives = [];
foreach (glob(ARCHIVE_FOLDER . "*-archive.sql") as $existingArchive) {
    $archives[] = $existingArchive;
}
rsort($archives);

if(count($archives) > 0) {
    $lastArchiveEndpoints = getEndpointList($archives[0]);
    if(count(array_keys($lastArchiveEndpoints)) > 0) {
        echo "Resuming backup..." . PHP_EOL;
        $filename = $archives[0];
        $endpoints = $lastArchiveEndpoints;
    }
}

if(!file_exists($filename))
{
    $endpoints = getEndpointList($filename);
}


//Backup process
$database = new PDO("sqlite:" . $filename);

$rateLimitExceeded = false;
$timeLimitExceeded = false;
$started = time();

foreach($endpoints as $endpoint => $lastPage)
{
    $totalAmount = 0;
    $perPage = 100;
    $totalPages = $lastPage + 1;

    $rowCount = 0;

    echo "Loading data " . $endpoint . "..." . PHP_EOL;

    $isFirstRun = true;
    $tableCreated = false;
    for($currentPage = $lastPage + 1; $currentPage <= $totalPages; $currentPage++)
    {
        if($started + 60 * 10 <= time())
        {
            $timeLimitExceeded = true;
            break;
        }

        echo "- Loading page " . $currentPage . "..." . PHP_EOL;

        $request = $api->request($endpoint, ProSonata::GET, [
            "search" => [
                "page" => $currentPage,
                "perPage" => $perPage,
            ],
        ]);
        if(!$request)
        {
            echo "FAILED for " . $endpoint . " (page " . $currentPage . ")" . PHP_EOL;
            continue;
        }
        if($request->status === 429)
        {
            $rateLimitExceeded = true;
            break;
        }
        $result = json_decode($request->body, true);
        if(!$result)
        {
            echo "Parsing FAILED for " . $endpoint . " (page " . $currentPage . ")" . PHP_EOL;
            continue;
        }

        if($isFirstRun)
        {
            $totalAmount = $result["meta"]["totalCount"];
            $perPage = $result["meta"]["perPage"];
            $totalPages = ceil($totalAmount / $perPage);
        }
        $isFirstRun = false;

        $rows = $result["data"];

        if(count($rows) > 0)
        {
            $columns = [];
            $values = [];
            foreach($rows[0] as $key => $value)
            {
                if(in_array(strtoupper($key), SQLITE_COLUMN_BLACKLIST)) $key = "ps_" . $key;
                $columns[] = $key . " TEXT";
                $values[] = "?";
            }
            $values = implode(", ", $values);

            if(!$tableCreated)
            {
                $creation = $database->prepare("CREATE TABLE IF NOT EXISTS " . $endpoint . " (" . implode(", ", $columns) . ")");
                $creation->execute();
                $tableCreated = true;
            }

            $insertion = $database->prepare("INSERT INTO " . $endpoint . " VALUES (" . $values . ")");
            foreach($rows as $row)
            {
                $rowCount++;
                $insertion->execute(array_values($row));
            }
        }

        $statusUpdate = $database->prepare("UPDATE backupinfo SET pageNum = ? WHERE endpoint = ?");
        $statusUpdate->execute([$currentPage, $endpoint]);
    }

    echo "Added " . $rowCount . " rows" . PHP_EOL;

    if($rateLimitExceeded)
    {
        echo "+++ RATE LIMIT EXCEEDED +++" . PHP_EOL;
        exit();
    }

    if($timeLimitExceeded)
    {
        echo "+++ TIME LIMIT EXCEEDED +++" . PHP_EOL;
        exit();
    }

    $statusUpdate = $database->prepare("UPDATE backupinfo SET complete = ? WHERE endpoint = ?");
    $statusUpdate->execute([1, $endpoint]);

    echo "Archived " . $endpoint . PHP_EOL;
}

$database = null;

echo "Backup complete." . PHP_EOL;

function getEndpointList($filename)
{
    $endpoints = getEndpoints();
    $database = new PDO("sqlite:" . $filename);
    $database->query("CREATE TABLE IF NOT EXISTS backupinfo (endpoint VARCHAR(255), pageNum INT NOT NULL, complete INT NOT NULL)");
    foreach($endpoints as $endpoint => $pageNum)
    {
        $selection = $database->prepare("SELECT pageNum, complete FROM backupinfo WHERE endpoint = ? LIMIT 1");
        $selection->execute([$endpoint]);
        if($row = $selection->fetch())
        {
            if($row["complete"])
            {
                unset($endpoints[$endpoint]);
            }
            else
            {
                $endpoints[$endpoint] = intval($row["pageNum"]);
            }
        }
        else
        {
            $insertion = $database->prepare("INSERT INTO backupinfo (endpoint, pageNum, complete) VALUES (?, ?, ?)");
            $insertion->execute([$endpoint, $pageNum, 0]);
        }
    }
    $database = null;
    return $endpoints;
}

function getEndpoints()
{
    return [
        "addresses" => 0,
        "contacts" => 0,
        "customers" => 0,
        "externalcosts" => 0,
        "projects" => 0,
        "projecttasks" => 0,
        "projecttimes" => 0,
        "projecttimecategories" => 0,
        "users" => 0,
        "workingtimes" => 0,
    ];
}
