var memberTimeline = null;

jQuery(document).ready(function () {
    
    var csrfToken = $("meta[name='csrftoken']").attr("content");
    
    $.ajaxSetup({
        beforeSend: function (request) {
            request.setRequestHeader("CSRF-Token", csrfToken);
        }
    });

    $(".sorting-link[data-sort-by='" + $("#order_by").val() + "'][data-sort-order='" + $("#order_order").val() + "']").addClass("text-primary");

    $(".sorting-link").click(function () {
        $(".sorting-link").removeClass("text-primary");
        $(this).addClass("text-primary");
        $("#order_by").val($(this).attr("data-sort-by"));
        $("#order_order").val($(this).attr("data-sort-order"));
    });

    $("#delete-button").on("click", function () {
        if (window.confirm("Are you sure you want to delete this fleet?")) {
            deleteFleet();
        }
    });

    $(".member_entry").on("click", function () {
        if (memberTimeline !== null) {
            memberTimeline.destroy();
        }
        loadMemberTimeline($(this).attr("data-row-id"));
    });

    let fleet_id = window.location.pathname.slice(13, -1);
    if (fleet_id != "" && !isNaN(fleet_id)) {
        loadClassBreakdown();
        loadShipBreakdown();

        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        

    }
    
});

function deleteFleet() {

    $("#delete-button").prop("disabled", true);

    dataObject = {
        "Action": "Delete_Fleet"
    };

    $.ajax({
        url: (window.location.pathname + "?core_action=api"),
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            window.location.assign("/fleet_stats/");
            return false;

        },
        error: function(result) {

            $("#delete-button").text("Failed to Delete");

        }
    });

}

function createClassBreakdown(incomingData) {

    new Chart(
        $("#classes-chart"),
        {
            type: "line",
            data: {
                labels: incomingData["Labels"],
                datasets: incomingData["Datasets"]
            },
            options: {
                normalized: true,
                animation: false,
                scales: {
                    x: {
                        type: "time",
                        adapters: {
                            date: {
                                zone: "utc"
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                interaction: {
                    mode: "index"
                },
                plugins: {
                    decimation: {
                        enabled: true,
                        threshold: 500
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        bodySpacing: 0,
                        yAlign: "top",
                        filter: (context) => context.raw > 0,
                    }
                }
            }
        }
    );
    
}

function loadClassBreakdown() {

    $("#classes-chart").prop("hidden", true);
    $("#classes-chart").empty();
    $("#classes-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Class_Breakdown"
    };

    $.ajax({
        url: (window.location.pathname + "?core_action=api"),
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createClassBreakdown(result);
            $("#classes-spinner").prop("hidden", true);
            $("#classes-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#classes-spinner").prop("hidden", true);
            $("#classes-card").prop("hidden", true);
            $("#classes-chart").prop("hidden", false);

        }
    });

}

function createShipBreakdown(incomingData) {

    new Chart(
        $("#ships-chart"),
        {
            type: "line",
            data: {
                labels: incomingData["Labels"],
                datasets: incomingData["Datasets"]
            },
            options: {
                normalized: true,
                animation: false,
                scales: {
                    x: {
                        type: "time",
                        adapters: {
                            date: {
                                zone: "utc"
                            }
                        }
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                interaction: {
                    mode: "index"
                },
                plugins: {
                    decimation: {
                        enabled: true,
                        threshold: 500
                    },
                    legend: {
                        display: false
                    },
                    tooltip: {
                        bodySpacing: 0,
                        yAlign: "top",
                        filter: (context) => context.raw > 0,
                    }
                }
            }
        }
    );
    
}

function loadShipBreakdown() {

    $("#ships-chart").prop("hidden", true);
    $("#ships-chart").empty();
    $("#ships-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Ship_Breakdown"
    };

    $.ajax({
        url: (window.location.pathname + "?core_action=api"),
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createShipBreakdown(result);
            $("#ships-spinner").prop("hidden", true);
            $("#ships-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#ships-spinner").prop("hidden", true);
            $("#ships-card").prop("hidden", true);
            $("#ships-chart").prop("hidden", false);

        }
    });

}

function createMemberTimeline(incomingData) {

    memberTimeline = new Chart(
        $("#member-timeline-chart"),
        {
            type: "bar",
            data: {
                labels: ["Role", "Ship", "Location"],
                datasets: incomingData["Datasets"]
            },
            options: {
                indexAxis: "y",
                skipNull: true,
                animation: false,
                scales: {
                    x: {
                        type: "time",
                        adapters: {
                            date: {
                                zone: "utc"
                            }
                        },
                        min: incomingData["Start"],
                        max: incomingData["End"]
                    },
                    y: {
                        stacked: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return (context.dataset.label);
                            }
                        }
                    }
                }
            }
        }
    );
    
}

function renderTimeline(incomingData) {

    for (timestamp in incomingData["Events"]) {

        $("#member-event-log").append(
            $("<li/>")
                .addClass("list-group-item list-group-item-secondary fw-bold mt-2")
                .text(new Date(parseInt(timestamp)).toISOString())
        );

        for (eachEvent of incomingData["Events"][timestamp]) {

            $("#member-event-log").append(
                $("<li/>")
                    .addClass("list-group-item list-group-item-secondary")
                    .text(eachEvent)
            );

        }

    }

}

function loadMemberTimeline(member_id) {

    $("#modal-member-name").text("Loading...");
    $("#member-timeline-chart").prop("hidden", true);
    $("#member-timeline-chart").empty();
    $("#member-event-container").prop("hidden", true);
    $("#member-event-log").empty();
    $("#modal-spinner").prop("hidden", false);
    $("#modal-error").prop("hidden", true);

    dataObject = {
        "Action": "Get_Member_Timeline",
        "Member_ID": member_id
    };

    $.ajax({
        url: (window.location.pathname + "?core_action=api"),
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#modal-member-name").text(result["Character"]);
            createMemberTimeline(result);
            renderTimeline(result);
            $("#modal-spinner").prop("hidden", true);
            $("#member-timeline-chart").prop("hidden", false);
            $("#member-event-container").prop("hidden", false);

        },
        error: function(result) {

            $("#modal-member-name").text("ERROR");
            $("#modal-spinner").prop("hidden", true);
            $("#modal-error").prop("hidden", false);

        }
    });

}