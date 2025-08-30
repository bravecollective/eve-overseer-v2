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

    $("#pap_minimum").on("input", function () {
        $("#pap_number").text($(this).val())
    });

    $("#run_minimum").on("input", function () {
        $("#run_number").text($(this).val())
    });

    $("#corporation_condition").on("change", function () {

        newValue = $(this).val();
        if (newValue != null && newValue != "" && !(newValue >= 1000000 && newValue <= 2000000)) {
            checkForPAPMode(newValue);
        }
        else {
            $("#pap_settings").prop("hidden", true);
            $("#pap_auth").prop("hidden", true);
            $("#pap_status").val("");
            $("#pap_mode").prop("checked", false);
        }

    });

    $(".player_entry").click(function () {
        getAccountData($(this).attr("data-row-type"), $(this).attr("data-row-id"), $(this).attr("data-row-name"));
    });
    
});

function checkForPAPMode(corporationID) {

    $("#pap_spinner").prop("hidden", false);
    $("#pap_settings").prop("hidden", true);
    $("#pap_auth").prop("hidden", true);
    $("#pap_status").val("");
    $("#pap_mode").prop("checked", false);

    dataObject = {
        "Action": "Check_For_PAP",
        "ID": corporationID
    };

    $.ajax({
        url: "/player_participation/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#pap_status").val("enabled");
            $("#pap_spinner").prop("hidden", true);
            $("#pap_settings").prop("hidden", false);

        },
        error: function(result) {

            $("#pap_status").val("disabled");
            $("#pap_spinner").prop("hidden", true);
            $("#pap_auth").prop("hidden", false);

        }
    });

}

function getAccountData(accountType, accountID, accountName) {

    $("#modal-account-type").text(accountType);
    $("#modal-account-name").text(accountName);
    $("#modal-spinner").prop("hidden", false);
    $("#modal-error").prop("hidden", true);
    $("#account-container").prop("hidden", true);
    $("#account-characters").empty();
    $("#account-fleets").empty();

    dataObject = {
        "Action": "Get_User_Data",
        "Account_Type": accountType,
        "Account_ID": accountID
    };

    $.ajax({
        url: "/player_participation/?core_action=api",
        type: "POST",
        data: dataObject,
        mimeType: "application/json",
        dataType: "json",
        success: function(result) {
            
            $("#modal-spinner").prop("hidden", true);
            $("#modal-error").prop("hidden", true);
            $("#account-container").prop("hidden", false);

            for (eachCharacter in result["Characters"]) {

                characterName = result["Characters"][eachCharacter]["Name"];
                corporationName = result["Characters"][eachCharacter]["Corporation Name"];
                allianceName = (result["Characters"][eachCharacter]["Alliance Name"] !== null) ? result["Characters"][eachCharacter]["Alliance Name"] : "";

                $("#account-characters").append(
                    $("<tr/>")
                        .append(
                            $("<td/>")
                                .text(characterName)
                                .addClass("fw-bold")
                        )
                        .append(
                            $("<td/>")
                                .text(corporationName)
                        )
                        .append(
                            $("<td/>")
                                .text(allianceName)
                        )
                );

            }

            for (eachFleet in result["Fleets"]) {

                if (result["Link Fleets"]) {

                    $("#account-fleets").append(
                        $("<tr/>")
                            .append(
                                $("<td/>")
                                    .append(
                                        $("<a/>")
                                            .text(result["Fleets"][eachFleet]["Name"])
                                            .addClass("fw-bold")
                                            .attr("href", "/fleet_stats/" + result["Fleets"][eachFleet]["ID"] + "/")
                                    )
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Type"])
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Date"])
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Character"])
                                    .addClass("fw-bold")
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Duration"])
                            )
                    );

                }
                else {

                    $("#account-fleets").append(
                        $("<tr/>")
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Name"])
                                    .addClass("fw-bold")
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Type"])
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Date"])
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Character"])
                                    .addClass("fw-bold")
                            )
                            .append(
                                $("<td/>")
                                    .text(result["Fleets"][eachFleet]["Duration"])
                            )
                    );

                }

            }

        },
        error: function(result) {

            $("#modal-spinner").prop("hidden", true);
            $("#modal-error").prop("hidden", false);
            $("#account-container").prop("hidden", true);

        }
    });

}
