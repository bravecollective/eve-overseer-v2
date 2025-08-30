<?php

    namespace Ridley\Controllers\AlliancePAP;

    class Controller implements \Ridley\Interfaces\Controller {
        
        private $databaseConnection;
        private $logger;
        private $configVariables;
        private $approvedSortKeys = [
            "corporation_name",
            "alliance_name",
            "recheck",
            "members",
            "total_fleets",
            "recent_fleets",
            "knowns",
            "actives",
            "total_paps_count",
            "total_paps_time",
            "recent_paps_count",
            "recent_paps_time",
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

        public function generateAccessRestrictions($forCorpTrackers = false) {

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
                    "Corporation" => [],
                    "Alliance" => []
                ];
                $placeholderCounter = 0;
                $allowedEntities = $participationAccessController->getEntityLeadershipFilters();

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

                if ($forCorpTrackers) {
                    $corporationRestriction = (!empty($placeholders["Corporation"])) ? ("corptrackers.corporationid IN (" . implode(",", $placeholders["Corporation"]) . ")") : "FALSE";
                    $allianceRestriction = (!empty($placeholders["Alliance"])) ? ("corptrackers.allianceid IN (" . implode(",", $placeholders["Alliance"]) . ")") : "FALSE";
                }
                else {
                    $corporationRestriction = (!empty($placeholders["Corporation"])) ? ("fleetmembers.corporationid IN (" . implode(",", $placeholders["Corporation"]) . ")") : "FALSE";
                    $allianceRestriction = (!empty($placeholders["Alliance"])) ? ("fleetmembers.allianceid IN (" . implode(",", $placeholders["Alliance"]) . ")") : "FALSE";
                }

                $filterDetails["Request"] = "(
                    " . $corporationRestriction . " 
                    OR " . $allianceRestriction . " 
                )";

                return $filterDetails;

            }

        }

        public function generateWherePrefix($currentRequest) {
            
            if ($currentRequest === "") {
                
                return "WHERE ";
                
            }
            else {
                
                return " AND ";
                
            }
        }

        public function generateOrder() {

            $request = "ORDER BY corporation_name";

            if (isset($_POST["order_by"]) and $_POST["order_by"] != "") {

                if (in_array($_POST["order_by"], ["total_paps", "recent_paps"], true)) {

                    $isTimeMode = (isset($_POST["time_mode"]) and $_POST["time_mode"] == "true");
                    $postfix = $isTimeMode ? "_time" : "_count";
                    $sortKey = ($_POST["order_by"] . $postfix);

                }
                else {

                    $sortKey = $_POST["order_by"];

                }

                if (in_array($sortKey, $this->approvedSortKeys, true)) {
                    $request = "ORDER BY " . $this->approvedSortKeys[array_search($sortKey, $this->approvedSortKeys, true)];
                    $request .= ((isset($_POST["order_order"]) and $_POST["order_order"] == "descending") ? " DESC" : "");
                    $request .= ", corporation_name";
                }

            }

            return $request;

        }

        public function generateFleetConditions() {

            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

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
        
        public function generateWhere() {
            
            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            //Access Restrictions
            $accessRestrictions = $this->generateAccessRestrictions(true);
            if ($accessRestrictions["Enabled"]) {
                $filterDetails["Request"] .= (
                    $this->generateWherePrefix($filterDetails["Request"]) 
                    . $accessRestrictions["Request"]
                );
                $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $accessRestrictions["Variables"]);
            }
            
            //Filter by Corporation
            if (isset($_POST["corporation_condition"]) and $_POST["corporation_condition"] != "") {
                
                $filterDetails["Request"] .= (
                    $this->generateWherePrefix($filterDetails["Request"]) 
                    . "corptrackers.corporationid = :corporationid"
                );
                
                $filterDetails["Variables"][":corporationid"] = ["Value" => $_POST["corporation_condition"], "Type" => \PDO::PARAM_INT];
                
            }

            //Filter by Alliance
            if (isset($_POST["alliance_condition"]) and $_POST["alliance_condition"] != "") {
                
                $filterDetails["Request"] .= (
                    $this->generateWherePrefix($filterDetails["Request"]) 
                    . "corptrackers.allianceid = :allianceid"
                );
                
                $filterDetails["Variables"][":allianceid"] = ["Value" => $_POST["alliance_condition"], "Type" => \PDO::PARAM_INT];
                
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