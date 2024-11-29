<?php

    namespace Ridley\Models\Tracking;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        private $databaseConnection;
        private $configVariables;
        private $authorizationControl;
        private $characterData;
        private $coreGroups;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            $this->authorizationControl = $this->dependencies->get("Authorization Control");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");
            
        }


        public function checkIfAuthedFC() {

            try {
                $tokenCheck = $this->authorizationControl->getAccessToken("FC", $this->characterData["Character ID"]);
            }
            catch (\Exception $exception) {
                $tokenCheck = false;
            }
            return ($tokenCheck !== false);

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
        
    }
?>