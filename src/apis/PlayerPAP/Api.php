<?php

    namespace Ridley\Apis\PlayerPAP;

    use Ridley\Core\Exceptions\UserInputException;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;
        private $logger;
        private $authorizationControl;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");
            $this->logger = $this->dependencies->get("Logging");
            $this->authorizationControl = $this->dependencies->get("Authorization Control");

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Check_For_PAP" and isset($_POST["ID"])) {

                    $this->checkTracking($_POST["ID"]);
                    
                }
                else {

                    throw new UserInputException(
                        inputs: ["Action", "Secondary Arguments"], 
                        expected_values: ["A valid action command", "The action's arguments"], 
                        hard_coded_inputs: true,
                        value_missing: true
                    );

                }

            }
            else {

                throw new UserInputException(
                    inputs: "Action", 
                    expected_values: "An action command", 
                    hard_coded_inputs: true,
                    value_missing: true
                );

            }

        }

        private function checkTracking($corporationID) {

            $checkQuery = $this->databaseConnection->prepare("SELECT characterid, corporationid, allianceid, recheck FROM corptrackers WHERE corporationid=:corporationid");
            $checkQuery->bindParam(":corporationid", $corporationID);
            $checkQuery->execute();
            $queryResult = $checkQuery->fetch(\PDO::FETCH_ASSOC);

            if (!empty($queryResult)) {

                if (
                    $queryResult["recheck"] > time() 
                    or $this->updateTracking($queryResult["characterid"], $queryResult["corporationid"], $queryResult["allianceid"])
                ) {

                    echo json_encode(["Status" => "Success"]);
                    return;

                }
                else {

                    $this->deleteTrackingData($queryResult["corporationid"]);

                }

            }

            echo json_encode(["Status" => "Not Found"]);
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

        }

        private function deleteTrackingData($corporationID) {

            $corpMemberCleanup = $this->databaseConnection->prepare("DELETE FROM corpmembers WHERE corporationid=:corporationid");
            $corpMemberCleanup->bindParam(":corporationid", $corporationID);
            $corpMemberCleanup->execute();

            $corpTrackerCleanup = $this->databaseConnection->prepare("DELETE FROM corptrackers WHERE corporationid=:corporationid");
            $corpTrackerCleanup->bindParam(":corporationid", $corporationID);
            $corpTrackerCleanup->execute();

        }

        private function updateTracking($characterID, $corporationID, $allianceID) {

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
            $recheckTime = time() + 86400;

            $affiliationsCall = $authenticatedESIHandler->call(endpoint: "/characters/affiliation/", characters: [$characterID], retries: 1);
            if ($affiliationsCall["Success"]) {
                                
                foreach ($affiliationsCall["Data"] as $eachCharacter) {

                    $currentCorporation = (int)$eachCharacter["corporation_id"];
                    if (isset($eachCharacter["alliance_id"])) {
                        $currentAlliance = (int)$eachCharacter["alliance_id"];
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

            $memberData = [];

            foreach (array_chunk($memberList, 995) as $subLists) {

                $namesCall = $authenticatedESIHandler->call(endpoint: "/universe/names/", ids: $subLists, retries: 1);
    
                if ($namesCall["Success"]) {
    
                    foreach ($namesCall["Data"] as $each) {
    
                        if ($each["category"] === "character" and in_array($each["id"], $memberList)) {
    
                            $memberData[] = [
                                "ID" => $each["id"],
                                "Name" => $each["name"]
                            ];
    
                        }
    
                    }
    
                }
                else {
    
                    $logString = ("Corporation ID: " . $corporationID . " \nCharacter ID: " . $characterID . " \nReason: Names Call Failure");
                    $this->logger->make_log_entry(logType: "Removed Corp Tracker", logDetails: $logString);
                    return false;
    
                }

            }

            $updateTracker = $this->databaseConnection->prepare("UPDATE corptrackers SET recheck=:recheck, allianceid=:allianceid WHERE corporationid=:corporationid");
            $updateTracker->bindParam(":recheck", $recheckTime);
            $updateTracker->bindParam(":allianceid", $currentAlliance);
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
