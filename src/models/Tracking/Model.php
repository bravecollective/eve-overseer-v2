<?php

    namespace Ridley\Models\Tracking;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        private $authorizationControl;
        private $characterData;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            $this->authorizationControl = $this->dependencies->get("Authorization Control");
            $this->characterData = $this->dependencies->get("Character Stats");
            
        }


        public function checkIfAuthedFC() {

            try {
                $tokenCheck = $this->authorizationControl->getAccessToken("FC", $this->characterData["Character ID"]);
            }
            catch (\Exception $exception) {
                $tokenCheck = false;
            }
            return ($tokenCheck !== false);

        }
        
    }
?>