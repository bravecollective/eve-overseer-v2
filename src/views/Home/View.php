<?php

    namespace Ridley\Views\Home;

    class Templates {
        
        protected function mainTemplate() {
            ?>

            <?php $this->trackingStatusTemplate(); ?>
            
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="alert alert-primary text-center">
                        <h4 class="alert-heading">Welcome to Eve Overseer!</h4>
                        <hr>
                        This app is designed to track fleet participation. 
                        <br>
                        You can view your participation data and check if your current fleet is being tracked by logging in.
                    </div>
                </div>
            </div>
            
            <?php
        }

        protected function trackingStatusTemplate() {

            if ($this->loginStatus and in_array("Member", $this->accessRoles)) {

                $trackingStatus = $this->model->checkIfInFleet();
                
                $statusColor = ($trackingStatus) ? "success" : "danger";
                $statusText = ($trackingStatus) ? "Your fleet is currently being tracked!" : "Your fleet is not currently being tracked!";
                
                ?>

                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-<?php echo $statusColor; ?> text-center">
                            <h4 class="alert-heading"><?php echo $statusText; ?></h4>
                        </div>
                    </div>
                </div>

                <?php
            }

        }
        
        protected function metaTemplate() {
            ?>
            
            <title>Eve Overseer</title>
            <meta property="og:title" content="Eve Overseer">
            <meta property="og:description" content="The Eve Overseer App">
            <meta property="og:type" content="website">
            <meta property="og:url" content="<?php echo $_SERVER["SERVER_NAME"]; ?>">
            
            <?php
        }
        
    }

    class View extends Templates implements \Ridley\Interfaces\View {
        
        protected $model;
        protected $accessRoles;
        protected $loginStatus;

        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->model = $this->dependencies->get("Model");
            $this->loginStatus = $this->dependencies->get("Login Status");
            $this->accessRoles = $this->dependencies->get("Access Roles");

        }
        
        public function renderContent() {
            
            $this->mainTemplate();
            
        }
        
        public function renderMeta() {
            
            $this->metaTemplate();
            
        }
        
    }

?>