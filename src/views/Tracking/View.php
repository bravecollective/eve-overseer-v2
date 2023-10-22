<?php

    namespace Ridley\Views\Tracking;

    class Templates {
        
        protected function mainTemplate() {
            ?>
            
            <div class="row">
            </div>
            
            <?php
        }
        
        protected function metaTemplate() {
            ?>
            
            <title>Fleet Tracking</title>
            
            <script src="/resources/js/Tracking.js"></script>
            
            <?php
        }
        
    }

    class View extends Templates implements \Ridley\Interfaces\View {
        
        protected $model;
        protected $controller;
        protected $configVariables;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->model = $this->dependencies->get("Model");
            $this->controller = $this->dependencies->get("Controller");
            
        }
        
        public function renderContent() {
            
            $this->mainTemplate();
            
        }
        
        public function renderMeta() {
            
            $this->metaTemplate();
            
        }
        
    }

?>