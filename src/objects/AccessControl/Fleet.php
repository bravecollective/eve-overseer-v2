<?php

    namespace Ridley\Objects\AccessControl;

    use Ridley\Core\Exceptions\ESIException;

    class Fleet {

        private $databaseConnection;
        private $accessRoles;
        private $characterData;
        private $coreGroups;
        private $configVariables;

        function __construct(
            private $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");
            $this->configVariables = $this->dependencies->get("Configuration Variables");

        }

        public function checkForAuditAccessBypass() {

            return in_array("Super Admin", $this->accessRoles) or in_array("View Fleet Stats", $this->accessRoles);

        }

        public function getFleetTypes($forAudit = false) {

            $fleetTypes = [];
            $accessType = $forAudit ? "Audit" : "Command";

            if ($forAudit and $this->checkForAuditAccessBypass()) {

                $checkQuery = $this->databaseConnection->prepare("
                    SELECT fleettypes.id AS id, fleettypes.name AS name FROM fleettypes
                ");
                $checkQuery->execute();

                while ($incomingTypes = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {
                    $fleetTypes[$incomingTypes["id"]] = $incomingTypes["name"];
                }

            }
            else {

                $checkQuery = $this->databaseConnection->prepare("
                    SELECT fleettypes.id AS id, fleettypes.name AS name, fleettypeaccess.roletype AS roletype, fleettypeaccess.roleid AS roleid
                    FROM fleettypeaccess
                    LEFT JOIN fleettypes
                    ON fleettypeaccess.typeid = fleettypes.id
                    WHERE fleettypeaccess.accesstype = :accesstype
                    ORDER BY name ASC
                ");
                $checkQuery->bindValue(":accesstype", $accessType);
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

            }

            return $fleetTypes;

        }

    }
?>
