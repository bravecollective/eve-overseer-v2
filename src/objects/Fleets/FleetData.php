<?php

    namespace Ridley\Objects\Fleets;

    class FleetData {

        private $databaseConnection;
        private $userAuthorization;

        private $esiHandler;

        private $WingsSquadsAndCharacters = [
            -1 => [
                "Name" => "Fleet", 
                "Commander" => [
                    "Name" => "",
                    "Corporation" => "",
                    "Alliance" => "",
                    "Ship" => "", 
                    "Region" => "",
                    "System" => ""
                ],
                "Squads" => []
            ]
        ];
        private $ShipGroupsAndTypes = [];
        private $AlliancesAndCorporations = [];
        private $RegionsAndSystems = [];
        private $headerData = [
            "Fleet Name" => "",
            "Fleet Type" => "", 
            "Commander Name" => "",
            "Fleet Members" => 0,
            "User Accounts" => 0
        ];
        private $knownAccounts = [];

        public $fleetFound = false;

        function __construct(
            private $dependencies,
            private $incomingFleetID = null,
            private $incomingShareKey = null,
            private $onlyWithCommander = false,
            private $onlyInWing = null,
            private $onlyInSquad = null
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->userAuthorization = $this->dependencies->get("Authorization Control");

            $this->buildData(!is_null($this->incomingShareKey));
            
        }

        private function buildData($fromShareKey) {

            //Pull Fleet and Commander IDs
            if ($fromShareKey) {

                $fleetQuery = $this->databaseConnection->prepare("SELECT fleets.id AS id, fleets.name AS name, fleettypes.name AS type, commanderid FROM fleets LEFT JOIN fleettypes ON fleets.type = fleettypes.id WHERE status=:status AND sharekey=:sharekey");
                $fleetQuery->bindValue(":status", "Active");
                $fleetQuery->bindParam(":sharekey", $this->incomingShareKey);

            }
            else {

                $fleetQuery = $this->databaseConnection->prepare("SELECT fleets.id AS id, fleets.name AS name, fleettypes.name AS type, commanderid FROM fleets LEFT JOIN fleettypes ON fleets.type = fleettypes.id WHERE status=:status AND fleets.id=:id");
                $fleetQuery->bindValue(":status", "Active");
                $fleetQuery->bindParam(":id", $this->incomingFleetID);

            }

            $fleetQuery->execute();
            $fleetInfo = $fleetQuery->fetch(\PDO::FETCH_ASSOC);

            if ($fleetInfo !== false) {

                $fleetID = $fleetInfo["id"];
                $commanderID = $fleetInfo["commanderid"];
                $commanderSystemID = null;
                $this->headerData["Fleet Name"] = $fleetInfo["name"];
                $this->headerData["Fleet Type"] = $fleetInfo["type"];

            }
            else {

                return;
                
            }

            //Get Fleet Structure
            $this->esiHandler = new \Ridley\Objects\ESI\Handler(
                $this->databaseConnection,
                $this->userAuthorization->getAccessToken("FC", $commanderID)
            );

            $fleetStructureCall = $this->esiHandler->call(endpoint: "/fleets/{fleet_id}/wings/", fleet_id: $fleetID, retries: 1);

            if ($fleetStructureCall["Success"]) {

                foreach ($fleetStructureCall["Data"] as $eachWing) {

                    $this->WingsSquadsAndCharacters[(int)$eachWing["id"]] = [
                        "Name" => $eachWing["name"],
                        "Commander" => [
                            "Name" => "",
                            "Corporation" => "",
                            "Alliance" => "",
                            "Ship" => "", 
                            "Region" => "",
                            "System" => ""
                        ],
                        "Squads" => []
                    ];

                    foreach ($eachWing["squads"] as $eachSquad) {

                        $this->WingsSquadsAndCharacters[(int)$eachWing["id"]]["Squads"][(int)$eachSquad["id"]] = [
                            "Name" => $eachSquad["name"],
                            "Commander" => [
                                "Name" => "",
                                "Corporation" => "",
                                "Alliance" => "",
                                "Ship" => "", 
                                "Region" => "",
                                "System" => ""
                            ],
                            "Characters" => []
                        ];

                    }

                }

            }
            else {

                return;

            }

            //ID's to resolve to names. We're using keys to mimic a set.
            $setOfIDs = [];

            //Get fleet member data... Everything's indexed so hopefully this is actually timely.
            $fleetMembersQuery = $this->databaseConnection->prepare("
                SELECT 
                    fleetmembers.characterid as characterid, 
                    fleetmembers.corporationid AS corporationid, 
                    fleetmembers.allianceid AS allianceid, 
                    CONCAT(useraccounts.accounttype, ':', useraccounts.accountid) AS account,
                    fleetmembers.role AS role, 
                    fleetmembers.wingid AS wingid, 
                    fleetmembers.squadid AS squadid,
                    fleetships.shipid AS shipid,
                    evegroups.id AS shipgroupid,
                    evegroups.name AS shipgroupname,
                    fleetlocations.systemid AS systemid,
                    evesystems.regionid AS regionid
                FROM fleetmembers 
                INNER JOIN userlinks
                ON 
                    fleetmembers.characterid = userlinks.characterid
                INNER JOIN useraccounts
                ON 
                    userlinks.accounttype = useraccounts.accounttype 
                    AND userlinks.accountid = useraccounts.accountid 
                INNER JOIN fleetships
                ON 
                    fleetmembers.fleetid = fleetships.fleetid 
                    AND fleetmembers.characterid = fleetships.characterid
                INNER JOIN evetypes
                ON
                    fleetships.shipid = evetypes.id
                INNER JOIN evegroups
                ON
                    evetypes.groupid = evegroups.id
                INNER JOIN fleetlocations
                ON 
                    fleetmembers.fleetid = fleetlocations.fleetid 
                    AND fleetmembers.characterid = fleetlocations.characterid
                INNER JOIN evesystems
                ON
                    fleetlocations.systemid = evesystems.id
                WHERE 
                    fleetmembers.fleetid=:fleetid 
                    AND fleetmembers.endtime IS NULL 
                    AND fleetlocations.endtime IS NULL 
                    AND fleetships.endtime IS NULL
            ");
            $fleetMembersQuery->bindParam(":fleetid", $fleetID);
            $fleetMembersQuery->execute();

            $incomingMembers = $fleetMembersQuery->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($incomingMembers as $eachMember) {

                $this->headerData["Fleet Members"]++;

                if (!in_array($eachMember["account"], $this->knownAccounts)) {
                    $this->knownAccounts[] = $eachMember["account"];
                    $this->headerData["User Accounts"]++;
                }

                $setOfIDs[$eachMember["characterid"]] = true;
                $setOfIDs[$eachMember["corporationid"]] = true;

                if (!is_null($eachMember["allianceid"])) {
                    $setOfIDs[$eachMember["allianceid"]] = true;
                }

                $setOfIDs[$eachMember["shipid"]] = true;
                $setOfIDs[$eachMember["systemid"]] = true;
                $setOfIDs[$eachMember["regionid"]] = true;

                if ($commanderID == $eachMember["characterid"]) {
                    $commanderSystemID = $eachMember["systemid"];
                }
            
            }

            //Convert keys to an array we can pass to ESI
            $idsToLookup = array_keys($setOfIDs);
            $resolvedIDs = [
                "alliance" => [],
                "character" => [],
                "corporation" => [],
                "inventory_type" => [],
                "region" => [],
                "solar_system" => []
            ];

            //Parsing IDs to Names
            foreach (array_chunk($idsToLookup, 995) as $subLists) {

                $namesCall = $this->esiHandler->call(endpoint: "/universe/names/", ids: $subLists, retries: 1);

                if ($namesCall["Success"]) {

                    foreach ($namesCall["Data"] as $eachParse) {

                        if (isset($resolvedIDs[$eachParse["category"]])) {

                            $resolvedIDs[$eachParse["category"]][$eachParse["id"]] = $eachParse["name"];

                        }

                    }

                }
                else {
                    
                    return;

                }

            }

            //Sort member data into sections and prepare it for displaying.
            foreach ($incomingMembers as $eachMember) {

                $memberData = [
                    "Name" => $resolvedIDs["character"][$eachMember["characterid"]] ?? "[Unknown Character Name]",
                    "Corporation" => $resolvedIDs["corporation"][$eachMember["corporationid"]] ?? "[Unknown Corporation Name]",
                    "Alliance" => $resolvedIDs["alliance"][$eachMember["allianceid"]] ?? "",
                    "Ship" => $resolvedIDs["inventory_type"][$eachMember["shipid"]] ?? "[Unknown Ship Type]", 
                    "Region" => $resolvedIDs["region"][$eachMember["regionid"]] ?? "[Unknown Region Name]", 
                    "System" => $resolvedIDs["solar_system"][$eachMember["systemid"]] ?? "[Unknown System Name]"
                ];

                if ($commanderID == $eachMember["characterid"]) {
                    $this->headerData["Commander Name"] = $memberData["Name"];
                }

                //Member Wing Doesn't Exist
                if (
                    $eachMember["wingid"] != -1 
                    and !isset($this->WingsSquadsAndCharacters[$eachMember["wingid"]])
                ) {

                    $this->WingsSquadsAndCharacters[$eachMember["wingid"]] = [
                        "Name" => "Unknown Wing",
                        "Commander" => [
                            "Name" => "",
                            "Corporation" => "",
                            "Alliance" => "",
                            "Ship" => "", 
                            "Region" => "",
                            "System" => ""
                        ],
                        "Squads" => []
                    ];

                }

                //Member Squad Doesn't Exist
                if (
                    $eachMember["squadid"] != -1 
                    and !isset($this->WingsSquadsAndCharacters[$eachMember["wingid"]]["Squads"][$eachMember["squadid"]])
                ) {

                    $this->WingsSquadsAndCharacters[$eachMember["wingid"]]["Squads"][$eachMember["squadid"]] = [
                        "Name" => "Unknown Squad",
                        "Commander" => [
                            "Name" => "",
                            "Corporation" => "",
                            "Alliance" => "",
                            "Ship" => "", 
                            "Region" => "",
                            "System" => ""
                        ],
                        "Characters" => []
                    ];

                }

                //Populate Fleet Structure With Member Data
                if ($eachMember["role"] === "fleet_commander") {

                    $this->WingsSquadsAndCharacters[-1]["Commander"] = $memberData;

                }
                elseif ($eachMember["role"] === "wing_commander") {

                    $this->WingsSquadsAndCharacters[$eachMember["wingid"]]["Commander"] = $memberData;

                }
                elseif ($eachMember["role"] === "squad_commander") {

                    $this->WingsSquadsAndCharacters[$eachMember["wingid"]]["Squads"][$eachMember["squadid"]]["Commander"] = $memberData;

                }
                elseif ($eachMember["role"] === "squad_member") {

                    $this->WingsSquadsAndCharacters[$eachMember["wingid"]]["Squads"][$eachMember["squadid"]]["Characters"][] = $memberData;

                }

                //Check for user-specified listing restrictions
                if (
                    ($this->onlyWithCommander and $eachMember["systemid"] == $commanderSystemID)
                    or (!is_null($this->onlyInWing) and $eachMember["wingid"] == $this->onlyInWing)
                    or (!is_null($this->onlyInSquad) and $eachMember["squadid"] == $this->onlyInSquad)
                    or (!$this->onlyWithCommander and is_null($this->onlyInWing) and is_null($this->onlyInSquad))
                ) {

                    //Populate Ship Breakdown
                    if (!isset($this->ShipGroupsAndTypes[$eachMember["shipgroupid"]])) {

                        $this->ShipGroupsAndTypes[$eachMember["shipgroupid"]] = [
                            "Name" => $eachMember["shipgroupname"],
                            "Count" => 0,
                            "Ships" => []
                        ];

                    }

                    if (!isset($this->ShipGroupsAndTypes[$eachMember["shipgroupid"]]["Ships"][$eachMember["shipid"]])) {

                        $this->ShipGroupsAndTypes[$eachMember["shipgroupid"]]["Ships"][$eachMember["shipid"]] = [
                            "Name" => $resolvedIDs["inventory_type"][$eachMember["shipid"]] ?? "[Unknown Ship Type]",
                            "Count" => 0
                        ];

                    }

                    $this->ShipGroupsAndTypes[$eachMember["shipgroupid"]]["Count"]++;
                    $this->ShipGroupsAndTypes[$eachMember["shipgroupid"]]["Ships"][$eachMember["shipid"]]["Count"]++;

                    //Populate Affiliation Breakdown
                    if (!isset($this->AlliancesAndCorporations[$eachMember["allianceid"]])) {

                        $this->AlliancesAndCorporations[$eachMember["allianceid"]] = [
                            "Name" => $resolvedIDs["alliance"][$eachMember["allianceid"]] ?? "[No Alliance]",
                            "Count" => 0,
                            "Corporations" => []
                        ];

                    }

                    if (!isset($this->AlliancesAndCorporations[$eachMember["allianceid"]]["Corporations"][$eachMember["corporationid"]])) {

                        $this->AlliancesAndCorporations[$eachMember["allianceid"]]["Corporations"][$eachMember["corporationid"]] = [
                            "Name" => $resolvedIDs["corporation"][$eachMember["corporationid"]] ?? "[Unknown Corporation Name]",
                            "Count" => 0
                        ];

                    }

                    $this->AlliancesAndCorporations[$eachMember["allianceid"]]["Count"]++;
                    $this->AlliancesAndCorporations[$eachMember["allianceid"]]["Corporations"][$eachMember["corporationid"]]["Count"]++;

                    //Populate Location Breakdown
                    if (!isset($this->RegionsAndSystems[$eachMember["regionid"]])) {

                        $this->RegionsAndSystems[$eachMember["regionid"]] = [
                            "Name" => $resolvedIDs["region"][$eachMember["regionid"]] ?? "[Unknown Region Name]",
                            "Count" => 0,
                            "Systems" => []
                        ];

                    }

                    if (!isset($this->RegionsAndSystems[$eachMember["regionid"]]["Systems"][$eachMember["systemid"]])) {

                        $this->RegionsAndSystems[$eachMember["regionid"]]["Systems"][$eachMember["systemid"]] = [
                            "Name" => $resolvedIDs["solar_system"][$eachMember["systemid"]] ?? "[Unknown System Name]",
                            "Count" => 0
                        ];

                    }

                    $this->RegionsAndSystems[$eachMember["regionid"]]["Count"]++;
                    $this->RegionsAndSystems[$eachMember["regionid"]]["Systems"][$eachMember["systemid"]]["Count"]++;

                }
            
            }

            //This is a lot of sorting, hopefully it doesn't slow things down
            uasort($this->RegionsAndSystems, function($a, $b) {
                return $b["Count"] <=> $a["Count"];
            });

            foreach ($this->RegionsAndSystems as &$eachRegion) {

                uasort($eachRegion["Systems"], function($a, $b) {
                    return $b["Count"] <=> $a["Count"];
                });

            }

            uasort($this->AlliancesAndCorporations, function($a, $b) {
                return $b["Count"] <=> $a["Count"];
            });

            foreach ($this->AlliancesAndCorporations as &$eachAlliance) {

                uasort($eachAlliance["Corporations"], function($a, $b) {
                    return $b["Count"] <=> $a["Count"];
                });

            }

            uasort($this->ShipGroupsAndTypes, function($a, $b) {
                return $b["Count"] <=> $a["Count"];
            });

            foreach ($this->ShipGroupsAndTypes as &$eachGroup) {

                uasort($eachGroup["Ships"], function($a, $b) {
                    return $b["Count"] <=> $a["Count"];
                });

            }

            ksort($this->WingsSquadsAndCharacters, SORT_NUMERIC);

            foreach ($this->WingsSquadsAndCharacters as &$eachWing) {

                ksort($eachWing["Squads"], SORT_NUMERIC);

                foreach ($eachWing["Squads"] as &$eachSquad) {

                    usort($eachSquad["Characters"], function($a, $b) {
                        return strtolower($a["Name"]) <=> strtolower($b["Name"]);
                    });

                }
            }

            //Processing Successful
            $this->fleetFound = true;

        }

        private function renderShips() {

            foreach ($this->ShipGroupsAndTypes as $eachGroupID => $eachGroup) {

                ?>
                <li class="mt-1 list-group-item list-group-item-secondary fw-bold d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($eachGroup["Name"]); ?>
                    <span class="badge bg-dark"><?php echo htmlspecialchars($eachGroup["Count"]); ?></span>
                </li>
                <?php

                foreach ($eachGroup["Ships"] as $eachShipID => $eachShip) {

                    ?>
                    <li class="ms-4 list-group-item list-group-item-secondary d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($eachShip["Name"]); ?>
                        <span class="badge bg-dark"><?php echo htmlspecialchars($eachShip["Count"]); ?></span>
                    </li>
                    <?php

                }

            }

        }

        private function renderAffiliations() {

            foreach ($this->AlliancesAndCorporations as $eachAllianceID => $eachAlliance) {

                ?>
                <li class="mt-1 list-group-item list-group-item-secondary fw-bold d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($eachAlliance["Name"]); ?>
                    <span class="badge bg-dark"><?php echo htmlspecialchars($eachAlliance["Count"]); ?></span>
                </li>
                <?php

                foreach ($eachAlliance["Corporations"] as $eachCorporationID => $eachCorporation) {

                    ?>
                    <li class="ms-4 list-group-item list-group-item-secondary d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($eachCorporation["Name"]); ?>
                        <span class="badge bg-dark"><?php echo htmlspecialchars($eachCorporation["Count"]); ?></span>
                    </li>
                    <?php

                }

            }

        }

        private function renderLocations() {

            foreach ($this->RegionsAndSystems as $eachRegionID => $eachRegion) {

                ?>
                <li class="mt-1 list-group-item list-group-item-secondary fw-bold d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($eachRegion["Name"]); ?>
                    <span class="badge bg-dark"><?php echo htmlspecialchars($eachRegion["Count"]); ?></span>
                </li>
                <?php

                foreach ($eachRegion["Systems"] as $eachSystemID => $eachSystem) {

                    ?>
                    <li class="ms-4 list-group-item list-group-item-secondary d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($eachSystem["Name"]); ?>
                        <span class="badge bg-dark"><?php echo htmlspecialchars($eachSystem["Count"]); ?></span>
                    </li>
                    <?php

                }

            }

        }

        private function renderMembers() {

            foreach ($this->WingsSquadsAndCharacters as $eachWingID => $eachWing) {

                ?>
                <li class="mt-1 list-group-item list-group-item-secondary fw-bold">
                    <?php echo htmlspecialchars($eachWing["Name"]); ?>
                    <div class="row member-item">
                        <div class="col-lg-6">
                            <?php echo htmlspecialchars($eachWing["Commander"]["Name"]); ?>
                            <?php echo ($eachWing["Commander"]["Corporation"] != "") ? htmlspecialchars(" (" . $eachWing["Commander"]["Corporation"] . ")") : ""; ?>
                            <?php echo ($eachWing["Commander"]["Alliance"] != "") ? htmlspecialchars(" [" . $eachWing["Commander"]["Alliance"] . "]") : ""; ?>
                        </div>
                        <div class="col-lg-3">
                            <?php echo ($eachWing["Commander"]["Region"] != "") ? htmlspecialchars(" [" . $eachWing["Commander"]["Region"] . "]") : ""; ?>
                            <?php echo htmlspecialchars($eachWing["Commander"]["System"]); ?>
                        </div>
                        <div class="col-lg-3">
                            <?php echo htmlspecialchars($eachWing["Commander"]["Ship"]); ?>
                        </div>
                    </div>
                </li>
                <?php

                foreach ($eachWing["Squads"] as $eachSquadID => $eachSquad) {

                    ?>
                    <li class="ms-2 list-group-item list-group-item-secondary fw-bold">
                        <?php echo htmlspecialchars($eachSquad["Name"]); ?>
                        <div class="row member-item">
                            <div class="col-lg-6">
                                <?php echo htmlspecialchars($eachSquad["Commander"]["Name"]); ?>
                                <?php echo ($eachSquad["Commander"]["Corporation"] != "") ? htmlspecialchars(" (" . $eachSquad["Commander"]["Corporation"] . ")") : ""; ?>
                                <?php echo ($eachSquad["Commander"]["Alliance"] != "") ? htmlspecialchars(" [" . $eachSquad["Commander"]["Alliance"] . "]") : ""; ?>
                            </div>
                            <div class="col-lg-3">
                                <?php echo ($eachSquad["Commander"]["Region"] != "") ? htmlspecialchars(" [" . $eachSquad["Commander"]["Region"] . "]") : ""; ?>
                                <?php echo htmlspecialchars($eachSquad["Commander"]["System"]); ?>
                            </div>
                            <div class="col-lg-3">
                                <?php echo htmlspecialchars($eachSquad["Commander"]["Ship"]); ?>
                            </div>
                        </div>
                    </li>
                    <?php

                    foreach ($eachSquad["Characters"] as $eachCharacter) {

                        ?>
                        <li class="ms-4 list-group-item list-group-item-secondary">
                            <div class="row member-item">
                                <div class="col-lg-6">
                                    <?php echo htmlspecialchars($eachCharacter["Name"]); ?>
                                    <?php echo htmlspecialchars(" (" . $eachCharacter["Corporation"] . ")"); ?>
                                    <?php echo ($eachCharacter["Alliance"] != "") ? htmlspecialchars(" [" . $eachCharacter["Alliance"] . "]") : ""; ?>
                                </div>
                                <div class="col-lg-3">
                                    <?php echo htmlspecialchars(" [" . $eachCharacter["Region"] . "]"); ?>
                                    <?php echo htmlspecialchars($eachCharacter["System"]); ?>
                                </div>
                                <div class="col-lg-3">
                                    <?php echo htmlspecialchars($eachCharacter["Ship"]); ?>
                                </div>
                            </div>
                        </li>
                        <?php

                    }

                }

            }
            
        }

        public function renderData() {
            ?>

            <div class="card mt-3" style="color: #41464b; background-color: #e2e3e5;">
                <div class="card-body p-2">
                    <div class="row">

                        <div class="col-lg-4">

                            <div class="fw-bold"><?php echo htmlspecialchars($this->headerData["Fleet Name"]); ?></div>
                            <div class="small"><?php echo htmlspecialchars($this->headerData["Fleet Type"]); ?></div>

                        </div>
                        <div class="col-lg-4 text-center">

                            <div class="fw-bold"><?php echo htmlspecialchars($this->headerData["Commander Name"]); ?></div>

                        </div>
                        <div class="col-lg-4 text-end">

                            <div class="fw-bold">Fleet Members: <?php echo htmlspecialchars($this->headerData["Fleet Members"]); ?></div>
                            <div class="small">User Accounts: <?php echo htmlspecialchars($this->headerData["User Accounts"]); ?></div>

                        </div>

                    </div>
                </div>
            </div>

            <div class="row mt-3">

                <div class="col-lg-3">

                    <h4>Ship Breakdown</h4>

                    <ul class="list-group rounded-0 mt-3">
                        <?php $this->renderShips(); ?>
                    </ul>

                    <h4 class="mt-3">Location Breakdown</h4>

                    <ul class="list-group rounded-0 mt-3">
                        <?php $this->renderLocations(); ?>
                    </ul>

                </div>
                <div class="col-lg-3">

                    <h4>Affiliation Breakdown</h4>

                    <ul class="list-group rounded-0 mt-3">
                        <?php $this->renderAffiliations(); ?>
                    </ul>

                </div>
                <div class="col-lg-6">

                    <h4>Fleet Structure</h4>

                    <ul class="list-group rounded-0 mt-3">
                        <?php $this->renderMembers(); ?>
                    </ul>

                </div>

            </div>

            <?php
        }
        
    }

?>