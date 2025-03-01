jQuery(document).ready(function () {
    
    var csrfToken = $("meta[name='csrftoken']").attr("content");
    
    $.ajaxSetup({
        beforeSend: function (request) {
            request.setRequestHeader("CSRF-Token", csrfToken);
        }
    });

    loadTimezones();
    loadFleetTypes();
    loadFleetRoles();
    loadShipClasses();
    loadShipTypes();
    
});

function createTimezoneChart(incomingData) {

    if ($("#time-mode").is(":checked")) {

        new Chart(
            $("#timezone-chart"),
            {
                type: "bar",
                data: {
                    labels: incomingData["Times"]["Labels"],
                    datasets: [{
                        data: incomingData["Times"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Hours");
                                }
                            }
                        }
                    },
                    indexAxis: 'y',
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "Hours Attended"
                            },
                            beginAtZero: true
                        }
                    }
                }
            }
        );

    }
    else {

        new Chart(
            $("#timezone-chart"),
            {
                type: "bar",
                data: {
                    labels: incomingData["Counts"]["Labels"],
                    datasets: [{
                        data: incomingData["Counts"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Fleets");
                                }
                            }
                        }
                    },
                    indexAxis: 'y',
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "Fleets Attended"
                            },
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            }
        );

    }
    
}

function loadTimezones() {

    $("#timezone-chart").prop("hidden", true);
    $("#timezone-chart").empty();
    $("#timezone-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Timezones",
        "date-start": $("#date-start").val(),
        "date-end": $("#date-end").val(),
        "fleet-type": $("#fleet-type").val()
    };

    $.ajax({
        url: "/personal_stats/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createTimezoneChart(result);
            $("#timezone-spinner").prop("hidden", true);
            $("#timezone-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#timezone-spinner").prop("hidden", true);
            $("#timezone-card").prop("hidden", true);
            $("#timezone-chart").prop("hidden", false);

        }
    });

}

function createFleetTypesChart(incomingData) {

    if ($("#time-mode").is(":checked")) {

        new Chart(
            $("#fleet-type-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Times"]["Labels"],
                    datasets: [{
                        data: incomingData["Times"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Hours");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    else {

        new Chart(
            $("#fleet-type-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Counts"]["Labels"],
                    datasets: [{
                        data: incomingData["Counts"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Fleets");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    
}

function loadFleetTypes() {

    $("#fleet-type-chart").prop("hidden", true);
    $("#fleet-type-chart").empty();
    $("#fleet-type-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Fleet_Types",
        "date-start": $("#date-start").val(),
        "date-end": $("#date-end").val(),
        "fleet-type": $("#fleet-type").val()
    };

    $.ajax({
        url: "/personal_stats/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createFleetTypesChart(result);
            $("#fleet-type-spinner").prop("hidden", true);
            $("#fleet-type-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#fleet-type-spinner").prop("hidden", true);
            $("#fleet-type-card").prop("hidden", true);
            $("#fleet-type-chart").prop("hidden", false);

        }
    });

}

function createFleetRoleChart(incomingData) {

    if ($("#time-mode").is(":checked")) {

        new Chart(
            $("#fleet-role-chart"),
            {
                type: "bar",
                data: {
                    labels: incomingData["Times"]["Labels"],
                    datasets: [{
                        data: incomingData["Times"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Hours");
                                }
                            }
                        }
                    },
                    indexAxis: 'y',
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "Hours Attended"
                            },
                            beginAtZero: true
                        }
                    }
                }
            }
        );

    }
    else {

        new Chart(
            $("#fleet-role-chart"),
            {
                type: "bar",
                data: {
                    labels: incomingData["Counts"]["Labels"],
                    datasets: [{
                        data: incomingData["Counts"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Fleets");
                                }
                            }
                        }
                    },
                    indexAxis: 'y',
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: "Fleets Attended"
                            },
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            }
        );

    }
    
}

function loadFleetRoles() {

    $("#fleet-role-chart").prop("hidden", true);
    $("#fleet-role-chart").empty();
    $("#fleet-role-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Fleet_Roles",
        "date-start": $("#date-start").val(),
        "date-end": $("#date-end").val(),
        "fleet-type": $("#fleet-type").val()
    };

    $.ajax({
        url: "/personal_stats/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createFleetRoleChart(result);
            $("#fleet-role-spinner").prop("hidden", true);
            $("#fleet-role-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#fleet-role-spinner").prop("hidden", true);
            $("#fleet-role-card").prop("hidden", true);
            $("#fleet-role-chart").prop("hidden", false);

        }
    });

}

function createShipClassesChart(incomingData) {

    if ($("#time-mode").is(":checked")) {

        new Chart(
            $("#ship-class-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Times"]["Labels"],
                    datasets: [{
                        data: incomingData["Times"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Hours");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    else {

        new Chart(
            $("#ship-class-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Counts"]["Labels"],
                    datasets: [{
                        data: incomingData["Counts"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Fleets");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    
}

function loadShipClasses() {

    $("#ship-class-chart").prop("hidden", true);
    $("#ship-class-chart").empty();
    $("#ship-class-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Ship_Classes",
        "date-start": $("#date-start").val(),
        "date-end": $("#date-end").val(),
        "fleet-type": $("#fleet-type").val()
    };

    $.ajax({
        url: "/personal_stats/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createShipClassesChart(result);
            $("#ship-class-spinner").prop("hidden", true);
            $("#ship-class-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#ship-class-spinner").prop("hidden", true);
            $("#ship-class-card").prop("hidden", true);
            $("#ship-class-chart").prop("hidden", false);

        }
    });

}

function createShipTypesChart(incomingData) {

    if ($("#time-mode").is(":checked")) {

        new Chart(
            $("#ship-type-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Times"]["Labels"],
                    datasets: [{
                        data: incomingData["Times"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Hours");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    else {

        new Chart(
            $("#ship-type-chart"),
            {
                type: "doughnut",
                data: {
                    labels: incomingData["Counts"]["Labels"],
                    datasets: [{
                        data: incomingData["Counts"]["Data"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return (context.formattedValue + " Fleets");
                                }
                            }
                        }
                    }
                }
            }
        );

    }
    
}

function loadShipTypes() {

    $("#ship-type-chart").prop("hidden", true);
    $("#ship-type-chart").empty();
    $("#ship-type-spinner").prop("hidden", false);

    dataObject = {
        "Action": "Get_Ship_Types",
        "date-start": $("#date-start").val(),
        "date-end": $("#date-end").val(),
        "fleet-type": $("#fleet-type").val()
    };

    $.ajax({
        url: "/personal_stats/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            createShipTypesChart(result);
            $("#ship-type-spinner").prop("hidden", true);
            $("#ship-type-chart").prop("hidden", false);

        },
        error: function(result) {

            $("#ship-type-spinner").prop("hidden", true);
            $("#ship-type-card").prop("hidden", true);
            $("#ship-type-chart").prop("hidden", false);

        }
    });

}