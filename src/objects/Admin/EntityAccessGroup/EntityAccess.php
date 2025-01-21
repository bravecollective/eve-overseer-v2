<?php

    namespace Ridley\Objects\Admin\EntityAccessGroup;

    class EntityAccess {
        
        private $databaseConnection;
        private $logger;
        private $configVariables;
        
        function __construct(
            private $dependencies,
            private $id,
            private $name,
            private $type
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            
        }
        
        public function getAccessGroups() {
            
            $scope = ($this->configVariables["Auth Type"] === "Neucore") ? "access.type = 'Neucore'" : "access.type IN ('Alliance', 'Corporation', 'Character')";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT access.name AS name, access.type AS type, access.id AS id, 
                CASE 
                    WHEN entitytypeaccess.roleid IS NOT NULL THEN 1
                    ELSE 0
                END AS found
                FROM access 
                LEFT JOIN entitytypeaccess ON (entitytype = :entitytype AND entityid = :entityid AND entitytypeaccess.roletype = access.type AND entitytypeaccess.roleid = access.id)
                WHERE " . $scope . "
                ORDER BY access.type, access.name"
            );
            $checkQuery->bindParam(":entitytype", $this->type);
            $checkQuery->bindParam(":entityid", $this->id, \PDO::PARAM_INT);
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($checkData)) {
                
                return $checkData;
                
            }
            else {

                return [];

            }
            
        }
        
        public function renderAccessPanel() {
                        
            ?>
            
            <div class="card bg-dark text-white mt-3 mb-3 entity-access-card" data-type="<?php echo htmlspecialchars($this->type); ?>" data-id="<?php echo htmlspecialchars($this->id); ?>">
                <h4 class="card-header"><?php echo htmlspecialchars($this->name); ?></h4>
                <div class="card-body">
                    <?php

                    $lastType = null;
                    
                    foreach ($this->getAccessGroups() as $eachGroup) {
                        
                        $showCheck = boolval($eachGroup["found"]) ? "checked" : "";
                        $boxID = "entity-" . strtolower($this->type) . "-" . $this->id . "-group-" . strtolower($eachGroup["type"]) . "-" . $eachGroup["id"];
                        
                        if ($eachGroup["type"] !== $lastType) {
                            echo (!is_null($lastType) ? "<br>" : "");
                            echo "<div class='d-inline-block me-3 fw-bold'>" . htmlspecialchars($eachGroup["type"]) . "</div>";
                            $lastType = $eachGroup["type"];

                        }

                        ?>
                        
                        <div class="form-check form-switch form-check-inline">
                            <input class="form-check-input entity-acl-switch" data-entity-type="<?php echo htmlspecialchars($this->type); ?>" data-entity-id="<?php echo htmlspecialchars($this->id); ?>" data-group-type="<?php echo htmlspecialchars($eachGroup["type"]); ?>" data-group-id="<?php echo htmlspecialchars($eachGroup["id"]); ?>" type="checkbox" id="<?php echo htmlspecialchars($boxID); ?>" <?php echo $showCheck; ?>>
                            <label class="form-check-label" for="<?php echo htmlspecialchars($boxID); ?>"><?php echo htmlspecialchars($eachGroup["name"]); ?></label>
                        </div>
                        
                        <?php
                        
                    }
                    
                    ?>
                    <br>
                    <button class="btn btn-sm btn-outline-danger mt-3 entity-delete-button" data-type="<?php echo htmlspecialchars($this->type); ?>" data-id="<?php echo htmlspecialchars($this->id); ?>">Delete Entity</button>
                </div>
            </div>
            
            <?php
        }
        
        public function grantAccess($newType, $newID) {
            
            $grantQuery = $this->databaseConnection->prepare("REPLACE INTO entitytypeaccess (entitytype, entityid, roletype, roleid) VALUES (:entitytype, :entityid, :roletype, :roleid)");
            $grantQuery->bindParam(":entitytype", $this->type);
            $grantQuery->bindParam(":entityid", $this->id, \PDO::PARAM_INT);
            $grantQuery->bindParam(":roletype", $newType);
            $grantQuery->bindParam(":roleid", $newID, \PDO::PARAM_INT);
            $grantQuery->execute();
            
        }

        public function removeAccess($oldType, $oldID) {
            
            $grantQuery = $this->databaseConnection->prepare("DELETE FROM entitytypeaccess WHERE entitytype = :entitytype AND entityid = :entityid AND roletype = :roletype AND roleid = :roleid)");
            $grantQuery->bindParam(":entitytype", $this->type);
            $grantQuery->bindParam(":entityid", $this->id, \PDO::PARAM_INT);
            $grantQuery->bindParam(":roletype", $oldType);
            $grantQuery->bindParam(":roleid", $oldID, \PDO::PARAM_INT);
            $grantQuery->execute();
            
        }
        
    }

?>