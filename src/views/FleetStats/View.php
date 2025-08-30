<?php

    namespace Ridley\Views\FleetStats;

    class Templates {

        // Fleet Details View
        protected function detailedTemplate($fleetID) {

            $fleetData = $this->controller->getFleetData($fleetID);

            if ($fleetData["Found"]) {

                $startDate = date("M d, Y — H:i EVE", (int)($fleetData["Data"]["start_time"] / 1000));
                ?>

                <div class="row">

                    <div class="col-lg-3">
                        <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body">

                                <h5 class="card-title">Fleet Information</h5>

                                <div>Fleet Name: <?php echo htmlspecialchars($fleetData["Data"]["name"]); ?></div>
                                <div>Fleet Type: <?php echo htmlspecialchars($fleetData["Data"]["type"]); ?></div>
                                <div>Fleet Commander: <?php echo htmlspecialchars($fleetData["Data"]["commander"]); ?></div>

                                <?php
                                if (in_array("Super Admin", $this->accessRoles) or in_array("Delete Fleets", $this->accessRoles)) {
                                    ?>

                                    <div class="d-grid mt-2">
                                        <button id="delete-button" class="btn btn-danger">Delete Fleet</button>
                                    </div>

                                    <?php
                                }
                                ?>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6"></div>

                    <div class="col-lg-3">
                        <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body">

                                <h5 class="card-title">Fleet Statistics</h5>

                                <div>Total Members: <?php echo htmlspecialchars($fleetData["Data"]["member_count"]); ?></div>
                                <div>Total Accounts: <?php echo htmlspecialchars($fleetData["Data"]["account_count"]); ?></div>
                                <br>
                                <div>Start Date: <?php echo htmlspecialchars($startDate); ?></div>
                                <div>Duration: <?php echo htmlspecialchars($this->formatTime($fleetData["Data"]["duration"])); ?></div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">

                    <div class="col-lg-6">
                        <div class="card mt-4" id="classes-card" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body text-center">

                                <h5 class="card-title">Class Breakdown</h5>

                                <div class="spinner-border" id="classes-spinner"></div>
                                <canvas id="classes-chart" hidden>

                                </canvas>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card mt-4" id="ships-card" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body text-center">

                                <h5 class="card-title">Ship Breakdown</h5>

                                <div class="spinner-border" id="ships-spinner"></div>
                                <canvas id="ships-chart" hidden>

                                </canvas>

                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">

                    <div class="col-lg-3">
                        <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body text-center">

                                <h5 class="card-title">Affiliation Breakdown</h5>
                                <ul class="list-group rounded-0 mt-3">
                                    <?php $this->affiliationsTemplate($fleetID); ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card mt-4" style="color: #41464b; background-color: #e2e3e5;">
                            <div class="card-body text-center">

                                <h5 class="card-title">Member Breakdown</h5>

                                    <table class="table table-secondary table-hover align-middle text-wrap small mt-2">
                                        <thead class="p-4">
                                            <tr class="align-middle">
                                                <th scope="col" class="text-start" style="width: 15%;">
                                                    Name
                                                </th>
                                                <th scope="col" class="text-start" style="width: 15%;">
                                                    Corporation
                                                </th>
                                                <th scope="col" class="text-start" style="width: 15%;">
                                                    Alliance
                                                </th>
                                                <th scope="col" class="text-end" style="width: 20%;">
                                                    First Instance
                                                </th>
                                                <th scope="col" class="text-end" style="width: 10%;">
                                                    Total Instances
                                                </th>
                                                <th scope="col" class="text-end" style="width: 10%;">
                                                    Time in Fleet
                                                </th>
                                                <th scope="col" class="text-end" style="width: 15%;">
                                                    Warnings
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <?php $this->membersTemplate($fleetID); ?>

                                        </tbody>
                                    </table>

                            </div>
                        </div>
                    </div>

                </div>

                <?php $this->memberDetailsModalTemplate(); ?>

                <?php
            }
            else {
                ?>

                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-danger text-center">
                            <h4 class="alert-heading">You either don't have access to this fleet, or it does not exist!</h4>
                        </div>
                    </div>
                </div>

                <?php
            }

        }

        protected function affiliationsTemplate($fleetID) {

            $alliancesAndCorporations = $this->model->getFleetAffiliations($fleetID);

            foreach ($alliancesAndCorporations as $eachAllianceID => $eachAlliance) {

                ?>
                <li class="mt-1 list-group-item list-group-item-dark fw-bold d-flex justify-content-between align-items-center">
                    <?php echo htmlspecialchars($eachAlliance["Name"]); ?>
                    <span class="badge bg-dark"><?php echo htmlspecialchars($eachAlliance["Count"]); ?></span>
                </li>
                <?php

                foreach ($eachAlliance["Corporations"] as $eachCorporationID => $eachCorporation) {

                    ?>
                    <li class="ms-4 list-group-item list-group-item-dark d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($eachCorporation["Name"]); ?>
                        <span class="badge bg-dark"><?php echo htmlspecialchars($eachCorporation["Count"]); ?></span>
                    </li>
                    <?php

                }

            }

        }

        protected function generateWarnings($memberData) {

            $warningHTML = "";

            if ($memberData["Instances in Command"] > 0) {

                if (($memberData["Time in Command"] / $memberData["Instances in Command"]) < (1000 * 60 * 5)) {
                    $warningHTML .= '
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Possible Fleet Composition Gathering">
                        <i class="bi bi-sunglasses"></i>
                    </button> 
                    ';
                }

                $warningHTML .= '
                <button type="button" class="btn btn-info btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Held Command Position">
                    <i class="bi bi-chevron-double-up"></i>
                </button> 
                ';
            }
            if ($memberData["Instances in Command"] >= 3) {
                $warningHTML .= '
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="High Number of Instances in Command">
                    <i class="bi bi-chevron-double-up"></i>
                </button> 
                ';
            }
            if (($memberData["Time in Fleet"] / $memberData["Total Instances"]) < (1000 * 60 * 5)) {
                $warningHTML .= '
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="Low Per-Instance Time in Fleet">
                    <i class="bi bi-send"></i>
                </button> 
                ';
            }
            if ($memberData["Total Instances"] >= 5) {
                $warningHTML .= '
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" data-bs-placement="left" title="High Number of Joins or Position Changes.">
                    <i class="bi bi-send-exclamation"></i>
                </button> 
                ';
            }

            return $warningHTML;

        }

        protected function membersTemplate($fleetID) {

            $memberData = $this->model->getFleetMembers($fleetID);

            foreach ($memberData as $eachID => $eachMemberData) {

                $initialDate = date("M d, Y — H:i EVE", (int)($eachMemberData["First Instance"] / 1000));
                ?>
                
                <tr class="member_entry" data-row-id="<?php echo $eachMemberData["ID"]; ?>"  data-bs-toggle="modal" data-bs-target="#details-modal">
                    <td class="text-start"><?php echo htmlspecialchars($eachMemberData["Name"] ?? ""); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($eachMemberData["Corporation Name"] ?? ""); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($eachMemberData["Alliance Name"] ?? ""); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($initialDate); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($eachMemberData["Total Instances"] ?? ""); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($this->formatTime($eachMemberData["Time in Fleet"])); ?></td>
                    <td class="text-end"><?php echo $this->generateWarnings($eachMemberData); ?></td>
                </tr>
                
                <?php
            }

        }

        protected function memberDetailsModalTemplate() {
            ?>

            <div id="details-modal" class="modal fade" tabindex="-1" aria-hidden="true">

                <div class="modal-dialog modal-xl">

                    <div class="modal-content bg-dark text-light border-secondary">

                        <div class="modal-header border-secondary">

                            <h5 class="modal-title">Member Details — <span id="modal-member-name"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                        </div>
                        <div class="modal-body">
                            
                            <div id="modal-spinner">
                                <div class="d-flex justify-content-center" >
                                    <div class="spinner-border text-secondary" style="width: 75px; height: 75px;">
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-danger fw-bold" id="modal-error">

                                An error occurred while trying to get member data! Try again?

                            </div>
                            <canvas id="member-timeline-chart" hidden>

                            </canvas>
                            <div id="member-event-container" class="text-light mt-3">
                                <h5>Event Log</h5>
                                <ul id="member-event-log" class="list-group list-group-flush">

                                </ul>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <?php
        }

        // List of Fleets View
        protected function mainTemplate() {

            $this->model->getRowCount();

            $nameValue = htmlspecialchars($_POST["name_condition"] ?? "");
            $commanderValue = htmlspecialchars($_POST["commander_condition"] ?? "");
            $startDateValue = htmlspecialchars($_POST["date_start_condition"] ?? "");
            $endDateValue = htmlspecialchars($_POST["date_end_condition"] ?? "");
            $sortingValue = htmlspecialchars($_POST["order_by"] ?? "");
            $sortingOrder = htmlspecialchars($_POST["order_order"] ?? "");

            ?>

            <div class="row">

                <div class="col-lg-3 small">

                    <form method="post">

                        <label class="form-label mt-2" for="name_condition">Fleet Name</label>
                        <input type="text" class="form-control form-control-sm" name="name_condition" id="name_condition" value="<?php echo $nameValue; ?>">

                        <label class="form-label mt-2" for="commander_condition">Commander Name</label>
                        <input type="text" class="form-control form-control-sm" name="commander_condition" id="commander_condition" value="<?php echo $commanderValue; ?>">

                        <label class="form-label mt-2" for="fleet_condition">Fleet Types</label>
                        <select class="form-select form-select-sm" multiple size="4" name="fleet_condition[]" id="fleet_condition">
                            <?php $this->fleetsTemplate(); ?>
                        </select>

                        <label class="form-label text-light mt-2">Timing</label>
                        <div class="form-floating">
                            <input type="date" class="form-control form-control-sm" name="date_start_condition" id="date_start_condition" value="<?php echo $startDateValue; ?>">
                            <label for="date_start_condition">Start Date</label>
                        </div>
                        <div class="form-floating mt-2">
                            <input type="date" class="form-control form-control-sm" name="date_end_condition" id="date_end_condition" value="<?php echo $endDateValue; ?>">
                            <label for="date-end">End Date</label>
                        </div>

                        <input type="hidden" id="order_by" name="order_by" value="<?php echo $sortingValue; ?>">
                        <input type="hidden" id="order_order" name="order_order" value="<?php echo $sortingOrder; ?>">

                        <div class="d-grid mt-2">
                            <input class="btn btn-sm btn-success" type="submit" value="Filter">
                        </div>
                        
                        <div class="d-flex justify-content-center mt-2">
                            <div class="btn-group btn-group-sm" role="group">
                                                
                                <?php $this->paginationTemplate(); ?>
                                
                            </div>
                        </div>

                    </form>

                </div>

                <div class="col-lg-9">
                <?php $this->countingTemplate(); ?>
                    <table class="table table-dark table-hover align-middle text-wrap small mt-2">
                        <thead class="p-4">
                            <tr class="align-middle">
                                <th scope="col" style="width: 20%;">
                                    <a class="sorting-link" data-sort-by="name" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="name" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Name
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="type" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="type" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Type
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="commander" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="commander" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Commander
                                </th>
                                <th scope="col" class="text-end" style="width: 20%;">
                                    <a class="sorting-link" data-sort-by="start_time" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="start_time" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Start Time
                                    <br>
                                    End Time
                                </th>
                                <th scope="col" class="text-end" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="duration" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="duration" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Duration
                                </th>
                                <th scope="col" class="text-end" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="member_count" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="member_count" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Members
                                </th>
                                <th scope="col" class="text-end" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="account_count" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="account_count" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Accounts
                                </th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php $this->rowsTemplate(); ?>

                        </tbody>
                    </table>
                </div>

            </div>

            
            <?php
        }

        protected function formatTime($milliseconds) {

            $seconds = $milliseconds / 1000;

            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds - ($hours * 3600)) / 60);

            return sprintf("%02dh %02dm", $hours, $minutes);

        }

        protected function rowsTemplate() {

            $rowData = $this->model->queryRows();

            foreach ($rowData as $eachRow) {

                $startDate = date("M d, Y — H:i EVE", (int)($eachRow["start_time"] / 1000));
                $endDate = date("M d, Y — H:i EVE", (int)($eachRow["end_time"] / 1000));
                ?>
                
                <tr class="fleet_entry" data-row-id="<?php echo $eachRow["id"]; ?>">
                    <td><a href="/fleet_stats/<?php echo $eachRow["id"]; ?>/"><?php echo htmlspecialchars($eachRow["name"] ?? ""); ?></a></td>
                    <td><?php echo htmlspecialchars($eachRow["type"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["commander"] ?? ""); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($startDate ?? ""); ?><br><?php echo htmlspecialchars($endDate ?? ""); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($this->formatTime($eachRow["duration"])); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($eachRow["member_count"] ?? ""); ?></td>
                    <td class="text-end"><?php echo htmlspecialchars($eachRow["account_count"] ?? ""); ?></td>
                </tr>
                
                <?php
            }
        }

        protected function fleetsTemplate() {

            $selectedFleets = (isset($_POST["fleet_condition"]) and !empty($_POST["fleet_condition"])) ? $_POST["fleet_condition"] : [];
            $fleetList = $this->fleetAccessController->getFleetTypes(forAudit: True);

            foreach ($fleetList as $eachID => $eachName) {
                $sectionStatus = (in_array($eachID, $selectedFleets)) ? "selected" : "";
                ?>

                <option value="<?php echo htmlspecialchars($eachID); ?>" <?php echo $sectionStatus; ?>><?php echo htmlspecialchars($eachName); ?></option>

                <?php
            }

        }

        protected function countingTemplate() {
            
            $rowCount = $this->model->rowCount;
            $currentPage = $this->controller->getPageNumber();
            $pageStart = min($rowCount, ((100 * $currentPage) + 1));
            $pageEnd = min($rowCount, (100 * ($currentPage + 1)));
            ?>
            
            <div class="small text-end text-white fst-italic fw-bold mt-2"><div class="small"><?php echo $pageStart; ?> - <?php echo $pageEnd; ?> of <?php echo $rowCount; ?></div></div>
                
            <?php
        }

        protected function paginationTemplate() {
            
            $rowCount = $this->model->rowCount;
            $currentPage = $this->controller->getPageNumber() + 1;
            $maxPage = max(1, ceil($rowCount / 100));
            $startPage = min($maxPage, max(1, $currentPage - 2));
            $endPage = min($maxPage, max(1, $currentPage + 2));
            $backButtonDisabled = ($currentPage == 1) ? "disabled" : "";
            $nextButtonDisabled = ($currentPage == $maxPage) ? "disabled" : "";
            ?>
                
                <button class="btn btn-dark pb-2" type="submit" name="page" value="1" <?php echo $backButtonDisabled; ?>><i class="bi-chevron-bar-left"></i></button>
                <button class="btn btn-dark pb-2" type="submit" name="page" value="<?php echo $currentPage - 1; ?>" <?php echo $backButtonDisabled; ?>><i class="bi-chevron-left"></i></button>
                
                <?php foreach (range($startPage, $endPage) as $num) {
                
                    $isActive = ($num == $currentPage) ? "active" : "";
                    ?>
                    
                    <input class="btn btn-dark <?php echo $isActive; ?>" type="submit" name="page" value="<?php echo $num; ?>">
                    
                <?php } ?>
                
                <button class="btn btn-dark pb-2" type="submit" name="page" value="<?php echo $currentPage + 1; ?>" <?php echo $nextButtonDisabled; ?>><i class="bi-chevron-right"></i></button>
                <button class="btn btn-dark pb-2" type="submit" name="page" value="<?php echo $maxPage; ?>" <?php echo $nextButtonDisabled; ?>><i class="bi-chevron-bar-right"></i></button>
                
            <?php
        }
        
        protected function metaTemplate() {
            ?>
            
            <title>Fleet Stats</title>
            
            <script src="/resources/js/FleetStats.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/luxon@3.5.0/build/global/luxon.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1/dist/chartjs-adapter-luxon.umd.min.js"></script>
            
            <?php
        }

        protected function styleTemplate() {
            ?>
            
            a, .form-check-label, .form-label {

                color: var(--bs-light);

            }
            
            <?php
        }
        
    }

    class View extends Templates implements \Ridley\Interfaces\View {
        
        protected $model;
        protected $controller;
        protected $accessRoles;
        protected $urlData;
        protected $fleetAccessController;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->model = $this->dependencies->get("Model");
            $this->controller = $this->dependencies->get("Controller");
            $this->accessRoles = $this->dependencies->get("Access Roles");
            $this->urlData = $this->dependencies->get("URL Data");
            $this->fleetAccessController = new \Ridley\Objects\AccessControl\Fleet($this->dependencies);
            
        }
        
        public function renderContent() {
            
            if ($this->urlData["Page Topic"] !== false) {
                $this->detailedTemplate($this->urlData["Page Topic"]);
            }
            else {
                $this->mainTemplate();
            }
            
        }
        
        public function renderMeta() {
            
            $this->metaTemplate();
            
        }

        public function renderStyle() {
            
            $this->styleTemplate();
            
        }
        
    }

?>