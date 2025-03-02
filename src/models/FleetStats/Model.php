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
            
        }

        public function getFleetAffiliations($fleetID) {

            $affiliationsList = [];
            $idsToCheck = [];

            $accessRestrictions = $this->controller->generateAccessRestrictions();
            $restrictionsQuery = ($accessRestrictions["Enabled"]) ? (" AND " . $accessRestrictions["Request"]) : "";

            $affiliationQuery = $this->databaseConnection->prepare("
                SELECT
                    fleetmembers.corporationid AS corporation_id,
                    fleetmembers.allianceid AS alliance_id,
                    COUNT(DISTINCT fleetmembers.characterid) AS count
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                WHERE fleetmembers.fleetid = :fleetid " . $restrictionsQuery . "
                GROUP BY fleetmembers.corporationid, fleetmembers.allianceid
            ");
            $affiliationQuery->bindValue(":fleetid", $fleetID);

            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $affiliationQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $affiliationQuery->execute();

            while ($incomingCorps = $affiliationQuery->fetch(\PDO::FETCH_ASSOC)) {

                if (!is_null($incomingCorps["alliance_id"]) and !in_array($incomingCorps["alliance_id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingCorps["alliance_id"];
                }
                if (!in_array($incomingCorps["corporation_id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingCorps["corporation_id"];
                }

                $allianceIDToUse = $incomingCorps["alliance_id"] ?? 0;

                if (!isset($affiliationsList[$allianceIDToUse])) {

                    $affiliationsList[$allianceIDToUse] = [
                        "ID" => $allianceIDToUse,
                        "Name" => null,
                        "Count" => 0,
                        "Corporations" => []
                    ];

                }

                if (!isset($affiliationsList[$allianceIDToUse]["Corporations"][$incomingCorps["corporation_id"]])) {

                    $affiliationsList[$allianceIDToUse]["Corporations"][$incomingCorps["corporation_id"]] = [
                        "ID" => $incomingCorps["corporation_id"],
                        "Name" => null,
                        "Count" => 0
                    ];

                }

                $affiliationsList[$allianceIDToUse]["Count"] += $incomingCorps["count"];
                $affiliationsList[$allianceIDToUse]["Corporations"][$incomingCorps["corporation_id"]]["Count"] += $incomingCorps["count"];

            }

            $knownNames = [
                "alliance" => [],
                "corporation" => []
            ];
            if (!empty($idsToCheck)) {
                $esiHandler = new \Ridley\Objects\ESI\Handler($this->databaseConnection);

                $namesCall = $esiHandler->call(endpoint: "/universe/names/", ids: $idsToCheck, retries: 1);

                if ($namesCall["Success"]) {

                    foreach ($namesCall["Data"] as $eachID) {

                        if ($eachID["category"] === "alliance" or $eachID["category"] === "corporation") {
    
                            $knownNames[$eachID["category"]][$eachID["id"]] = $eachID["name"];
    
                        }

                    }

                }
                else {
                    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                    throw new \Exception("A names call failed while trying to get affiliations.", 11002);
                }

            }

            uasort($affiliationsList, function($a, $b) {
                return $b["Count"] <=> $a["Count"];
            });

            foreach ($affiliationsList as $eachAllianceID => &$eachAllianceData) {

                if (isset($knownNames["alliance"][$eachAllianceID])) {

                    $eachAllianceData["Name"] = $knownNames["alliance"][$eachAllianceID];

                }
                elseif ($eachAllianceID === 0) {

                    $eachAllianceData["Name"] = "No Alliance";

                }
                else {

                    $eachAllianceData["Name"] = "Unknown Alliance";

                }

                uasort($eachAllianceData["Corporations"], function($a, $b) {
                    return $b["Count"] <=> $a["Count"];
                });

                foreach ($eachAllianceData["Corporations"] as $eachCorporationID => &$eachCorporationData) {

                    if (isset($knownNames["corporation"][$eachCorporationID])) {

                        $eachCorporationData["Name"] = $knownNames["corporation"][$eachCorporationID];

                    }
                    else {

                        $eachCorporationData["Name"] = "Unknown Corporation";

                    }

                }

            }

            return $affiliationsList;

        }

        public function getFleetMembers($fleetID) {

            $memberList = [];
            $idsToCheck = [];

            $accessRestrictions = $this->controller->generateAccessRestrictions();
            $restrictionsQuery = ($accessRestrictions["Enabled"]) ? (" AND " . $accessRestrictions["Request"]) : "";

            $memberQuery = $this->databaseConnection->prepare("
                SELECT
                    fleetmembers.characterid AS id,
                    fleetmembers.corporationid AS corporation_id,
                    fleetmembers.allianceid AS alliance_id,
                    MIN(fleetmembers.starttime) AS first_instance,
                    COUNT(fleetmembers.characterid) AS instances,
                    SUM(fleetmembers.endtime - fleetmembers.starttime) AS time_in_fleet,
                    SUM(CASE
                        WHEN 
                            fleetmembers.role != 'squad_member'
                        THEN 1
                    END) AS instances_in_command,
                    SUM(CASE
                        WHEN 
                            fleetmembers.role != 'squad_member'
                        THEN (fleetmembers.endtime - fleetmembers.starttime)
                    END) AS time_in_command
                FROM fleetmembers
                LEFT JOIN fleets ON fleetmembers.fleetid = fleets.id
                WHERE fleetmembers.fleetid = :fleetid " . $restrictionsQuery . "
                GROUP BY fleetmembers.characterid, fleetmembers.corporationid, fleetmembers.allianceid
            ");
            $memberQuery->bindValue(":fleetid", $fleetID);

            foreach ($accessRestrictions["Variables"] as $eachVariable => $eachValue) {
                $memberQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $memberQuery->execute();

            while ($incomingMembers = $memberQuery->fetch(\PDO::FETCH_ASSOC)) {

                if (!in_array($incomingMembers["id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingMembers["id"];
                }
                if (!in_array($incomingMembers["corporation_id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingMembers["corporation_id"];
                }
                if (!is_null($incomingMembers["alliance_id"]) and !in_array($incomingMembers["alliance_id"], $idsToCheck)) {
                    $idsToCheck[] = $incomingMembers["alliance_id"];
                }

                $allianceIDToUse = $incomingMembers["alliance_id"] ?? 0;

                $memberList[$incomingMembers["id"]] = [
                    "ID" => $incomingMembers["id"],
                    "Name" => "",
                    "Corporation ID" => $incomingMembers["corporation_id"],
                    "Corporation Name" => "",
                    "Alliance ID" => $allianceIDToUse,
                    "AllianceName" => "",
                    "First Instance" => $incomingMembers["first_instance"],
                    "Total Instances" => $incomingMembers["instances"],
                    "Time in Fleet" => $incomingMembers["time_in_fleet"],
                    "Instances in Command" => $incomingMembers["instances_in_command"],
                    "Time in Command" => $incomingMembers["time_in_command"]
                ];

            }

            $knownNames = [
                "character" => [],
                "corporation" => [],
                "alliance" => [],
            ];
            if (!empty($idsToCheck)) {
                $esiHandler = new \Ridley\Objects\ESI\Handler($this->databaseConnection);

                $namesCall = $esiHandler->call(endpoint: "/universe/names/", ids: $idsToCheck, retries: 1);

                if ($namesCall["Success"]) {

                    foreach ($namesCall["Data"] as $eachID) {

                        if ($eachID["category"] === "alliance" or $eachID["category"] === "corporation" or $eachID["category"] === "character") {
    
                            $knownNames[$eachID["category"]][$eachID["id"]] = $eachID["name"];
    
                        }

                    }

                }
                else {
                    header($_SERVER["SERVER_PROTOCOL"] . " 500 Internal Server Error");
                    throw new \Exception("A names call failed while trying to get member data.", 11002);
                }

            }

            foreach ($memberList as $eachMemberID => &$eachMemberData) {

                if (isset($knownNames["character"][$eachMemberData["ID"]])) {
                    $eachMemberData["Name"] = $knownNames["character"][$eachMemberData["ID"]];
                }
                else {
                    $eachMemberData["Name"] = "Unknown Character";
                }

                if (isset($knownNames["corporation"][$eachMemberData["Corporation ID"]])) {
                    $eachMemberData["Corporation Name"] = $knownNames["corporation"][$eachMemberData["Corporation ID"]];
                }
                else {
                    $eachMemberData["Corporation Name"] = "Unknown Corporation";
                }

                if (isset($knownNames["alliance"][$eachMemberData["Alliance ID"]])) {
                    $eachMemberData["Alliance Name"] = $knownNames["alliance"][$eachMemberData["Alliance ID"]];
                } 
                elseif ($eachMemberData["Alliance ID"] === 0) {
                    $eachMemberData["Alliance Name"] = "No Alliance";
                }
                else {
                    $eachMemberData["Alliance Name"] = "Unknown Alliance";
                }

            }

            uasort($memberList, function($a, $b) {
                return strtolower($a["Name"]) <=> strtolower($b["Name"]);
            });

            return $memberList;

        }

        public function getRowCount() {
            
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