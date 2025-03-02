<?php

    namespace Ridley\Apis\FleetStats;

    use Ridley\Core\Exceptions\UserInputException;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $logger;
        private $accessRoles;
        private $characterData;
        private $coreGroups;
        private $configVariables;
        private $urlData;
        private $targetFleet;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            $this->urlData = $this->dependencies->get("URL Data");
            $this->targetFleet = $this->urlData["Page Topic"];

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

        private function getFleetTypes() {

            $fleetTypes = [];

            $checkQuery = $this->databaseConnection->prepare("
                SELECT fleettypes.id AS id, fleettypes.name AS name, fleettypeaccess.roletype AS roletype, fleettypeaccess.roleid AS roleid
                FROM fleettypeaccess
                LEFT JOIN fleettypes
                ON fleettypeaccess.typeid = fleettypes.id
                WHERE fleettypeaccess.accesstype = :accesstype
                ORDER BY name ASC
            ");
            $checkQuery->bindValue(":accesstype", "Audit");
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

                $approvedFleets = $this->getFleetTypes();

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

        private function deleteFleet($fleetID) {

            if (in_array("Super Admin", $this->accessRoles) or in_array("View Fleet Stats", $this->accessRoles)) {

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
