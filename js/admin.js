$(document).ready(function() {

  // Login
  $("#username").keypress(function(e) { if(e.which == 13) $("#password").focus(); });
  $("#password").keypress(function(e) { if(e.which == 13) $("#btnLogin").click(); });
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

  $("#userTableRefresh").click(function() {
    let tbody = $("#userTable > tbody");
    let page = parseInt($("#userPageNavigation li.active > a").text().trim());
    tbody.find("tr").remove();
    tbody.append("<tr><td colspan=\"4\" class=\"text-center\">Loading… " + createLoadingIcon() + "</td></tr>");

    jsCore.apiCall("/user/fetch", { page: page}, function (data) {
      let pageCount = data["pages"];
      let users = data["users"];
      let userRows = [];

      // TODO: .. maybe use ts instead of plain js?
      for(let userId in users) {
        let user = users[userId];
        userRows.push("<tr><td>" + user.name + "</td><td>" + user.email + "</td><td></td><td></td></tr>");
      }

      tbody.html(userRows.join(""));
    }, function (err) {
      alert(err);
    });
  });
});
