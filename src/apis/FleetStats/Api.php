<?php

    namespace Ridley\Apis\FleetStats;

    use Ridley\Core\Exceptions\UserInputException;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $accessRoles;
        private $characterData;
        private $urlData;
        private $targetFleet;
        private $fleetAccessController;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->urlData = $this->dependencies->get("URL Data");
            $this->targetFleet = $this->urlData["Page Topic"];
            $this->fleetAccessController = new \Ridley\Objects\AccessControl\Fleet($this->dependencies);

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Delete_Fleet" and $this->targetFleet !== false) {

                    $this->deleteFleet($this->targetFleet);
                    
                }
                elseif ($_POST["Action"] == "Get_Ship_Breakdown" and $this->targetFleet !== false) {

                    $this->getShipBreakdown($this->targetFleet);
                    
                }
                elseif ($_POST["Action"] == "Get_Class_Breakdown" and $this->targetFleet !== false) {

                    $this->getClassBreakdown($this->targetFleet);
                    
                }
                elseif ($_POST["Action"] == "Get_Member_Timeline" and $this->targetFleet !== false and isset($_POST["Member_ID"])) {

                    $this->getMemberTimeline($this->targetFleet, $_POST["Member_ID"]);
                    
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
        
        private function generateAccessRestrictions() {

            $filterDetails = [
                "Enabled" => true,
                "Request" => "",
                "Variables" => []
            ];

            if (in_array("Super Admin", $this->accessRoles) or in_array("View Fleet Stats", $this->accessRoles)) {

                $filterDetails["Enabled"] = false;
                return $filterDetails;

            }
            else {

                $approvedFleets = $this->fleetAccessController->getFleetTypes(forAudit: True);

                $fleetPlaceholders = [];
                $fleetCounter = 0;
    
                foreach ($approvedFleets as $eachID => $eachName) {

                    $fleetPlaceholders[] = (":access_fleet_" . $fleetCounter);
                    $filterDetails["Variables"][(":access_fleet_" . $fleetCounter)] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                    $fleetCounter++;
                    
                }
        
                if ($fleetCounter > 0) {
    
                    $filterDetails["Request"] = ("(fleets.type IN (" . implode(",", $fleetPlaceholders) . ") OR fleets.commanderid=:commanderid)");
    
                }
                else {

                    $filterDetails["Request"] = "fleets.commanderid=:commanderid";

                }

                $filterDetails["Variables"][":commanderid"] = ["Value" => $this->characterData["Character ID"], "Type" => \PDO::PARAM_INT];

            }

            return $filterDetails;

        }

        private function getClassBreakdown($fleetID) {

            $accessRestrictions = $this->generateAccessRestrictions();
            $restrictionsQuery = ($accessRestrictions["Enabled"]) ? (" AND " . $accessRestrictions["Request"]) : "";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT COUNT(*) FROM fleets WHERE id=:id" . $restrictionsQuery
            );
            $checkQuery->bindValue(":id", $fleetID);

            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $checkQuery->execute();

            if ($checkQuery->fetchColumn() > 0) {

                $returnData = [
                    "Labels" => [],
                    "Datasets" => []
                ];
        
                $classBreakdownQuery = $this->databaseConnection->prepare("
                    SELECT
                        fleetsnapshots.timestamp AS timestamp,
                        evegroups.id AS id,
                        evegroups.name AS name,
                        COUNT(DISTINCT (CASE
                            WHEN 
                                fleetships.starttime <= fleetsnapshots.timestamp
                                AND fleetships.endtime > fleetsnapshots.timestamp
                            THEN fleetships.characterid
                        END)) AS count
                    FROM fleetsnapshots
                    INNER JOIN fleetships ON fleetsnapshots.fleetid = fleetships.fleetid
                    INNER JOIN evetypes ON fleetships.shipid = evetypes.id
                    INNER JOIN evegroups ON evetypes.groupid = evegroups.id
                    WHERE fleetsnapshots.fleetid = :fleetid
                    GROUP BY fleetsnapshots.timestamp, evegroups.id
                    ORDER BY fleetsnapshots.timestamp ASC, count DESC
                ");
    
                $classBreakdownQuery->bindValue(":fleetid", $fleetID);
                $classBreakdownQuery->execute();
                while ($incomingClasses = $classBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (!in_array($incomingClasses["timestamp"], $returnData["Labels"])) {
                        $returnData["Labels"][] = $incomingClasses["timestamp"];
                    }
    
                    if (!isset($returnData["Datasets"][$incomingClasses["id"]])) {
                        $returnData["Datasets"][$incomingClasses["id"]] = [
                            "label" => $incomingClasses["name"],
                            "data" => [],
                            "pointStyle" => false,
                            "borderWidth" => 1
                        ];
                    }

                    $returnData["Datasets"][$incomingClasses["id"]]["data"][] = $incomingClasses["count"];
    
                }
    
                if (empty($returnData["Labels"])) {
                    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                }
                
                $returnData["Datasets"] = array_values($returnData["Datasets"]);
                echo json_encode($returnData);
        
            }
            else {

                throw new UserInputException(
                    inputs: "Fleet ID", 
                    expected_values: "A Valid Fleet ID to Pull Ships For", 
                    hard_coded_inputs: true,
                );

            }

        }

        private function getShipBreakdown($fleetID) {

            $accessRestrictions = $this->generateAccessRestrictions();

            $restrictionsQuery = ($accessRestrictions["Enabled"]) ? (" AND " . $accessRestrictions["Request"]) : "";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT COUNT(*) FROM fleets WHERE id=:id" . $restrictionsQuery
            );
            $checkQuery->bindValue(":id", $fleetID);

            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $checkQuery->execute();

            if ($checkQuery->fetchColumn() > 0) {

                $returnData = [
                    "Labels" => [],
                    "Datasets" => []
                ];
                $idsToCheck = [];
        
                $shipBreakdownQuery = $this->databaseConnection->prepare("
                    SELECT
                        fleetsnapshots.timestamp AS timestamp,
                        fleetships.shipid AS id,
                        COUNT(DISTINCT (CASE
                            WHEN 
                                fleetships.starttime <= fleetsnapshots.timestamp
                                AND fleetships.endtime > fleetsnapshots.timestamp
                            THEN fleetships.characterid
                        END)) AS count
                    FROM fleetsnapshots
                    INNER JOIN fleetships ON fleetsnapshots.fleetid = fleetships.fleetid
                    WHERE fleetsnapshots.fleetid = :fleetid
                    GROUP BY fleetsnapshots.timestamp, fleetships.shipid
                    ORDER BY fleetsnapshots.timestamp ASC, count DESC
                ");
    
                $shipBreakdownQuery->bindValue(":fleetid", $fleetID);
                $shipBreakdownQuery->execute();
                while ($incomingShips = $shipBreakdownQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (!in_array($incomingShips["timestamp"], $returnData["Labels"])) {
                        $returnData["Labels"][] = $incomingShips["timestamp"];
                    }
    
                    if (!isset($returnData["Datasets"][$incomingShips["id"]])) {
                        $returnData["Datasets"][$incomingShips["id"]] = [
                            "label" => null,
                            "data" => [],
                            "pointStyle" => false,
                            "borderWidth" => 1
                        ];
                        $idsToCheck[] = $incomingShips["id"];
                    }

                    $returnData["Datasets"][$incomingShips["id"]]["data"][] = $incomingShips["count"];
    
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

                foreach ($returnData["Datasets"] as $eachID => $eachData) {

                    if (isset($knownNames[$eachID])) {
                        $returnData["Datasets"][$eachID]["label"] = $knownNames[$eachID];
                    }
                    else {
                        $returnData["Datasets"][$eachID]["label"] = "Unknown Ship";
                    }

                }
    
                if (empty($returnData["Labels"])) {
                    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                }
                
                $returnData["Datasets"] = array_values($returnData["Datasets"]);
                echo json_encode($returnData);
        
            }
            else {

                throw new UserInputException(
                    inputs: "Fleet ID", 
                    expected_values: "A Valid Fleet ID to Pull Ships For", 
                    hard_coded_inputs: true,
                );

            }

        }

        private function getMemberTimeline($fleetID, $memberID) {

            $accessRestrictions = $this->generateAccessRestrictions();

            $restrictionsQuery = ($accessRestrictions["Enabled"]) ? (" AND " . $accessRestrictions["Request"]) : "";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT starttime, endtime FROM fleets WHERE id=:id" . $restrictionsQuery
            );
            $checkQuery->bindValue(":id", $fleetID);

            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $checkQuery->execute();

            while ($incomingFleet = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {

                $returnData = [
                    "Datasets" => [],
                    "Events" => [],
                    "Character" => null,
                    "Start" => $incomingFleet["starttime"],
                    "End" => $incomingFleet["endtime"]
                ];

                //Get IDs and Associated Names for Parsing
                $idsQuery = $this->databaseConnection->prepare("
                    SELECT DISTINCT shipid
                    FROM fleetships
                    WHERE fleetid = :fleetid AND characterid = :memberid

                    UNION

                    SELECT DISTINCT systemid
                    FROM fleetlocations
                    WHERE fleetid = :fleetid AND characterid = :memberid

                    UNION

                    SELECT DISTINCT regionid
                    FROM fleetlocations
                    LEFT JOIN evesystems ON evesystems.id = fleetlocations.systemid
                    WHERE fleetid = :fleetid AND characterid = :memberid
                ");
                $idsQuery->bindValue(":fleetid", $fleetID);
                $idsQuery->bindValue(":memberid", $memberID);
                $idsQuery->execute();

                $idsToCheck = $idsQuery->fetchAll(\PDO::FETCH_COLUMN);
                $idsToCheck[] = $memberID;
        
                $knownNames = [];
                if (!empty($idsToCheck)) {
                    $esiHandler = new \Ridley\Objects\ESI\Handler($this->databaseConnection);
    
                    $namesCall = $esiHandler->call(endpoint: "/universe/names/", ids: $idsToCheck, retries: 1);
    
                    if ($namesCall["Success"]) {
    
                        foreach ($namesCall["Data"] as $eachID) {

                            if (!isset($knownNames[$eachID["category"]])) {
                                $knownNames[$eachID["category"]] = [];
                            }

                            $knownNames[$eachID["category"]][$eachID["id"]] = $eachID["name"];
    
                        }
    
                    }
                    else {
                        header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                        throw new \Exception("A names call failed while trying to get ship names.", 11001);
                    }
    
                }

                $returnData["Character"] = htmlentities($knownNames["character"][$memberID] ?? "Unknown Character");

                //Get Roles and Position
                $memberQuery = $this->databaseConnection->prepare("
                    SELECT role, wingid, squadid, starttime, endtime
                    FROM fleetmembers
                    WHERE fleetid = :fleetid AND characterid = :memberid
                ");
    
                $memberQuery->bindValue(":fleetid", $fleetID);
                $memberQuery->bindValue(":memberid", $memberID);
                $memberQuery->execute();

                while ($incomingStatuses = $memberQuery->fetch(\PDO::FETCH_ASSOC)) {

                    $returnData["Datasets"][] = [
                        "label" => (htmlspecialchars(ucwords(str_replace("_", " ", $incomingStatuses["role"]))) . " — Wing: " . htmlspecialchars($incomingStatuses["wingid"] ?? "NONE") . " — Squad: " . htmlspecialchars($incomingStatuses["squadid"] ?? "NONE")),
                        "data" => [[$incomingStatuses["starttime"], $incomingStatuses["endtime"]], null, null],
                        "barThickness" => "flex"
                    ];

                    if (!isset($returnData["Events"][$incomingStatuses["starttime"]])) {
                        $returnData["Events"][$incomingStatuses["starttime"]] = [];
                    }
                    $returnData["Events"][$incomingStatuses["starttime"]][] = htmlspecialchars("
                        Joined Squad " . ($incomingStatuses["squadid"] ?? "NONE") . " in Wing " . ($incomingStatuses["wingid"] ?? "NONE") . " with Role " . ucwords(str_replace("_", " ", $incomingStatuses["role"])) . ".
                    ");

                    if (!isset($returnData["Events"][$incomingStatuses["endtime"]])) {
                        $returnData["Events"][$incomingStatuses["endtime"]] = [];
                    }
                    $returnData["Events"][$incomingStatuses["endtime"]][] = htmlspecialchars("
                        Left Squad " . ($incomingStatuses["squadid"] ?? "NONE") . " in Wing " . ($incomingStatuses["wingid"] ?? "NONE") . " with Role " . ucwords(str_replace("_", " ", $incomingStatuses["role"])) . ".
                    ");
    
                }

                //Get Ships
                $shipQuery = $this->databaseConnection->prepare("
                    SELECT shipid, starttime, endtime
                    FROM fleetships
                    WHERE fleetid = :fleetid AND characterid = :memberid
                ");
    
                $shipQuery->bindValue(":fleetid", $fleetID);
                $shipQuery->bindValue(":memberid", $memberID);
                $shipQuery->execute();

                while ($incomingShips = $shipQuery->fetch(\PDO::FETCH_ASSOC)) {

                    $shipName = $knownNames["inventory_type"][$incomingShips["shipid"]] ?? "Unknown Ship";
                    $returnData["Datasets"][] = [
                        "label" => htmlspecialchars($shipName),
                        "data" => [null, [$incomingShips["starttime"], $incomingShips["endtime"]], null],
                        "barThickness" => "flex"
                    ];

                    if (!isset($returnData["Events"][$incomingShips["starttime"]])) {
                        $returnData["Events"][$incomingShips["starttime"]] = [];
                    }
                    $returnData["Events"][$incomingShips["starttime"]][] = htmlspecialchars("Began Piloting a(n) " . $shipName . ".");

                    if (!isset($returnData["Events"][$incomingShips["endtime"]])) {
                        $returnData["Events"][$incomingShips["endtime"]] = [];
                    }
                    $returnData["Events"][$incomingShips["endtime"]][] = htmlspecialchars("Stopped Piloting a(n) " . $shipName . ".");
    
                }

                //Get Locations
                $locationQuery = $this->databaseConnection->prepare("
                    SELECT systemid, evesystems.regionid AS regionid, starttime, endtime
                    FROM fleetlocations
                    LEFT JOIN evesystems ON evesystems.id = fleetlocations.systemid
                    WHERE fleetid = :fleetid AND characterid = :memberid
                ");
    
                $locationQuery->bindValue(":fleetid", $fleetID);
                $locationQuery->bindValue(":memberid", $memberID);
                $locationQuery->execute();

                while ($incomingLocations = $locationQuery->fetch(\PDO::FETCH_ASSOC)) {

                    $regionName = $knownNames["region"][$incomingLocations["regionid"]] ?? "Unknown Region";
                    $systemName = $knownNames["solar_system"][$incomingLocations["systemid"]] ?? "Unknown System";
                    $returnData["Datasets"][] = [
                        "label" => "[" . htmlspecialchars($regionName) . "] " . htmlspecialchars($systemName),
                        "data" => [null, null, [$incomingLocations["starttime"], $incomingLocations["endtime"]]],
                        "barThickness" => "flex"
                    ];

                    if (!isset($returnData["Events"][$incomingLocations["starttime"]])) {
                        $returnData["Events"][$incomingLocations["starttime"]] = [];
                    }
                    $returnData["Events"][$incomingLocations["starttime"]][] = htmlspecialchars("Entered the System of " . $systemName . " in the Region of " . $regionName . ".");

                    if (!isset($returnData["Events"][$incomingLocations["endtime"]])) {
                        $returnData["Events"][$incomingLocations["endtime"]] = [];
                    }
                    $returnData["Events"][$incomingLocations["endtime"]][] = htmlspecialchars("Exited the System of " . $systemName . " in the Region of " . $regionName . ".");

                }

                ksort($returnData["Events"]);

                echo json_encode($returnData);
                return;
        
            }

            throw new UserInputException(
                inputs: "Fleet ID", 
                expected_values: "A Valid Fleet ID to Pull Ships For", 
                hard_coded_inputs: true,
            );


        }

        private function deleteFleet($fleetID) {

            if (in_array("Super Admin", $this->accessRoles) or in_array("Delete Fleets", $this->accessRoles)) {

                $checkQuery = $this->databaseConnection->prepare("
                    SELECT COUNT(*) FROM fleets WHERE id=:id
                ");
                $checkQuery->bindValue(":id", $fleetID);
                $checkQuery->execute();

                if ($checkQuery->fetchColumn() > 0) {

                    $deleteFleet = $this->databaseConnection->prepare("
                        DELETE FROM fleets WHERE id=:id
                    ");
                    $deleteFleet->bindValue(":id", $fleetID);
                    $deleteFleet->execute();

                    $deleteFleetSnapshots = $this->databaseConnection->prepare("
                        DELETE FROM fleetsnapshots WHERE fleetid=:id
                    ");
                    $deleteFleetSnapshots->bindValue(":id", $fleetID);
                    $deleteFleetSnapshots->execute();

                    $deleteFleetMembers = $this->databaseConnection->prepare("
                        DELETE FROM fleetmembers WHERE fleetid=:id
                    ");
                    $deleteFleetMembers->bindValue(":id", $fleetID);
                    $deleteFleetMembers->execute();

                    $deleteFleetLocations = $this->databaseConnection->prepare("
                        DELETE FROM fleetlocations WHERE fleetid=:id
                    ");
                    $deleteFleetLocations->bindValue(":id", $fleetID);
                    $deleteFleetLocations->execute();

                    $deleteFleetShips = $this->databaseConnection->prepare("
                        DELETE FROM fleetships WHERE fleetid=:id
                    ");
                    $deleteFleetShips->bindValue(":id", $fleetID);
                    $deleteFleetShips->execute();

                    echo json_encode(["Success" => true]);

                }
                else {

                    throw new UserInputException(
                        inputs: "Fleet ID", 
                        expected_values: "A Valid Fleet ID to Delete", 
                        hard_coded_inputs: true,
                    );

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
                return;

            }

        }

    }

?>
