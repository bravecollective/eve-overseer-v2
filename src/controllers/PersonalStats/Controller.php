<?php

    namespace Ridley\Controllers\PersonalStats;

    class Controller implements \Ridley\Interfaces\Controller {
        
        private $databaseConnection;
        private $characterData;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->characterData = $this->dependencies->get("Character Stats");

        }

        public function getFleetTypes() {

            $fleetTypes = [];

            $checkQuery = $this->databaseConnection->prepare("
                SELECT 
                    DISTINCT fleettypes.id AS id,
                    fleettypes.name AS name
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                LEFT JOIN fleettypes ON fleets.type = fleettypes.id
                WHERE fleetmembers.endtime IS NOT NULL AND characterid=:characterid
                ORDER BY fleettypes.name
            ");
            $checkQuery->bindValue(":characterid", $this->characterData["Character ID"]);
            $checkQuery->execute();

            while ($incomingTypes = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {
                $fleetTypes[$incomingTypes["id"]] = $incomingTypes["name"];
            }

            return $fleetTypes;

        }

        public function generateWhere() {
            
            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            //Access Restriction
            $filterDetails["Request"] .= " AND fleetmembers.characterid = :characterid";
            $filterDetails["Variables"][":characterid"] = ["Value" => $this->characterData["Character ID"], "Type" => \PDO::PARAM_INT];

            //Filter by Start Date
            if (isset($_POST["date-start"]) and $_POST["date-start"] != "") {
                
                $incomingStartTime = strtotime($_POST["date-start"]);
                
                if ($incomingStartTime !== false) {

                    $incomingStartTime *= 1000;
                    
                    $filterDetails["Request"] .= " AND fleetmembers.starttime >= :date_start";
                    
                    $filterDetails["Variables"][":date_start"] = ["Value" => $incomingStartTime, "Type" => \PDO::PARAM_INT];
                    
                }
                
            }
            
            //Filter by End Date
            if (isset($_POST["date-end"]) and $_POST["date-end"] != "") {
                
                $incomingEndTime = strtotime($_POST["date-end"]);
                
                if ($incomingEndTime !== false) {
                    
                    $incomingEndTime += 86400;
                    $incomingEndTime *= 1000;
                
                    $filterDetails["Request"] .= " AND fleetmembers.endtime <= :date_end";
                    
                    $filterDetails["Variables"][":date_end"] = ["Value" => $incomingEndTime, "Type" => \PDO::PARAM_INT];
                    
                }
            }
            
            //Filter by Fleets
            $fleetPlaceholders = [];
            $fleetCounter = 0;
            if (isset($_POST["fleet-type"]) and !empty($_POST["fleet-type"])) {

                $knownFleets = $this->getFleetTypes();

                foreach ($_POST["fleet-type"] as $eachID) {

                    if (isset($knownFleets[$eachID])) {
                        $fleetPlaceholders[] = (":fleet_" . $fleetCounter);
                        $filterDetails["Variables"][(":fleet_" . $fleetCounter)] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                        $fleetCounter++;
                    }
                    
                }

            }

            if ($fleetCounter > 0) {

                $filterDetails["Request"] .= (" AND fleets.type IN (" . implode(",", $fleetPlaceholders) . ")");

            }

            
            return $filterDetails;
            
        }

    }

?>