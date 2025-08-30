<?php

    namespace Ridley\Views\AlliancePAP;

    class Templates {
        
        protected function mainTemplate() {

            $startDateValue = htmlspecialchars($_POST["date_start_condition"] ?? "");
            $endDateValue = htmlspecialchars($_POST["date_end_condition"] ?? "");
            $timeCheck = (isset($_POST["time_mode"]) and $_POST["time_mode"] == "true") ? "checked" : "";
            $sortingValue = htmlspecialchars($_POST["order_by"] ?? "");
            $sortingOrder = htmlspecialchars($_POST["order_order"] ?? "");
            $papStatus = htmlspecialchars($_POST["pap_status"] ?? "");
            $papAuth = ($papStatus == "disabled") ? "" : "hidden";

            ?>

            <div class="row">

                <div class="col-lg-3 small">

                    <form method="post">

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

                        <div class="d-grid mt-2">
                            <button type="button" class="btn btn-sm btn-warning" id="update_all_button">Update All Corporations</button>
                        </div>

                        <label class="form-label mt-2" for="fleet_condition">Fleet Types</label>
                        <select class="form-select form-select-sm" multiple size="4" name="fleet_condition[]" id="fleet_condition">
                            <?php $this->fleetsTemplate(); ?>
                        </select>

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
                        <div id="pap_auth" <?php echo $papAuth; ?>>
                            <h5 class="text-light mt-2">Auth Corporation: </h5>
                            <a href="/alliance_participation/?action=login" class="mt-3">
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
                                    <a class="sorting-link" data-sort-by="corporation_name" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="corporation_name" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Corporation
                                </th>
                                <th scope="col" style="width: 15%;">
                                    <a class="sorting-link" data-sort-by="alliance_name" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="alliance_name" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Alliance
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="members" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="members" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Members
                                </th>
                                <th scope="col" style="width: 7.5%;">
                                    <a class="sorting-link" data-sort-by="knowns" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="knowns" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Known
                                </th>
                                <th scope="col" style="width: 7.5%;">
                                    <a class="sorting-link" data-sort-by="actives" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="actives" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Active
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="total_fleets" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="total_fleets" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Tot. Fleets
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="recent_fleets" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="recent_fleets" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Rec. Fleets
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="total_paps" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="total_paps" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Tot. PAPs
                                </th>
                                <th scope="col" style="width: 10%;">
                                    <a class="sorting-link" data-sort-by="recent_paps" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="recent_paps" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    Rec. PAPs
                                </th>
                                <th scope="col" style="width: 5%;">
                                    <a class="sorting-link" data-sort-by="recheck" data-sort-order="ascending" href="#"><i class="bi bi-arrow-up"></i></a><a class="sorting-link" data-sort-by="recheck" data-sort-order="descending" href="#"><i class="bi bi-arrow-down"></i></a> 
                                    <i class="bi bi-stopwatch"></i>
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

            foreach ($rowData as $eachRow) {
                
                $totalPAPValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["total_paps_time"]) : $eachRow["total_paps_count"]);
                $recentPAPValue = htmlspecialchars(($isTimeMode) ? $this->formatTime($eachRow["recent_paps_time"]) : $eachRow["recent_paps_count"]);
                $recheckColor = ($eachRow["recheck"]) ? "text-danger" : "text-success";
                ?>
                
                <tr class="corporation_entry" data-row-id="<?php echo $eachRow["corporation_id"]; ?>">
                    <td><?php echo htmlspecialchars($eachRow["corporation_name"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["alliance_name"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["members"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["knowns"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["actives"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["total_fleets"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($eachRow["recent_fleets"] ?? ""); ?></td>
                    <td><?php echo $totalPAPValue; ?></td>
                    <td><?php echo $recentPAPValue; ?></td>
                    <td><i class="bi bi-stopwatch <?php echo $recheckColor; ?>"></i></td>
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
            
            <title>Alliance Participation</title>
            
            <script src="/resources/js/AlliancePAP.js"></script>
            
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