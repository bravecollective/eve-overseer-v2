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
