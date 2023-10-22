<?php

    namespace Ridley\Models\FleetStats;

    class Model implements \Ridley\Interfaces\Model {
        
        private $controller;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->controller = $this->dependencies->get("Controller");
            
        }
        
    }
?>