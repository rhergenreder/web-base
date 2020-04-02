$(document).ready(function() {
  $("#username").keypress(function(e) { if(e.which == 13) $("#password").focus(); });
  $("#password").keypress(function(e) { if(e.which == 13) $("#btnLogin").click(); });
  $("#btnLogin").click(function() {
    var username = $("#username").val();
    var password = $("#password").val();
    var errorDiv = $("#loginError");
    var createdDiv = $("#accountCreated");
    var btn      = $(this);

    errorDiv.hide();
    btn.prop("disabled", true);
    btn.html("Logging in… <i class=\"fa fa-spin fa-circle-notch\"></i>");
    jsCore.apiCall("user/login", {"username": username, "password": password}, function(data) {
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
});
