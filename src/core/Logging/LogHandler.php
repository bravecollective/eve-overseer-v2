<?php

    namespace Ridley\Core\Logging;

    class LogHandler {

        private $typeGroups = [
            "access-grant" => ["Name" => "Access Grants", "Types" => ["Access Granted"]],
            "access-deny" => ["Name" => "Access Denials", "Types" => ["Access Denied"]],
            "page-not-found" => ["Name" => "Page Not Found Notices", "Types" => ["Page Not Found"]],
            "login-success" => ["Name" => "Login Successes", "Types" => ["Login Success"]],
            "login-failure" => ["Name" => "Login Failures", "Types" => ["Login Failure"]],
            "base-exception" => ["Name" => "Base Exceptions", "Types" => [
                "Fatal Error",
                "Warning",
                "Parsing Error",
                "Notice",
                "Core Error",
                "Core Warning",
                "Compile Error",
                "Compile Warning",
                "Recoverable Error",
                "Deprecated Code Error"
            ]],
            "user-exception" => ["Name" => "User Exceptions", "Types" => [
                "User Error",
                "User Warning",
                "User Notice",
                "User Deprecated Code Error"
            ]],
            "core-database-update" => ["Name" => "Core Database Updates", "Types" => [
                "Access Group Created",
                "Access Group Updated",
                "Access Group Deleted",
                "Entity Type Created",
                "Entity Type Updated",
                "Entity Type Deleted",
                "Fleet Type Created",
                "Fleet Type Updated",
                "Fleet Type Deleted"
            ]],
        ];

        function __construct(
            private $logConnection,
            private $loggingVariables
        ) {

        }

        public function register(string $safeName, string $fullName, string ...$containedTypes) {

            $this->typeGroups[$safeName] = ["Name" => $fullName, "Types" => $containedTypes];

        }

        public function getTypeGroups() {

            return $this->typeGroups;

        }

        public function getCurrentPage($sessionID) {

            $pullPage = $this->logConnection->prepare("SELECT currentpage, expiration FROM sessions WHERE id=:id");
            $pullPage->bindParam(":id", $sessionID);
            $pullPage->execute();
            $pageData = $pullPage->fetchAll();

            if (!empty($pageData)) {

                    $pageData = $pageData[0];

                    if (time() < $pageData["expiration"]) {

                        return $pageData["currentpage"];

                    }
                    else {

                        return "Unknown Page";

                    }

            }
            else {

                return "Unknown Page";

            }

        }

        public function getActor($sessionID) {

            $pullActor = $this->logConnection->prepare("SELECT COALESCE(charactername, characterid) as `actor`, expiration FROM sessions WHERE id=:id");
            $pullActor->bindParam(":id", $sessionID);
            $pullActor->execute();
            $actorData = $pullActor->fetchAll();

            if (!empty($actorData)) {

                    $actorData = $actorData[0];

                    if (time() < $actorData["expiration"]) {

                        return $actorData["actor"];

                    }
                    else {

                        return "Unknown Actor";

                    }

            }
            else {

                return "Unknown Actor";

            }

        }

        public function make_log_entry($logType, $logPage = null, $logActor = null, $logDetails = null) {
            
            $currentTime = time();

            $writtenLogType = $logType;

            if (is_null($logPage)) {

                if (isset($_COOKIE[$this->loggingVariables["Auth Cookie Name"]])) {

                    $writtenLogPage = $this->getCurrentPage($_COOKIE[$this->loggingVariables["Auth Cookie Name"]]);

                }
                else {

                    $writtenLogPage = "Unknown Page";

                }

            }
            else {

                $writtenLogPage = $logPage;

            }

            if (is_null($logActor)) {

                if (isset($_COOKIE[$this->loggingVariables["Auth Cookie Name"]])) {

                    $writtenLogActor = $this->getActor($_COOKIE[$this->loggingVariables["Auth Cookie Name"]]) ;

                }
                else {

                    $writtenLogActor = "Unknown Actor";

                }

            }
            else {

                $writtenLogActor = $logActor;

            }

            if (is_null($logDetails)) {

                $writtenLogDetails = "Unknown";

            }
            else {

                $writtenLogDetails = $logDetails;

            }

            if ($this->loggingVariables["Store Visitor IPs"] === true) {

                $remoteAddress = "Unknown";
                $forwardedAddress = "Unknown";

                if (isset($_SERVER["REMOTE_ADDR"])) {

                    $remoteAddress = $_SERVER["REMOTE_ADDR"];

                }
                if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {

                    $forwardedAddress = $_SERVER["HTTP_X_FORWARDED_FOR"];

                }

            }

            else {

                $remoteAddress = "N/A";
                $forwardedAddress = "N/A";

            }

            $toInsert = $this->logConnection->prepare("INSERT INTO logs (timestamp, type, page, actor, details, trueip, forwardip) VALUES (:timestamp, :type, :page, :actor, :details, :trueip, :forwardip)");
            $toInsert->bindParam(':timestamp', $currentTime);
            $toInsert->bindParam(':type', $writtenLogType);
            $toInsert->bindParam(':page', $writtenLogPage);
            $toInsert->bindParam(':actor', $writtenLogActor);
            $toInsert->bindParam(':details', $writtenLogDetails);
            $toInsert->bindParam(':trueip', $remoteAddress);
            $toInsert->bindParam(':forwardip', $forwardedAddress);
            $toInsert->execute();

        }


    };


?>
