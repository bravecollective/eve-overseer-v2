<?php

    namespace Ridley\Models\PlayerPAP;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            
        }
        
    }
?>