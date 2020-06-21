$(document).ready(function() {

  // Login
  $("#username").keypress(function(e) { if(e.which === 13) $("#password").focus(); });
  $("#password").keypress(function(e) { if(e.which === 13) $("#btnLogin").click(); });
  $("#btnLogin").click(function() {
    const username = $("#username").val();
    const password = $("#password").val();
    const errorDiv = $("#loginError");
    const createdDiv = $("#accountCreated");
    const stayLoggedIn = $("#stayLoggedIn").is(":checked");
    const btn = $(this);

    errorDiv.hide();
    btn.prop("disabled", true);
    btn.html("Logging in… <i class=\"fa fa-spin fa-circle-notch\"></i>");
    jsCore.apiCall("/user/login", {"username": username, "password": password, "stayLoggedIn": stayLoggedIn }, function(data) {
      window.location.reload();
    }, function(err) {
      btn.html("Login");
      btn.prop("disabled", false);
      $("#password").val("");
      createdDiv.hide();
      errorDiv.html(err);
      errorDiv.show();
    });
  });

  $("#btnLogout").click(function() {
    jsCore.apiCall("/user/logout", function(data) {
      document.location = "/admin/dashboard";
    }, function(err) {
      alert(err);
    });
  });
});
