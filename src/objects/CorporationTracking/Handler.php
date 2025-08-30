<?php

    namespace Ridley\Objects\CorporationTracking;

    use Ridley\Core\Exceptions\ESIException;

    class Handler {

        private $databaseConnection;
        private $logger;
        private $authorizationControl;

        function __construct(
            private $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->authorizationControl = $this->dependencies->get("Authorization Control");

        }

        public function deleteTrackingData($corporationID) {

            $corpMemberCleanup = $this->databaseConnection->prepare("DELETE FROM corpmembers WHERE corporationid=:corporationid");
            $corpMemberCleanup->bindParam(":corporationid", $corporationID);
            $corpMemberCleanup->execute();

            $corpTrackerCleanup = $this->databaseConnection->prepare("DELETE FROM corptrackers WHERE corporationid=:corporationid");
            $corpTrackerCleanup->bindParam(":corporationid", $corporationID);
            $corpTrackerCleanup->execute();

        }

        public function updateAllCorps() {

            $updateQuery = $this->databaseConnection->prepare("SELECT characterid, corporationid, allianceid, recheck FROM corptrackers");
            $updateQuery->execute();

            while ($corpData = $updateQuery->fetch(\PDO::FETCH_ASSOC)) {

                if (
                    $corpData["recheck"] <= time() 
                    and !$this->updateTracking($corpData["characterid"], $corpData["corporationid"], $corpData["allianceid"])
                ) {
                    $this->deleteTrackingData($corpData["corporationid"]);
                }

            }

        }

        public function updateTracking($characterID, $corporationID, $allianceID) {

            try {

                $accessToken = $this->authorizationControl->getAccessToken("Corp_Tracking", $characterID);

            }
            catch (\Exception $exception) {

                $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Token Went Invalid");
                $this->logger->make_log_entry(logType: "Removed Corp Tracker", logDetails: $logString);
                return false;

            }

            $authenticatedESIHandler = new \Ridley\Objects\ESI\Handler($this->databaseConnection, $accessToken);
            $currentCorporation = null;
            $currentAlliance = null;
            $idsToCheck = [];
            $recheckTime = time() + 86400;

            $affiliationsCall = $authenticatedESIHandler->call(endpoint: "/characters/affiliation/", characters: [$characterID], retries: 1);
            if ($affiliationsCall["Success"]) {
                                
                foreach ($affiliationsCall["Data"] as $eachCharacter) {

                    $currentCorporation = (int)$eachCharacter["corporation_id"];
                    $idsToCheck[] = (int)$eachCharacter["corporation_id"];
                    if (isset($eachCharacter["alliance_id"])) {
                        $currentAlliance = (int)$eachCharacter["alliance_id"];
                        $idsToCheck[] = (int)$eachCharacter["alliance_id"];
                    }

                }
                
            }
            else {

                $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Affiliation Call Failed");
                $this->logger->make_log_entry(logType: "Removed Corp Tracker", logDetails: $logString);
                return false;
                                
            }

            if (is_null($currentCorporation) or $currentCorporation != $corporationID or ($currentCorporation >= 1000000 and $currentCorporation <= 2000000)) {

                $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Character No Longer In Target Corporation");
                $this->logger->make_log_entry(logType: "Removed Corp Tracker", logDetails: $logString);
                return false;

            }

            $memberList = [];

            $membersCall = $authenticatedESIHandler->call(endpoint: "/corporations/{corporation_id}/members/", corporation_id: $corporationID, retries: 1);
            if ($membersCall["Success"] and !empty($membersCall["Data"])) {
                $memberList = $membersCall["Data"];
            }
            else {

                $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Members Call Failure");
                $this->logger->make_log_entry(logType: "Removed Corp Tracker", logDetails: $logString);
                return false;
                                
            }

            $currentCorporationName = null;
            $currentAllianceName = null;
            $idsToCheck = array_merge($idsToCheck, $memberList);
            $memberData = [];

            foreach (array_chunk($idsToCheck, 995) as $subLists) {

                $namesCall = $authenticatedESIHandler->call(endpoint: "/universe/names/", ids: $subLists, retries: 1);
    
                if ($namesCall["Success"]) {
    
                    foreach ($namesCall["Data"] as $each) {
    
                        if ($each["category"] === "character" and in_array($each["id"], $memberList)) {
    
                            $memberData[] = [
                                "ID" => $each["id"],
                                "Name" => $each["name"]
                            ];
    
                        }
                        elseif ($each["category"] === "corporation" and $each["id"] == $currentCorporation) {

                            $currentCorporationName = $each["name"];

                        }
                        elseif ($each["category"] === "alliance" and $each["id"] == $currentAlliance) {

                            $currentAllianceName = $each["name"];
                            
                        }

                    }
    
                }
                else {
    
                    $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Names Call Failure");
                    $this->logger->make_log_entry(logType: "Corp Tracker Failure", logDetails: $logString);
                    return true;
    
                }

            }

            $updateTracker = $this->databaseConnection->prepare("UPDATE corptrackers SET recheck=:recheck, allianceid=:allianceid, corporationname=:corporationname, alliancename=:alliancename WHERE corporationid=:corporationid");
            $updateTracker->bindParam(":recheck", $recheckTime);
            $updateTracker->bindParam(":allianceid", $currentAlliance);
            $updateTracker->bindParam(":corporationname", $currentCorporationName);
            $updateTracker->bindParam(":alliancename", $currentAllianceName);
            $updateTracker->bindParam(":corporationid", $corporationID);
            $updateTracker->execute();

            $corpMemberCleanup = $this->databaseConnection->prepare("DELETE FROM corpmembers WHERE corporationid=:corporationid");
            $corpMemberCleanup->bindParam(":corporationid", $corporationID);
            $corpMemberCleanup->execute();

            $entries = [];
            $massInsertStatement = "REPLACE INTO corpmembers (characterid, charactername, corporationid) VALUES ";

            foreach (range(1 , count($memberData)) as $eachNum) {
                $entries[] = "(:characterid_$eachNum, :charactername_$eachNum, :corporationid_$eachNum)";
            }

            $massInsertStatement .= implode(",", $entries);
            $insertMembers = $this->databaseConnection->prepare($massInsertStatement);

            $eachNum = 1;
            foreach ($memberData as $eachData) {

                $insertMembers->bindValue((":characterid_" . $eachNum), $eachData["ID"]);
                $insertMembers->bindValue((":charactername_" . $eachNum), $eachData["Name"]);
                $insertMembers->bindValue((":corporationid_" . $eachNum), $corporationID);

                $eachNum++;

            }

            $insertMembers->execute();

            $insertAccounts = $this->databaseConnection->prepare("
                INSERT INTO useraccounts (accountid, accounttype, accountname)
                    (
                        SELECT corpmembers.characterid, 'Character', corpmembers.charactername 
                        FROM corpmembers
                        WHERE 
                            corpmembers.corporationid = :corporationid
                            AND corpmembers.characterid NOT IN (
                                SELECT DISTINCT userlinks.characterid FROM userlinks
                            )
                    )
            ");
            $insertAccounts->bindValue(":corporationid", $corporationID);
            $insertAccounts->execute();

            $insertLinks = $this->databaseConnection->prepare("
                INSERT INTO userlinks (characterid, accountid, accounttype)
                    (
                        SELECT corpmembers.characterid, corpmembers.characterid, 'Character' 
                        FROM corpmembers
                        WHERE 
                            corpmembers.corporationid = :corporationid
                            AND corpmembers.characterid NOT IN (
                                SELECT DISTINCT userlinks.characterid FROM userlinks
                            )
                    )
            ");
            $insertLinks->bindValue(":corporationid", $corporationID);
            $insertLinks->execute();

            $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nMembers: " . count($entries));
            $this->logger->make_log_entry(logType: "Updated Corp Members", logDetails: $logString);
            return true;

        }

    }
?>
