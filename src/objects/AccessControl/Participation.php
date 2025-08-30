<?php

    namespace Ridley\Objects\AccessControl;

    class Participation {

        private $databaseConnection;
        private $accessRoles;
        private $characterData;
        private $coreGroups;
        private $configVariables;

        function __construct(
            private $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->characterData = $this->dependencies->get("Character Stats");
            $this->coreGroups = $this->dependencies->get("Core Groups");
            $this->configVariables = $this->dependencies->get("Configuration Variables");

        }

        public function checkForAccessBypass() {

            return in_array("Super Admin", $this->accessRoles) or in_array("View PAP Data", $this->accessRoles);

        }

        public function getEntityLeadershipFilters() {

            $allowedEntities = [
                "Character" => [],
                "Corporation" => [],
                "Alliance" => []
            ];

            $checkQuery = $this->databaseConnection->prepare("
                SELECT entitytype, entityid, roletype, roleid FROM entitytypeaccess
            ");
            $checkQuery->execute();

            if ($this->configVariables["Auth Type"] == "Eve") {

                while ($incomingEntities = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (
                        ($incomingEntities["roletype"] == "Character" and $incomingEntities["roleid"] == $this->characterData["Character ID"])
                        or ($incomingEntities["roletype"] == "Corporation" and $incomingEntities["roleid"] == $this->characterData["Corporation ID"])
                        or ($incomingEntities["roletype"] == "Alliance" and $incomingEntities["roleid"] == $this->characterData["Alliance ID"])
                    ) {
                        $allowedEntities[$incomingEntities["entitytype"]][] = $incomingEntities["entityid"];
                    }

                }

            }
            elseif ($this->configVariables["Auth Type"] == "Neucore") {

                while ($incomingEntities = $checkQuery->fetch(\PDO::FETCH_ASSOC)) {

                    if (
                        $incomingEntities["roletype"] == "Neucore" and isset($this->coreGroups[$incomingEntities["roleid"]])
                    ) {
                        $allowedEntities[$incomingEntities["entitytype"]][] = $incomingEntities["entityid"];
                    }

                }

            }

            return $allowedEntities;

        }

        private function generateAccountAccessRestrictions() {

            $filterDetails = [
                "Fleet Request" => "",
                "Entity Request" => "",
                "Fleet Variables" => [],
                "Entity Variables" => []
            ];

            $placeholderCounter = 0;
            $allowedEntities = $this->getEntityLeadershipFilters();

            $fleetPlaceholders = [
                "Corporation" => [],
                "Alliance" => []
            ];

            foreach ($allowedEntities["Corporation"] as $eachID) {
                $fleetPlaceholders["Corporation"][] = (":placeholder_" . $placeholderCounter);
                $filterDetails["Fleet Variables"][":placeholder_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                $placeholderCounter++;
            }

            foreach ($allowedEntities["Alliance"] as $eachID) {
                $fleetPlaceholders["Alliance"][] = (":placeholder_" . $placeholderCounter);
                $filterDetails["Fleet Variables"][":placeholder_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                $placeholderCounter++;
            }

            $fleetCorporationRestriction = (!empty($fleetPlaceholders["Corporation"])) ? ("fleetmembers.corporationid IN (" . implode(",", $fleetPlaceholders["Corporation"]) . ")") : "FALSE";
            $fleetAllianceRestriction = (!empty($fleetPlaceholders["Alliance"])) ? ("fleetmembers.allianceid IN (" . implode(",", $fleetPlaceholders["Alliance"]) . ")") : "FALSE";

            $filterDetails["Fleet Request"] = "(
                " . $fleetCorporationRestriction . " 
                OR " . $fleetAllianceRestriction . " 
            )";

            $entityPlaceholders = [
                "Character" => [],
                "Corporation" => [],
                "Alliance" => []
            ];

            foreach ($allowedEntities["Character"] as $eachID) {
                $entityPlaceholders["Character"][] = (":placeholder_" . $placeholderCounter);
                $filterDetails["Entity Variables"][":placeholder_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                $placeholderCounter++;
            }

            foreach ($allowedEntities["Corporation"] as $eachID) {
                $entityPlaceholders["Corporation"][] = (":placeholder_" . $placeholderCounter);
                $filterDetails["Entity Variables"][":placeholder_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                $placeholderCounter++;
            }

            foreach ($allowedEntities["Alliance"] as $eachID) {
                $entityPlaceholders["Alliance"][] = (":placeholder_" . $placeholderCounter);
                $filterDetails["Entity Variables"][":placeholder_" . $placeholderCounter] = ["Value" => $eachID, "Type" => \PDO::PARAM_INT];
                $placeholderCounter++;
            }

            $entityCharacterRestriction = (!empty($entityPlaceholders["Character"])) ? ("userlinks.characterid IN (" . implode(",", $entityPlaceholders["Character"]) . ")") : "FALSE";
            $entityCorporationRestriction = (!empty($entityPlaceholders["Corporation"])) ? ("corptrackers.corporationid IN (" . implode(",", $entityPlaceholders["Corporation"]) . ")") : "FALSE";
            $entityAllianceRestriction = (!empty($entityPlaceholders["Alliance"])) ? ("corptrackers.allianceid IN (" . implode(",", $entityPlaceholders["Alliance"]) . ")") : "FALSE";

            $filterDetails["Entity Request"] = "(
                " . $entityCharacterRestriction . " 
                OR " . $entityCorporationRestriction . " 
                OR " . $entityAllianceRestriction . " 
            )";

            return $filterDetails;

        }

        public function checkForAccountAccess($accountType, $accountID) {

            if ($this->checkForAccessBypass()) {
                return true;
            }

            $accessRestrictions = $this->generateAccountAccessRestrictions();

            $checkQuery = $this->databaseConnection->prepare("
                SELECT DISTINCT userlinks.accountid
                FROM fleetmembers
                    LEFT JOIN userlinks ON userlinks.characterid = fleetmembers.characterid
                WHERE " . $accessRestrictions["Fleet Request"] . " AND userlinks.accountid = :target_id_fleets AND userlinks.accounttype = :target_type_fleets

                UNION 

                SELECT DISTINCT userlinks.accountid
                FROM corptrackers
                    LEFT JOIN corpmembers ON corpmembers.corporationid = corptrackers.corporationid
                    LEFT JOIN userlinks ON userlinks.characterid = corpmembers.characterid
                WHERE " . $accessRestrictions["Entity Request"] . " AND userlinks.accountid = :target_id_entities AND userlinks.accounttype = :target_type_entities
            ");

            foreach ($accessRestrictions["Fleet Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }
            foreach ($accessRestrictions["Entity Variables"] as $eachVariable => $eachValue) {
                $checkQuery->bindValue($eachVariable, $eachValue["Value"], $eachValue["Type"]);
            }

            $checkQuery->bindValue(":target_id_fleets", $accountID);
            $checkQuery->bindValue(":target_type_fleets", $accountType);
            $checkQuery->bindValue(":target_id_entities", $accountID);
            $checkQuery->bindValue(":target_type_entities", $accountType);

            $checkQuery->execute();
            
            return !empty($checkQuery->fetchAll());

        }

    }
?>
