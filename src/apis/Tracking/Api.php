<?php

    namespace Ridley\Apis\Tracking;

    use Ridley\Core\Exceptions\UserInputException;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $configVariables;
        private $logger;
        private $characterData;
        private $coreGroups;
        private $userAuthorization;
        private $esiHandler;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            $this->logger = $this->dependencies->get("Logging");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");
            $this->userAuthorization = $this->dependencies->get("Authorization Control");

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Toggle_Tracking" and isset($_POST["Fleet_Name"]) and isset($_POST["Fleet_Type"]) and isset($_POST["Share_Fleet"])) {

                    $this->toggleTracking(
                        $_POST["Fleet_Name"],
                        $_POST["Fleet_Type"],
                        ($_POST["Share_Fleet"] === "true")
                    );
                    
                }
                elseif ($_POST["Action"] == "Track_Shared" and isset($_POST["Share_Key"])) {

                    $this->subscribeToFleet(
                        $_POST["Share_Key"]
                    );
                    
                }
                elseif ($_POST["Action"] == "Get_Fleet_Data" and isset($_POST["Only_With_Commander"])) {

                    $this->getFleetData(
                        $_POST["Share_Key"] ?? null,
                        ($_POST["Only_With_Commander"] === "true")
                    );
                    
                }
                elseif ($_POST["Action"] == "Get_Fleet_Info") {

                    $this->getFleetInfo();
                    
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

        private function toggleTracking($fleetName, $fleetType, $shareFleet) {

            $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                $this->databaseConnection,
                $this->userAuthorization->getAccessToken("FC", $this->characterData["Character ID"])
            );

            $fleetCall = $this->esiHandler->call(endpoint: "/characters/{character_id}/fleet/", character_id: $this->characterData["Character ID"], retries: 1);

            if ($fleetCall["Success"] and $fleetCall["Data"]["fleet_boss_id"] == $this->characterData["Character ID"]) {

                $fleetStatus = $this->getFleetTrackingStatus(
                    fleetID: $fleetCall["Data"]["fleet_id"]
                );

                if (!$fleetStatus["Fleet Found"]) {

                    if (!isset($this->getFleetTypes()[$fleetType])) {
                
                        throw new UserInputException(
                            inputs: "Fleet Type", 
                            expected_values: "A valid fleet type that the user has access to", 
                            hard_coded_inputs: true
                        );
        
                    }

                    $shareBytes = random_bytes(16);

                    $startTracking = $this->databaseConnection->prepare("
                        INSERT INTO fleets
                            (id, name, type, commanderid, status, sharekey)
                        VALUES
                            (:id, :name, :type, :commanderid, :status, :sharekey)
                    ");
                    $startTracking->bindParam(":id", $fleetCall["Data"]["fleet_id"]);
                    $startTracking->bindParam(":name", $fleetName);
                    $startTracking->bindParam(":type", $fleetType);
                    $startTracking->bindParam(":commanderid", $this->characterData["Character ID"]);
                    $startTracking->bindValue(":status", "Starting");
                    $startTracking->bindValue(":sharekey", ($shareFleet) ? bin2hex($shareBytes) : null);
                    $startTracking->execute();

                    $getFleetTypeName = $this->databaseConnection->prepare("SELECT name FROM fleettypes WHERE id=:id");
                    $getFleetTypeName->bindParam(":id", $fleetType);
                    $getFleetTypeName->execute();
                    $fleetTypeName = $getFleetTypeName->fetchColumn();

                    $this->logger->make_log_entry(logType: "Started Tracking", logDetails: $this->characterData["Character Name"] . " started tracking a new " . $fleetTypeName . " fleet: " . $fleetName . " (ID: " . $fleetCall["Data"]["fleet_id"] . ")");

                    echo json_encode(["Status" => "Success", "New Status" => "Active", "Name" => $fleetName, "Type" => $fleetType, "Share Key" => ($shareFleet) ? bin2hex($shareBytes) : null]);

                }
                elseif (
                    $fleetStatus["Status"] === "Active" 
                    and (int)$fleetStatus["Commander"] === (int)$this->characterData["Character ID"]
                ) {

                    $stopTracking = $this->databaseConnection->prepare("UPDATE fleets SET status=:status WHERE id=:id");
                    $stopTracking->bindValue(":status", "Closing");
                    $stopTracking->bindParam(":id", $fleetCall["Data"]["fleet_id"]);
                    $stopTracking->execute();

                    $this->logger->make_log_entry(logType: "Stopped Tracking", logDetails: $this->characterData["Character Name"] . " stopped tracking the " . $fleetStatus["Type"] . " fleet: " . $fleetStatus["Name"] . " (ID: " . $fleetStatus["ID"] . ")");

                    echo json_encode(["Status" => "Success", "New Status" => "Closed"]);

                }
                elseif (
                    $fleetStatus["Status"] === "Closed" 
                    and (int)$fleetStatus["Commander"] === (int)$this->characterData["Character ID"]
                ) {

                    if (!isset($this->getFleetTypes()[$fleetStatus["Type ID"]])) {
                
                        throw new UserInputException(
                            inputs: "Fleet Type", 
                            expected_values: "A fleet cannot be restarted if the tracker doesn't have access to the original fleet type."
                        );
        
                    }

                    $restartTracking = $this->databaseConnection->prepare("UPDATE fleets SET status=:status WHERE id=:id");
                    $restartTracking->bindValue(":status", "Active");
                    $restartTracking->bindParam(":id", $fleetCall["Data"]["fleet_id"]);
                    $restartTracking->execute();

                    $this->logger->make_log_entry(logType: "Restarted Tracking", logDetails: $this->characterData["Character Name"] . " restarted tracking for the " . $fleetStatus["Type"] . " fleet: " . $fleetStatus["Name"] . " (ID: " . $fleetStatus["ID"] . ")");

                    echo json_encode(["Status" => "Success", "New Status" => "Active", "Name" => $fleetStatus["Name"], "Type" => $fleetStatus["Type ID"], "Share Key" => $fleetStatus["Share Key"]]);

                }
                elseif (
                    $fleetStatus["Status"] === "Closed" 
                    and (int)$fleetStatus["Commander"] !== (int)$this->characterData["Character ID"]
                ) {

                    if (!isset($this->getFleetTypes()[$fleetStatus["Type ID"]])) {
                
                        throw new UserInputException(
                            inputs: "Fleet Type", 
                            expected_values: "A fleet cannot be restarted if the tracker doesn't have access to the original fleet type."
                        );
        
                    }

                    $restartTracking = $this->databaseConnection->prepare("UPDATE fleets SET status=:status, commanderid=:commanderid WHERE id=:id");
                    $restartTracking->bindValue(":status", "Active");
                    $restartTracking->bindParam(":commanderid", $this->characterData["Character ID"]);
                    $restartTracking->bindParam(":id", $fleetCall["Data"]["fleet_id"]);
                    $restartTracking->execute();

                    $this->logger->make_log_entry(logType: "Fleet Tracker Changed", logDetails: $this->characterData["Character Name"] . " restarted and took over tracking for the " . $fleetStatus["Type"] . " fleet: " . $fleetStatus["Name"] . " (ID: " . $fleetStatus["ID"] . ")");

                    echo json_encode(["Status" => "Success", "New Status" => "Active", "Name" => $fleetStatus["Name"], "Type" => $fleetStatus["Type ID"], "Share Key" => $fleetStatus["Share Key"]]);

                }
                elseif ($fleetStatus["Status"] === "Starting" or $fleetStatus["Status"] === "Closing") {

                    echo json_encode(["Status" => "Failed"]);

                }
                else {

                    throw new UserInputException(
                        inputs: "Fleet Status", 
                        expected_values: "A fleet's tracker cannot be changed while someone else is actively tracking it."
                    );

                }


            }
            else {

                throw new UserInputException(
                    inputs: "Fleet Boss", 
                    expected_values: "The tracker must be the boss of a fleet."
                );

            }

        }

        private function subscribeToFleet($shareKey) {

            $fleetStatus = $this->getFleetTrackingStatus(shareKey: $shareKey);

            if ($fleetStatus["Fleet Found"] and $fleetStatus["Status"] === "Active") {

                if (!$fleetStatus["Is Subscribed"]) {

                    $subscribeToFleet = $this->databaseConnection->prepare("REPLACE INTO sharesubscriptions (sharekey, characterid) VALUES (:sharekey, :characterid)");
                    $subscribeToFleet->bindParam(":sharekey", $shareKey);
                    $subscribeToFleet->bindParam(":characterid", $this->characterData["Character ID"]);
                    $subscribeToFleet->execute();

                    $this->logger->make_log_entry(logType: "Subscribed to Fleet", logDetails: $this->characterData["Character Name"] . " subscribed to the " . $fleetStatus["Type"] . " fleet: " . $fleetStatus["Name"] . " (ID: " . $fleetStatus["ID"] . ")");

                }
                
                echo json_encode(["Status" => "Success", "Name" => $fleetStatus["Name"], "Type" => $fleetStatus["Type ID"]]);

            }
            else {

                throw new UserInputException(
                    inputs: "Share Key", 
                    expected_values: "A valid share key for an active fleet"
                );

            }

        }

        private function getFleetData($shareKey=null, $onlyWithCommander=false) {

            if (is_null($shareKey)) {

                $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                    $this->databaseConnection,
                    $this->userAuthorization->getAccessToken("FC", $this->characterData["Character ID"])
                );
    
                $fleetCall = $this->esiHandler->call(endpoint: "/characters/{character_id}/fleet/", character_id: $this->characterData["Character ID"], retries: 1);
    
                if (
                    $fleetCall["Success"] 
                    and (int)$fleetCall["Data"]["fleet_boss_id"] === (int)$this->characterData["Character ID"]
                ) {

                    $fleetStatus = $this->getFleetTrackingStatus(fleetID: $fleetCall["Data"]["fleet_id"]);

                    if ($fleetStatus["Fleet Found"] and $fleetStatus["Status"] === "Active") {
    
                        $fleetData = new \Ridley\Objects\Fleets\FleetData(
                            dependencies: $this->dependencies,
                            incomingFleetID: $fleetCall["Data"]["fleet_id"],
                            onlyWithCommander: $onlyWithCommander
                        );

                        $fleetData->renderData();
                        return;

                    }
    
                }

            }
            else {

                $fleetStatus = $this->getFleetTrackingStatus(shareKey: $shareKey);

                if ($fleetStatus["Fleet Found"] and $fleetStatus["Status"] === "Active") {

                    $fleetData = new \Ridley\Objects\Fleets\FleetData(
                        dependencies: $this->dependencies,
                        incomingShareKey: $shareKey,
                        onlyWithCommander: $onlyWithCommander
                    );

                    $fleetData->renderData();
                    return;

                }

            }

            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            
        }

        private function getFleetInfo() {

            $infoQuery = $this->databaseConnection->prepare("SELECT name, type, sharekey FROM fleets WHERE commanderid=:commanderid AND status=:status");
            $infoQuery->bindValue(":commanderid", $this->characterData["Character ID"]);
            $infoQuery->bindValue(":status", "Active");
            $infoQuery->execute();

            $data = $infoQuery->fetch();

            if (!empty($data)) {

                echo json_encode(["Name" => $data["name"], "Type" => $data["type"], "Share Key" => $data["sharekey"]]);

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

            }

        }

        private function getFleetTrackingStatus($fleetID=null, $shareKey=null) {

            $output = [
                "Fleet Found" => false,
                "Is Subscribed" => false,
                "ID" => null,
                "Status" => null, 
                "Name" => null,
                "Type ID" => null,
                "Type" => null,
                "Commander" => null,
                "Share Key" => null
            ];

            if (!is_null($fleetID)) {

                $trackingQuery = $this->databaseConnection->prepare("
                    SELECT fleets.id AS id, fleets.status AS status, fleets.name AS name, fleets.type AS type_id, fleettypes.name AS type, fleets.commanderid AS commanderid, fleets.sharekey AS sharekey
                    FROM fleets 
                    LEFT JOIN fleettypes ON fleettypes.id = fleets.type 
                    WHERE fleets.id=:id
                ");
                $trackingQuery->bindParam(":id", $fleetID);
                $trackingQuery->execute();

                $fleetData = $trackingQuery->fetch();

                if (!empty($fleetData)) {

                    $output["Fleet Found"] = true;
                    $output["ID"] = $fleetData["id"];
                    $output["Status"] = $fleetData["status"];
                    $output["Name"] = $fleetData["name"];
                    $output["Type ID"] = $fleetData["type_id"];
                    $output["Type"] = $fleetData["type"];
                    $output["Commander"] = $fleetData["commanderid"];
                    $output["Share Key"] = $fleetData["sharekey"];

                }

            }
            elseif (!is_null($shareKey)) {

                $trackingQuery = $this->databaseConnection->prepare("
                    SELECT fleets.id AS id, fleets.status AS status, fleets.name AS name, fleets.type AS type_id, fleettypes.name AS type, fleets.commanderid AS commanderid, fleets.sharekey AS sharekey
                    FROM fleets 
                    LEFT JOIN fleettypes ON fleettypes.id = fleets.type 
                    WHERE fleets.sharekey=:sharekey
                ");
                $trackingQuery->bindParam(":sharekey", $shareKey);
                $trackingQuery->execute();

                $fleetData = $trackingQuery->fetch();

                if (!empty($fleetData)) {

                    $output["Fleet Found"] = true;
                    $output["ID"] = $fleetData["id"];
                    $output["Status"] = $fleetData["status"];
                    $output["Name"] = $fleetData["name"];
                    $output["Type ID"] = $fleetData["type_id"];
                    $output["Type"] = $fleetData["type"];
                    $output["Commander"] = $fleetData["commanderid"];
                    $output["Share Key"] = $fleetData["sharekey"];

                    $subscriptionQuery = $this->databaseConnection->prepare("SELECT COUNT(*) FROM sharesubscriptions WHERE sharekey=:sharekey AND characterid=:characterid");
                    $subscriptionQuery->bindParam(":sharekey", $shareKey);
                    $subscriptionQuery->bindParam(":characterid", $this->characterData["Character ID"]);
                    $subscriptionQuery->execute();
    
                    if ((bool)$subscriptionQuery->fetchColumn()) {
    
                        $output["Is Subscribed"] = true;
    
                    }

                }

            }

            return $output;

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

    }

?>
