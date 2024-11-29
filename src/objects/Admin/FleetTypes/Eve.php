<?php

    namespace Ridley\Objects\Admin\FleetTypes;

    class Eve {
        
        private $accessTypes = ["Command", "Audit"];
        private $hasAccess = [];
        private $databaseConnection;
        private $logger;
        
        function __construct(
            private $dependencies,
            private $id,
            private $name
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            
        }
        
        private function getGroups($onlyIDs=false) {
            
            $checkQuery = $this->databaseConnection->prepare("SELECT id, name, type FROM access WHERE type IN (:type_char, :type_corp, :type_ally)");
            $checkQuery->bindValue(":type_char", "Character");
            $checkQuery->bindValue(":type_corp", "Corporation");
            $checkQuery->bindValue(":type_ally", "Alliance");
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll();
            
            if (!empty($checkData)) {
                
                if ($onlyIDs) {

                    //Generates an array containing only the ids of the results
                    return array_map(fn($row) => $row["id"], $checkData);

                }
                else {

                    return $checkData;

                }
                
            }
            else {

                return [];

            }
            
        }

        private function populateAccess() {
            
            $checkQuery = $this->databaseConnection->prepare("SELECT accesstype, roletype, roleid FROM fleettypeaccess WHERE typeid=:typeid AND roletype IN (:type_char, :type_corp, :type_ally)");
            $checkQuery->bindParam(":typeid", $this->id);
            $checkQuery->bindValue(":type_char", "Character");
            $checkQuery->bindValue(":type_corp", "Corporation");
            $checkQuery->bindValue(":type_ally", "Alliance");
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll();
            
            if (!empty($checkData)) {
                
                foreach ($checkData as $eachRole) {

                    if (!isset($this->hasAccess[$eachRole["roletype"]])) {
                        $this->hasAccess[$eachRole["roletype"]] = [];
                    }
                    if (!isset($this->hasAccess[$eachRole["roletype"]][$eachRole["roleid"]])) {
                        $this->hasAccess[$eachRole["roletype"]][$eachRole["roleid"]] = [];

                        foreach ($this->accessTypes as $eachType) {
                            $this->hasAccess[$eachRole["roletype"]][$eachRole["roleid"]][$eachType] = false;
                        }

                    }

                    $this->hasAccess[$eachRole["roletype"]][$eachRole["roleid"]][$eachRole["accesstype"]] = true;

                }
                
            }
            
        }
        
        public function renderAccessPanels() {
            
            $groups = $this->getGroups();
            $this->populateAccess();
            
            foreach ($groups as $eachGroup) {
                ?>
                
                <div class="card bg-dark text-white mt-3 mb-3">
                    <h4 class="card-header"><?php echo htmlspecialchars($eachGroup["name"]); ?></h4>
                    <div class="card-body">
                        <?php
                        
                        foreach ($this->accessTypes as $eachAccess) {
                            
                            $showCheck = (isset($this->hasAccess[$eachGroup["type"]][$eachGroup["id"]][$eachAccess]) and $this->hasAccess[$eachGroup["type"]][$eachGroup["id"]][$eachAccess]) ? "checked" : "";
                            $boxID = "fleet-" . $this->id . "-" . strtolower($eachGroup["type"]) . "-group-" . $eachGroup["id"] . "-" . str_replace(" ", "_", strtolower($eachAccess));
                            
                            ?>
                            
                            <div class="form-check form-switch form-check-inline">
                                <input class="form-check-input fleet-acl-switch" data-fleet-type="<?php echo htmlspecialchars($this->id); ?>" data-group-type="<?php echo htmlspecialchars($eachGroup["type"]); ?>" data-group="<?php echo htmlspecialchars($eachGroup["id"]); ?>" data-access-type="<?php echo htmlspecialchars($eachAccess); ?>" type="checkbox" id="<?php echo htmlspecialchars($boxID); ?>" <?php echo $showCheck; ?>>
                                <label class="form-check-label" for="<?php echo htmlspecialchars($boxID); ?>"><?php echo htmlspecialchars($eachAccess); ?></label>
                            </div>
                            
                            <?php
                            
                        }
                        
                        ?>
                    </div>
                </div>
                
                <?php

            }

        }

        public function delete() {

            $deleteQuery = $this->databaseConnection->prepare("DELETE FROM fleettypes WHERE id=:id");
            $deleteQuery->bindParam(":id", $this->id);
            $deleteQuery->execute();

            $cleanupQuery = $this->databaseConnection->prepare("DELETE FROM fleettypeaccess WHERE typeid=:typeid");
            $cleanupQuery->bindParam(":typeid", $this->id);
            $cleanupQuery->execute();

        }

        public function addAccess($roleType, $roleID, $accessType) {

            if (
                in_array($accessType, $this->accessTypes) 
                and in_array($roleType, ["Character", "Corporation", "Alliance"])
                and in_array($roleID, $this->getGroups(true))
            ) {

                $addQuery = $this->databaseConnection->prepare("REPLACE INTO fleettypeaccess (typeid, roletype, roleid, accesstype) VALUES (:typeid, :role_type, :role_id, :accesstype)");
                $addQuery->bindParam(":typeid", $this->id);
                $addQuery->bindParam(":role_type", $roleType);
                $addQuery->bindParam(":role_id", $roleID);
                $addQuery->bindParam(":accesstype", $accessType);
                $addQuery->execute();

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Tried to add access for a nonexistent Role ID, Role Type, or Access Type.");

            }

        }

        public function removeAccess($roleType, $roleID, $accessType) {

            $removeQuery = $this->databaseConnection->prepare("DELETE FROM fleettypeaccess WHERE typeid=:typeid AND roletype=:role_type AND roleid=:role_id AND accesstype=:accesstype");
            $removeQuery->bindParam(":typeid", $this->id);
            $removeQuery->bindParam(":role_type", $roleType);
            $removeQuery->bindParam(":role_id", $roleID);
            $removeQuery->bindParam(":accesstype", $accessType);
            $removeQuery->execute();

        }
        
    }

?>