<?php

    namespace Ridley\Models\Home;

    class Model implements \Ridley\Interfaces\Model {
        
        private $databaseConnection;
        private $characterData;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->characterData = $this->dependencies->get("Character Stats");
            
        }

        public function checkIfInFleet() {

            $checkQuery = $this->databaseConnection->prepare("SELECT COUNT(*) FROM fleetmembers WHERE characterid=:characterid AND endtime IS NULL");
            $checkQuery->bindValue(":characterid", $this->characterData["Character ID"]);
            $checkQuery->execute();

            return ($checkQuery->fetchColumn() > 0);

        }
        
    }
?>