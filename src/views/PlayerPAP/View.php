<?php

    namespace Ridley\Views\PlayerPAP;

    class Templates {
        
        protected function mainTemplate() {

            $nameValue = htmlspecialchars($_POST["name_condition"] ?? "");
            $neucoreSelection = (isset($_POST["account_condition"]) and $_POST["account_condition"] == "neucore") ? "selected" : "";
            $characterSelection = (isset($_POST["account_condition"]) and $_POST["account_condition"] == "character") ? "selected" : "";
            $startDateValue = htmlspecialchars($_POST["date_start_condition"] ?? "");
            $endDateValue = htmlspecialchars($_POST["date_end_condition"] ?? "");
            $timeCheck = (isset($_POST["time_mode"]) and $_POST["time_mode"] == "true") ? "checked" : "";
            $papCheck = (isset($_POST["pap_mode"]) and $_POST["pap_mode"] == "true") ? "checked" : "";
            $papValue = htmlspecialchars($_POST["pap_minimum"] ?? "0");
            $runValue = htmlspecialchars($_POST["run_minimum"] ?? "0");
            $sortingValue = htmlspecialchars($_POST["order_by"] ?? "");
            $sortingOrder = htmlspecialchars($_POST["order_order"] ?? "");
            $papStatus = htmlspecialchars($_POST["pap_status"] ?? "");
            $papSettings = ($papStatus == "enabled") ? "" : "hidden";
            $papAuth = ($papStatus == "disabled") ? "" : "hidden";

            ?>

            <div class="row">

                <div class="col-lg-3 small">

                    <form method="post">

                        <label class="form-label mt-2" for="name_condition">Account Name</label>
                        <input type="text" class="form-control form-control-sm" name="name_condition" id="name_condition" value="<?php echo $nameValue; ?>">

                        <label class="form-label mt-2" for="account_condition">Account Type</label>
                        <select class="form-select form-select-sm" name="account_condition" id="account_condition">
                            <option></option>
                            <option value="neucore" <?php echo $neucoreSelection; ?>>Neucore</option>
                            <option value="character" <?php echo $characterSelection; ?>>Character</option>
                        </select>

                        <label class="form-label mt-2" for="alliance_condition">Alliance</label>
                        <select class="form-select form-select-sm" name="alliance_condition" id="alliance_condition">
                            <option></option>
                            <?php $this->allianceTemplate(); ?>
                        </select>

                        <label class="form-label mt-2" for="corporation_condition">Corporation</label>
                        <select class="form-select form-select-sm" name="corporation_condition" id="corporation_condition">
                            <option></option>
                            <?php $this->corporationTemplate(); ?>
                        </select>

                        <label class="form-label mt-2" for="fleet_condition">Fleet Types</label>
                        <select class="form-select form-select-sm" multiple size="4" name="fleet_condition[]" id="fleet_condition">
                            <?php $this->fleetsTemplate(); ?>
                        </select>

                        <div class="mt-2">
                            <label for="pap_minimum" class="form-label"><span id="pap_number"><?php echo $papValue; ?></span> Required Fleet(s)</label>
                            <input type="range" class="form-range" min="0" max="10" value="<?php echo $papValue; ?>" name="pap_minimum" id="pap_minimum">
                        </div>

                        <div class="mt-2">
                            <label for="run_minimum" class="form-label"><span id="run_number"><?php echo $runValue; ?></span> Required Run(s)</label>
                            <input type="range" class="form-range" min="0" max="10" value="<?php echo $runValue; ?>" name="run_minimum" id="run_minimum">
                        </div>

                        <label class="form-label text-light mt-2">Recency Boundary</label>
                        <div class="form-floating">
                            <input type="date" class="form-control form-control-sm" name="date_start_condition" id="date_start_condition" value="<?php echo $startDateValue; ?>">
                            <label for="date_start_condition">Start Date</label>
                        </div>
                        <div class="form-floating mt-2">
                            <input type="date" class="form-control form-control-sm" name="date_end_condition" id="date_end_condition" value="<?php echo $endDateValue; ?>">
                            <label for="date-end">End Date</label>
                        </div>

                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="time_mode" id="time_mode" value="true" <?php echo $timeCheck; ?>>
                            <label class="form-check-label" for="time_mode">Time Mode</label>
                        </div>

                        <div id="pap_spinner" class="spinner-border" hidden></div>
                        <div id="pap_settings" <?php echo $papSettings; ?>>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="pap_mode" id="pap_mode" value="true" <?php echo $papCheck; ?>>
                                <label class="form-check-label" for="pap_mode">PAP Mode</label>
                            </div>
                        </div>

                        <div id="pap_auth" <?php echo $papAuth; ?>>
                            <h5 class="text-light mt-2">Auth for PAP Mode: </h5>
                            <a href="player_participation/?action=login" class="mt-3">
                                <img class="login-button" src="/resources/images/sso_image.png">
                            </a>
                        </div>

                        <input type="hidden" id="order_by" name="order_by" value="<?php echo $sortingValue; ?>">
                        <input type="hidden" id="order_order" name="order_order" value="<?php echo $sortingOrder; ?>">
                        <input type="hidden" id="pap_status" name="pap_status" value="<?php echo $papStatus; ?>">

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
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="account_name" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="account_name" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Name
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="account_type" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="account_type" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Type
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="recent_fleets" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="recent_fleets" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Recent Fleets
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="total_fleets" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="total_fleets" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Total Fleets
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="recent_runs" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="recent_runs" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Recent Run
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="total_runs" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="total_runs" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Total Run
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="last_active" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="last_active" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Last Active
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

            $days = floor($seconds / 86400);
            $hours = floor(($seconds - ($days * 86400)) / 3600);
            $minutes = floor(($seconds - ($days * 86400) - ($hours * 3600)) / 60);

            return sprintf("%02dd %02dh %02dm", $days, $hours, $minutes);

        }

        protected function rowsTemplate() {

            $rowData = $this->model->queryRows();
            $isTimeMode = (isset($_POST["time_mode"]) and $_POST["time_mode"] == "true");
            $recentFleetsMinimum = (isset($_POST["pap_minimum"]) and is_numeric($_POST["pap_minimum"])) ? (int)$_POST["pap_minimum"] : 0;
            $recentRunsMinimum = (isset($_POST["run_minimum"]) and is_numeric($_POST["run_minimum"])) ? (int)$_POST["run_minimum"] : 0;

            foreach ($rowData as $eachRow) {
                
                $highlight = ($eachRow["recent_fleets_count"] < $recentFleetsMinimum or $eachRow["recent_runs_count"] < $recentRunsMinimum) ? "text-warning" : "";
                $recentFleetsValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["recent_fleets_time"]) : $eachRow["recent_fleets_count"]);
                $totalFleetsValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["total_fleets_time"]) : $eachRow["total_fleets_count"]);
                $recentRunsValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["recent_runs_time"]) : $eachRow["recent_runs_count"]);
                $totalRunsValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["total_runs_time"]) : $eachRow["total_runs_count"]);
                $lastActiveValue = ($eachRow["last_active"] != 0) ? date("F jS, o", htmlspecialchars((int)$eachRow["last_active"])) : "NEVER";
                ?>
                
                <tr class="player_entry <?php echo $highlight; ?>" data-row-id="<?php echo $eachRow["account_id"]; ?>" data-row-type="<?php echo $eachRow["account_type"]; ?>">
                    <td><?php echo htmlspecialchars($eachRow["account_name"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["account_type"] ?? ""); ?></td>
                    <td><?php echo $recentFleetsValue; ?></td>
                    <td><?php echo $totalFleetsValue; ?></td>
                    <td><?php echo $recentRunsValue; ?></td>
                    <td><?php echo $totalRunsValue; ?></td>
                    <td><?php echo $lastActiveValue; ?></td>
                </tr>
                
                <?php
            }
        }

        protected function allianceTemplate() {

            $allianceValue = $_POST["alliance_condition"] ?? "";
            $allianceList = $this->model->getAlliances();

            foreach ($allianceList as $eachID => $eachData) {
                $sectionStatus = ($allianceValue == $eachData["ID"]) ? "selected" : "";
                ?>

                <option value="<?php echo htmlspecialchars($eachData["ID"]); ?>" <?php echo $sectionStatus; ?>><?php echo htmlspecialchars($eachData["Name"]); ?></option>

                <?php
            }

        }

        protected function corporationTemplate() {

            $corporationValue = $_POST["corporation_condition"] ?? "";
            $corporationList = $this->model->getCorporations();

            foreach ($corporationList as $eachID => $eachData) {
                $sectionStatus = ($corporationValue == $eachData["ID"]) ? "selected" : "";
                ?>

                <option value="<?php echo htmlspecialchars($eachData["ID"]); ?>" <?php echo $sectionStatus; ?>><?php echo htmlspecialchars($eachData["Name"]); ?></option>

                <?php
            }

        }

        protected function fleetsTemplate() {

            $selectedFleets = (isset($_POST["fleet_condition"]) and !empty($_POST["fleet_condition"])) ? $_POST["fleet_condition"] : [];
            $fleetList = $this->controller->getFleetTypes();

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
            
            <title>Player Participation</title>
            
            <script src="/resources/js/PlayerPAP.js"></script>
            
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
        protected $pageList;
        
        public function __construct(
            private \Ridley\Core\Dependencies\DependencyManager $dependencies
        ) {
            
            $this->model = $this->dependencies->get("Model");
            $this->controller = $this->dependencies->get("Controller");
            $this->pageList = $this->dependencies->get("Page Names");
            
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