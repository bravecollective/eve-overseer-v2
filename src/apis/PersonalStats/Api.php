<?php

    namespace Ridley\Apis\PersonalStats;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $configVariables;
        private $characterData;
        private $coreGroups;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Action" and isset($_POST["ID"])) {


                    
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

            $filterRequest = $this->getFilterRequest("fleetmembers.endtime IS NOT NULL");

            $typeBreakdownQuery = $this->databaseConnection->prepare("
                SELECT fleettypes.name AS name, fleettypes.id AS id, COUNT(DISTINCT fleets.id) AS number_attended, (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime)) AS milliseconds_attended
                FROM fleets
                LEFT JOIN fleettypes ON fleettypes.id = fleets.type
                LEFT JOIN fleetmembers ON fleetmembers.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY fleettypes.id
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $typeBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $typeBreakdownQuery->execute();
            $typeData = $typeBreakdownQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode($typeData);

        }

        private function getRoleBreakdown() {

            $filterRequest = $this->getFilterRequest("fleetmembers.endtime IS NOT NULL");

            $roleBreakdownQuery = $this->databaseConnection->prepare("
                SELECT fleetmembers.role AS role, COUNT(DISTINCT fleets.id) AS number_attended, (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime)) AS milliseconds_attended
                FROM fleets
                LEFT JOIN fleetmembers ON fleetmembers.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY fleetmembers.role
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $roleBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $roleBreakdownQuery->execute();
            $roleData = $roleBreakdownQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode($roleData);

        }

        private function getTimezoneBreakdown() {

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
                COUNT(DISTINCT fleets.id) AS number_attended, (SUM(fleetmembers.endtime) - SUM(fleetmembers.starttime)) AS milliseconds_attended
                FROM fleets
                LEFT JOIN fleetmembers ON fleetmembers.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY timezone
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $timezoneBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $timezoneBreakdownQuery->execute();
            $timezoneData = $timezoneBreakdownQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode($timezoneData);

        }

        private function getClassBreakdown() {

            $filterRequest = $this->getFilterRequest("fleetships.endtime IS NOT NULL");

            $classBreakdownQuery = $this->databaseConnection->prepare("
                SELECT evegroups.id AS id, evegroups.name AS name, COUNT(DISTINCT fleets.id) AS number_attended, (SUM(fleetships.endtime) - SUM(fleetships.starttime)) AS milliseconds_attended
                FROM fleets
                LEFT JOIN fleetships ON fleetships.fleetid = fleets.id
                LEFT JOIN evetypes ON evetypes.id = fleetships.shipid
                LEFT JOIN evegroups ON evegroups.id = evetypes.groupid
                " . $filterRequest["Request"] . "
                GROUP BY evegroups.id
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $classBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $classBreakdownQuery->execute();
            $classData = $classBreakdownQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode($classData);

        }

        private function getShipBreakdown() {

            $filterRequest = $this->getFilterRequest("fleetships.endtime IS NOT NULL");

            $shipBreakdownQuery = $this->databaseConnection->prepare("
                SELECT fleetships.shipid AS id, COUNT(DISTINCT fleets.id) AS number_attended, (SUM(fleetships.endtime) - SUM(fleetships.starttime)) AS milliseconds_attended
                FROM fleets
                LEFT JOIN fleetships ON fleetships.fleetid = fleets.id
                " . $filterRequest["Request"] . "
                GROUP BY fleetships.shipid
            ");

            foreach ($filterRequest["Variables"] as $eachVariable => $eachValue) {
                
                $shipBreakdownQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
                
            }

            $shipBreakdownQuery->execute();
            $typeData = $shipBreakdownQuery->fetchAll(\PDO::FETCH_ASSOC);

            //NOTE: We need to parse these IDs into names!
            
            echo json_encode($typeData);

        }

        public function getFleetTypes() {

            $fleetTypes = [];

            $checkQuery = $this->databaseConnection->prepare("
                SELECT fleettypes.id AS id, fleettypes.name AS name, fleettypeaccess.roletype AS roletype, fleettypeaccess.roleid AS roleid
                FROM fleettypeaccess
                LEFT JOIN fleettypes
                ON fleettypeaccess.typeid = fleettypes.id
                WHERE fleettypeaccess.accesstype = :accesstype
                ORDER BY name ASC
            ");
            $checkQuery->bindValue(":accesstype", "Command");
            $checkQuery->execute();

            if ($this->configVariables["Auth Type"] == "Eve") {

                while ($incomingTypes = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (
                        ($incomingTypes["roletype"] == "Character" and $incomingTypes["roleid"] == $this->characterData["Character ID"])
                        or ($incomingTypes["roletype"] == "Corporation" and $incomingTypes["roleid"] == $this->characterData["Corporation ID"])
                        or ($incomingTypes["roletype"] == "Alliance" and $incomingTypes["roleid"] == $this->characterData["Alliance ID"])
                    ) {
                        $fleetTypes[$incomingTypes["id"]] = $incomingTypes["name"];
                    }

                }

            }
            elseif ($this->configVariables["Auth Type"] == "Neucore") {

                while ($incomingTypes = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (
                        $incomingTypes["roletype"] == "Neucore" and isset($this->coreGroups[$incomingTypes["roleid"]])
                    ) {
                        $fleetTypes[$incomingTypes["id"]] = $incomingTypes["name"];
                    }

                }

            }

            return $fleetTypes;

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
                    
                    $filterDetails["Request"] .= " AND fleets.starttime >= :date_start";
                    
                    $filterDetails["Variables"][":date_start"] = ["Value" => $incomingStartTime, "Type" => \PDO::PARAM_INT];
                    
                }
                
            }
            
            //Filter by End Date
            if (isset($_POST["date-end"]) and $_POST["date-end"] != "") {
                
                $incomingEndTime = strtotime($_POST["date-end"]);
                
                if ($incomingEndTime !== false) {
                    
                    $incomingEndTime += 86400;
                
                    $filterDetails["Request"] .= " AND fleets.endtime <= :date_end";
                    
                    $filterDetails["Variables"][":date_end"] = ["Value" => $incomingEndTime, "Type" => \PDO::PARAM_INT];
                    
                }
            }
            
            //Filter by Fleet Type
            $typeSubstring = "AND fleets.type IN (";
            
            $typeCounter = 0;
            foreach ($this->getFleetTypes() as $eachID => $eachName) {
                
                if (isset($_POST["type-" . $eachID]) and $_POST["type-" . $eachID] === "true") {

                    $typeSubstring .= (":type_" . $typeCounter . ",");
                    $filterDetails["Variables"][":type_" . $typeCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                    $typeCounter++;

                }
                
            }
            
            $typeSubstring = (rtrim($typeSubstring, ",") . ")");
            
            if (!str_ends_with($typeSubstring, "()")) {
                
                $filterDetails["Request"] .= $typeSubstring;
                
            }
            
            return $filterDetails;
            
        }

    }

?>
