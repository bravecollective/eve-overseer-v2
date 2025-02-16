<?php

    namespace Ridley\Models\PlayerPAP;

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

        private function getNamesFromIDs($ids, $type) {

            $nameCombinations = [];

            $namesCall = $this->esiHandler->call(endpoint: "/universe/names/", ids: $ids, retries: 1);

            if ($namesCall["Success"]) {

                foreach ($namesCall["Data"] as $eachName) {

                    if ($eachName["category"] == $type) {

                        $nameCombinations[$eachName["id"]] = htmlspecialchars($eachName["name"]);

                    }

                }

                return $nameCombinations;

            }
            else {

                return false;

            }

        }

        public function getAlliances() {

            $alliances = [];
            $accessRestrictions = $this->controller->generateAccessRestrictions();
            $accessClause = ($accessRestrictions["Enabled"]) ? ("WHERE " . $accessRestrictions["Request"]) : "";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT DISTINCT allianceid AS id
                FROM fleetmembers
                " . $accessClause . "
                ORDER BY allianceid
            ");
            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            $checkQuery->execute();

            while ($incomingID = $checkQuery->fetchColumn()) {
                $alliances[$incomingID] = ["ID" => $incomingID, "Name" => null];
            }

            if (!empty($alliances)) {

                $idChunks = array_chunk(array_keys($alliances), 1000);

                foreach ($idChunks as $eachChunk) {

                    $chunkNames = $this->getNamesFromIDs($eachChunk, "alliance");
                    
                    foreach ($chunkNames as $eachID => $eachName) {

                        $alliances[$eachID]["Name"] = $eachName;

                    }

                }

            }

            uasort($alliances, function ($a, $b) {
                return $a["Name"] <=> $b["Name"];
            });

            return $alliances;

        }

        public function getCorporations() {

            $corporations = [];
            $accessRestrictions = $this->controller->generateAccessRestrictions();
            $accessClause = ($accessRestrictions["Enabled"]) ? ("WHERE " . $accessRestrictions["Request"]) : "";

            $checkQuery = $this->databaseConnection->prepare("
                SELECT DISTINCT corporationid AS id
                FROM fleetmembers
                " . $accessClause . "
                ORDER BY corporationid
            ");
            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            $checkQuery->execute();

            while ($incomingID = $checkQuery->fetchColumn()) {
                $corporations[$incomingID] = ["ID" => $incomingID, "Name" => null];
            }

            if (!empty($corporations)) {

                $idChunks = array_chunk(array_keys($corporations), 1000);

                foreach ($idChunks as $eachChunk) {

                    $chunkNames = $this->getNamesFromIDs($eachChunk, "corporation");
                    
                    foreach ($chunkNames as $eachID => $eachName) {

                        $corporations[$eachID]["Name"] = $eachName;

                    }

                }

            }

            uasort($corporations, function ($a, $b) {
                return $a["Name"] <=> $b["Name"];
            });

            return $corporations;

        }

        private function getRowCount() {
            
            $isPAPMode = $this->controller->isPAPMode();
            $toOrder = $this->controller->generateOrder();
            $recencyConditions = $this->controller->generateRecency();
            $fleetConditions = $this->controller->generateFleetConditions();
            $toWhere = $this->controller->generateWhere();
            $toHaving = $this->controller->generateHaving();

            $normalModeTables = "
            FROM fleetmembers
            LEFT JOIN fleets ON fleets.id = fleetmembers.fleetid
            INNER JOIN userlinks ON userlinks.characterid = fleetmembers.characterid
            INNER JOIN useraccounts ON (useraccounts.accountid = userlinks.accountid AND useraccounts.accounttype = userlinks.accounttype)
            WHERE fleetmembers.endtime IS NOT NULL 
            " . $toWhere["Request"];
            $papModeTables = "
            FROM userlinks
            INNER JOIN useraccounts ON (useraccounts.accountid = userlinks.accountid AND useraccounts.accounttype = userlinks.accounttype)
            LEFT JOIN corpmembers ON corpmembers.characterid = userlinks.characterid
            LEFT JOIN corptrackers ON corptrackers.corporationid = corpmembers.corporationid 
            LEFT JOIN fleetmembers ON (fleetmembers.characterid = userlinks.characterid AND fleetmembers.endtime IS NOT NULL)
            LEFT JOIN fleets ON (fleets.id = fleetmembers.fleetid" . $fleetConditions["Request"] . ")
            ";
            $tables = ($isPAPMode) ? $papModeTables : $normalModeTables;

            $queryText = "
                SELECT COUNT(*) FROM (SELECT 
                    useraccounts.accountid AS account_id, 
                    useraccounts.accountname AS account_name, 
                    useraccounts.accounttype AS account_type,
                    COUNT(DISTINCT (CASE
                        WHEN " . $recencyConditions["Request"] . "
                        THEN fleets.id
                    END)) AS recent_fleets_count,
                    SUM(CASE
                        WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS recent_fleets_time,
                    COUNT(DISTINCT fleets.id) AS total_fleets_count,
                    SUM(CASE
                        WHEN fleets.id IS NOT NULL
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS total_fleets_time,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.commanderid = fleetmembers.characterid AND " . $recencyConditions["Request"] . "
                        THEN fleetmembers.fleetid
                    END)) AS recent_runs_count,
                    SUM(CASE
                        WHEN fleets.commanderid = fleetmembers.characterid AND " . $recencyConditions["Request"] . "
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS recent_runs_time,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.commanderid = fleetmembers.characterid
                        THEN fleetmembers.fleetid
                    END)) AS total_runs_count,
                    SUM(CASE
                        WHEN fleets.commanderid = fleetmembers.characterid
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS total_runs_time,
                    MAX(CASE
                        WHEN fleets.id IS NOT NULL
                        THEN IFNULL((fleetmembers.endtime / 1000), 0)
                        ELSE 0
                    END) AS last_active
                " . $tables . " GROUP BY useraccounts.accountid, useraccounts.accounttype " . $toHaving["Request"] . " " . $toOrder . ") AS subquery
            ";
            
            $countQuery = $this->databaseConnection->prepare($queryText);

            foreach ($recencyConditions["Variables"] as $eachVariable => $eachValue) {
                $countQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($fleetConditions["Variables"] as $eachVariable => $eachValue) {
                $countQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($toWhere["Variables"] as $eachVariable => $eachValue) {
                $countQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($toHaving["Variables"] as $eachVariable => $eachValue) {
                $countQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            
            $countQuery->execute();
            $entryCount = $countQuery->fetchColumn();
            
            $this->rowCount = $entryCount;
            
        }
        
        public function queryRows() {
            
            $isPAPMode = $this->controller->isPAPMode();
            $toOrder = $this->controller->generateOrder();
            $recencyConditions = $this->controller->generateRecency();
            $fleetConditions = $this->controller->generateFleetConditions();
            $toWhere = $this->controller->generateWhere();
            $toHaving = $this->controller->generateHaving();
            $pageOffset = ($this->controller->getPageNumber() * 100);

            $normalModeTables = "
            FROM fleetmembers
            LEFT JOIN fleets ON fleets.id = fleetmembers.fleetid
            INNER JOIN userlinks ON userlinks.characterid = fleetmembers.characterid
            INNER JOIN useraccounts ON (useraccounts.accountid = userlinks.accountid AND useraccounts.accounttype = userlinks.accounttype)
            WHERE fleetmembers.endtime IS NOT NULL 
            " . $toWhere["Request"];
            $papModeTables = "
            FROM userlinks
            INNER JOIN useraccounts ON (useraccounts.accountid = userlinks.accountid AND useraccounts.accounttype = userlinks.accounttype)
            LEFT JOIN corpmembers ON corpmembers.characterid = userlinks.characterid
            LEFT JOIN corptrackers ON corptrackers.corporationid = corpmembers.corporationid 
            LEFT JOIN fleetmembers ON (fleetmembers.characterid = userlinks.characterid AND fleetmembers.endtime IS NOT NULL)
            LEFT JOIN fleets ON (fleets.id = fleetmembers.fleetid" . $fleetConditions["Request"] . ")
            ";
            $tables = ($isPAPMode) ? $papModeTables : $normalModeTables;

            $queryText = "
                SELECT 
                    useraccounts.accountid AS account_id, 
                    useraccounts.accountname AS account_name, 
                    useraccounts.accounttype AS account_type,
                    COUNT(DISTINCT (CASE
                        WHEN " . $recencyConditions["Request"] . "
                        THEN fleets.id
                    END)) AS recent_fleets_count,
                    SUM(CASE
                        WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS recent_fleets_time,
                    COUNT(DISTINCT fleets.id) AS total_fleets_count,
                    SUM(CASE
                        WHEN fleets.id IS NOT NULL
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS total_fleets_time,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.commanderid = fleetmembers.characterid AND " . $recencyConditions["Request"] . "
                        THEN fleetmembers.fleetid
                    END)) AS recent_runs_count,
                    SUM(CASE
                        WHEN fleets.commanderid = fleetmembers.characterid AND " . $recencyConditions["Request"] . "
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS recent_runs_time,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.commanderid = fleetmembers.characterid
                        THEN fleetmembers.fleetid
                    END)) AS total_runs_count,
                    SUM(CASE
                        WHEN fleets.commanderid = fleetmembers.characterid
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END) AS total_runs_time,
                    MAX(CASE
                        WHEN fleets.id IS NOT NULL
                        THEN IFNULL((fleetmembers.endtime / 1000), 0)
                        ELSE 0
                    END) AS last_active
                " . $tables . " GROUP BY useraccounts.accountid, useraccounts.accounttype " . $toHaving["Request"] . " " . $toOrder . " LIMIT 100 OFFSET :offset 
            ";
            
            $papQuery = $this->databaseConnection->prepare($queryText);
            $papQuery->bindParam(":offset", $pageOffset, \PDO::PARAM_INT);

            foreach ($recencyConditions["Variables"] as $eachVariable => $eachValue) {
                $papQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($fleetConditions["Variables"] as $eachVariable => $eachValue) {
                $papQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($toWhere["Variables"] as $eachVariable => $eachValue) {
                $papQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($toHaving["Variables"] as $eachVariable => $eachValue) {
                $papQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            
            $papQuery->execute();
            $papData = $papQuery->fetchAll();
            
            return $papData;
            
        }
        
    }
?>