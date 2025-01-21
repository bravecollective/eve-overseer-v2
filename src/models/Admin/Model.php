<?php

    namespace Ridley\Models\Admin;

    class Model implements \Ridley\Interfaces\Model {
        
        private $knownGroups;
        private $controller;
        private $databaseConnection;
        private $entities = [];
        private $fleetTypes = [];
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->knownGroups = $this->controller->passKnownGroups();
            
            $this->populateEntities();
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

        public function populateEntities() {

            $checkQuery = $this->databaseConnection->prepare("SELECT type, id, name FROM entitytypes");
            $checkQuery->execute();
            $checkData = $checkQuery->fetchAll();
            
            if (!empty($checkData)) {

                foreach ($checkData as $eachEntity) {
                
                    if (!isset($this->entities[$eachEntity["type"]])) {

                        $this->entities[$eachEntity["type"]] = [];

                    }

                    $this->entities[$eachEntity["type"]][$eachEntity["id"]] = new \Ridley\Objects\Admin\EntityAccessGroup\EntityAccess($this->dependencies, $eachEntity["id"], $eachEntity["name"], $eachEntity["type"]);

                }
                
            }

        }

        public function getEntities() {
            
            return $this->entities;
            
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