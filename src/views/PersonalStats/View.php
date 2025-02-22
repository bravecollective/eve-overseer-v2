<?php

    namespace Ridley\Views\PersonalStats;

    class Templates {
        
        protected function mainTemplate() {
            $startDateValue = htmlspecialchars($_POST["date-start"] ?? "");
            $endDateValue = htmlspecialchars($_POST["date-end"] ?? "");
            $timeCheck = (isset($_POST["time-mode"]) and $_POST["time-mode"] == "true") ? "checked" : "";

            $accountData = $this->model->getAccountInformation();
            $participationData = $this->model->getParticipationInformation();
            ?>
            
            <div class="row">

                <div class="col-lg-3">
                    <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body">

                            <h5 class="card-title">Account Information</h5>

                            <div>Account Name: <?php echo htmlspecialchars($accountData["Name"]); ?></div>
                            <div>Account Type: <?php echo htmlspecialchars($accountData["Type"]); ?></div>

                        </div>
                    </div>
                </div>

                <div class="col-lg-6">

                    <form method="post">
                        <div class="row mt-3">
                            <div class="col-lg-3">

                            <label class="form-label text-light" for="fleet-type">Fleet Types</label>
                            <select class="form-select form-select-sm" multiple size="2" name="fleet-type[]" id="fleet-type">
                                <?php $this->fleetsTemplate(); ?>
                            </select>

                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-light" for="date-start">Start Date</label>
                                <input type="date" class="form-control" name="date-start" id="date-start" value="<?php echo $startDateValue; ?>">
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label text-light" for="date-end">End Date</label>
                                <input type="date" class="form-control" name="date-end" id="date-end" value="<?php echo $endDateValue; ?>">
                            </div>
                            <div class="col-lg-3">
                                <div class="form-check form-switch mt-4 pt-3">
                                    <input class="form-check-input" type="checkbox" role="switch" name="time-mode" id="time-mode" value="true" <?php echo $timeCheck; ?>>
                                    <label class="form-check-label text-light" for="time-mode">Time Mode</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid mt-2">
                            <input class="btn btn-sm btn-success" type="submit" value="Filter">
                        </div>
                    </form>

                </div>

                <div class="col-lg-3">
                    <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body">

                            <h5 class="card-title">Participation Overview</h5>
                            
                            <div>Unique Fleets: <?php echo htmlspecialchars($participationData["Count"]); ?></div>
                            <div>Time in Fleets: <?php echo htmlspecialchars( $this->formatTime($participationData["Time"])); ?></div>

                        </div>
                    </div>
                </div>

            </div>

            <div class="row justify-content-md-center">

                <div class="col-lg-4">
                    <div class="card mt-4" id="timezone-card" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body text-center">

                            <h5 class="card-title">Timezones</h5>

                            <div class="spinner-border" id="timezone-spinner"></div>
                            <canvas id="timezone-chart" hidden>

                            </canvas>

                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card mt-4" id="fleet-type-card" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body text-center">

                            <h5 class="card-title">Fleet Types</h5>

                            <div class="spinner-border" id="fleet-type-spinner"></div>
                            <canvas id="fleet-type-chart" hidden>

                            </canvas>

                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mt-4" id="fleet-role-card" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body text-center">

                            <h5 class="card-title">Fleet Roles</h5>

                            <div class="spinner-border" id="fleet-role-spinner"></div>
                            <canvas id="fleet-role-chart" hidden>

                            </canvas>

                        </div>
                    </div>
                </div>

            </div>

            <div class="row justify-content-md-center">

                <div class="col-lg-4">
                    <div class="card mt-4" id="ship-class-card" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body text-center">

                            <h5 class="card-title">Ship Classes</h5>

                            <div class="spinner-border" id="ship-class-spinner"></div>
                            <canvas id="ship-class-chart" hidden>

                            </canvas>

                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mt-4" id="ship-type-card" style="color: #41464b; background-color: #e2e3e5;">
                        <div class="card-body text-center">

                            <h5 class="card-title">Ship Types</h5>

                            <div class="spinner-border" id="ship-type-spinner"></div>
                            <canvas id="ship-type-chart" hidden>

                            </canvas>

                        </div>
                    </div>
                </div>

            </div>
            
            <?php
        }

        protected function formatTime($milliseconds) {

            $seconds = $milliseconds / 1000;

            $days = floor($seconds / 86400);
            $hours = floor(($seconds - ($days * 86400)) / 3600);
            $minutes = floor(($seconds - ($days * 86400) - ($hours * 3600)) / 60);

            return sprintf("%02dd %02dh %02dm", $days, $hours, $minutes);

        }

        protected function fleetsTemplate() {

            $selectedFleets = (isset($_POST["fleet-type"]) and !empty($_POST["fleet-type"])) ? $_POST["fleet-type"] : [];
            $fleetList = $this->controller->getFleetTypes();

            foreach ($fleetList as $eachID => $eachName) {
                $sectionStatus = (in_array($eachID, $selectedFleets)) ? "selected" : "";
                ?>

                <option value="<?php echo htmlspecialchars($eachID); ?>" <?php echo $sectionStatus; ?>><?php echo htmlspecialchars($eachName); ?></option>

                <?php
            }

        }
        
        protected function metaTemplate() {
            ?>
            
            <title>Personal Stats</title>
            
            <script src="/resources/js/PersonalStats.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
            
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