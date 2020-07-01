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

    function submitForm(btn, method, params, onSuccess) {
        let textBefore = btn.text();
        btn.prop("disabled", true);
        btn.html("Submitting… <i class='fas fa-spin fa-spinner'></i>")
        jsCore.apiCall(method, params, (res) => {
            btn.prop("disabled", false);
            btn.text(textBefore);
            if (!res.success) {
                showAlert("danger", res.msg);
            } else {
                onSuccess();
            }
        });
    }

    // Login
    $("#btnLogin").click(function() {
        const username = $("#username").val();
        const password = $("#password").val();
        const createdDiv = $("#accountCreated");
        const stayLoggedIn = $("#stayLoggedIn").is(":checked");
        const btn = $(this);

        hideAlert();
        btn.prop("disabled", true);
        btn.html("Logging in… <i class=\"fa fa-spin fa-circle-notch\"></i>");
        jsCore.apiCall("/user/login", {"username": username, "password": password, "stayLoggedIn": stayLoggedIn }, function(res) {
            if (res.success) {
                document.location.reload();
            } else {
                btn.html("Login");
                btn.prop("disabled", false);
                $("#password").val("");
                createdDiv.hide();
                showAlert(res.msg);
            }
        });
    });

    $("#btnRegister").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        let btn = $(this);
        let username = $("#username").val().trim();
        let email = $("#email").val().trim();
        let password = $("#password").val();
        let confirmPassword = $("#confirmPassword").val();
        let siteKey = $("#siteKey").val().trim();

        if (username === '' || email === '' || password === '' || confirmPassword === '') {
            showAlert("danger", "Please fill out every field.");
        } else if(password !== confirmPassword) {
            showAlert("danger", "Your passwords did not match.");
        } else {
            let params = { username: username, email: email, password: password, confirmPassword: confirmPassword };
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'register'}).then(function(captcha) {
                        params["captcha"] = captcha;
                        submitForm(btn, "user/register", params, () => {
                            showAlert("success", "Account successfully created, check your emails.");
                            $("input").val("");
                        });
                    });
                });
            } else {
                submitForm(btn, "user/register", params, () => {
                    showAlert("success", "Account successfully created, check your emails.");
                    $("input").val("");
                });
            }
        }
    });

    $("#btnAcceptInvite").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        let btn = $(this);
        let token = $("#token").val();
        let password = $("#password").val();
        let confirmPassword = $("#confirmPassword").val();

        if(password !== confirmPassword) {
            showAlert("danger", "Your passwords did not match.");
        } else {
            let textBefore = btn.text();
            let params = { token: token, password: password, confirmPassword: confirmPassword };

            btn.prop("disabled", true);
            btn.html("Submitting… <i class='fas fa-spin fa-spinner'></i>")
            jsCore.apiCall("user/acceptInvite", params, (res) => {
                btn.prop("disabled", false);
                btn.text(textBefore);
                if (!res.success) {
                    showAlert("danger", res.msg);
                } else {
                    showAlert("success", "Account successfully created. You may now login.");
                    $("input").val("");
                }
            });
        }
    });

    $("#btnRequestPasswordReset").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        let btn = $(this);
        let email = $("#email").val();
        let siteKey = $("#siteKey").val().trim();

        let params = { email: email };
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, {action: 'resetPassword'}).then(function(captcha) {
                    params["captcha"] = captcha;
                    submitForm(btn, "user/requestPasswordReset", params, () => {
                        showAlert("success", "If the e-mail address exists and is linked to a account, you will receive a password reset token.");
                        $("input").val("");
                    });
                });
            });
        } else {
            submitForm(btn, "user/requestPasswordReset", params, () => {
                showAlert("success", "If the e-mail address exists and is linked to a account, you will receive a password reset token.");
                $("input").val("");
            });
        }
    });

    $("#btnResetPassword").click(function (e) {
        e.preventDefault();
        e.stopPropagation();

        let btn = $(this);
        let token = $("#token").val();
        let password = $("#password").val();
        let confirmPassword = $("#confirmPassword").val();

        if(password !== confirmPassword) {
            showAlert("danger", "Your passwords did not match.");
        } else {
            let textBefore = btn.text();
            let params = { token: token, password: password, confirmPassword: confirmPassword };

            btn.prop("disabled", true);
            btn.html("Submitting… <i class='fas fa-spin fa-spinner'></i>")
            jsCore.apiCall("user/resetPassword", params, (res) => {
                btn.prop("disabled", false);
                btn.text(textBefore);
                if (!res.success) {
                    showAlert("danger", res.msg);
                } else {
                    showAlert("success", "Your password was successfully changed. You may now login.");
                    $("input").val("");
                }
            });
        }
    });
});