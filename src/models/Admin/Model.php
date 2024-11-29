<?php

    namespace Ridley\Models\Admin;

    class Model implements \Ridley\Interfaces\Model {
        
        private $knownGroups;
        private $controller;
        private $databaseConnection;
        private $fleetTypes = [];
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->knownGroups = $this->controller->passKnownGroups();
            
            $this->populateFleetTypes();
        }
        
        public function getGroups() {
            
            $activeGroups = [];
            
            foreach ($this->knownGroups as $subGroupName => $subGroups) {
                
                if (!empty($subGroups)) {
                    
                    $activeGroups[$subGroupName] = $subGroups;
                    
                }
                
            }
            
            return $activeGroups;
            
        }

        public function populateFleetTypes() {
            
            $checkQuery = $this->databaseConnection->prepare("SELECT id, name FROM fleettypes");
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll();
            
            if (!empty($checkData)) {

                foreach ($checkData as $eachFleetType) {
                
                    $this->fleetTypes[] = ["ID" => $eachFleetType["id"], "Name" => $eachFleetType["name"]];

                }
                
            }
            
        }

        public function getFleetTypes() {

            return $this->fleetTypes;
            
        }
        
    }
?>