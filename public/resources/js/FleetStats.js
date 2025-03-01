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

    let fleet_id = window.location.pathname.slice(13, -1);
    if (fleet_id != "" && !isNaN(fleet_id)) {
        loadClassBreakdown();
        loadShipBreakdown();
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
                        itemSort: (a, b) => b.raw - a.raw
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
                        itemSort: (a, b) => b.raw - a.raw
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