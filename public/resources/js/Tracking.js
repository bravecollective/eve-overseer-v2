var is_tracking = false;
var shared_fleets = {};

jQuery(document).ready(function () {
    
    var csrfToken = $("meta[name='csrftoken']").attr("content");
    
    $.ajaxSetup({
        beforeSend: function (request) {
            request.setRequestHeader("CSRF-Token", csrfToken);
        }
    });

    var tab_count = 0;

    $("#add-fleet").click(function () {

        createTab(tab_count++);

    });

    $(document).on("click", ".delete-tab", function () {

        tab_number = $(this).attr("data-share-tab-number");
        
        deleteTab(tab_number);
        
    });

    $("#toggle_tracking").click(function () {
        
        toggleTracking(
            $("#fleet_name").val(), 
            $("#fleet_type").val(), 
            $("#share_fleet").is(":checked")
        );
        
    });

    $(document).on("click", ".track-shared", function () {

        tab_number = $(this).attr("data-share-tab-number");
        
        toggleSharedTracking(
            $(`.share-key-input[data-share-tab-number='${tab_number}']`).val(),
            tab_number
        );
        
    });

    getInitialFleetStatus();

    setInterval(getTrackingData, 15000);
    
});

function createTab(tab_number) {

    //Create Shared Fleet Tab
    $("#fleet-tabs").append(
        $("<div/>")
            .addClass("tab-pane fade")
            .attr("id", "shared-fleet-" + tab_number)
            .attr("role", "tabpanel")
            .attr("aria-labelledby", "shared-fleet-" + tab_number)
            .append(
                $("<div/>")
                    .addClass("row")
                    .append(
                        $("<div/>")
                            .addClass("col-lg-3")
                            .append(
                                $("<div/>")
                                    .addClass("mt-3")
                                    .append(
                                        $("<label/>")
                                            .addClass("form-label")
                                            .attr("for", "share-key-input-id-" + tab_number)
                                            .text("Share Key")
                                    )
                                    .append(
                                        $("<input/>")
                                            .addClass("share-key-input form-control")
                                            .attr("id", "share-key-input-id-" + tab_number)
                                            .attr("data-share-tab-number", tab_number)
                                    )
                            )
                            .append(
                                $("<div/>")
                                    .addClass("d-grid mt-3")
                                    .append(
                                        $("<button/>")
                                            .addClass("track-shared btn btn-outline-primary")
                                            .attr("data-share-tab-number", tab_number)
                                            .text("Track Shared Fleet")
                                    )
                            )
                            .append(
                                $("<div/>")
                                    .addClass("d-grid mt-3")
                                    .append(
                                        $("<button/>")
                                            .addClass("delete-tab btn btn-outline-danger")
                                            .attr("data-share-tab-number", tab_number)
                                            .text("Delete Tab")
                                    )
                            )
                            .append(
                                $("<div/>")
                                    .addClass("mt-3 form-check form-check-inline")
                                    .append(
                                        $("<input/>")
                                            .addClass("form-check-input shared_only_with_commander")
                                            .attr("id", `only-with-commander-tab-${tab_number}`)
                                            .attr("type", "checkbox")
                                            .attr("data-share-tab-number", tab_number)
                                            .val("true")
                                    )
                                    .append(
                                        $("<label/>")
                                            .addClass("form-check-label")
                                            .attr("for", `only-with-commander-tab-${tab_number}`)
                                            .text("Only With Commander")
                                    )
                            )
                    )
                    .append(
                        $("<div/>")
                            .addClass("col-lg-9")
                            .append(
                                $("<div/>")
                                    .addClass("fleet-display")
                                    .attr("data-share-tab-number", tab_number)
                            )
                    )
            )
    );

    //Create Button to View Tab
    $("#add-fleet-item").before(
        $("<li/>")
            .addClass("nav-item")
            .attr("role", "presentation")
            .attr("id", "shared-fleet-" + tab_number + "-button")
            .append(
                $("<button/>")
                    .addClass("nav-link")
                    .attr("id", "shared-fleet-" + tab_number + "-tab")
                    .attr("data-bs-toggle", "tab")
                    .attr("data-bs-target", "#shared-fleet-" + tab_number)
                    .attr("type", "button")
                    .attr("role", "tab")
                    .attr("aria-controls", "shared-fleet-" + tab_number)
                    .attr("aria-selected", "false")
                    .text("Shared Fleet " + tab_number)
            )
    );

}

function deleteTab(tab_number) {

    delete shared_fleets[tab_number];
    $(`#shared-fleet-${tab_number}-button`).remove();
    $(`#shared-fleet-${tab_number}`).remove();

}

function toggleSharedTracking(share_key, tab_number) {
    $(`.track-shared[data-share-tab-number='${tab_number}']`).prop("disabled", true);
    $(`.share-key-input[data-share-tab-number='${tab_number}']`).prop("disabled", true);

    dataObject = {
        "Action": "Track_Shared",
        "Share_Key": share_key
    };

    $.ajax({
        url: "/tracking/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            shared_fleets[tab_number] = share_key;
            $(`.track-shared[data-share-tab-number='${tab_number}']`).removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
            $(`.track-shared[data-share-tab-number='${tab_number}']`).addClass("btn-outline-success");
            $(`.track-shared[data-share-tab-number='${tab_number}']`).text("Tracking");
            $(`#shared-fleet-${tab_number}-tab`).text(result["Name"]);
            
        },
        error: function(result) {
            
            $(`.track-shared[data-share-tab-number='${tab_number}']`).removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
            $(`.track-shared[data-share-tab-number='${tab_number}']`).addClass("btn-outline-danger");
            $(`.track-shared[data-share-tab-number='${tab_number}']`).text("Invalid Share Key - Try Again?");
            $(`.track-shared[data-share-tab-number='${tab_number}']`).prop("disabled", false);
            $(`.share-key-input[data-share-tab-number='${tab_number}']`).prop("disabled", false);
            
        }
    });

}

function toggleTracking(fleet_name, fleet_type, share) {

    $("#toggle_tracking").prop("disabled", true);
    $("#fleet_name").prop("disabled", true);
    $("#fleet_type").prop("disabled", true);
    $("#share_fleet").prop("disabled", true);
    
    dataObject = {
        "Action": "Toggle_Tracking",
        "Fleet_Name": fleet_name,
        "Fleet_Type": fleet_type,
        "Share_Fleet": share.toString()
    };

    $.ajax({
        url: "/tracking/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            if (result["Status"] == "Failed") {

                $("#toggle_tracking").removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
                $("#toggle_tracking").addClass("btn-outline-warning");
                $("#toggle_tracking").text("Change In-Progress - Try Again?");
                $("#fleet_name").prop("disabled", false);
                $("#fleet_type").prop("disabled", false);
                $("#share_fleet").prop("disabled", false);

            }
            else if (result["New Status"] == "Closed") {

                $("#toggle_tracking").removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
                $("#toggle_tracking").addClass("btn-outline-primary");
                $("#toggle_tracking").text("Start Tracking");
                $("#share_key").val("");
                $("#share_container").prop("hidden", true);
                $("#fleet_name").prop("disabled", false);
                $("#fleet_type").prop("disabled", false);
                $("#share_fleet").prop("disabled", false);
                $(".fleet-display[data-tab-id='my-fleet']").empty();

                is_tracking = false;

            }
            else if (result["New Status"] == "Active") {

                $("#fleet_name").val(result["Name"]);
                $("#fleet_type").val(result["Type"].toString());
    
                $("#toggle_tracking").removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
                $("#toggle_tracking").addClass("btn-outline-danger");
                $("#toggle_tracking").text("Stop Tracking");
    
                if (result["Share Key"] !== null) {
                    $("#share_fleet").prop("checked", true);
                    $("#share_key").val(result["Share Key"]);
                    $("#share_container").prop("hidden", false);
                }

                is_tracking = true;

            }

            $("#toggle_tracking").prop("disabled", false);
            
        },
        error: function(result) {
            
            $("#toggle_tracking").removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
            $("#toggle_tracking").addClass("btn-outline-danger");
            $("#toggle_tracking").text("Operation Failed - Try Again?");
            $("#toggle_tracking").prop("disabled", false);
            $("#fleet_name").prop("disabled", false);
            $("#fleet_type").prop("disabled", false);
            $("#share_fleet").prop("disabled", false);
            
        }
    });

}

function getTrackingData() {

    if (is_tracking) {

        dataObject = {
            "Action": "Get_Fleet_Data",
            "Only_With_Commander": $("#only_with_commander:checked").val() ?? false
        };
    
        $.ajax({
            url: "/tracking/?core_action=api",
            type: "POST",
            data: dataObject,
            dataType: "html",
            success: function(result) {
                
                $(".fleet-display[data-tab-id='my-fleet']").html(result);
                
            },
            error: function(result) {

                $(".fleet-display[data-tab-id='my-fleet']").empty();

            }
        });

    }
    for (each_tab in shared_fleets) {

        dataObject = {
            "Action": "Get_Fleet_Data",
            "Share_Key": shared_fleets[each_tab],
            "Only_With_Commander": $(`.shared_only_with_commander[data-share-tab-number='${each_tab}']:checked`).val() ?? false
        };
    
        $.ajax({
            url: "/tracking/?core_action=api",
            type: "POST",
            data: dataObject,
            dataType: "html",
            success: function(result) {
                
                $(`.fleet-display[data-share-tab-number='${each_tab}']`).html(result);
                
            },
            error: function(result) {

                $(`.fleet-display[data-share-tab-number='${each_tab}']`).empty()

            }
        });

    }

}

function getInitialFleetStatus() {

    $("#fleet_name").prop("disabled", true);
    $("#fleet_type").prop("disabled", true);
    $("#share_fleet").prop("disabled", true);

    dataObject = {
        "Action": "Get_Fleet_Info"
    };

    $.ajax({
        url: "/tracking/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#fleet_name").val(result["Name"]);
            $("#fleet_type").val(result["Type"].toString());

            $("#toggle_tracking").removeClass("btn-outline-primary btn-outline-danger btn-outline-warning");
            $("#toggle_tracking").addClass("btn-outline-danger");
            $("#toggle_tracking").text("Stop Tracking");

            if (result["Share Key"] !== null) {
                $("#share_fleet").prop("checked", true);
                $("#share_key").val(result["Share Key"]);
                $("#share_container").prop("hidden", false);
            }

            is_tracking = true;
            
        },
        error: function(result) {

            $("#fleet_name").prop("disabled", false);
            $("#fleet_type").prop("disabled", false);
            $("#share_fleet").prop("disabled", false);

        }
    });

}