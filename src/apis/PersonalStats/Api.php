<?php

    namespace Ridley\Apis\PersonalStats;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $characterData;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->characterData = $this->dependencies->get("Character Stats");

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Get_Ship_Classes") {

                    $this->getClassBreakdown();
                    
                }
                elseif ($_POST["Action"] == "Get_Ship_Types") {

                    $this->getShipBreakdown();
                    
                }
                elseif ($_POST["Action"] == "Get_Timezones") {

                    $this->getTimezoneBreakdown();
                    
                }
                elseif ($_POST["Action"] == "Get_Fleet_Types") {

                    $this->getTypeBreakdown();
                    
                }
                elseif ($_POST["Action"] == "Get_Fleet_Roles") {

                    $this->getRoleBreakdown();
                    
                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("No valid combination of action and required secondary arguments was received.", 10002);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Request is missing the action argument.", 10001);

            }

        }

        private function getTypeBreakdown() {

            $returnData = [
                "Counts" => [
                    "Labels" => [],
                    "Data" => []
                ],
                "Times" => [
                    "Labels" => [],
                    "Data" => []
                ]
            ];

            $filterRequest = $this->getFilterRequest("fleetmembers.endtime IS NOT NULL");

            $typeBreakdownQuery = $this->databaseConnection->prepare("
                SELECT 
                    fleettypes.id AS id, 
                    fleettypes.name AS name, 
                    COUNT(DISTINCT fleets.id) AS number_attended, 
                    (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime))/1000/3600 AS hours_attended
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                LEFT JOIN fleettypes ON fleettypes.id = fleets.type
                " . $filterRequest["Request"] . "
                GROUP BY fleettypes.id
                ORDER BY fleettypes.name
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $typeBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $typeBreakdownQuery->execute();
            while ($incomingTypes = $typeBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                $returnData["Counts"]["Labels"][] = htmlspecialchars($incomingTypes["name"]);
                $returnData["Counts"]["Data"][] = $incomingTypes["number_attended"];
                $returnData["Times"]["Labels"][] = htmlspecialchars($incomingTypes["name"]);
                $returnData["Times"]["Data"][] = $incomingTypes["hours_attended"];

            }
            
            if (empty($returnData["Counts"]["Labels"])) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            }

            echo json_encode($returnData);

        }

        private function getRoleBreakdown() {

            $returnData = [
                "Counts" => [
                    "Labels" => [],
                    "Data" => []
                ],
                "Times" => [
                    "Labels" => [],
                    "Data" => []
                ]
            ];

            $filterRequest = $this->getFilterRequest("fleetmembers.endtime IS NOT NULL AND fleetmembers.role != 'squad_member'");

            $roleBreakdownQuery = $this->databaseConnection->prepare("
                SELECT 
                    fleetmembers.role AS role, 
                    COUNT(DISTINCT fleets.id) AS number_attended, 
                    (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime))/1000/3600 AS hours_attended
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY fleetmembers.role
                ORDER BY FIELD('fleet_commander', 'wing_commander', 'squad_commander')
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $roleBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $roleBreakdownQuery->execute();
            while ($incomingRoles = $roleBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                $returnData["Counts"]["Labels"][] = htmlspecialchars(ucwords(str_replace("_", " ", $incomingRoles["role"])));
                $returnData["Counts"]["Data"][] = $incomingRoles["number_attended"];
                $returnData["Times"]["Labels"][] = htmlspecialchars(ucwords(str_replace("_", " ", $incomingRoles["role"])));
                $returnData["Times"]["Data"][] = $incomingRoles["hours_attended"];

            }

            if (empty($returnData["Counts"]["Labels"])) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            }
            
            echo json_encode($returnData);

        }

        private function getTimezoneBreakdown() {

            $returnData = [
                "Counts" => [
                    "Labels" => [],
                    "Data" => []
                ],
                "Times" => [
                    "Labels" => [],
                    "Data" => []
                ]
            ];

            $filterRequest = $this->getFilterRequest("fleetmembers.endtime IS NOT NULL");

            //This might need to be optimized
            $timezoneBreakdownQuery = $this->databaseConnection->prepare("
                SELECT 
                    CASE 
                        WHEN CAST(DATE_FORMAT(FROM_UNIXTIME(fleetmembers.starttime/1000), '%k') AS UNSIGNED) BETWEEN 0 AND 4 
                            OR CAST(DATE_FORMAT(FROM_UNIXTIME(fleetmembers.starttime/1000), '%k') AS UNSIGNED) BETWEEN 21 AND 23 THEN 'USTZ'
                        WHEN CAST(DATE_FORMAT(FROM_UNIXTIME(fleetmembers.starttime/1000), '%k') AS UNSIGNED) BETWEEN 5 AND 12 THEN 'AUTZ'
                        WHEN CAST(DATE_FORMAT(FROM_UNIXTIME(fleetmembers.starttime/1000), '%k') AS UNSIGNED) BETWEEN 12 AND 22 THEN 'EUTZ'
                        ELSE 'Unknown'
                    END
                    AS timezone,
                    COUNT(DISTINCT fleets.id) AS number_attended, 
                    (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime))/1000/3600 AS hours_attended
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY timezone
                ORDER BY FIELD('EUTZ', 'USTZ', 'AUTZ')
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $timezoneBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $timezoneBreakdownQuery->execute();
            while ($incomingTimezones = $timezoneBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                $returnData["Counts"]["Labels"][] = htmlspecialchars($incomingTimezones["timezone"]);
                $returnData["Counts"]["Data"][] = $incomingTimezones["number_attended"];
                $returnData["Times"]["Labels"][] = htmlspecialchars($incomingTimezones["timezone"]);
                $returnData["Times"]["Data"][] = $incomingTimezones["hours_attended"];

            }

            if (empty($returnData["Counts"]["Labels"])) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            }
            
            echo json_encode($returnData);

        }

        private function getClassBreakdown() {

            $returnData = [
                "Counts" => [
                    "Labels" => [],
                    "Data" => []
                ],
                "Times" => [
                    "Labels" => [],
                    "Data" => []
                ]
            ];

            $filterRequest = $this->getFilterRequest("fleetships.endtime IS NOT NULL");

            $classBreakdownQuery = $this->databaseConnection->prepare("
                SELECT 
                    evegroups.id AS id, 
                    evegroups.name AS name, 
                    COUNT(DISTINCT fleetships.fleetid) AS number_attended, 
                    (SUM(fleetships.endtime) - SUM(fleetships.starttime))/1000/3600 AS hours_attended
                FROM fleetships
                LEFT JOIN evetypes ON evetypes.id = fleetships.shipid
                LEFT JOIN evegroups ON evegroups.id = evetypes.groupid
                LEFT JOIN fleets ON fleetships.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY evegroups.id
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $classBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $classBreakdownQuery->execute();
            while ($incomingClasses = $classBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                $returnData["Counts"]["Labels"][] = htmlspecialchars($incomingClasses["name"]);
                $returnData["Counts"]["Data"][] = $incomingClasses["number_attended"];
                $returnData["Times"]["Labels"][] = htmlspecialchars($incomingClasses["name"]);
                $returnData["Times"]["Data"][] = $incomingClasses["hours_attended"];

            }

            if (empty($returnData["Counts"]["Labels"])) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            }
            
            echo json_encode($returnData);

        }

        private function getShipBreakdown() {

            $returnData = [
                "Counts" => [
                    "Labels" => [],
                    "Data" => []
                ],
                "Times" => [
                    "Labels" => [],
                    "Data" => []
                ]
            ];

            $filterRequest = $this->getFilterRequest("fleetships.endtime IS NOT NULL");

            $shipBreakdownQuery = $this->databaseConnection->prepare("
                SELECT 
                    fleetships.shipid AS id, 
                    COUNT(DISTINCT fleetships.fleetid) AS number_attended, 
                    (SUM(fleetships.endtime) - SUM(fleetships.starttime))/1000/3600 AS hours_attended
                FROM fleetships 
                LEFT JOIN fleets ON fleetships.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY fleetships.shipid
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $shipBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $shipBreakdownQuery->execute();

            $idsToCheck = [];
            $originalData = [];
            while ($incomingShips = $shipBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                if (!in_array($incomingShips["id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingShips["id"];
                }

                $originalData[$incomingShips["id"]] = [
                    "Count" => $incomingShips["number_attended"],
                    "Time" => $incomingShips["hours_attended"]
                ];

            }

            $knownNames = [];
            if (!empty($idsToCheck)) {
                $esiHandler = new \Ridley\Objects\ESI\Handler($this->databaseConnection);

                $namesCall = $esiHandler->call(endpoint: "/universe/names/", ids: $idsToCheck, retries: 1);

                if ($namesCall["Success"]) {

                    foreach ($namesCall["Data"] as $eachID) {

                        if ($eachID["category"] === "inventory_type") {
    
                            $knownNames[$eachID["id"]] = $eachID["name"];
    
                        }

                    }

                }
                else {
                    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                    throw new \Exception("A names call failed while trying to get ship names.", 11001);
                }

            }

            if (empty($originalData)) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                echo json_encode($returnData);
                return;
            }

            foreach ($originalData as $eachID => $eachData) {
                
                if (isset($knownNames[$eachID])) {
                    $returnData["Counts"]["Labels"][] = htmlspecialchars($knownNames[$eachID]);
                    $returnData["Times"]["Labels"][] = htmlspecialchars($knownNames[$eachID]);
                }
                else {
                    $returnData["Counts"]["Labels"][] = htmlspecialchars("Unknown Ship");
                    $returnData["Times"]["Labels"][] = htmlspecialchars("Unknown Ship");
                }
                $returnData["Counts"]["Data"][] = $eachData["Count"];
                $returnData["Times"]["Data"][] = $eachData["Time"];
            }


            if (empty($returnData["Counts"]["Labels"])) {
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            }
            
            echo json_encode($returnData);

        }

        public function getFleetTypes() {

            $checkQuery = $this->databaseConnection->prepare("
                SELECT 
                    DISTINCT fleettypes.id AS id
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                LEFT JOIN fleettypes ON fleets.type = fleettypes.id
                WHERE fleetmembers.endtime IS NOT NULL AND fleetmembers.characterid=:characterid
            ");
            $checkQuery->bindValue(":characterid", $this->characterData["Character ID"]);
            $checkQuery->execute();

            return $checkQuery->fetchAll(\PDO::FETCH_COLUMN);

        }

        public function getFilterRequest($addedRequest=null) {
            
            $filterDetails = [
                "Request" => "WHERE characterid=:characterid",
                "Variables" => [":characterid" => ["Value" => $this->characterData["Character ID"], "Type" => \PDO::PARAM_INT]]
            ];

            if (!is_null($addedRequest)) {
                $filterDetails["Request"] .= (" AND " . $addedRequest);
            }
            
            //Filter by Start Date
            if (isset($_POST["date-start"]) and $_POST["date-start"] != "") {
                
                $incomingStartTime = strtotime($_POST["date-start"]);
                
                if ($incomingStartTime !== false) {

                    $incomingStartTime *= 1000;
                    
                    $filterDetails["Request"] .= " AND fleets.starttime >= :date_start";
                    
                    $filterDetails["Variables"][":date_start"] = ["Value" => $incomingStartTime, "Type" => \PDO::PARAM_INT];
                    
                }
                
            }
            
            //Filter by End Date
            if (isset($_POST["date-end"]) and $_POST["date-end"] != "") {
                
                $incomingEndTime = strtotime($_POST["date-end"]);
                
                if ($incomingEndTime !== false) {
                    
                    $incomingEndTime += 86400;
                    $incomingEndTime *= 1000;
                
                    $filterDetails["Request"] .= " AND fleets.endtime <= :date_end";
                    
                    $filterDetails["Variables"][":date_end"] = ["Value" => $incomingEndTime, "Type" => \PDO::PARAM_INT];
                    
                }
            }

            //Filter by Fleets
            $fleetPlaceholders = [];
            $fleetCounter = 0;
            if (isset($_POST["fleet-type"]) and !empty($_POST["fleet-type"])) {

                $knownFleets = $this->getFleetTypes();

                foreach ($_POST["fleet-type"] as $eachID) {

                    if (in_array($eachID, $knownFleets)) {
                        $fleetPlaceholders[] = (":fleet_" . $fleetCounter);
                        $filterDetails["Variables"][(":fleet_" . $fleetCounter)] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                        $fleetCounter++;
                    }
                    
                }

            }

            if ($fleetCounter > 0) {

                $filterDetails["Request"] .= (" AND fleets.type IN (" . implode(",", $fleetPlaceholders) . ")");

            }
            
            return $filterDetails;
            
        }

    }

?>
