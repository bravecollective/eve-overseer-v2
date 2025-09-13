<?php

    namespace Ridley\Controllers\FleetStats;

    class Controller implements \Ridley\Interfaces\Controller {
        
        private $databaseConnection;
        private $accessRoles;
        private $characterData;
        private $fleetAccessController;
        private $approvedSortKeys = [
            "name",
            "type",
            "commander",
            "start_time",
            "duration",
            "member_count",
            "account_count"
        ];
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->fleetAccessController = new \Ridley\Objects\AccessControl\Fleet($this->dependencies);
            
        }

        public function getFleetData($fleetID) {

            $returnData = [
                "Found" => false,
                "Data" => null
            ];

            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            //Access Restrictions
            $accessRestrictions = $this->generateAccessRestrictions(true);
            if ($accessRestrictions["Enabled"]) {
                $filterDetails["Request"] .= (" AND " . $accessRestrictions["Request"]);
                $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $accessRestrictions["Variables"]);
            }

            $checkQuery = $this->databaseConnection->prepare("
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
                INNER JOIN useraccounts member_accounts ON (member_links.accountid = member_accounts.accountid AND member_links.accounttype = member_accounts.accounttype)
                WHERE fleets.id=:id AND fleets.endtime IS NOT NULL" . $filterDetails["Request"] . "
                GROUP BY fleets.id
            ");
            $checkQuery->bindValue(":id", $fleetID);
            foreach ($filterDetails["Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            $checkQuery->execute();

            while ($fleetData = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {
                $returnData["Found"] = true;
                $returnData["Data"] = $fleetData;
            }

            return $returnData;

        }

        public function generateAccessRestrictions() {

            $filterDetails = [
                "Enabled" => true,
                "Request" => "",
                "Variables" => []
            ];

            if (in_array("Super Admin", $this->accessRoles) or in_array("View Fleet Stats", $this->accessRoles)) {

                $filterDetails["Enabled"] = false;
                return $filterDetails;

            }
            else {

                $approvedFleets = $this->fleetAccessController->getFleetTypes(forAudit: True);

                $fleetPlaceholders = [];
                $fleetCounter = 0;
    
                foreach ($approvedFleets as $eachID => $eachName) {

                    $fleetPlaceholders[] = (":access_fleet_" . $fleetCounter);
                    $filterDetails["Variables"][(":access_fleet_" . $fleetCounter)] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                    $fleetCounter++;
                    
                }
        
                if ($fleetCounter > 0) {
    
                    $filterDetails["Request"] = ("(fleets.type IN (" . implode(",", $fleetPlaceholders) . ") OR fleets.commanderid=:commanderid)");
    
                }
                else {

                    $filterDetails["Request"] = "fleets.commanderid=:commanderid";

                }

                $filterDetails["Variables"][":commanderid"] = ["Value" => $this->characterData["Character ID"], "Type" => \PDO::PARAM_INT];

            }

            return $filterDetails;

        }

        public function generateOrder() {

            $request = "ORDER BY fleets.starttime DESC";

            if (isset($_POST["order_by"]) and $_POST["order_by"] != "") {

                if (in_array($_POST["order_by"], $this->approvedSortKeys, true)) {
                    $request = "ORDER BY " . $this->approvedSortKeys[array_search($_POST["order_by"], $this->approvedSortKeys, true)];
                    $request .= ((isset($_POST["order_order"]) and $_POST["order_order"] == "descending") ? " DESC" : "");
                    $request .= ", fleets.starttime DESC";
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

                $knownFleets = $this->fleetAccessController->getFleetTypes(forAudit: True);

                foreach ($_POST["fleet_condition"] as $eachID) {

                    if (isset($knownFleets[$eachID])) {
                        $fleetPlaceholders[] = (":fleet_" . $fleetCounter);
                        $filterDetails["Variables"][(":fleet_" . $fleetCounter)] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                        $fleetCounter++;
                    }
                    
                }

            }

            if ($fleetCounter > 0) {

                $filterDetails["Request"] .= ("fleets.type IN (" . implode(",", $fleetPlaceholders) . ")");

            }

            return $filterDetails;

        }
        
        public function generateWhere() {
            
            $filterDetails = [
                "Request" => "",
                "Variables" => []
            ];

            //Access Restrictions
            $accessRestrictions = $this->generateAccessRestrictions();
            if ($accessRestrictions["Enabled"]) {
                $filterDetails["Request"] .= (" AND " . $accessRestrictions["Request"]);
                $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $accessRestrictions["Variables"]);
            }

            //Filter by Name
            if (isset($_POST["name_condition"]) and $_POST["name_condition"] != "") {
                
                $filterDetails["Request"] .= " AND LOWER(fleets.name) LIKE LOWER(:cmdr_name)";
                
                $filterDetails["Variables"][":cmdr_name"] = ["Value" => ("%" . $_POST["name_condition"] . "%"), "Type" => \PDO::PARAM_STR];
                
            }

            //Filter by Commander
            if (isset($_POST["commander_condition"]) and $_POST["commander_condition"] != "") {
                
                $filterDetails["Request"] .= " AND LOWER(cmdr_accounts.accountname) LIKE LOWER(:cmdr_name)";
                
                $filterDetails["Variables"][":cmdr_name"] = ["Value" => ("%" . $_POST["commander_condition"] . "%"), "Type" => \PDO::PARAM_STR];
                
            }

            //Filter by Start Date
            if (isset($_POST["date_start_condition"]) and $_POST["date_start_condition"] != "") {
                
                $incomingStartTime = strtotime($_POST["date_start_condition"]);
                
                if ($incomingStartTime !== false) {

                    $incomingStartTime *= 1000;
                    
                    $filterDetails["Request"] .= " AND fleets.starttime >= :date_start";
                    
                    $filterDetails["Variables"][":date_start"] = ["Value" => $incomingStartTime, "Type" => \PDO::PARAM_INT];
                    
                }
                
            }
            
            //Filter by End Date
            if (isset($_POST["date_end_condition"]) and $_POST["date_end_condition"] != "") {
                
                $incomingEndTime = strtotime($_POST["date_end_condition"]);
                
                if ($incomingEndTime !== false) {
                    
                    $incomingEndTime += 86400;
                    $incomingEndTime *= 1000;
                
                    $filterDetails["Request"] .= " AND fleets.endtime <= :date_end";
                    
                    $filterDetails["Variables"][":date_end"] = ["Value" => $incomingEndTime, "Type" => \PDO::PARAM_INT];
                    
                }
            }

            //Fleet Conditions
            $fleetFilter = $this->generateFleetConditions();
            if (!empty($fleetFilter["Variables"])) {
                $filterDetails["Request"] .= (" AND " . $fleetFilter["Request"]);
                $filterDetails["Variables"] = array_merge($filterDetails["Variables"], $fleetFilter["Variables"]);
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