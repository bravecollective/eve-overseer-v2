<?php

    namespace Ridley\Controllers\Tracking;

    class Controller implements \Ridley\Interfaces\Controller {
        
        private $databaseConnection;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->databaseConnection = $this->dependencies->get("Database");
            
        }
        
    }

?>