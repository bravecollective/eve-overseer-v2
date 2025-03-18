<?php

    namespace Ridley\Models\AlliancePAP;

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
            
            $toOrder = $this->controller->generateOrder();
            $recencyConditions = $this->controller->generateRecency();
            $fleetConditions = $this->controller->generateFleetConditions();
            $toWhere = $this->controller->generateWhere();

            $queryText = "
                SELECT COUNT(*) FROM (
                    SELECT
                        corptrackers.corporationid AS corporation_id,
                        corptrackers.corporationname AS corporation_name,
                        corptrackers.allianceid AS alliance_id,
                        corptrackers.alliancename AS alliance_name,
                        (CASE
                            WHEN corptrackers.recheck < UNIX_TIMESTAMP()
                            THEN 1
                            ELSE 0
                        END) AS recheck,
                        COUNT(DISTINCT corpmembers.characterid) AS members,
                        COUNT(DISTINCT fleets.id) AS total_fleets,
                        COUNT(DISTINCT (CASE
                            WHEN " . $recencyConditions["Request"] . "
                            THEN fleets.id
                        END)) AS recent_fleets,
                        COUNT(DISTINCT (CASE
                            WHEN fleets.id IS NOT NULL
                            THEN fleetmembers.characterid
                        END)) AS knowns,
                        COUNT(DISTINCT (CASE
                            WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                            THEN fleetmembers.characterid
                        END)) AS actives,
                        COUNT(DISTINCT (CASE
                            WHEN fleets.id IS NOT NULL
                            THEN CONCAT(fleetmembers.characterid, fleetmembers.fleetid)
                        END)) AS total_paps_count,
                        SUM((CASE
                            WHEN fleets.id IS NOT NULL
                            THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                            ELSE 0
                        END)) AS total_paps_time,
                        COUNT(DISTINCT (CASE
                            WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                            THEN CONCAT(fleetmembers.characterid, fleetmembers.fleetid)
                        END)) AS recent_paps_count,
                        SUM((CASE
                            WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                            THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                            ELSE 0
                        END)) AS recent_paps_time
                    FROM corptrackers
                    LEFT JOIN corpmembers ON corpmembers.corporationid=corptrackers.corporationid
                    LEFT JOIN fleetmembers ON (fleetmembers.characterid = corpmembers.characterid AND fleetmembers.endtime IS NOT NULL)
                    LEFT JOIN fleets ON (fleets.id = fleetmembers.fleetid" . $fleetConditions["Request"] . ")
                    " . $toWhere["Request"] . "
                    GROUP BY corptrackers.corporationid " . $toOrder . "
                ) AS subquery
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
            
            $countQuery->execute();
            $entryCount = $countQuery->fetchColumn();
            
            $this->rowCount = $entryCount;
            
        }
        
        public function queryRows() {
            
            $toOrder = $this->controller->generateOrder();
            $recencyConditions = $this->controller->generateRecency();
            $fleetConditions = $this->controller->generateFleetConditions();
            $toWhere = $this->controller->generateWhere();
            $pageOffset = ($this->controller->getPageNumber() * 100);

            $queryText = "
                SELECT
                    corptrackers.corporationid AS corporation_id,
                    corptrackers.corporationname AS corporation_name,
                    corptrackers.allianceid AS alliance_id,
                    corptrackers.alliancename AS alliance_name,
                    (CASE
                        WHEN corptrackers.recheck < UNIX_TIMESTAMP()
                        THEN 1
                        ELSE 0
                    END) AS recheck,
                    COUNT(DISTINCT corpmembers.characterid) AS members,
                    COUNT(DISTINCT fleets.id) AS total_fleets,
                    COUNT(DISTINCT (CASE
                        WHEN " . $recencyConditions["Request"] . "
                        THEN fleets.id
                    END)) AS recent_fleets,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.id IS NOT NULL
                        THEN fleetmembers.characterid
                    END)) AS knowns,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                        THEN fleetmembers.characterid
                    END)) AS actives,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.id IS NOT NULL
                        THEN CONCAT(fleetmembers.characterid, fleetmembers.fleetid)
                    END)) AS total_paps_count,
                    SUM((CASE
                        WHEN fleets.id IS NOT NULL
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END)) AS total_paps_time,
                    COUNT(DISTINCT (CASE
                        WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                        THEN CONCAT(fleetmembers.characterid, fleetmembers.fleetid)
                    END)) AS recent_paps_count,
                    SUM((CASE
                        WHEN fleets.id IS NOT NULL AND " . $recencyConditions["Request"] . "
                        THEN IFNULL((fleetmembers.endtime - fleetmembers.starttime), 0)
                        ELSE 0
                    END)) AS recent_paps_time
                FROM corptrackers
                LEFT JOIN corpmembers ON corpmembers.corporationid=corptrackers.corporationid
                LEFT JOIN fleetmembers ON (fleetmembers.characterid = corpmembers.characterid AND fleetmembers.endtime IS NOT NULL)
                LEFT JOIN fleets ON (fleets.id = fleetmembers.fleetid" . $fleetConditions["Request"] . ")
                " . $toWhere["Request"] . "
                GROUP BY corptrackers.corporationid " . $toOrder . " LIMIT 100 OFFSET :offset 
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
            
            $papQuery->execute();
            $papData = $papQuery->fetchAll();
            
            return $papData;
            
        }
        
    }
?>