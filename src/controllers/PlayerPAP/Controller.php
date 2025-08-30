<?php

    namespace Ridley\Controllers\PlayerPAP;

    class Controller implements \Ridley\Interfaces\Controller {
        
        private $databaseConnection;
        private $logger;
        private $configVariables;
        private $approvedAccountTypes = [
            "character", 
            "neucore"
        ];
        private $approvedSortKeys = [
            "account_name",
            "account_type",
            "recent_fleets_count",
            "recent_fleets_time",
            "total_fleets_count",
            "total_fleets_time",
            "recent_runs_count",
            "recent_runs_time",
            "total_runs_count",
            "total_runs_time",
            "last_active"
        ];
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->configVariables = $this->dependencies->get("Configuration Variables");
            
            if (isset($_GET["action"]) and $_GET["action"] == "login") {
                
                $auth = new \Ridley\Core\Authorization\Base\AuthBase(
                    $this->logger, 
                    $this->databaseConnection, 
                    $this->configVariables
                );
                
                $auth->login("Corp_Tracking", "esi-search.search_structures.v1 esi-corporations.read_corporation_membership.v1");
                
            }
            
        }

        public function isPAPMode() {

            return (
                isset($_POST["pap_mode"]) 
                and $_POST["pap_mode"] === "true" 
                and isset($_POST["corporation_condition"]) 
                and $_POST["corporation_condition"] != ""
            );

        }

        public function getFleetTypes() {

            $fleetTypes = [];

            $checkQuery = $this->databaseConnection->prepare("
                SELECT id, name
                FROM fleettypes
                ORDER BY name ASC
            ");
            $checkQuery->execute();

            while ($incomingTypes = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {
                $fleetTypes[$incomingTypes["id"]] = $incomingTypes["name"];
            }

            return $fleetTypes;

        }

        public function generateAccessRestrictions($forHaving = false) {

            $filterDetails = [
                "Enabled" => true,
                "Request" => "",
                "Variables" => []
            ];

            $participationAccessController = new \Ridley\Objects\AccessControl\Participation($this->dependencies);

            if ($participationAccessController->checkForAccessBypass()) {

                $filterDetails["Enabled"] = false;
                return $filterDetails;

            }
            else {

                $placeholders = [
                    "Character" => [],
                    "Corporation" => [],
                    "Alliance" => []
                ];
                $placeholderCounter = 0;
                $allowedEntities = $participationAccessController->getEntityLeadershipFilters();

                if (!$forHaving) {

                    foreach ($allowedEntities["Character"] as $eachID) {
                        $placeholders["Character"][] = (":entity_" . $placeholderCounter);
                        $filterDetails["Variables"][":entity_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                        $placeholderCounter++;
                    }

                }

                foreach ($allowedEntities["Corporation"] as $eachID) {
                    $placeholders["Corporation"][] = (":entity_" . $placeholderCounter);
                    $filterDetails["Variables"][":entity_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                    $placeholderCounter++;
                }

                foreach ($allowedEntities["Alliance"] as $eachID) {
                    $placeholders["Alliance"][] = (":entity_" . $placeholderCounter);
                    $filterDetails["Variables"][":entity_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                    $placeholderCounter++;
                }

                if ($forHaving) {

                    $corporationRestriction = (!empty($placeholders["Corporation"])) ? ("corptrackers.corporationid IN (" . implode(",", $placeholders["Corporation"]) . ")") : "FALSE";
                    $allianceRestriction = (!empty($placeholders["Alliance"])) ? ("corptrackers.allianceid IN (" . implode(",", $placeholders["Alliance"]) . ")") : "FALSE";
    
                    $filterDetails["Request"] = "SUM(CASE WHEN (
                        " . $corporationRestriction . " 
                        OR " . $allianceRestriction . " 
                    ) THEN 1 ELSE 0 END) > 0";

                }
                else {

                    $characterRestriction = (!empty($placeholders["Character"])) ? ("fleetmembers.characterid IN (" . implode(",", $placeholders["Character"]) . ")") : "FALSE";
                    $corporationRestriction = (!empty($placeholders["Corporation"])) ? ("fleetmembers.corporationid IN (" . implode(",", $placeholders["Corporation"]) . ")") : "FALSE";
                    $allianceRestriction = (!empty($placeholders["Alliance"])) ? ("fleetmembers.allianceid IN (" . implode(",", $placeholders["Alliance"]) . ")") : "FALSE";
    
                    $filterDetails["Request"] = "(
                        " . $characterRestriction . " 
                        OR " . $corporationRestriction . " 
                        OR " . $allianceRestriction . " 
                    )";

                }

                return $filterDetails;

            }

        }

        public function generateHavingPrefix($currentRequest) {
            
            if ($currentRequest === "") {
                
                return "HAVING ";
                
            }
            else {
                
                return " AND ";
                
            }
        }

        public function generateOrder() {

            $request = "ORDER BY account_name";

            if (isset($_POST["order_by"]) and $_POST["order_by"] != "") {

                if (in_array($_POST["order_by"], ["account_name", "account_type", "last_active"], true)) {

                    $sortKey = $_POST["order_by"];

                }
                else {

                    $isTimeMode = (isset($_POST["time_mode"]) and $_POST["time_mode"] == "true");
                    $postfix = $isTimeMode ? "_time" : "_count";
                    $sortKey = ($_POST["order_by"] . $postfix);

                }

                if (in_array($sortKey, $this->approvedSortKeys, true)) {
                    $request = "ORDER BY " . $this->approvedSortKeys[array_search($sortKey, $this->approvedSortKeys, true)];
                    $request .= ((isset($_POST["order_order"]) and $_POST["order_order"] == "descending") ? " DESC" : "");
                    $request .= ", account_name";
                }

            }

            return $request;

        }

        public function generateFleetConditions() {

            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            if ($this->isPAPMode()) {

                //Filter by Fleets
                $fleetPlaceholders = [];
                $fleetCounter = 0;
                if (isset($_POST["fleet_condition"]) and !empty($_POST["fleet_condition"])) {

                    $knownFleets = $this->getFleetTypes();

                    foreach ($_POST["fleet_condition"] as $eachID) {

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

            }

            return $filterDetails;

        }

        public function generateRecency() {

            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            $filterDetails["Request"] .= "fleetmembers.starttime >= :date_start";

            $definedStartTime = false;
            if (isset($_POST["date_start_condition"]) and $_POST["date_start_condition"] != "") {
                
                $incomingStartTime = strtotime($_POST["date_start_condition"]) * 1000;
                
                if ($incomingStartTime != false) {
                    $definedStartTime = true;
                }
                
            }
            if (!$definedStartTime) {
                $incomingStartTime = ((time() - (86400 * 30)) * 1000);
            }

            $filterDetails["Variables"][":date_start"] = ["Value" => $incomingStartTime, "Type" => \PDO::PARAM_INT];

            if (isset($_POST["date_end_condition"]) and $_POST["date_end_condition"] != "") {
                
                $incomingEndTime = strtotime($_POST["date_end_condition"]);
                
                if ($incomingEndTime !== false) {
                    
                    $incomingEndTime += 86400;
                    $incomingEndTime *= 1000;
                
                    $filterDetails["Request"] .= " AND fleetmembers.endtime <= :date_end";
                    
                    $filterDetails["Variables"][":date_end"] = ["Value" => $incomingEndTime, "Type" => \PDO::PARAM_INT];
                    
                }
            }
            
            return $filterDetails;

        }

        public function generateHaving() {

            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            if ($this->isPAPMode()) {

                //Access Restrictions
                $accessRestrictions = $this->generateAccessRestrictions(true);
                if ($accessRestrictions["Enabled"]) {

                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . $accessRestrictions["Request"]
                    );

                    $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $accessRestrictions["Variables"]);

                }

                //Filter by Corporation
                if (isset($_POST["corporation_condition"]) and $_POST["corporation_condition"] != "") {
                    
                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . "SUM(CASE WHEN corptrackers.corporationid = :corporation THEN 1 ELSE 0 END) > 0"
                    );
                    
                    $filterDetails["Variables"][":corporation"] = ["Value" => $_POST["corporation_condition"], "Type" => \PDO::PARAM_INT];
                    
                }

            }
            else {

                //Filter by Corporation
                if (isset($_POST["corporation_condition"]) and $_POST["corporation_condition"] != "") {
                    
                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . "SUM(CASE WHEN fleetmembers.corporationid = :corporation THEN 1 ELSE 0 END) > 0"
                    );
                    
                    $filterDetails["Variables"][":corporation"] = ["Value" => $_POST["corporation_condition"], "Type" => \PDO::PARAM_INT];
                    
                }

                //Filter by Alliance
                if (isset($_POST["alliance_condition"]) and $_POST["alliance_condition"] != "") {
                    
                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . "SUM(CASE WHEN fleetmembers.allianceid = :alliance THEN 1 ELSE 0 END) > 0"
                    );
                    
                    $filterDetails["Variables"][":alliance"] = ["Value" => $_POST["alliance_condition"], "Type" => \PDO::PARAM_INT];
                    
                }

                //Filter by Recent Fleets
                if (isset($_POST["pap_minimum"]) and $_POST["pap_minimum"] != 0 and $_POST["pap_minimum"] != "") {
                    
                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . "recent_fleets_count >= :minimum_fleets"
                    );
                    
                    $filterDetails["Variables"][":minimum_fleets"] = ["Value" => $_POST["pap_minimum"], "Type" => \PDO::PARAM_INT];
                    
                }

                //Filter by Recent Runs
                if (isset($_POST["run_minimum"]) and $_POST["run_minimum"] != 0 and $_POST["run_minimum"] != "") {
                    
                    $filterDetails["Request"] .= (
                        $this->generateHavingPrefix($filterDetails["Request"]) 
                        . "recent_runs_count >= :minimum_runs"
                    );
                    
                    $filterDetails["Variables"][":minimum_runs"] = ["Value" => $_POST["run_minimum"], "Type" => \PDO::PARAM_INT];
                    
                }

            }

            return $filterDetails;

        }
        
        public function generateWhere() {
            
            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            if (!$this->isPAPMode()) {

                //Access Restrictions
                $accessRestrictions = $this->generateAccessRestrictions();
                if ($accessRestrictions["Enabled"]) {
                    $filterDetails["Request"] .= (" AND " . $accessRestrictions["Request"]);
                    $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $accessRestrictions["Variables"]);
                }
                
                //Filter by Actor
                if (isset($_POST["name_condition"]) and $_POST["name_condition"] != "") {
                    
                    $filterDetails["Request"] .= " AND LOWER(useraccounts.accountname) LIKE LOWER(:name)";
                    
                    $filterDetails["Variables"][":name"] = ["Value" => ("%" . $_POST["name_condition"] . "%"), "Type" => \PDO::PARAM_STR];
                    
                }

                //Filter by Account Type
                if (isset($_POST["account_condition"]) and in_array($_POST["account_condition"], $this->approvedAccountTypes, true)) {
                    
                    $filterDetails["Request"] .= " AND useraccounts.accounttype = :type";
                    
                    $filterDetails["Variables"][":type"] = ["Value" => $_POST["account_condition"], "Type" => \PDO::PARAM_STR];
                    
                }
                
                //Filter by Fleets
                $fleetPlaceholders = [];
                $fleetCounter = 0;
                if (isset($_POST["fleet_condition"]) and !empty($_POST["fleet_condition"])) {

                    $knownFleets = $this->getFleetTypes();

                    foreach ($_POST["fleet_condition"] as $eachID) {

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

            }
            
            return $filterDetails;
            
        }

        public function getPageNumber() {
            
            if (
                isset($_POST["page"]) 
                and is_numeric($_POST["page"]) 
                and (
                    is_int($_POST["page"]) 
                    or ctype_digit($_POST["page"])
                )
            ) {
                return max(0, (intval($_POST["page"] - 1)));
            }
            else {
                return 0;
            }
            
        }
        
    }

?>