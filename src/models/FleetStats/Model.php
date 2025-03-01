<?php

    namespace Ridley\Models\FleetStats;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        private $databaseConnection;
        private $esiHandler;
        public $rowCount = 0;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                $this->databaseConnection
            );

            $this->getRowCount();
            
        }

        private function getRowCount() {
            
            $toWhere = $this->controller->generateWhere();
            $toOrder = $this->controller->generateOrder();

            $queryText = "
                SELECT COUNT(*) FROM (
                    SELECT
                        fleets.id AS id,
                        fleets.name AS name,
                        fleettypes.name AS type,
                        cmdr_accounts.accountname AS commander,
                        COUNT(DISTINCT fleetmembers.characterid) AS member_count,
                        COUNT(DISTINCT member_accounts.accountid) AS account_count,
                        fleets.starttime AS start_time,
                        fleets.endtime AS end_time,
                        (fleets.endtime - fleets.starttime) AS duration
                    FROM fleets
                    INNER JOIN userlinks cmdr_links ON fleets.commanderid = cmdr_links.characterid
                    INNER JOIN useraccounts cmdr_accounts ON (cmdr_links.accountid = cmdr_accounts.accountid AND cmdr_links.accounttype = cmdr_accounts.accounttype)
                    LEFT JOIN fleettypes ON fleets.type = fleettypes.id
                    LEFT JOIN fleetmembers ON fleets.id = fleetmembers.fleetid
                    INNER JOIN userlinks member_links ON fleetmembers.characterid = member_links.characterid
                    INNER JOIN useraccounts member_accounts ON (member_links.accountid = member_accounts.accountid AND cmdr_links.accounttype = member_accounts.accounttype)
                    WHERE fleets.endtime IS NOT NULL" . $toWhere["Request"] . "
                    GROUP BY fleets.id " . $toOrder . "
                ) AS subquery
            ";
            
            $countQuery = $this->databaseConnection->prepare($queryText);

            foreach ($toWhere["Variables"] as $eachVariable => $eachValue) {
                $countQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            
            $countQuery->execute();
            $entryCount = $countQuery->fetchColumn();
            
            $this->rowCount = $entryCount;
            
        }
        
        public function queryRows() {
            
            $toWhere = $this->controller->generateWhere();
            $toOrder = $this->controller->generateOrder();
            $pageOffset = ($this->controller->getPageNumber() * 100);

            $queryText = "
                SELECT
                    fleets.id AS id,
                    fleets.name AS name,
                    fleettypes.name AS type,
                    cmdr_accounts.accountname AS commander,
                    COUNT(DISTINCT fleetmembers.characterid) AS member_count,
                    COUNT(DISTINCT member_accounts.accountid) AS account_count,
                    fleets.starttime AS start_time,
                    fleets.endtime AS end_time,
                    (fleets.endtime - fleets.starttime) AS duration
                FROM fleets
                INNER JOIN userlinks cmdr_links ON fleets.commanderid = cmdr_links.characterid
                INNER JOIN useraccounts cmdr_accounts ON (cmdr_links.accountid = cmdr_accounts.accountid AND cmdr_links.accounttype = cmdr_accounts.accounttype)
                LEFT JOIN fleettypes ON fleets.type = fleettypes.id
                LEFT JOIN fleetmembers ON fleets.id = fleetmembers.fleetid
                INNER JOIN userlinks member_links ON fleetmembers.characterid = member_links.characterid
                INNER JOIN useraccounts member_accounts ON (member_links.accountid = member_accounts.accountid AND cmdr_links.accounttype = member_accounts.accounttype)
                WHERE fleets.endtime IS NOT NULL" . $toWhere["Request"] . "
                GROUP BY fleets.id " . $toOrder . " LIMIT 100 OFFSET :offset 
            ";
            
            $fleetQuery = $this->databaseConnection->prepare($queryText);
            $fleetQuery->bindParam(":offset", $pageOffset, \PDO::PARAM_INT);

            foreach ($toWhere["Variables"] as $eachVariable => $eachValue) {
                $fleetQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            
            $fleetQuery->execute();
            $fleetData = $fleetQuery->fetchAll();
            
            return $fleetData;
            
        }
        
    }
?>