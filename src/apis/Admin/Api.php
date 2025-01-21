<?php

    namespace Ridley\Apis\Admin;

    class Api implements \Ridley\Interfaces\Api {

        private $availableRoles = [];
        private $databaseConnection;
        private $logger;
        private $configVariables;
        private $characterStats;
        private $userAuthorization;
        private $esiHandler;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            $this->characterStats = $this->dependencies->get("Character Stats");
            $this->userAuthorization = $this->dependencies->get("Authorization Control");

            $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                $this->databaseConnection,
                $this->userAuthorization->getAccessToken("Default", $this->characterStats["Character ID"])
            );

            require __DIR__ . "/../../registers/accessRoles.php";

            if (isset($_POST["Action"])) {

                if (
                    $_POST["Action"] == "Search"
                    and isset($_POST["Type"])
                    and isset($_POST["Term"])
                    and isset($_POST["Strict"])
                    and in_array($_POST["Strict"], ["true", "false"], true)
                ){

                    $this->getSearchResults($_POST["Type"], $_POST["Term"], $_POST["Strict"]);

                }
                elseif (
                    $_POST["Action"] == "Add_Group"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                    and isset($_POST["Name"])
                ){

                    $this->addGroup($_POST["Type"], $_POST["ID"], $_POST["Name"]);

                }
                elseif (
                    $_POST["Action"] == "Add_Entity"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                    and isset($_POST["Name"])
                ){

                    $this->addEntity($_POST["Type"], $_POST["ID"], $_POST["Name"]);

                }
                elseif (
                    $_POST["Action"] == "Remove_Group"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                ){

                    $this->removeGroup($_POST["Type"], $_POST["ID"]);

                }
                elseif (
                    $_POST["Action"] == "Remove_Entity"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                ){

                    $this->removeEntity($_POST["Type"], $_POST["ID"]);

                }
                elseif (
                    $_POST["Action"] == "Update_Group"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                    and isset($_POST["Change"])
                    and isset($_POST["Role"])
                ){

                    $this->updateGroup($_POST["Type"], $_POST["ID"], $_POST["Change"], $_POST["Role"]);

                }
                elseif (
                    $_POST["Action"] == "Update_Entity"
                    and isset($_POST["Type"])
                    and isset($_POST["ID"])
                    and isset($_POST["Group_Type"])
                    and isset($_POST["Group_ID"])
                    and isset($_POST["Change"])
                ){

                    $this->updateEntity($_POST["Type"], $_POST["ID"], $_POST["Group_Type"], $_POST["Group_ID"], $_POST["Change"]);

                }
                elseif (
                    $_POST["Action"] == "Create_Fleet_Type"
                    and isset($_POST["Fleet_Type_Name"])
                ){

                    $this->createFleetType($_POST["Fleet_Type_Name"]);

                }
                elseif (
                    $_POST["Action"] == "Delete_Fleet_Type"
                    and isset($_POST["Fleet_Type_ID"])
                ){

                    $this->deleteFleetType($_POST["Fleet_Type_ID"]);

                }
                elseif (
                    $_POST["Action"] == "Update_Fleet_Type_Access"
                    and isset($_POST["Fleet_Type_ID"])
                    and isset($_POST["Role_Type"])
                    and isset($_POST["Role_ID"])
                    and isset($_POST["Access_Type"])
                    and isset($_POST["Change"])
                ){

                    $this->updateFleetTypeAccess(
                        $_POST["Change"],
                        $_POST["Fleet_Type_ID"],
                        $_POST["Role_Type"],
                        $_POST["Role_ID"],
                        $_POST["Access_Type"]
                    );

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

        private function registerRole(string $newRole) {

            $this->availableRoles[] = $newRole;

        }

        private function checkGroupExists($type, $id, $table = "access") {

            $checkQuery = $this->databaseConnection->prepare("SELECT id FROM $table WHERE type=:type AND id=:id");
            $checkQuery->bindParam(":type", $type);
            $checkQuery->bindParam(":id", $id, \PDO::PARAM_INT);
            $checkQuery->execute();
            $checkData = $checkQuery->fetch();

            return !empty($checkData);

        }

        private function checkEntityExists($type, $id, $name) {

            $namesCall = $this->esiHandler->call(endpoint: "/universe/names/", ids: [$id], retries: 1);

            if ($namesCall["Success"]) {

                foreach ($namesCall["Data"] as $eachName) {

                    if ($eachName["category"] === strtolower($type) and $eachName["id"] === (int)$id and $eachName["name"] === htmlspecialchars_decode($name)) {

                        return true;

                    }

                }

                return false;

            }
            else {

                return false;

            }

        }

        private function getNamesFromIDs($ids, $type) {

            $namesCall = $this->esiHandler->call(endpoint: "/universe/names/", ids: $ids, retries: 1);

            if ($namesCall["Success"]) {

                foreach ($namesCall["Data"] as $eachName) {

                    if ($eachName["category"] == $type) {

                        $nameCombinations[$eachName["id"]] = htmlspecialchars($eachName["name"]);

                    }

                }

                return $nameCombinations;

            }
            else {

                return false;

            }

        }

        private function getSearchResults($type, $term, $strict) {

            $approvedTypes = ["Character", "Corporation", "Alliance"];

            if (in_array($type, $approvedTypes)) {

                if ($term !== "") {

                    $searchCall = $this->esiHandler->call(
                        endpoint: "/characters/{character_id}/search/",
                        character_id: $this->characterStats["Character ID"],
                        categories: [strtolower($type)],
                        search: $term,
                        strict: $strict,
                        retries: 1
                    );

                    if ($searchCall["Success"]) {

                        if (!empty($searchCall["Data"])) {

                            $combinedData = $this->getNamesFromIDs(array_slice($searchCall["Data"][strtolower($type)], 0, 1000), strtolower($type));

                            if ($combinedData !== false) {

                                $searchResults = [
                                    "Type" => $type,
                                    "Entities" => $combinedData
                                ];

                                echo json_encode($searchResults);

                            }
                            else {

                                header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                                throw new \Exception("A valid list of IDs from a search failed to convert to names.", 12001);

                            }

                        }
                        else {

                            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

                        }

                    }
                    else {

                        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

                    }

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Unapproved Search Type Requested.", 12002);

            }

        }

        private function addGroup($type, $id, $name) {

            if ($this->checkEntityExists($type, $id, $name)) {

                if (!$this->checkGroupExists($type, $id)) {

                    $creationQuery = $this->databaseConnection->prepare("INSERT INTO access (type, id, name, roles) VALUES (:type, :id, :name, :roles)");
                    $creationQuery->bindParam(":type", $type);
                    $creationQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                    $creationQuery->bindParam(":name", $name);
                    $creationQuery->bindValue(":roles", json_encode([]));
                    $creationQuery->execute();

                    $this->logger->make_log_entry(logType: "Access Group Created", logDetails: "Created " . $type . " Group " . $name . " with ID " . $id . ".");

                    $addedGroup = new \Ridley\Objects\Admin\Groups\Eve($this->dependencies, $id, $name, $type);
                    $addedGroup->renderAccessPanel();

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("A group that was requested to be added already exists.", 12003);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                throw new \Exception("The ID or Name of a group that was requested to be added does not exist.", 12004);

            }

        }

        private function addEntity($type, $id, $name) {

            if ($this->checkEntityExists($type, $id, $name)) {

                if (!$this->checkGroupExists($type, $id, "entitytypes")) {

                    $creationQuery = $this->databaseConnection->prepare("INSERT INTO entitytypes (type, id, name) VALUES (:type, :id, :name)");
                    $creationQuery->bindParam(":type", $type);
                    $creationQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                    $creationQuery->bindParam(":name", $name);
                    $creationQuery->execute();

                    $this->logger->make_log_entry(logType: "Entity Type Created", logDetails: "Created " . $type . " Entity " . $name . " with ID " . $id . ".");

                    $addedEntity = new \Ridley\Objects\Admin\EntityAccessGroup\EntityAccess($this->dependencies, $id, $name, $type);
                    $addedEntity->renderAccessPanel();

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("An entity that was requested to be added already exists.", 12003);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                throw new \Exception("The ID or Name of an entity that was requested to be added does not exist.", 12004);

            }

        }

        private function removeGroup($type, $id) {

            if ($this->checkGroupExists($type, $id)) {

                $deletionQuery = $this->databaseConnection->prepare("DELETE FROM access WHERE type=:type AND id=:id");
                $deletionQuery->bindParam(":type", $type);
                $deletionQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                $deletionQuery->execute();

                $cleanupQuery = $this->databaseConnection->prepare("DELETE FROM fleettypeaccess WHERE roletype=:roletype AND roleid=:roleid");
                $cleanupQuery->bindParam(":roletype", $type);
                $cleanupQuery->bindParam(":roleid", $id, \PDO::PARAM_INT);
                $cleanupQuery->execute();

                $this->logger->make_log_entry(logType: "Access Group Deleted", logDetails: "Deleted " . $type . " Group with ID " . $id . ".");

                echo json_encode(["Status" => "Success"]);

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                throw new \Exception("The group that was requested to be removed does not exist.", 12005);

            }

        }

        private function removeEntity($type, $id) {

            if ($this->checkGroupExists($type, $id, "entitytypes")) {

                $deletionQuery = $this->databaseConnection->prepare("DELETE FROM entitytypes WHERE type=:type AND id=:id");
                $deletionQuery->bindParam(":type", $type);
                $deletionQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                $deletionQuery->execute();

                $cleanupQuery = $this->databaseConnection->prepare("DELETE FROM entitytypeaccess WHERE entitytype=:entitytype AND entityid=:entityid");
                $cleanupQuery->bindParam(":entitytype", $type);
                $cleanupQuery->bindParam(":entityid", $id, \PDO::PARAM_INT);
                $cleanupQuery->execute();

                $this->logger->make_log_entry(logType: "Entity Type Deleted", logDetails: "Deleted " . $type . " Entity with ID " . $id . ".");

                echo json_encode(["Status" => "Success"]);

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                throw new \Exception("The entity that was requested to be removed does not exist.", 12005);

            }

        }

        private function updateGroup($type, $id, $change, $role) {

            if (in_array($role, $this->availableRoles)) {

                $checkQuery = $this->databaseConnection->prepare("SELECT * FROM access WHERE type=:type AND id=:id");
                $checkQuery->bindParam(":type", $type);
                $checkQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                $checkQuery->execute();
                $checkData = $checkQuery->fetch();

                if (!empty($checkData)) {

                    $oldRoles = array_unique(json_decode($checkData["roles"]));

                    switch ($change) {
                        case "Added":

                            $oldRoles[] = $role;
                            break;

                        case "Removed":

                            $searchKey = array_search($role, $oldRoles);
                            if ($searchKey !== false) {
                                unset($oldRoles[$searchKey]);
                            }
                            break;

                        default:

                            header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                            throw new \Exception("Invalid type of change was received for an access group.", 12006);

                    }

                    $newRoles = array_unique(array_values($oldRoles));

                    $updateQuery = $this->databaseConnection->prepare("UPDATE access SET roles=:roles WHERE type=:type AND id=:id");
                    $updateQuery->bindValue(":roles", json_encode($newRoles));
                    $updateQuery->bindParam(":type", $type);
                    $updateQuery->bindParam(":id", $id, \PDO::PARAM_INT);
                    $updateQuery->execute();

                    $this->logger->make_log_entry(logType: "Access Group Updated", logDetails: $type . " Group with ID " . $id . " - " . $role . " Role " . $change);

                    echo json_encode(["Status" => "Success"]);

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                    throw new \Exception("The group for which a change was requested does not exist.", 12007);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("A change was requested using an invalid role.", 12008);

            }

        }

        private function updateEntity($type, $id, $groupType, $groupID, $change) {

            if ($this->checkGroupExists($type, $id, "entitytypes") and $this->checkGroupExists($groupType, $groupID)) {

                if ($change === "Added") {

                    $addQuery = $this->databaseConnection->prepare("INSERT INTO entitytypeaccess (entitytype, entityid, roletype, roleid) VALUES (:entitytype, :entityid, :roletype, :roleid)");
                    $addQuery->bindParam(":entitytype", $type);
                    $addQuery->bindParam(":entityid", $id, \PDO::PARAM_INT);
                    $addQuery->bindParam(":roletype", $groupType);
                    $addQuery->bindParam(":roleid", $groupID, \PDO::PARAM_INT);
                    $addQuery->execute();

                }
                elseif ($change === "Removed") {

                    $removeQuery = $this->databaseConnection->prepare("DELETE FROM entitytypeaccess WHERE entitytype=:entitytype AND entityid=:entityid AND roletype=:roletype AND roleid=:roleid");
                    $removeQuery->bindParam(":entitytype", $type);
                    $removeQuery->bindParam(":entityid", $id, \PDO::PARAM_INT);
                    $removeQuery->bindParam(":roletype", $groupType);
                    $removeQuery->bindParam(":roleid", $groupID, \PDO::PARAM_INT);
                    $removeQuery->execute();

                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("Invalid type of change was received for an entity.", 12006);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("A change was requested using an invalid entity or role.", 12008);

            }

            $this->logger->make_log_entry(logType: "Entity Type Updated", logDetails: $type . " Entity with ID " . $id . " - " . $groupType . " Group with ID " . $groupID . " " . $change);

            echo json_encode(["Status" => "Success"]);

        }

        private function getFleetTypes() {

            $fleetTypes = [];
            
            $checkQuery = $this->databaseConnection->prepare("SELECT id, name FROM fleettypes");
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll();
            
            if (!empty($checkData)) {

                foreach ($checkData as $eachFleetType) {
                
                    $fleetTypes[$eachFleetType["id"]] = ["ID" => $eachFleetType["id"], "Name" => $eachFleetType["name"]];

                }
                
            }

            return $fleetTypes;
            
        }

        private function createFleetType($name) {

            $createQuery = $this->databaseConnection->prepare("INSERT INTO fleettypes (name) VALUES (:name)");
            $createQuery->bindParam(":name", $name);
            $createQuery->execute();

            $this->logger->make_log_entry(logType: "Fleet Type Created", logDetails: "Created Fleet Type " . $name . ".");
            echo json_encode(["Status" => "Success"]);
            
        }

        private function deleteFleetType($incomingID) {

            $fleetTypes = $this->getFleetTypes();

            if (isset($fleetTypes[$incomingID])) {

                $typeClass = "\\Ridley\\Objects\\Admin\\FleetTypes\\" . $this->configVariables["Auth Type"];
                $fleetType = new $typeClass($this->dependencies, $fleetTypes[$incomingID]["ID"], $fleetTypes[$incomingID]["Name"]);

                $fleetType->delete();

                $this->logger->make_log_entry(logType: "Fleet Type Deleted", logDetails: "Deleted Fleet Type " . $fleetTypes[$incomingID]["Name"] . ".");
                echo json_encode(["Status" => "Success"]);

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Tried to delete a nonexistent fleet type.");
                
            }
        
        }

        private function updateFleetTypeAccess($action, $fleetTypeID, $groupType, $groupID, $accessType) {

            $fleetTypes = $this->getFleetTypes();

            if (isset($fleetTypes[$fleetTypeID])) {

                $typeClass = "\\Ridley\\Objects\\Admin\\FleetTypes\\" . $this->configVariables["Auth Type"];
                $fleetType = new $typeClass($this->dependencies, $fleetTypes[$fleetTypeID]["ID"], $fleetTypes[$fleetTypeID]["Name"]);

                if ($action == "Add") {

                    $fleetType->addAccess($groupType, $groupID, $accessType);

                }
                elseif ($action == "Remove") {

                    $fleetType->removeAccess($groupType, $groupID, $accessType);
                    
                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("Unknown modification action for a fleet type.");

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Tried to modify access for a nonexistent fleet type.");

            }

            $this->logger->make_log_entry(logType: "Fleet Type Updated", logDetails: "Updated Fleet Type " . $fleetTypes[$fleetTypeID]["Name"] . ". " . $action . " access to " . $groupType . " group with ID " . $groupID . ".");
            echo json_encode(["Status" => "Success"]);

        }

    }

?>
