<?php

    namespace Ridley\Models\PersonalStats;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        private $databaseConnection;
        private $characterData;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->characterData = $this->dependencies->get("Character Stats");
            
        }

        public function getAccountInformation() {

            $returnData = [
                "Name" => "Unknown",
                "Type" => "Unknown"
            ];

            $accountQuery = $this->databaseConnection->prepare("
                SELECT 
                    useraccounts.accountname AS name,
                    useraccounts.accounttype AS type
                FROM userlinks
                INNER JOIN useraccounts on userlinks.accountid = useraccounts.accountid
                WHERE characterid=:characterid
            ");
            $accountQuery->bindValue(":characterid", $this->characterData["Character ID"]);
            $accountQuery->execute();

            while ($accountData = $accountQuery->fetch(\PDO::FETCH_ASSOC)) {
                $returnData["Name"] = $accountData["name"];
                $returnData["Type"] = $accountData["type"];
            }

            return $returnData;

        }

        public function getParticipationInformation() {

            $returnData = [
                "Count" => 0,
                "Time" => 0
            ];

            $toWhere = $this->controller->generateWhere();

            $papQuery = $this->databaseConnection->prepare("
                SELECT 
                    fleetmembers.characterid AS id,
                    COUNT(DISTINCT fleets.id) AS count,
                    SUM(IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)) AS time
                FROM fleetmembers
                LEFT JOIN fleets ON fleets.id = fleetmembers.fleetid
                WHERE fleetmembers.endtime IS NOT NULL 
                " . $toWhere["Request"] . "
                GROUP BY fleetmembers.characterid
            ");
            foreach ($toWhere["Variables"] as $eachVariable => $eachValue) {
                $papQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            $papQuery->execute();

            while ($papData = $papQuery->fetch(\PDO::FETCH_ASSOC)) {
                $returnData["Count"] = $papData["count"];
                $returnData["Time"] = $papData["time"];
            }

            return $returnData;

        }
        
    }
?>