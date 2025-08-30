<?php

    namespace Ridley\Apis\AlliancePAP;

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

                $corpTrackingHandler = new \Ridley\Objects\CorporationTracking\Handler($this->dependencies);

                if (
                    $queryResult["recheck"] > time() 
                    or $corpTrackingHandler->updateTracking($queryResult["characterid"], $queryResult["corporationid"], $queryResult["allianceid"])
                ) {

                    echo json_encode(["Status" => "Success"]);
                    return;

                }
                else {

                    $corpTrackingHandler->deleteTrackingData($queryResult["corporationid"]);

                }

            }

            echo json_encode(["Status" => "Not Found"]);
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");

        }

    }

?>
