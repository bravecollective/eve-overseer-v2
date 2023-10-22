<?php

    namespace Ridley\Apis\FleetStats;

    class Api implements \Ridley\Interfaces\Api {

        private $databaseConnection;

        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {

            $this->databaseConnection = $this->dependencies->get("Database");

            if (isset($_POST["Action"])) {

                if ($_POST["Action"] == "Action" and isset($_POST["ID"])) {


                    
                }
                else {

                    header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                    throw new \Exception("No valid combination of action and required secondary arguments was received.", 10002);

                }

            }
            else {

                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                throw new \Exception("Request is missing the action argument.", 10001);

            }

        }

    }

?>
