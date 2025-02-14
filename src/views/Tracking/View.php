<?php

    namespace Ridley\Views\Tracking;

    class Templates {
        
        protected function mainTemplate() {
            ?>
            
            <div class="text-light">
                <ul class="nav nav-tabs mt-3" id="fleet-tabs-control" role="tablist">
                    <li class="nav-item" role="presentation" id="my-fleet-button">
                        <button class="nav-link active" id="my-fleet-tab" data-bs-toggle="tab" data-bs-target="#my-fleet" type="button" role="tab" aria-controls="my-fleet" aria-selected="true">My Fleet</button>
                    </li>
                    <li id="add-fleet-item" class="nav-item" role="presentation">
                        <button class="nav-link text-white pt-0 pb-0" id="add-fleet" type="button">
                            <i class="fs-3 bi bi-plus"></i>
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="fleet-tabs">
                    <div class="tab-pane fade show active" id="my-fleet" role="tabpanel" aria-labelledby="my-fleet">
                        <div class="row">
                            <div class="col-lg-3">
                                <?php if ($this->model->checkIfAuthedFC()) : ?>

                                    <div class="mt-3">
                                        <label for="fleet_name" class="form-label">Fleet Name</label>
                                        <input type="text" class="form-control" id="fleet_name">
                                    </div>

                                    <select id="fleet_type" class="form-select mt-3" aria-label="Fleet Type">
                                        <option selected>Select a Fleet Type</option>
                                        <?php

                                        foreach ($this->model->getFleetTypes() as $eachID => $eachName) {
                                            ?> 
                                            <option value="<?php echo htmlspecialchars($eachID);?>">
                                                <?php echo htmlspecialchars($eachName);?>
                                            </option> 
                                            <?php
                                        }

                                        ?>
                                    </select>

                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" value="true" id="share_fleet">
                                        <label class="form-check-label" for="share_fleet">Share Fleet</label>
                                    </div>

                                    <div class="d-grid mt-3">
                                        <button id="toggle_tracking" class="btn btn-outline-primary">Start Tracking</button>
                                    </div>
                                    
                                    <div id="share_container" class="mt-4" hidden>
                                        <label for="fleet_name" class="form-label">Share Key</label>
                                        <input type="text" class="form-control" id="share_key" disabled>
                                    </div>

                                    <div class="mt-3 form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="only_with_commander" value="true">
                                        <label class="form-check-label" for="only_with_commander">Only With Commander</label>
                                    </div>

                                <?php else: ?>
                                    
                                    <h3 class="mt-3">Auth Tracking Scopes: </h3>
                                    <a href="home/?action=login" class="mt-3">
                                        <img class="login-button" src="/resources/images/sso_image.png">
                                    </a>

                                <?php endif; ?>
                            </div>
                            <div class="col-lg-9">

                                <div class="fleet-display" data-tab-id="my-fleet">

                                </div>
                            
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
        }
        
        protected function metaTemplate() {
            ?>
            
            <title>Fleet Tracking</title>
            
            <script src="/resources/js/Tracking.js"></script>
            
            <?php
        }

        protected function styleTemplate() {
            ?>
            
            .member-item {
                font-size: 0.750rem !important;
            }
            
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

        public function renderStyle() {
            
            $this->styleTemplate();
            
        }
        
    }

?>