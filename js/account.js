$(document).ready(function () {

    function showAlert(type, msg) {
        let alert = $("#alertMessage");
        alert.text(msg);
        alert.attr("class", "mt-2 alert alert-" + type);
        alert.show();
    }

    function hideAlert() {
        $("#alertMessage").hide();
    }

    $("#btnRegister").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        let btn = $(this);
        let username = $("#username").val().trim();
        let email = $("#email").val().trim();
        let password = $("#password").val();
        let confirmPassword = $("#confirmPassword").val();

        if (username === '' || email === '' || password === '' || confirmPassword === '') {
            showAlert("danger", "Please fill out every field.");
        } else if(password !== confirmPassword) {
            showAlert("danger", "Your passwords did not match.");
        } else {
            let textBefore = btn.text();
            let params = { username: username, email: email, password: password, confirmPassword: confirmPassword };

            btn.prop("disabled", true);
            btn.html("Submitting… <i class='fas fa-spin fa-spinner'></i>")
            jsCore.apiCall("user/register", params, (res) => {
                btn.prop("disabled", false);
                btn.text(textBefore);
                if (!res.success) {
                    showAlert("danger", res.msg);
                } else {
                    showAlert("success", "Account successfully created, check your emails.");
                    $("input").val("");
                }
            });
        }
    });
});