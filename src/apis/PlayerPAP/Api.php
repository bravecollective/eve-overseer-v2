<?php

    namespace Ridley\Apis\PlayerPAP;

    use Ridley\Core\Exceptions\UserInputException;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $esiHandler;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                $this->databaseConnection
            );

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Check_For_PAP" and isset($_POST["ID"])) {

                    $this->checkTracking($_POST["ID"]);
                    
                }
                elseif ($_POST["Action"] == "Get_User_Data" and isset($_POST["Account_Type"]) and isset($_POST["Account_ID"])) {

                    $this->getUserData($_POST["Account_Type"], $_POST["Account_ID"]);

                }
                else {

                    throw new UserInputException(
                        inputs: ["Action", "Secondary Arguments"], 
                        expected_values: ["A valid action command", "The action's arguments"], 
                        hard_coded_inputs: true,
                        value_missing: true
                    );

                }

            }
            else {

                throw new UserInputException(
                    inputs: "Action", 
                    expected_values: "An action command", 
                    hard_coded_inputs: true,
                    value_missing: true
                );

            }

        }

        private function getUserData($accountType, $accountID) {
            
            $participationAccessController = new \Ridley\Objects\AccessControl\Participation($this->dependencies);
            $fleetAccessController = new \Ridley\Objects\AccessControl\Fleet($this->dependencies);

            if ($participationAccessController->checkForAccountAccess($accountType, $accountID)) {

                $userData = [
                    "Characters" => [],
                    "Fleets" => [],
                    "Link Fleets" => $fleetAccessController->checkForAuditAccessBypass()
                ];
                $idsToCheck = [];
                $idNames = [];

                // Pull Account Characters
                $charactersQuery = $this->databaseConnection->prepare("
                    SELECT characterid 
                    FROM userlinks 
                    WHERE accounttype = :accounttype AND accountid = :accountid
                ");
                $charactersQuery->bindParam(":accounttype", $accountType);
                $charactersQuery->bindParam(":accountid", $accountID);
                $charactersQuery->execute();
                $charactersToCheck = $charactersQuery->fetchAll(\PDO::FETCH_COLUMN, 0);

                // Get Character Affiliations
                $affiliationsCall = $this->esiHandler->call(endpoint: "/characters/affiliation/", characters: $charactersToCheck, retries: 1);

                if ($affiliationsCall["Success"]) {

                    foreach ($affiliationsCall["Data"] as $eachCharacter) {

                        $userData["Characters"][$eachCharacter["character_id"]] = [
                            "ID" => $eachCharacter["character_id"],
                            "Name" => null,
                            "Corporation ID" => $eachCharacter["corporation_id"],
                            "Corporation Name" => null,
                            "Alliance ID" => ($eachCharacter["alliance_id"] ?? null),
                            "Alliance Name" => ($eachCharacter["alliance_id"] ?? null),
                        ];

                        if (!in_array($eachCharacter["character_id"], $idsToCheck)) {
                            $idsToCheck[] = $eachCharacter["character_id"];
                        }
                        if (!in_array($eachCharacter["corporation_id"], $idsToCheck)) {
                            $idsToCheck[] = $eachCharacter["corporation_id"];
                        }
                        if (isset($eachCharacter["alliance_id"]) and !in_array($eachCharacter["alliance_id"], $idsToCheck)) {
                            $idsToCheck[] = $eachCharacter["alliance_id"];
                        }

                    }

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                    return;

                }

                // Get Names for all IDs
                $namesCall = $this->esiHandler->call(endpoint: "/universe/names/", ids: $idsToCheck, retries: 1);

                if ($namesCall["Success"]) {

                    foreach ($namesCall["Data"] as $eachName) {
                        $idNames[$eachName["id"]] = $eachName["name"];
                    }

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                    return;

                }

                // Populate Names
                foreach ($userData["Characters"] as $eachCharacter => &$eachCharacterData) {
                    $eachCharacterData["Name"] = $idNames[$eachCharacterData["ID"]];
                    $eachCharacterData["Corporation Name"] = $idNames[$eachCharacterData["Corporation ID"]];
                    if (!is_null($eachCharacterData["Alliance ID"])) {
                        $eachCharacterData["Alliance Name"] = $idNames[$eachCharacterData["Alliance ID"]];
                    }
                }

                // Pull Character Fleets
                $fleetsQuery = $this->databaseConnection->prepare("
                    SELECT 
                        fleets.id AS id,
                        fleets.name AS name, 
                        fleettypes.name AS type, 
                        FROM_UNIXTIME(fleets.starttime DIV 1000, GET_FORMAT(DATE, 'ISO')) AS date,
                        fleetmembers.characterid AS character_id,
                        SEC_TO_TIME(SUM(fleetmembers.endtime - fleetmembers.starttime) DIV 1000) AS duration
                    FROM userlinks 
                    LEFT JOIN fleetmembers ON fleetmembers.characterid = userlinks.characterid
                    LEFT JOIN fleets ON fleets.id = fleetmembers.fleetid
                    LEFT JOIN fleettypes ON fleettypes.id = fleets.type
                    WHERE accounttype = :accounttype AND accountid = :accountid AND fleets.endtime IS NOT NULL
                    GROUP BY fleetmembers.characterid, fleets.id
                    ORDER BY fleets.starttime DESC
                ");
                $fleetsQuery->bindParam(":accounttype", $accountType);
                $fleetsQuery->bindParam(":accountid", $accountID);
                $fleetsQuery->execute();

                while ($fleetData = $fleetsQuery->fetch(\PDO::FETCH_ASSOC)) {

                    $userData["Fleets"][$fleetData["id"]] = [
                        "ID" => $fleetData["id"],
                        "Name" => $fleetData["name"],
                        "Type" => $fleetData["type"],
                        "Date" => $fleetData["date"],
                        "Character" => $idNames[$fleetData["character_id"]],
                        "Duration" => $fleetData["duration"]
                    ];

                }

                echo json_encode($userData);

            }
            else {

                throw new UserInputException(
                    inputs: ["Account Type", "Account ID"], 
                    expected_values: ["A valid account type", "A valid account id that the user has access to"], 
                    hard_coded_inputs: true
                );

            }

        }

        private function checkTracking($corporationID) {

            $checkQuery = $this->databaseConnection->prepare("SELECT characterid, corporationid, allianceid, recheck FROM corptrackers WHERE corporationid=:corporationid");
            $checkQuery->bindParam(":corporationid", $corporationID);
            $checkQuery->execute();
            $queryResult = $checkQuery->fetch(\PDO::FETCH_ASSOC);

            if (!empty($queryResult)) {

                $corpTrackingHandler = new \Ridley\Objects\CorporationTracking\Handler($this->dependencies);

                if (
                    $queryResult["recheck"] > time() 
                    or $corpTrackingHandler->updateTracking($queryResult["characterid"], $queryResult["corporationid"], $queryResult["allianceid"])
                ) {

                    echo json_encode(["Status" => "Success"]);
                    return;

                }
                else {

                    $corpTrackingHandler->deleteTrackingData($queryResult["corporationid"]);

                }

            }

            echo json_encode(["Status" => "Not Found"]);
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

        }



    }

?>
